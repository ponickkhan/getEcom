<?php

namespace App\Commands;

use App\Site;
use App\Site\Dealonline;
use App\Site\Dominator;
use App\Site\Eurosport;
use App\Site\Kingbaby;
use App\Site\Sporty;
use App\Site\Zurmarket;
use App\Site\Zuzik;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Export extends Command
{
    protected static $defaultName = 'export';

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $export = new \App\Export();
        $sites = Site::$sites;

        foreach ($sites as $siteClass) {
            $site = new $siteClass();
            $export->export($site);
            $export->exportByCategories($site);
            $export->writeInHtml($site->site);
        }

    }

}
