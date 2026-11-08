<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Product;
use App\Entity\ProductImage;
use Doctrine\Common\DataFixtures\FixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * 30 детерминированных товаров: фиксированные коды FIX-0001…, 3–5 атрибутов,
 * 1–2 изображения (path = null — картинки из интернета не качаем).
 */
final class ProductFixtures implements FixtureInterface
{
    private const NAMES = [
        'Легинсы спортивные высокие',
        'Легинсы утеплённые зимние',
        'Легинсы с эффектом второй кожи',
        'Колготки классические 40 den',
        'Колготки плотные 120 den',
        'Брюки карго женские',
        'Брюки прямые со стрелками',
        'Джинсы скинни высокие',
        'Джеггинсы на резинке',
        'Лосины для фитнеса',
        'Топ спортивный с поддержкой',
        'Футболка оверсайз хлопок',
        'Майка базовая белая',
        'Свитшот с начёсом',
        'Худи на молнии',
        'Платье трикотажное миди',
        'Юбка карандаш трикотаж',
        'Шорты велосипедные',
        'Капри спортивные',
        'Носки тренировочные 3 пары',
        'Колготки детские 70 den',
        'Гольфы шерстяные',
        'Подследники ультратонкие',
        'Балаклава флисовая',
        'Шарф-снуд вязаный',
        'Перчатки сенсорные',
        'Джемпер крупной вязки',
        'Кардиган поясной',
        'Комплект домашний',
        'Пижама зимняя',
    ];

    public function load(ObjectManager $manager): void
    {
        foreach (self::NAMES as $index => $name) {
            $n = $index + 1;
            $price = 500 + (($n * 137) % 4500);
            $purchase = $price - intdiv($price, 4);
            $discount = number_format(($price - $purchase) / $price * 100, 2, '.', '');

            $product = new Product(
                sprintf('FIX-%04d', $n),
                $name,
                number_format($price, 2, '.', ''),
                sprintf('Описание товара №%d: %s. Производство Sherpa, коллекция 2026 года.', $n, $name),
                $n % 4 === 0 ? null : $discount,
            );

            $attributes = [
                'Бренд' => 'Sherpa',
                'Размер' => (string) (42 + $n % 6),
                'Цвет' => ['чёрный', 'синий', 'серый', 'бежевый'][$n % 4],
            ];
            if ($n % 3 === 0) {
                $attributes['Состав'] = 'хлопок 80%, эластан 20%';
            }
            if ($n % 5 === 0) {
                $attributes['Страна'] = 'Россия';
            }
            $product->replaceAttributes($attributes);

            $product->addImage(new ProductImage(
                $product,
                sprintf('https://picsum.photos/seed/fix%02d/600/800', $n),
                null,
                0,
            ));
            if ($n % 2 === 0) {
                $product->addImage(new ProductImage(
                    $product,
                    sprintf('https://picsum.photos/seed/fix%02db/600/800', $n),
                    null,
                    1,
                ));
            }

            $manager->persist($product);
        }

        $manager->flush();
    }
}
