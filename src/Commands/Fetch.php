<?php

namespace App\Commands;

use App\Site;
use App\Site\Afik2;
use App\Site\Dealonline;
use App\Site\Dominator;
use App\Site\Eurosport;
use App\Site\Kingbaby;
use App\Site\Sporty;
use App\Site\Zurmarket;
use App\Site\Zuzik;
use App\Site\CatDog;
use App\Site\Galgalim;
use App\Site\Petcall;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Fetch extends Command
{
    protected static $defaultName = 'fetch';


    protected function configure()
    {
        $this
            ->addOption('sites', 's', InputOption::VALUE_REQUIRED, 'Enter sites for parsing : dealcosmetics, zuzik,sporty,dealonline,eurosport,dominator,zurmarket,kingbaby,catdog,petcall,galgalim');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $allSites = Site::$sites;

        $sites = [];
        $reqSites = explode(',', $input->getOption('sites'));
        foreach ($reqSites as $reqSite) {
            $reqSite = trim($reqSite);
            if (isset($allSites[$reqSite])) {
                $sites[] = $allSites[$reqSite];
            }
        }
        if (empty($sites)) {
            $output->writeln('No valid site for parsing..');
        }

        $exporter = new \App\Exporter();

        foreach ($sites as $siteClass) {
            $site = new $siteClass();
            $output->writeln('');
            $output->writeln('Fetching categories for : ' . $site->getUid());
            $site->fetchCategories();
            $output->writeln('End fetching categories categories for : ' . $site->getUid());
            $output->writeln('');

            $output->writeln('');
            $output->writeln('Fetching products for : ' . $site->getUid());
            $site->fetchProducts($output);
            $output->writeln('End fetching products for : ' . $site->getUid());
            $output->writeln('');

            $output->writeln('');
            $output->writeln('Fetch products data for : ' . $site->getUid());
            $site->fetchProductData($output);
            $output->writeln('End fetching products data for : ' . $site->getUid());
            $output->writeln('');


            $output->writeln('');
            $output->writeln('Export Data and reset DB for : ' . $site->getUid());
            $exporter->export($site);
            $exporter->exportByCategories($site);
            $exporter->writeInHtml($site->site);
            $site->repository->resetAll($site->site);
            $output->writeln('Exported and reset for : ' . $site->getUid());
            $output->writeln('');


        }
    }

}
