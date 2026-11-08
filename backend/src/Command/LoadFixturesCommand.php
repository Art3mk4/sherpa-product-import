<?php

declare(strict_types=1);

namespace App\Command;

use App\DataFixtures\ProductFixtures;
use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:fixtures:load', description: 'Загружает фикстуры: 30 товаров с атрибутами и изображениями')]
final class LoadFixturesCommand extends Command
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('append', null, InputOption::VALUE_NONE, 'Добавить к существующим данным, без очистки БД');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $append = (bool) $input->getOption('append');

        $purger = $append ? null : new ORMPurger($this->em);
        $executor = new ORMExecutor($this->em, $purger);
        $executor->execute([new ProductFixtures()], $append);

        $output->writeln('<info>Фикстуры загружены: 30 товаров.</info>');

        return Command::SUCCESS;
    }
}
