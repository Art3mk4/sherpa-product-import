<?php

declare(strict_types=1);

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Messenger\EventListener\StopWorkerOnMemoryLimitListener;
use Symfony\Component\Messenger\EventListener\StopWorkerOnTimeLimitListener;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;
use Symfony\Component\Messenger\Worker;

#[AsCommand(name: 'app:worker', description: 'Воркер очереди импорта (Symfony Messenger)')]
final class WorkerCommand extends Command
{
    public function __construct(
        private readonly TransportInterface $transport,
        private readonly MessageBusInterface $bus,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('time-limit', null, InputOption::VALUE_REQUIRED, 'Секунд работы до перезапуска', '3600')
            ->addOption('memory-limit', null, InputOption::VALUE_REQUIRED, 'Лимит памяти, напр. 128M', '128M');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $timeLimit = (int) $input->getOption('time-limit');
        $memoryLimit = $this->parseBytes((string) $input->getOption('memory-limit'));

        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new StopWorkerOnTimeLimitListener($timeLimit));
        $dispatcher->addSubscriber(new StopWorkerOnMemoryLimitListener($memoryLimit));

        $worker = new Worker(['async' => $this->transport], $this->bus, $dispatcher);
        $worker->run(['sleep' => 1_000_000]);

        return Command::SUCCESS;
    }

    private function parseBytes(string $value): int
    {
        if (preg_match('/^(\d+)\s*([KMG]?)$/i', trim($value), $m) !== 1) {
            return 128 * 1024 * 1024;
        }

        $multiplier = match (strtoupper($m[2])) {
            'K' => 1024,
            'M' => 1024 ** 2,
            'G' => 1024 ** 3,
            default => 1,
        };

        return (int) $m[1] * $multiplier;
    }
}
