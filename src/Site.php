<?php

namespace App;

use GuzzleHttp\Client;
use simple_html_dom;

abstract class Site
{
    public static $sites = [
        'galgalim' => \App\Site\Galgalim::class,
        'petcall' => \App\Site\Petcall::class,
        'catdog' => \App\Site\CatDog::class,
        'zuzik' => \App\Site\Zuzik::class,
        'sporty' => \App\Site\Sporty::class,
        'dealonline' => \App\Site\Dealonline::class,
        'eurosport' => \App\Site\Eurosport::class,
        'dominator' => \App\Site\Dominator::class,
        'kingbaby' => \App\Site\Kingbaby::class,
        'zurmarket' => \App\Site\Zurmarket::class,
        'dealcosmetics' => \App\Site\Dealcosmetics::class,
	'girgurim' => \App\Site\Girgurim::class,
	'hameir' => \App\Site\Hameir::class,
	'kolbogifts' => \App\Site\Kolbogifts::class,
	'animalshop' => \App\Site\Animalshop::class,
	'afik2' => \App\Site\Afik2::class,
    ];
    
    public $site;
    public $baseUrl;
    public $encoding = 'UTF-8';
    protected $client;
    public static $purifier;
    /**
     * @var simple_html_dom
     */
    protected $parser;
    protected $browserHeaders = true;

    /**
     * @var Repository
     */
    public $repository;

    protected $ignoreProductSkus = [];

    public function __construct()
    {
        $this->client = new Client();
        $this->repository = new Repository();
    }

    abstract function fetchCategories();

    abstract function fetchProducts($output = null);

    abstract function fetchProductData($output = null);

    public function saveCategory($title, $url)
    {
        return 1;
    }

    protected function loadUrl($url)
    {
        $headers = [
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9',
            'Accept-Encoding' => 'gzip, deflate, br',
            'Accept-Language' => 'en-US;q=0.8,en;q=0.7',
            'Cache-Control' => 'no-cache',
            'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/75.0.3770.100 Safari/537.36'
        ];

        try {
            sleep(rand(12,17));
            $request = $this->browserHeaders ? $this->client->get($url, ['headers' => $headers]) : $this->client->get($url);
            $content = $request->getBody()->getContents();

            echo $url . '|' , $request->getStatusCode() . '|' . (empty($content) ? 'empty' : 'ok') . PHP_EOL;

            if (empty($content)) {
                return false;
            }

            if ($this->encoding === 'CP1255') {
                $content = @iconv('cp1255', 'UTF-8', $content);
                return $content ? (new simple_html_dom($content)) : false;
            }

            return new simple_html_dom($content);
        } catch (\Exception $e) {
            //... log error ?
        }
        return null;
    }

    protected function loadUrlC($url)
    {
        $headers = [
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9',
            'Accept-Encoding' => 'gzip, deflate, br',
            'Accept-Language' => 'en-US;q=0.8,en;q=0.7',
            'Cache-Control' => 'no-cache',
            'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/75.0.3770.100 Safari/537.36'
        ];

        try {
            //sleep(rand(12,17));
            $request = $this->browserHeaders ? $this->client->get($url, ['headers' => $headers]) : $this->client->get($url);
            $content = $request->getBody()->getContents();

            echo $url . '|' , $request->getStatusCode() . '|' . (empty($content) ? 'empty' : 'ok') . PHP_EOL;

            if (empty($content)) {
                return false;
            }

            if ($this->encoding === 'CP1255') {
                $content = @iconv('cp1255', 'UTF-8', $content);
                return $content ? (new simple_html_dom($content)) : false;
            }


                $curl = curl_init();
                //curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
                //curl_setopt($curl, CURLOPT_HEADER, false);
                curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 0);
                curl_setopt($curl, CURLOPT_URL, $url);
                curl_setopt($curl, CURLOPT_REFERER, $url);
                curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
                curl_setopt($curl, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows; U; Windows NT 6.1; en-US) AppleWebKit/533.4 (KHTML, like Gecko) Chrome/5.0.375.125 Safari/533.4");
                $str = curl_exec($curl);
                curl_close($curl);

                // Create a DOM object
                $dom = new simple_html_dom($content);
                // Load HTML from a string
                $dom->load($str);

                return $dom;
                //return new simple_html_dom($content);

                
        } catch (\Exception $e) {
            //... log error ?
        }
        return null;
    }



    protected function loadUrlS($url)
    {
         try {
           // sleep(rand(12,17));
       $html = file_get_html($url);
       $domain = parse_url($url, PHP_URL_HOST);
       //$domain = str_replace('www.','',$domain);
       echo $domain . '|' , '200' . '|' . (empty($html) ? 'empty' : 'ok') . PHP_EOL;

            if (empty($html)) {
                return false;
            }
         return $html;
         } catch (\Exception $e) {
            //... log error ?
        }

        return null;
      }

    protected function saveCategories($categories = [])
    {
	$urls = array();
        foreach ($categories as $url => $title) {
            if ($category = $this->repository->getCategory($url, $this->site)) {
                if ($category['title'] != $title) {
                    $this->repository->updateCategory($url, $title, $this->site);
                }
            } else {
                $this->repository->createCategory($url, $title, $this->site);
            }
	    array_push($urls, "'$url'");
	}
	$this->repository->deleteUnusedCategories($this->site, $urls);
    }

    public function getAllSiteProducts($categoryId = false)
    {
        return $this->repository->getAllProducts($this->site, true, $categoryId);
    }

    public function getUid()
    {
        return $this->site;
    }

    public function hasNotVisitedCategories()
    {
        return (bool) $this->repository->getCategories($this->getUid(), true);
    }

    public function hasEmptyProducts()
    {
        return (bool) $this->repository->countProductsForParsing($this->getUid());
    }

    public function purifyContent($uglyHtml = '', $config = [])
    {
        if (!self::$purifier) {
            $allowedElements = $config['elements'] ?? HTML_WHITELIST_TAGS;
            $allowedAttributes = $config['elements'] ?? [];
            $config = \HTMLPurifier_Config::createDefault();
            $config->set('HTML.AllowedElements', $allowedElements);
            $config->set('HTML.AllowedAttributes', $allowedAttributes);
            self::$purifier = new \HTMLPurifier($config);
        }

        return trim(self::$purifier->purify($uglyHtml));
    }
}


