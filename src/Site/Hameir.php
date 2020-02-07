<?php

namespace App\Site;

use App\Site;
use Symfony\Component\Console\Helper\ProgressBar;

class Hameir extends Site
{
    public $baseUrl = 'https://www.hameir.co.il';
    public $site = 'hameir';

    function fetchCategories()
    {
        $this->parser = $this->loadUrl($this->baseUrl . '/');

        $categories = [];
        foreach ($this->parser->find('div.footer_categories li') as $primeCategory) {
            if ($aTag = $primeCategory->find('a')) {
                $url = str_replace($this->baseUrl, '', $aTag[0]->href);
                $categories[$url] = $aTag[0]->text();
            }
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
            $url = $this->baseUrl . $category['url'];
            $origin_url = $url;
            $progressBar2 = new ProgressBar($output->section());
            $progressBar2->start();
            while ($url) {
                if (isset($progressBar2)) $progressBar2->advance();
                $count++;
                $newProducts = false;

                if ($this->parser = $this->loadUrl($url)) {
                    $url = $origin_url . "/page/" . ($count + 1);
                    foreach ($this->parser->find('ul.productBoxes li div.boxItem-wrap div.item-name a') as $productLink) {
                        $href = trim(str_replace($this->baseUrl, '', $productLink->href));
                        if (strpos($href, '/product/') === 0) {
                            $exp = explode('/', trim($href,'/'));
                            $href = '/product/' . urldecode($exp[1]);
                            if (!in_array($href, $pages)) {
                                $pages[] = $href;
                                $newProducts = true;
                            }
                        }
                    }
                } else {
                    if (isset($output)) $output->writeln('Unable to parse :' . $url);
                    break;
                }
                // End loop if no products were found
                if (!$newProducts) {
                    break;
                }
                unset($this->parser);

            }
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
            if (($find = $this->parser->find('div.ProductPageSection > div.wrap > div.content > div.item-name > h1')) && isset($find[0])) {
                $productData['title'] = trim($find[0]->plaintext);
            }

            if (($find = $this->parser->find('div#product-thumbnails img')) && isset($find[0])) {
              $images = [];
              foreach ($find as $img) {
                  $images [] = $img->attr['src'];
              }
              $productData['image'] = implode('||', $images);
            }
            else if (($find = $this->parser->find('div#imgBigDIV img')) && isset($find[0])) {
                $images = [];
                foreach ($find as $img) {
                    $images [] = $img->attr['src'];
                }
                $productData['image'] = implode('||', $images);
            }

            if (($find = $this->parser->find('div#product-prices-div div.prices-box strike.reg-price span span')) && isset($find[0])) {
                $productData['price'] = floatval(str_replace([',','₪','NIS'], '',trim($find[0]->attr['price'])));
            }

            if (($find = $this->parser->find('div#product-prices-div div.prices-box p.sale-price span span')) && isset($find[0])) {
                $productData['sale_price'] = floatval(str_replace(',', '',trim($find[0]->attr['price'])));
            }


            if (($find = $this->parser->find('div.description')) && isset($find[0])) {
                $description = $this->purifyContent($find[0]->innertext);
                $productData['description'] = str_replace("&nbsp;", ' ', trim($description));
            }

            if (($find = $this->parser->find('ul.product-details-ul li.model div.label_wrap span.value')) && isset($find[0])) {
                $productData['model'] = trim($find[0]->plaintext);
            }

            if (($find = $this->parser->find('ul.product-details-ul li.manufact div.label_wrap span.value')) && isset($find[0])) {
                $productData['manufacturer'] = trim($find[0]->plaintext);
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

            if (($find = $this->parser->find('input[name="itemident"]')) && isset($find[0])) {
                $productData['product_code'] = trim($find[0]->attr['value']);
            }

            // if (($find = $this->parser->find('div.item_delivery_time')) && isset($find[0])) {
            //     $productData['delivery'] = trim(preg_replace('!\s+!', ' ', str_replace('זמן אספקה:', '', $find[0]->plaintext)));
            // }
            if (($find = $this->parser->find('div.product-tabs-vertical iframe')) && isset($find[0])) {
                foreach ($find as $video) {
                    $productData['video'] = $video->attr['src'];
                }
            }


            $this->repository->updateProduct($product['id'], $productData);
        }

        $progressBar->finish();

}
}
