<?php

namespace App\Site;

use App\Site;
use Symfony\Component\Console\Helper\ProgressBar;

class Afik2 extends Site
{
    public $baseUrl = 'https://www.afik2.com';
    public $site = 'afik2';

    protected $ignoreProductSkus = [];

    function fetchCategories()
    {
        $this->parser = $this->loadUrl($this->baseUrl . '/');

        $categories = [];
        foreach ($this->parser->find('ul.MenuBarVertical li a') as $link) {
            $href = trim(str_replace($this->baseUrl, '', $link->href));
            $categories[$href] = trim(mb_substr($link->plaintext, (mb_strpos($link->plaintext, '.', null, 'UTF-8') + 1), null, 'UTF-8'));
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
            if ($output) {
                $progressBar2 = new ProgressBar($output->section());
                $progressBar2->start();
            }
            while (true) {
                if (isset($progressBar2)) $progressBar2->advance();
                $count++;
                $url = $this->baseUrl . $category['url'] . '/page/' . ($page++);
                $newProducts = false;

                $this->parser = $this->loadUrl($url);

                try {
                    foreach ($this->parser->find('a.prodtitle') as $productLink) {
                        $href = str_replace($this->baseUrl, '', $productLink->href);
                        if (!in_array($href, $pages)) {
                            $pages[] = $href;
                            $newProducts = true;
                        }
                    }
                } catch (\Error $er) {
                    if (isset($output)) $output->writeln('Unable to parse :' . $url);
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

    public function fetchProductData($output = false)
    {
        $counter = $this->repository->countProductsForParsing($this->site);
        $progressBar = new ProgressBar($output->section(), $counter);
        $count = 0;

        foreach ($this->repository->getAllProducts($this->site, false, false, 'id, url') as $product) {
            $progressBar->advance();
            $count++;

            $url = $this->baseUrl . $product['url'];

            $this->parser = $this->loadUrl($url);
            if (!$this->parser) {
                $this->repository->updateProduct($product['id'], ['visited' => 1]);
                continue;
            }

            $productData = [];
            if (($find = $this->parser->find('div.product_product_name h1')) && isset($find[0])) {
                $productData['title'] = $find[0]->plaintext;
            }

            if (($find = $this->parser->find('ul#imageGallery li')) && isset($find[0])) {
                $images = [];
                foreach ($find as $attr) {
                    if($attr->attr['data-type'] == 'image') {
                        $images [] = (strpos($attr->attr['data-src'], '/') === 0 ? $this->baseUrl : '') . $attr->attr['data-src'];
                    }
                    if($attr->attr['data-type'] == 'video') {
                        $productData ['video'] = $attr->attr['data-src'];
                    }
                }
                if ($images) {
                    $productData['image'] = implode(',', $images);
                }
            }

            if (($find = $this->parser->find('span.toal_regularprice_view')) && isset($find[0])) {
                $productData['price'] = floatval(str_replace(',', '',trim($find[0]->plaintext)));
                if (($find = $this->parser->find('span.toal_saleprice_view')) && isset($find[0])) {
                    $productData['sale_price'] = floatval(str_replace(',', '',trim($find[0]->plaintext)));
                }
            } elseif (($find = $this->parser->find('span.toal_saleprice_view')) && isset($find[0])) {
                $productData['price'] = floatval(str_replace(',', '',trim($find[0]->plaintext)));
            }


            if (($find = $this->parser->find('div.product_description div.description')) && isset($find[0])) {
                $productData['description'] = $this->purifyContent($find[0]->innertext);
            } elseif (($find = $this->parser->find('div.product_product_content_short')) && isset($find[0])) {
                $productData['description'] = $this->purifyContent($find[0]->innertext);
            }

            if (($find = $this->parser->find('div[ee_product_id]')) && isset($find[0])) {
                $productData['product_code'] = trim($find[0]->attr['ee_product_id']);
            }

            if (($find = $this->parser->find('li.manufact div.label_wrap span.value')) && isset($find[0])) {
                $productData['manufacturer'] = trim($find[0]->plaintext);
            }

            if (($find = $this->parser->find('li.delivery div.label_wrap span.value')) && isset($find[0])) {
                $productData['delivery'] = trim($find[0]->plaintext);
            }

            if (($find = $this->parser->find('li.warranty div.label_wrap span.value')) && isset($find[0])) {
                $productData['warranty'] = trim($find[0]->plaintext);
            }

            if ($find = $this->parser->find('div.product-properties-ul div.form-group')) {
                $options = [];
                foreach ($find as $property) {
                    $values = [];
                    foreach ($property->find('select option') as $op) {
                        if ($op->value) {
                            $attrs = explode('|', $op->value);
                            if (count($attrs) > 1) {
                                $key = (isset($op->dir) && $op->dir === 'rtl') ? $attrs[2] : array_pop($attrs);
                                $priceDiff = (isset($op->dir) && $op->dir === 'rtl') ? $attrs[1] : array_pop($attrs);
                                $values[$key] = [
                                    'title' => trim($op->plaintext),
                                    'price_increase' => intval($priceDiff)
                                ];
                            } else {
                                $values[$op->value] = $op->plaintext;
                            }
                        } else if (trim($op->plaintext) === 'לא מעוניין') {
                            $values[] = ['title' => trim($op->plaintext)];
                        }
                    }

                    $options[] = [
                        'title' => trim($property->find('label')[0]->plaintext),
                        'options' => $values,
                    ];
                }
                $productData['options'] = json_encode($options);
            }

            if (in_array($productData['product_code'], $this->ignoreProductSkus)) {
                $this->repository->updateProduct($product['id'], ['visited' => 1]);
            } else {
                $this->repository->updateProduct($product['id'], $productData);
            }
        }

        $progressBar->finish();
    }
}
