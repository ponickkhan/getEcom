<?php

namespace App\Site;

use App\Site;
use function GuzzleHttp\Psr7\parse_query;
use Symfony\Component\Console\Helper\ProgressBar;

class Eurosport extends Site
{
    public $baseUrl = 'https://euro-sport.co.il';
    public $site = 'eurosport';
    protected $browserHeaders = false;
    protected $categories = [];

    function findChildCategories(\simple_html_dom_node $ul, $parentSuffix)
    {
        foreach ($ul->children as $li) {
            foreach ($li->children as $subChild) {
                if ($subChild->tag === 'a') {
                    if ($subChild->attr['href'] === '#') continue;
                    $url = trim(str_replace($this->baseUrl, '', $subChild->href));
                    if (!isset($this->categories[$url])) {
                        $this->categories[$url] = trim($subChild->plaintext) . $parentSuffix;
                    }
                } elseif ($subChild->tag === 'ul') {
                    $this->findChildCategories($subChild, $parentSuffix);
                }
            }
        }
    }


    function fetchCategories()
    {
        $this->parser = $this->loadUrl($this->baseUrl . '/');
        $categories = [];
        // Some customised logic on storing categories
        foreach ($this->parser->find('div.header-main ul.header-nav-main li.has-dropdown') as $li) {
            $parentSuffix = '';
            // First get 'a'
            foreach ($li->children as $child) {
                /** @var $child \simple_html_dom_node */
                if ($child->tag === 'a') {
                    $this->categories[trim(str_replace($this->baseUrl, '', $child->href))] = trim($child->plaintext);
                    if (in_array(trim($child->plaintext), ['גברים', 'נשים ונוער', 'ילדים', 'תינוקות'])) {
                        $parentSuffix = " (" .trim($child->plaintext) . ")";
                    }
                }
            }

            foreach ($li->children as $child) {
                if ($child->tag === 'ul') {
                    $this->findChildCategories($child, $parentSuffix);
                }
            }
        }

        foreach ($this->parser->find('div.header-main ul.header-nav-main li a') as $link) {
            if ($link->href == '#') continue;
            $url = trim(str_replace($this->baseUrl, '', $link->href));
            if (!isset($this->categories[$url])) {
                $this->categories[$url] = trim($link->plaintext);
            }
        }

        $this->saveCategories($this->categories);
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

            $parsed = parse_url($category['url']);
            $urlEx = explode('/', $parsed['path']);
            $urlEx = array_map('urlencode', $urlEx);
            $baseUrl = $this->baseUrl . implode('/', $urlEx) . '/page/';
            $page = 1;
            $progressBar2 = new ProgressBar($output->section());
            $progressBar2->start();
            while (true) {
                if (isset($progressBar2)) $progressBar2->advance();

                $count++;
                $newProducts = false;
                $pages = [];
                $url = $baseUrl . ($page++) . (isset($parsed['query']) ? '?' . $parsed['query'] : '');

                if ($this->parser = $this->loadUrl($url)) {
                    foreach ($this->parser->find('div.product-small div.box-image a') as $productLink) {
                        $href = str_replace($this->baseUrl, '', $productLink->href);
                        if (strpos($href, '#') !== 0 && !in_array($href, $pages)) {
                            $pages[] = $href;
                            $newProducts = true;
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

                $this->repository->addNewProductUrls($this->site, $pages, $category['id']);
                unset($this->parser);
            }
            if (isset($progressBar2)) $progressBar2->finish();

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
            if (($find = $this->parser->find('h1.entry-title')) && isset($find[0])) {
                $productData['title'] = $find[0]->plaintext;
            }

            if (($find = $this->parser->find('div.product-gallery img.skip-lazy')) && isset($find[0])) {
                $images = [];
                foreach ($find as $img) {
                    $images [] = $img->attr['data-src'];
                }
                $productData['image'] = implode('||', $images);
            }
            if (empty($productData['image']) && ($find = $this->parser->find('meta[property="og:image"]')) && isset($find[0])) {
                $productData['image'] = $find[0]->content;
            }

            if (($find = $this->parser->find('p.product-page-price span.amount')) && isset($find[0])) {
                $productData['price'] = floatval(str_replace(['₪',',','&#8362;'],'', $find[0]->plaintext));
                if (isset($find[1])) {
                    $productData['sale_price'] = floatval(str_replace(['₪',',','&#8362;'],'', $find[1]->plaintext));
                }
            } elseif (($find = $this->parser->find('p.product-page-price span.amount')) && isset($find[0])) {
                $productData['price'] = floatval(str_replace(['₪',',','&#8362;'],'', $find[0]->plaintext));
            }

            if (($find = $this->parser->find('div.entry-content')) && isset($find[0])) {
                $description = $this->purifyContent($find[0]->innertext);
                $productData['description'] = str_replace("&nbsp;", ' ', $description);

//                $productData['description'] = trim($find[0]->plaintext);

                preg_match('/\d+ ימי עסקים/', $productData['description'], $matches);
                if (!empty($matches[0])) {
                    $productData['delivery'] = trim($matches[0]);
                }
            }

            if (($find = $this->parser->find('div.sku')) && isset($find[0])) {
                $productData['product_code'] = trim(str_replace('SKU:','', $find[0]->plaintext));
            }

            if ($find = $this->parser->find('table.variations tbody tr td.value')) {
                $options = [];
                foreach ($find as $property) {
                    $values = [];
                    foreach ($property->find('select option') as $op) {
                        if (empty($op->value)) {
                            $title = trim($op->plaintext);
                        } else {
                            $values[$op->value] = [
                                'title' => trim($op->plaintext)
                            ];
                        }
                    }

                    $options[] = [
                        'title' => $title,
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

