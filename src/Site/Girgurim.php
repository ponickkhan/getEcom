<?php

namespace App\Site;

use App\Site;
use Symfony\Component\Console\Helper\ProgressBar;

class Girgurim extends Site
{
    public $baseUrl = 'https://girgurimpetshop.co.il';
    public $site = 'girgurim';

    function fetchCategories()
    {
        $this->parser = $this->loadUrl($this->baseUrl . '/');

        $categories = [];
        foreach ($this->parser->find('#wide-nav li > a') as $primeCategory) {
            $url = str_replace($this->baseUrl, '', $primeCategory->href);
            $title = $primeCategory->text();
            if (strlen($title) < 2 || strlen($url) < 2) continue;
            $categories[$url] = $title;
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
        $count = 0;
        foreach ($categories as $category) {
            if (isset($progressBar)) $progressBar->advance();
            $pages = [];
            $url = $this->baseUrl . $category['url'];
            $progressBar2 = new ProgressBar($output->section());
            $progressBar2->start();

            if (isset($progressBar2)) $progressBar2->advance();
            $count = 0;
            $newProducts = false;
            $this->parser = $this->loadUrl($url);

            $url = false;
            foreach ($this->parser->find('div.title-wrapper p.product-title > a') as $productLink) {

                $href = trim(str_replace($this->baseUrl, '', $productLink->href));
                if (strpos($href, '/shop/') === 0) {
                  $count ++;
                  $exp = explode('/', trim($href,'/'));
                  $href = '';
                  foreach($exp as $ee) {
                    $href = $href . '/' . urldecode($ee);
                  }
                  $output->writeln($href);
                  if (!in_array($href, $pages)) {
                      $pages[] = $href;
                  }
                }
            }
            unset($this->parser);
            if (isset($progressBar2)) $progressBar2->finish();
            $output->writeln($count);
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

            $exp = explode('/', trim($product['url'],'/'));
            $href = '';
            foreach($exp as $ee) {
              $href = $href . '/' . urlencode($ee);
            }

            $url = $this->baseUrl . $href;
            $this->parser = $this->loadUrl($url);
            if (!$this->parser) {
                $this->repository->updateProduct($product['id'], ['visited' => 1]);
                continue;
            }

            $productData = [];
            if (($find = $this->parser->find('h1.entry-title')) && isset($find[0])) {
                $productData['title'] = trim($find[0]->plaintext);
            }

            if (($find = $this->parser->find('div.woocommerce-product-gallery__image img')) && isset($find[0])) {
                $images = [];
                foreach ($find as $img) {
                    $images [] = $img->attr['src'];
                }
                $productData['image'] = implode('||', $images);
            }

            if (($find = $this->parser->find('div.price-wrapper p.product-page-price span.woocommerce-Price-amount')) && isset($find[0])) {

                $productData['price'] = floatval(str_replace([',','₪','NIS', ' ', '$', '&#8362;'], '',trim($find[0]->text())));
                // if (($find = $this->parser->find('span.price_value')) && isset($find[0])) {
                //     $productData['sale_price'] = floatval(str_replace(',', '',trim($find[0]->attr['content'])));
                // }
            } elseif (($find = $this->parser->find('span.price_value')) && isset($find[0])) {
                $productData['price'] = floatval(str_replace(',', '',trim($find[0]->attr['content'])));
            }


            if (($find = $this->parser->find('div.product-short-description')) && isset($find[0])) {
                $description = $this->purifyContent($find[0]->text());
                $productData['description'] = 'מאפיינים עיקריים' . PHP_EOL . str_replace("&nbsp;", ' ', trim($description));
            }

            // if (($find = $this->parser->find('li.model div.label_wrap span.value')) && isset($find[0])) {
            //     $productData['model'] = trim($find[0]->plaintext);
            // }

            // if (($find = $this->parser->find('a[itemprop="brand]')) && isset($find[0])) {
            //     $productData['manufacturer'] = trim($find[0]->plaintext);
            // }

            if (($find = $this->parser->find('button.single_add_to_cart_button')) && isset($find[0])) {
                $productData['product_code'] = trim($find[0]->attr['value']);
            }
            //
            // if (($find = $this->parser->find('div#tab-ux_global_tab')) && isset($find[0])) {
            //   $output->writeln($find[0]->plaintext);
            //     $productData['delivery'] = $find[0]->plaintext;
            // }
            // print_r($productData);
            $this->repository->updateProduct($product['id'], $productData);
        }

        $progressBar->finish();

    }
}
