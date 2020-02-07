<?php

namespace App\Site;

use App\Site;
use Symfony\Component\Console\Helper\ProgressBar;

class Dealonline extends Site
{
    public $baseUrl = 'https://www.dealonline.co.il';
    public $site = 'dealonline';
    public $encoding = 'CP1255';

    protected $ignoreProductSkus = ['339'];

    function fetchCategories()
    {
        $this->parser = $this->loadUrl($this->baseUrl . '/');

        $categories = [];
        foreach ($this->parser->find('ul.ul_menu-horizontal_image_top li a') as $link) {
            $href = str_replace($this->baseUrl, '', $link->href);
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

            $urlEx = explode('/', $category['url']);
            $urlEx = array_map('urlencode', $urlEx);
            $pages = [];
            $page = 1;

            $progressBar2 = new ProgressBar($output->section());
            $progressBar2->start();
            while (true) {
                if (isset($progressBar2)) $progressBar2->advance();
                $count++;
                $url = $this->baseUrl . implode('/', $urlEx) . '/page/' . ($page++);
                $newProducts = false;

                $this->parser = $this->loadUrl($url);

                try {
                    foreach ($this->parser->find('a.ee_product_click') as $productLink) {
                        $href = str_replace($this->baseUrl, '', $productLink->href);
                        if (strpos($href, '/product/') === 0 || strpos($href, '/pl_product') === 0) {
                            if (!in_array($href, $pages)) {
                                $pages[] = $href;
                                $newProducts = true;
                            }
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

            $progressBar2->finish();

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

            $urlEx = explode('/', $product['url']);
            $urlEx = array_map('urlencode', $urlEx);
            $url = $this->baseUrl . implode('/', $urlEx);


            $this->parser = $this->loadUrl($url);
            if (!$this->parser) {
                $this->repository->updateProduct($product['id'], ['visited' => 1]);
                continue;
            }

            $productData = [];
            if (($find = $this->parser->find('div.product_product_name h1')) && isset($find[0])) {
                $productData['title'] = $find[0]->plaintext;
            }

            if (($find = $this->parser->find('meta[property="og:image"]')) && isset($find[0])) {
                $productData['image'] = $find[0]->content;
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
/*                $description = '';
                foreach ($find[0]->children as $child) {
                    if ($child->tag == 'ul') {
                        foreach ($child->children as $subChildren) {
                            $description .= trim($subChildren->plaintext) . PHP_EOL;
                        }
                    } else {
                        $description .= trim($child->plaintext) . PHP_EOL;
                    }
                }
*/
		$description = $this->purifyContent($find[0]->innertext);
                $productData['description'] = str_replace("&nbsp;", ' ', $description);
            }

            if (($find = $this->parser->find('li.model div.label_wrap span.value')) && isset($find[0])) {
                $productData['product_code'] = trim($find[0]->plaintext);
            }

            if (($find = $this->parser->find('li.manufact div.label_wrap span.value')) && isset($find[0])) {
                $productData['manufacturer'] = trim($find[0]->plaintext);
            }

            if (($find = $this->parser->find('li.delivery div.label_wrap span.value')) && isset($find[0])) {
                $productData['delivery'] = trim($find[0]->plaintext);
            }

            if (($find = $this->parser->find('li.delivery-price div.label_wrap span.value')) && isset($find[0])) {
                $productData['ship_price'] = floatval(str_replace(['₪',','],'', $find[0]->plaintext));
            }

            if (($find = $this->parser->find('span.features')) && isset($find[0])) {
                foreach ($find as $item) {
                    if (mb_strpos($item->plaintext, 'אחריות', 0, 'UTF-8') !== false) {
                        $productData['warranty'] = trim($item->plaintext);
                    }
                }
            }
            if ($find = $this->parser->find('ul.product-properties-ul li')) {
                $options = [];
                foreach ($find as $property) {
                    $values = [];
                    foreach ($property->find('select option') as $op) {
                        if ($op->value) {
                            $attrs = explode('|', $op->value);
                            $key = (isset($op->dir) && $op->dir === 'rtl') ? $attrs[0] : array_pop($attrs);
                            $priceDiff = (isset($op->dir) && $op->dir === 'rtl') ? $attrs[1] : array_pop($attrs);
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

            if (in_array($productData['product_code'], $this->ignoreProductSkus)) {
                $this->repository->updateProduct($product['id'], ['visited' => 1]);
            } else {
                $this->repository->updateProduct($product['id'], $productData);
            }
        }

        $progressBar->finish();


    }
}
