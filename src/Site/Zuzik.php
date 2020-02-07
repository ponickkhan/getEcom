<?php

namespace App\Site;

use App\Site;
use Symfony\Component\Console\Helper\ProgressBar;

class Zuzik extends Site
{
    public $baseUrl = 'https://www.zuzik.co.il';
    public $site = 'zuzik';

    function fetchCategories()
    {
        $this->parser = $this->loadUrl($this->baseUrl . '/');

        $categories = [];
        foreach ($this->parser->find('ul.store_categories li') as $primeCategory) {
            if ($aTag = $primeCategory->find('a')) {
                $url = str_replace($this->baseUrl, '', $aTag[0]->href);
                $categories[$url] = $aTag[0]->innertext;
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
            $url = $this->baseUrl . $category['url'];
            $progressBar2 = new ProgressBar($output->section());
            $progressBar2->start();
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

                    foreach ($this->parser->find('div#layout_category div.list_item_title_with_brand h3 a') as $productLink) {
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

            $url = $this->baseUrl . $product['url'];

            $this->parser = $this->loadUrl($url);
            if (!$this->parser) {
                $this->repository->updateProduct($product['id'], ['visited' => 1]);
                continue;
            }

            $productData = [];
            if (($find = $this->parser->find('div#item_current_title h1')) && isset($find[0])) {
                $productData['title'] = trim($find[0]->plaintext);
            }

            if (($find = $this->parser->find('ul#lightSlider li:not(.video_bg)')) && isset($find[0])) {
                $images = [];
                foreach ($find as $img) {
                    $images[] = $img->attr['data-src'];
                }
                $productData['image'] = implode('||', $images);
            }
            if (empty($productData['image']) && ($find = $this->parser->find('meta[property="og:image"]')) && isset($find[0])) {
                $productData['image'] = trim($find[0]->content);
            }

            if (($find = $this->parser->find('div.main_price_and_btn span.origin_price_number')) && isset($find[0])) {
                $productData['price'] = floatval(str_replace([',','₪','NIS'], '',trim($find[0]->plaintext)));
                if (($find = $this->parser->find('span.price_value')) && isset($find[0])) {
                    $productData['sale_price'] = floatval(str_replace(',', '',trim($find[0]->attr['content'])));
                }
            } elseif (($find = $this->parser->find('span.price_value')) && isset($find[0])) {
                $productData['price'] = floatval(str_replace(',', '',trim($find[0]->attr['content'])));
            }


            if (($find = $this->parser->find('span[itemprop="description"]')) && isset($find[0])) {
                $description = $this->purifyContent($find[0]->innertext);
                $productData['description'] = str_replace("&nbsp;", ' ', trim($description));
            }
	    if (($find = $this->parser->find('h3#features + .specifications')) && isset($find[0])) {
                $description = $this->purifyContent($find[0]->innertext);
                $productData['description'] .= str_replace("&nbsp;", ' ', trim($description));
	    }

            if (($find = $this->parser->find('li.model div.label_wrap span.value')) && isset($find[0])) {
                $productData['model'] = trim($find[0]->plaintext);
            }

            if (($find = $this->parser->find('a[itemprop="brand]')) && isset($find[0])) {
                $productData['manufacturer'] = trim($find[0]->plaintext);
            }

            if (($find = $this->parser->find('span.shipping_value')) && isset($find[0])) {
                $productData['ship_price'] = floatval(trim(str_replace(['₪',',','NIS'],'', $find[0]->plaintext)));
            }

            if (($find = $this->parser->find('span.warranty_value')) && isset($find[0])) {
                $productData['warranty'] = trim($find[0]->plaintext);
            }

            if (($find = $this->parser->find('input#item_id')) && isset($find[0])) {
                $productData['product_code'] = trim($find[0]->value);
            }

            if (($find = $this->parser->find('div.item_delivery_time')) && isset($find[0])) {
                $productData['delivery'] = trim(preg_replace('!\s+!', ' ', str_replace('זמן אספקה:', '', $find[0]->plaintext)));
            }
            if ($find = $this->parser->find('div.multipleSelects label')) {
                $options = [];
                foreach ($find as $property) {
                    $values = [];
                    foreach ($property->find('select option') as $op) {
                        if ($op->value) {
                            $values[trim($op->value)] = [
                                'title' => trim($op->plaintext),
                                'price_increase' => intval(str_replace(',','', ($op->attr['data-price']??0))),
                            ];
                        } else {
                            $values[] =  [
                                'title' => trim($op->plaintext),
                                'price_increase' => intval(str_replace(',','', ($op->attr['data-price']??0))),
                            ];
                        }
                    }

                    $options[] = [
                        'title' => trim(trim($property->find('span.title')[0]->plaintext, ':')),
                        'options' => $values,
                    ];
                }
                $productData['options'] =  json_encode($options);
            }
            if ($find = $this->parser->find('div.upgrades_form_fields label')) {
                $options = empty($productData['options']) ? [] : @json_decode($productData['options'], true);
                foreach ($find as $property) {
                    $values = [];
                    foreach ($property->find('select option') as $op) {
                        if ($op->value) {
                            $values[trim($op->value)] = [
                                'title' => trim($op->plaintext),
                                'price_increase' => intval(str_replace(',','', ($op->attr['data-price']??0))),
                            ];
                        }
                    }

                    $options[] = [
                        'title' => trim(trim($property->find('span')[0]->plaintext, ':')),
                        'options' => $values,
                    ];
                }
                $productData['options'] =  json_encode($options);
            }
            if ($find = $this->parser->find('li.video_bg')) {
                $productData['video'] = $find[0]->attr['data-src'];
            } elseif ($find = $this->parser->find('div#videoDivHolder iframe')) {
                $productData['video'] = $find[0]->attr['src'];
            } elseif ($find = $this->parser->find('div.specifications iframe')) {
                foreach ($find as $property) {
                    if ($property->attr['src']) {
                        $productData['video'] = $property->attr['src'];
                    }
                }
            }




            $this->repository->updateProduct($product['id'], $productData);
        }

        $progressBar->finish();

    }
}

