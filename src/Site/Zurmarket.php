<?php

namespace App\Site;

use App\Site;
use Symfony\Component\Console\Helper\ProgressBar;

class Zurmarket extends Site
{
    public $baseUrl = 'https://www.zurmarket.co.il';
    public $site = 'zurmarket';

    protected $ignoreProductSkus = [];

    function fetchCategories()
    {
        $this->parser = $this->loadUrl($this->baseUrl . '/');

        $categories = [];
        foreach ($this->parser->find('ul.store_categories a') as $link) {
            $href = trim(str_replace($this->baseUrl, '', $link->href));
            $categories[$href] = $link->plaintext;
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
            if ($output) {
                $progressBar2 = new ProgressBar($output->section());
                $progressBar2->start();
            }
            while ($url) {
                if (isset($progressBar2)) $progressBar2->advance();
                $count++;
                $newProducts = false;

                if ($this->parser = $this->loadUrl($url)) {
                    if ($nextUrl = $this->parser->find('link[rel="next"]')) {
                        $next = isset($nextUrl[0]) && isset($nextUrl[0]->attr['href']) ? $nextUrl[0]->attr['href'] : false;
                        $url = $url !== $next ? $next : false;
                    } else {
                        $url = false;
                    }

                    foreach ($this->parser->find('div.list_item_image a') as $productLink) {
                        $href = trim(str_replace($this->baseUrl, '', $productLink->href));
                        if (strpos($href, '/items/') === 0) {
                            $exp = explode('/', trim($href,'/'));
                            $href = '/items/' . urldecode($exp[1]);
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
            $this->repository->addNewProductUrls($this->site, $pages, $category['id']);
            $this->repository->updateCategory($category['url'], $category['title'], $category['site'], true);
            if (isset($progressBar2)) $progressBar2->finish();
        }
        if (isset($progressBar)) $progressBar->finish();


    }

    public function fetchProductData($output = false)
    {
        $counter = $this->repository->countProductsForParsing($this->site);
        if ($output) $progressBar = new ProgressBar($output->section(), $counter);
        $count = 0;

        foreach ($this->repository->getAllProducts($this->site, false, false, 'id, url') as $product) {
            if (isset($progressBar)) $progressBar->advance();
            $count++;

            $url = $this->baseUrl . $product['url'];

            $this->parser = $this->loadUrl($url);
            if (!$this->parser) {
                $this->repository->updateProduct($product['id'], ['visited' => 1]);
                continue;
            }

            $productData = [];
            if (($find = $this->parser->find('div#item_current_title h1')) && isset($find[0])) {
                $productData['title'] = $find[0]->plaintext;
            }

            if (($find = $this->parser->find('div.productCarousel a img')) && isset($find[0])) {
                $images = [];
                foreach ($find as $attr) {
                    $images [] = str_replace('/index/', '/original/', $attr->attr['src']);
                }
                if ($images) {
                    $productData['image'] = implode(',', $images);
                }
            }

            if (($find = $this->parser->find('div#videoDivHolder iframe')) && isset($find[0])) {
                $productData['video'] = $find[0]->attr['src'];
	    }

            if (($find = $this->parser->find('#item_show_price .price_value')) && isset($find[0])) {
                $productData['price'] = floatval(str_replace(',', '',trim($find[0]->attr['content'])));
            }

            if (($find = $this->parser->find('span.desc')) && isset($find[0])) {
                $productData['description'] = $this->purifyContent($find[0]->innertext);
            }

            if (($find = $this->parser->find('input#item_id')) && isset($find[0])) {
                $productData['product_code'] = trim($find[0]->attr['value']);
            }

            if (($find = $this->parser->find('li.manufact div.label_wrap span.value')) && isset($find[0])) {
                $productData['manufacturer'] = trim($find[0]->plaintext);
            }

            if (($find = $this->parser->find('span.delivery_time')) && isset($find[0])) {
                $productData['delivery'] = trim($find[0]->plaintext);
                if (($find = $this->parser->find('span.delivery_time_unit')) && isset($find[0])) {
                    $productData['delivery'] .=  ' ' . trim($find[0]->plaintext);
                }
            }

            if (($find = $this->parser->find('span.warranty_value')) && isset($find[0])) {
                $productData['warranty'] = trim($find[0]->plaintext);
            }

            if ($find = $this->parser->find('div.item_upgrades_with_images div.checkbox-group')) {
                $options = [];
                foreach ($find as $property) {
                    $values = [];
                    foreach ($property->find('label.checkbox') as $op) {
                        $values[$op->find('span.item_upgrades_with_images_radio_button input')[0]->value] = [
                            'title' => trim($op->find('span.item_upgrades_with_images_title')[0]->plaintext),
                            'price_increase' => intval(str_replace(['NIS', '₪'],'', $op->find('span.item_upgrades_with_images_price')[0]->plaintext))
                        ];
                    }

                    $options[] = [
                        'title' => trim($property->find('h4')[0]->plaintext),
                        'options' => $values,
                    ];
                }
                $productData['options'] = json_encode($options);
            }

            if ($find = $this->parser->find('div#item_upgrades div.multipleSelects label')) {
                $options = [];
                foreach ($find as $property) {
                    $values = [];
                    foreach ($property->find('select option') as $op) {
                        if ($op->value) {
                            $values[$op->value] = [
                                'title' => trim($op->plaintext)
                            ];
                            if (!empty($op->attr['data-price']) && $op->attr['data-price'] != '0' && $op->attr['data-price'] != '0.0') {
                                $values[$op->value] =  intval($op->attr['data-price']);
                            }
                        } else {
                            $values[] = [
                                'title' => trim($op->plaintext)
                            ];
                        }
                    }

                    $options[] = [
                        'title' => trim($property->find('span.title')[0]->plaintext),
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

        if (isset($progressBar)) $progressBar->finish();
    }
}
