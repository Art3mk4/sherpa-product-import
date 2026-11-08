<?php

declare(strict_types=1);

use App\Command\LoadFixturesCommand;
use App\Command\WorkerCommand;
use App\Controller\AuthController;
use App\Controller\DocsController;
use App\Controller\HealthController;
use App\Controller\ImportController;
use App\Controller\ProductController;
use App\Http\ErrorHandler;
use App\Http\JsonResponder;
use App\Http\Presenter\ImportJobPresenter;
use App\Http\Presenter\ProductPresenter;
use App\Message\ImportProductsMessage;
use App\MessageHandler\ImportProductsHandler;
use App\Middleware\JwtAuthMiddleware;
use App\Middleware\RateLimitMiddleware;
use App\Repository\ImportJobRepository;
use App\Repository\ProductAttributeRepository;
use App\Repository\ProductImageRepository;
use App\Repository\ProductRepository;
use App\Service\Auth\JwtService;
use App\Service\HealthService;
use App\Service\Import\ImageDownloader;
use App\Service\Import\ImportService;
use App\Service\Import\UploadValidator;
use App\Service\Import\XlsxReader;
use App\Service\ProductService;
use DI\ContainerBuilder;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\UnderscoreNamingStrategy;
use Doctrine\ORM\ORMSetup;
use Psr\Container\ContainerInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpTransportFactory;
use Symfony\Component\Messenger\Handler\HandlersLocator;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;
use Symfony\Component\Messenger\Middleware\SendMessageMiddleware;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Component\Messenger\Transport\Sender\SendersLocator;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;
use Symfony\Component\Messenger\Transport\TransportInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\CacheStorage;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Возвращает фабрику контейнера (а не сам контейнер): тесты собирают
 * свежий контейнер на каждый тест-кейс.
 */
return static function (): DI\Container {
    $settings = require __DIR__ . '/settings.php';

    $builder = new ContainerBuilder();
    if ($settings['app']['env'] === 'prod') {
        $builder->enableCompilation($settings['app']['cache_dir']);
    }

    $builder->addDefinitions([
        'settings' => $settings,

        // --- Doctrine ---
        EntityManagerInterface::class => \DI\factory(static function (ContainerInterface $c): EntityManagerInterface {
            $s = $c->get('settings');
            $config = ORMSetup::createAttributeMetadataConfiguration(
                [__DIR__ . '/../src/Entity'],
                isDevMode: (bool) $s['app']['debug'],
            );
            // camelCase свойств → snake_case колонок (externalCode → external_code)
            $config->setNamingStrategy(new UnderscoreNamingStrategy(CASE_LOWER));
            if ($s['app']['env'] === 'prod') {
                $config->setMetadataCache(new FilesystemAdapter('doctrine_meta', 0, $s['app']['cache_dir']));
            }

            $connection = DriverManager::getConnection([
                'driver' => 'pdo_pgsql',
                'host' => $s['db']['host'],
                'port' => $s['db']['port'],
                'dbname' => $s['app']['env'] === 'test' ? $s['db']['name_test'] : $s['db']['name'],
                'user' => $s['db']['user'],
                'password' => $s['db']['password'],
                'charset' => 'utf8',
            ], $config);

            return new EntityManager($connection, $config);
        }),
        Connection::class => \DI\factory(
            static fn (ContainerInterface $c): Connection => $c->get(EntityManagerInterface::class)->getConnection()
        ),

        // --- Репозитории (без наследования EntityRepository: EM в конструкторе) ---
        ProductRepository::class => \DI\factory(
            static fn (ContainerInterface $c): ProductRepository => new ProductRepository($c->get(EntityManagerInterface::class))
        ),
        ProductAttributeRepository::class => \DI\factory(
            static fn (ContainerInterface $c): ProductAttributeRepository => new ProductAttributeRepository($c->get(EntityManagerInterface::class))
        ),
        ProductImageRepository::class => \DI\factory(
            static fn (ContainerInterface $c): ProductImageRepository => new ProductImageRepository($c->get(EntityManagerInterface::class))
        ),
        ImportJobRepository::class => \DI\factory(
            static fn (ContainerInterface $c): ImportJobRepository => new ImportJobRepository($c->get(EntityManagerInterface::class))
        ),

        // --- Messenger ---
        'messenger.transport.async' => \DI\factory(static function (ContainerInterface $c): TransportInterface {
            $dsn = (string) $c->get('settings')['messenger']['dsn'];
            if (str_starts_with($dsn, 'in-memory://')) {
                return new InMemoryTransport();
            }

            return (new AmqpTransportFactory())->createTransport($dsn, [
                'exchange' => ['name' => 'imports'],
                'queues' => ['imports' => []],
                'auto_setup' => true,
            ], new PhpSerializer());
        }),
        TransportInterface::class => \DI\get('messenger.transport.async'),

        // Один bus на отправку и обработку: при dispatch() из контроллера
        // сообщение уходит в AMQP (SendMessageMiddleware); в воркере
        // конверт приходит с ReceivedStamp и доходит до HandleMessageMiddleware.
        MessageBusInterface::class => \DI\factory(static function (ContainerInterface $c): MessageBusInterface {
            $senders = new class ($c) implements ContainerInterface {
                public function __construct(private readonly ContainerInterface $container)
                {
                }

                public function get(string $id): mixed
                {
                    if ($id !== 'async') {
                        throw new RuntimeException(sprintf('Неизвестный отправитель "%s"', $id));
                    }

                    return $this->container->get('messenger.transport.async');
                }

                public function has(string $id): bool
                {
                    return $id === 'async';
                }
            };

            return new MessageBus([
                new SendMessageMiddleware(new SendersLocator(
                    [ImportProductsMessage::class => ['async']],
                    $senders,
                )),
                new HandleMessageMiddleware(new HandlersLocator([
                    ImportProductsMessage::class => [
                        static fn (ImportProductsMessage $message): mixed => $c->get(ImportProductsHandler::class)($message),
                    ],
                ])),
            ]);
        }),

        // --- HTTP-клиент для скачивания изображений ---
        HttpClientInterface::class => \DI\factory(static function (ContainerInterface $c): HttpClientInterface {
            $s = $c->get('settings')['import'];

            return HttpClient::create([
                'timeout' => (int) $s['image_timeout'],
                'max_redirects' => 3,
                'headers' => ['User-Agent' => 'ProductImporter/1.0'],
            ]);
        }),

        // --- Rate limiting ---
        RateLimiterFactory::class => \DI\factory(static function (ContainerInterface $c): RateLimiterFactory {
            $s = $c->get('settings');
            /** @var array{app: array{env: string, cache_dir: string}, import: array{rate_limit: int, rate_interval: string}} $s */
            $cache = $s['app']['env'] === 'test'
                ? new ArrayAdapter()                       // память процесса: состояние не протекает между тестами
                : new FilesystemAdapter('ratelimit', 0, $s['app']['cache_dir']);

            return new RateLimiterFactory([
                'id' => 'import',
                'policy' => 'sliding_window',
                'limit' => (int) $s['import']['rate_limit'],
                'interval' => (string) $s['import']['rate_interval'],
            ], new CacheStorage($cache));
        }),

        // --- HTTP-слой ---
        JsonResponder::class => \DI\create(),
        ErrorHandler::class => \DI\factory(
            static fn (ContainerInterface $c): ErrorHandler => new ErrorHandler($c->get(JsonResponder::class))
        ),

        // --- Сервисы ---
        HealthService::class => \DI\factory(
            static fn (ContainerInterface $c): HealthService => new HealthService(
                $c->get(Connection::class),
                (string) $c->get('settings')['messenger']['dsn'],
            )
        ),
        JwtService::class => \DI\factory(
            static fn (ContainerInterface $c): JwtService => new JwtService(
                (string) $c->get('settings')['jwt']['secret'],
                (int) $c->get('settings')['jwt']['ttl'],
            )
        ),
        ProductService::class => \DI\factory(
            static fn (ContainerInterface $c): ProductService => new ProductService($c->get(ProductRepository::class))
        ),
        UploadValidator::class => \DI\factory(
            static fn (ContainerInterface $c): UploadValidator => new UploadValidator((int) $c->get('settings')['import']['max_file_size'])
        ),
        XlsxReader::class => \DI\create(),
        ImageDownloader::class => \DI\factory(static function (ContainerInterface $c): ImageDownloader {
            /** @var array{image_timeout: int, image_max_size: int, image_concurrency: int, storage_path: string} $s */
            $s = $c->get('settings')['import'];

            return new ImageDownloader(
                $c->get(HttpClientInterface::class),
                (int) $s['image_timeout'],
                (int) $s['image_max_size'],
                (int) $s['image_concurrency'],
                (string) $s['storage_path'],
            );
        }),
        ImportService::class => \DI\factory(static function (ContainerInterface $c): ImportService {
            /** @var array{import: array{storage_path: string}} $s */
            $s = $c->get('settings');

            return new ImportService(
                $c->get(UploadValidator::class),
                $c->get(XlsxReader::class),
                $c->get(ImageDownloader::class),
                $c->get(ImportJobRepository::class),
                $c->get(ProductRepository::class),
                $c->get(ProductAttributeRepository::class),
                $c->get(ProductImageRepository::class),
                $c->get(Connection::class),
                $c->get(MessageBusInterface::class),
                (string) $s['import']['storage_path'],
            );
        }),

        // --- Презентеры и контроллеры ---
        ProductPresenter::class => \DI\create(),
        ImportJobPresenter::class => \DI\create(),
        HealthController::class => \DI\factory(
            static fn (ContainerInterface $c): HealthController => new HealthController($c->get(HealthService::class), $c->get(JsonResponder::class))
        ),
        AuthController::class => \DI\factory(
            static fn (ContainerInterface $c): AuthController => new AuthController($c->get(JwtService::class), $c->get(JsonResponder::class), $c->get('settings')['admin'])
        ),
        ProductController::class => \DI\factory(
            static fn (ContainerInterface $c): ProductController => new ProductController($c->get(ProductService::class), $c->get(ProductPresenter::class), $c->get(JsonResponder::class))
        ),
        ImportController::class => \DI\factory(
            static fn (ContainerInterface $c): ImportController => new ImportController($c->get(ImportService::class), $c->get(ImportJobRepository::class), $c->get(ImportJobPresenter::class), $c->get(JsonResponder::class))
        ),
        DocsController::class => \DI\factory(
            static fn (): DocsController => new DocsController(__DIR__ . '/../openapi.yaml')
        ),

        // --- Middleware ---
        JwtAuthMiddleware::class => \DI\factory(
            static fn (ContainerInterface $c): JwtAuthMiddleware => new JwtAuthMiddleware($c->get(JwtService::class))
        ),
        RateLimitMiddleware::class => \DI\factory(
            static fn (ContainerInterface $c): RateLimitMiddleware => new RateLimitMiddleware($c->get(RateLimiterFactory::class))
        ),

        // --- Консольные команды ---
        WorkerCommand::class => \DI\factory(
            static fn (ContainerInterface $c): WorkerCommand => new WorkerCommand($c->get('messenger.transport.async'), $c->get(MessageBusInterface::class))
        ),
        LoadFixturesCommand::class => \DI\factory(
            static fn (ContainerInterface $c): LoadFixturesCommand => new LoadFixturesCommand($c->get(EntityManagerInterface::class))
        ),
    ]);

    return $builder->build();
};
