<?php

namespace App\Commands;

use App\Repository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Reset extends Command
{
    protected static $defaultName = 'reset';

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $repository = new Repository();
        $repository->resetAll();

        $output->write('Reset completed for all categories and products!');
        $output->writeln('');
    }

}