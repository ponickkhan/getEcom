<?php

namespace App\Site;

use App\Site;
use Symfony\Component\Console\Helper\ProgressBar;

class Kingbaby extends Site
{
    public $baseUrl = 'http://kingbaby.co.il';
    public $site = 'kingbaby';

    protected $ignoreProductSkus = [];

    function fetchCategories()
    {
        $this->parser = $this->loadUrl($this->baseUrl . '/');

        $categories = [];
        foreach ($this->parser->find('div.cls_div_menu_h ul a') as $link) {
            if (strpos($link->href, $this->baseUrl) !== false) {
                $href = trim(str_replace($this->baseUrl, '', $link->href));
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

            $url = $this->baseUrl . $category['url'];
            $this->parser = $this->loadUrl($url);
            $pages = [];

            try {
                foreach ($this->parser->find('a.CssCatalogAdjusted_top') as $productLink) {
                    $href = str_replace($this->baseUrl, '', $productLink->href);
                    if (!in_array($href, $pages)) {
                        $pages[] = '/' . ltrim($href, '/');
                        $newProducts = true;
                    }
                }
            } catch (\Error $er) {
                if (isset($output)) $output->writeln('Unable to parse :' . $url);
            }

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

            $url = $this->baseUrl . '/'. $product['url'];

            $this->parser = $this->loadUrl($url);
            if (!$this->parser) {
                $this->repository->updateProduct($product['id'], ['visited' => 1]);
                continue;
            }

            $productData = [];
            if (($find = $this->parser->find('div.CssCatProductAdjusted_product h1.CssCatProductAdjusted_header')) && isset($find[0])) {
                $productData['title'] = $find[0]->plaintext;
            }

            $images = [];
            if (($find = $this->parser->find('div.CssCatProductAdjusted_BigPic a')) && isset($find[0])) {
                $images[] = $this->baseUrl . '/' . $find[0]->href;
            }

            if (($finds = $this->parser->find('div.CssCatProductAdjusted_MorePics div.more_pics a'))) {
                foreach ($finds as $find) {
                    $images[] = $this->baseUrl . '/' . $find->href;
                }
            }
            if ($images) {
                $productData['image'] = implode(',', $images);
            }

            if (($find = $this->parser->find('div.CssCatProductAdjusted_PicDesc iframe')) && isset($find[0])) {
                $productData['video'] = $find[0]->src;
            }

            if (($find = $this->parser->find('div.CssCatProductAdjusted_Price span.CAT_Values')) && isset($find[0])) {
                $productData['price'] = floatval(str_replace(',', '', trim($find[0]->plaintext)));
                if (($find = $this->parser->find('div.CssCatProductAdjusted_PriceSpecial span.CAT_Values')) && isset($find[0])) {
                    $productData['sale_price'] = floatval(str_replace(',', '', trim($find[0]->plaintext)));
                }
            } elseif (($find = $this->parser->find('div.CssCatProductAdjusted_PriceSpecial span.CAT_Values')) && isset($find[0])) {
                $productData['price'] = floatval(str_replace(',', '', trim($find[0]->plaintext)));
            }

            if (($find = $this->parser->find('div.CssCatProductAdjusted_PicDesc')) && isset($find[0])) {
                $productData['description'] = $this->purifyContent($find[0]->innertext);
            }

            if (($find = $this->parser->find('input#PicID')) && isset($find[0])) {
                $productData['product_code'] = trim($find[0]->attr['value']);
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

            if ($find = $this->parser->find('div.clsCatalogElmExtraRow')) {
                $options = [];
                foreach ($find as $property) {
                    $values = [];
                    foreach ($property->find('select option') as $op) {
                        if ($op->value) {
                            $values[$op->value] = ['title' => trim($op->plaintext)];
                            if (!empty($op->attr['data_price'])) {
                                $values[$op->value]['price_increase'] = $op->attr['data_price'];
                            }
                        } else if (trim($op->plaintext) === 'לא מעוניין') {
                            $values[] = ['title' => trim($op->plaintext)];
                        }
                    }

                    $options[] = [
                        'title' => trim($property->find('span.clsCatalogElmExtraRow_TextCont')[0]->plaintext),
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
