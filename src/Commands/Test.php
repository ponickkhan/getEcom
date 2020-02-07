<?php

namespace App\Commands;

use App\Repository;
use App\Site\Dominator;
use App\Site\Dealonline;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Test extends Command
{
    protected static $defaultName = 'test';

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $site = new Dominator();

        $site->fetchCategories();

        die;
        $site->fetchProducts($output);
        $site->fetchProductData($output);

    }

}
