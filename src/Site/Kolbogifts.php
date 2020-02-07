<?php

namespace App\Site;

use App\Site;
use Symfony\Component\Console\Helper\ProgressBar;

class Kolbogifts extends Site
{
    public $baseUrl = 'http://kolbogifts.co.il';
    public $site = 'kolbogifts';

    function fetchCategories()
    {
        $this->parser = $this->loadUrl($this->baseUrl . '/Catalog.asp?t1=1');

        $categories = [];
        foreach ($this->parser->find('a.clsCategoriesForMainCategory') as $primeCategory) {
              $url = '/' . str_replace($this->baseUrl, '', $primeCategory->href);
              $categories[$url] = $primeCategory->text();
        }
        $this->saveCategories($categories);
    }

    public function fetchProducts($output = null)
    {
        if (! ($categories = $this->repository->getCategoryForParsing($this->site))) {
            return null;
        }

        if ($output) {
            $output->writeln('Start on parsing category pages (total categories: '. count($categories) . ') for product links over site: ' . $this->getUid());
            $output->writeln('!!! Have in mind that some categories may have a lot of pages !!!');
            $progressBar = new ProgressBar($output->section(), count($categories));
            $progressBar->start();
        }
        foreach ($categories as $category) {
            $count = 0;
            if (isset($progressBar)) $progressBar->advance();
            $pages = [];
            $url = $category['url'];
            $progressBar2 = new ProgressBar($output->section());
            $progressBar2->start();

            if (isset($progressBar2)) $progressBar2->advance();

            $exp = explode('/', trim($url, '/'));
            $href = '';
            foreach($exp as $ee) {
              $href = $href . '/' . urlencode($ee);
            }
            $href = $this->baseUrl . $href;
            $output->writeln($href);
            if ($this->parser = $this->loadUrl($href)) {
              $output->writeln(count($this->parser->find('div.CssCatalogAdjusted_product_Container div.CssCatalogAdjusted_product > a')));
                foreach ($this->parser->find('div.CssCatalogAdjusted_product_Container div.CssCatalogAdjusted_product > a') as $productLink) {
                  $output->writeln($productLink->href);
                    $href = '/' . trim(str_replace($this->baseUrl, '', $productLink->href));
                    if (strlen($href) < 3) {
                      continue;
                    }
                    if (!in_array($href, $pages)) {
                        $pages[] = $href;
                    }
                }
            } else {
                if (isset($output)) $output->writeln('Unable to parse :' . $url);
            }
            unset($this->parser);

            if (isset($progressBar2)) $progressBar2->finish();

            $this->repository->addNewProductUrls($this->site, $pages, $category['id']);
            $this->repository->updateCategory($category['url'], $category['title'], $category['site'], true);
        }
        if (isset($progressBar)) $progressBar->finish();

    }

    public function fetchProductData($output = null)
    {
        $counter = $this->repository->countProductsForParsing($this->site);
        $progressBar = new ProgressBar($output->section(), $counter);
        $count = 0;

        foreach ($this->repository->getAllProducts($this->site, false, false, 'id, url') as $product) {
            $progressBar->advance();
            $count++;
            if (strpos($product['url'], 'asp') !== FALSE) {
              $url = $this->baseUrl . $product['url'];
            } else {
              $exp = explode('/', trim($product['url'],'/'));
              $href = '';
              foreach($exp as $ee) {
                $href = $href . '/' . urlencode($ee);
              }

              $url = $this->baseUrl . $href;
            }
            $output->writeln($url);
            $this->parser = $this->loadUrl($url);
            if (!$this->parser) {
              $url = urldecode($url);
              $this->parser = $this->loadUrl($url);
            }
            if (!$this->parser) {
                $this->repository->updateProduct($product['id'], ['visited' => 1]);
                continue;
            }

            $productData = [];
            if (($find = $this->parser->find('div#CssCatProductAdjusted_PicturesArea img.salesIcon')) && isset($find[0])) {
              continue;
            }
            if (($find = $this->parser->find('h1.CssCatProductAdjusted_header')) && isset($find[0])) {
                $productData['title'] = trim($find[0]->plaintext);
            }

            if (($find = $this->parser->find('div#CssCatProductAdjusted_PicturesArea img.CatIMG_PictureBig_Clean')) && isset($find[0])) {
                $images = [];
                foreach ($find as $img) {
                  $img_url = $img->attr['src'];
                  $img_url = $this->baseUrl . '/' . trim(str_replace($this->baseUrl, '', $img_url));
                  $images [] = $img_url;
                }
                $productData['image'] = implode('||', $images);
            }

            if (($find = $this->parser->find('meta[itemprop="price"]')) && isset($find[0])) {
                $productData['price'] = floatval(str_replace([',','₪','NIS'], '',trim($find[0]->attr['content'])));
            }
            //
            // if (($find = $this->parser->find('div#product-prices-div div.prices-box p.sale-price span span')) && isset($find[0])) {
            //     $productData['sale_price'] = floatval(str_replace(',', '',trim($find[0]->attr['price'])));
            // }


            if (($find = $this->parser->find('div.CssCatProductAdjusted_PicDesc')) && isset($find[0])) {
                $description = $this->purifyContent($find[0]->innertext);
                $productData['description'] = str_replace("&nbsp;", ' ', trim($description));
            }


            // if (($find = $this->parser->find('a#delivery-price-toggle')) && isset($find[0])) {
            //     $cost = explode("-", $find[0]->plaintext);
            //     $val = trim($cost[count($cost) - 1]);
            //     $output->writeln($find[0]->plaintext);
            //     $output->writeln($val);
            //     $productData['ship_price'] = floatval(trim(str_replace(['₪',',','NIS'],'', $val)));
            // }

            if (($find = $this->parser->find('li.warranty')) && isset($find[0])) {
                $productData['warranty'] = trim($find[0]->plaintext);
            }

            if (($find = $this->parser->find('meta[itemprop="productID"]')) && isset($find[0])) {
                $productData['product_code'] = trim($find[0]->attr['content']);
            }

            if (($find = $this->parser->find('div.CssCatProductAdjusted_Delivery')) && isset($find[0])) {
                $productData['delivery'] = trim(preg_replace('!\s+!', ' ', str_replace('זמן אספקה:', '', $find[0]->plaintext)));
            }


            $this->repository->updateProduct($product['id'], $productData);
        }

        $progressBar->finish();

    }
}
