<?php

namespace App\Site;

use App\Site;
use Symfony\Component\Console\Helper\ProgressBar;

class Sporty extends Site
{
    public $baseUrl = 'http://www.sporty.co.il';
    public $site = 'sporty';

    public function fetchCategories()
    {
        $this->parser = $this->loadUrl($this->baseUrl . '/');

        $categories = [];
        foreach ($this->parser->find('a') as $link) {
            $href = str_replace($this->baseUrl, '', $link->href);
            if (strpos($href, '/category') === 0) {
                $categories[$href] = $link->plaintext;
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
        $count = 0;
        foreach ($categories as $category) {
            if (isset($progressBar)) $progressBar->advance();
            $pages = [];
            $page = 1;
            $progressBar2 = new ProgressBar($output->section());
            $progressBar2->start();
            while (true) {
                if (isset($progressBar2)) $progressBar2->advance();

                $count++;
                $newProducts = false;
                $urlEx = explode('/', $category['url']);
                $urlEx = array_map('urlencode', $urlEx);
                $url = $this->baseUrl . implode('/', $urlEx) . '/page/' . ($page++);

                if ($this->parser = $this->loadUrl($url)) {
                    foreach ($this->parser->find('div.products-page a.ee_product_click') as $productLink) {
                        $href = str_replace($this->baseUrl, '', $productLink->href);
                        if (strpos($href, '/product/') === 0 || strpos($href, '/pl_product') === 0) {
                            if (!in_array($href, $pages)) {
                                $pages[] = $href;
                                $newProducts = true;
                            }
                        }
                    }
                } else {
                    if (isset($output)) $output->writeln('Unable to parse :' . $url);
                }

                // End loop if no products were found
                if (!$newProducts) {
                    break;
                }
                unset($this->parser);
            }

            $progressBar2->finish();

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

            if (mb_strpos($product['url'], '/product/', 0, 'UTF-8') !== false) {
                $urlEx = explode('/', $product['url']);
                $urlEx = array_map('urlencode', $urlEx);
                $url = $this->baseUrl . implode('/', $urlEx);
            } else {
                $url = $this->baseUrl . $product['url'];
            }


            $this->parser = $this->loadUrl($url);

            if (!$this->parser) {
                $this->repository->updateProduct($product['id'], ['visited' => 1]);
                continue;
            }

            $productData = [];
            if (($find = $this->parser->find('div.product_product_name h1')) && isset($find[0])) {
                $productData['title'] = $find[0]->plaintext;
            }

            if (($find = $this->parser->find('img#multizoom1')) && isset($find[0])) {
                $productData['image'] = str_replace('_large.', '.', $find[0]->src);
            }

            if (($find = $this->parser->find('span.toal_saleprice_view')) && isset($find[0])) {
                $productData['sale_price'] = floatval(str_replace(',', '',trim($find[0]->plaintext)));
            }
            if (($find = $this->parser->find('span.toal_regularprice_view')) && isset($find[0])) {
                $productData['price'] = floatval(str_replace(',', '',trim($find[0]->plaintext)));
            }

            if (($find = $this->parser->find('div.product_description')) && isset($find[0])) {
                $description = $this->purifyContent($find[0]->innertext);
                $productData['description'] = trim(preg_replace('!\s+!', ' ', $description));
            }

            if (($find = $this->parser->find('li.model div.label_wrap span.value')) && isset($find[0])) {
                $productData['product_code'] = trim($find[0]->plaintext);
            }

            if (($find = $this->parser->find('li.manufact div.label_wrap span.value')) && isset($find[0])) {
                $productData['manufacturer'] = trim($find[0]->plaintext);
            }

            if (($find = $this->parser->find('li.delivery-price div.label_wrap span.value')) && isset($find[0])) {
                $productData['ship_price'] = floatval(str_replace(['₪',','],'', $find[0]->plaintext));
            }

            if (($find = $this->parser->find('li.warranty div.label_wrap span.value')) && isset($find[0])) {
                $productData['warranty'] = trim($find[0]->plaintext);
            }

            if (($find = $this->parser->find('li.delivery div.label_wrap span.value')) && isset($find[0])) {
                $productData['delivery'] = trim($find[0]->plaintext);
            }
            if ($find = $this->parser->find('ul.product-properties-ul li')) {
                $options = [];
                foreach ($find as $property) {
                    $values = [];
                    foreach ($property->find('select option') as $op) {
                        if ($op->attr['value']) {
                            $attrs = explode('|', $op->attr['value']);
                            $key = (isset($op->attr['dir']) && $op->attr['dir'] === 'rtl') ? $attrs[0] : array_pop($attrs);
                            $priceDiff = (isset($op->attr['dir']) && $op->attr['dir'] === 'rtl') ? $attrs[1] : array_pop($attrs);
                            $values[$key] = [
                                'title' => trim($op->plaintext),
                                'price_increase' => intval($priceDiff)
                            ];
                        }
                    }
                    $options[] = [
                        'title' => trim($property->find('p')[0]->plaintext),
                        'options' => $values,
                    ];
                }
                $productData['options'] = json_encode($options);
            }

            $this->repository->updateProduct($product['id'], $productData);
        }
        $progressBar->finish();
    }

}
