<?php

namespace App;

use EasySlugger\SeoSlugger;

class Exporter
{
    public function exportByCategories($site)
    {
        $categories = $site->repository->getCategories($site->site);
        $categoryList = [];
        array_walk($categories, function ($category) use (&$categoryList){
            $categoryList[$category['id']] = $category['title'];
        });
        foreach ($categories as $category) {
            $xml = new \XMLWriter();

            $xml->openMemory();
            $xml->startDocument('1.0','UTF-8');
            $xml->startElement('STORE');
                $xml->writeAttribute('TITLE', $category['title']);
                $xml->writeAttribute('URL', $site->baseUrl . $category['url']);
                $this->loopProducts($xml, $site, $site->getAllSiteProducts($category['id']), $categoryList);
            $xml->endElement();
            $xml->endDocument();

            $filename = $site->getUid() . '-' . str_replace(['/', ' '], '-', SeoSlugger::uniqueSlugify($category['title'])) . '-export.xml';
            $filepath =  'exports/' . $site->getUid() . '/' . $filename;
            @file_put_contents(BASE_DIR . $filepath, $xml->outputMemory());
            @copy(BASE_DIR . $filepath, '/var/www/html/xmls/' . $filename);

            $this->writeInJson($site->site, $category['title'], $filename);

        }
    }

    public function export($site)
    {
        if (is_dir(BASE_DIR . 'exports/' .$site->getUid())) {
            @rename(BASE_DIR . 'exports/' .$site->getUid(), BASE_DIR . 'exports/' .$site->getUid() . date('-Y-m-d-H:i'));
        }
        @mkdir(BASE_DIR . 'exports/' .$site->getUid());

        $categoryList = [];
        foreach ($site->repository->getCategories($site->site) as $category) {
            $categoryList[$category['id']] = $category['title'];
        }


        $xml = new \XMLWriter();

        $xml->openMemory();

        $xml->startDocument('1.0','UTF-8');
        $xml->startElement('STORE');
        $xml->writeAttribute('url', $site->baseUrl);
        $this->loopProducts($xml, $site, $site->getAllSiteProducts(),$categoryList);
        $xml->endElement();
        $xml->endDocument();

        $filename = $site->getUid() . '.xml';
        $filepath = BASE_DIR . 'exports/' . $site->getUid() . '/' . $filename;
        @file_put_contents($filepath, $xml->outputMemory());
//        @copy($filepath, '/var/www/html/xmls/' . $filename);

        $this->writeInJson($site->site, false, $filename);
    }

    private function loopProducts(\XMLWriter &$xml, $site, $products = [], $categoryList = [])
    {
        $xml->startElement('PRODUCTS');
        foreach($products as $product) {
            $xml->startElement('PRODUCT');
            $xml->writeElement('PRODUCT_URL', $site->baseUrl . $product['url']);
            $xml->writeElement('PRODUCT_NAME', $product['title']);
            $xml->writeElement('PRODUCTCODE', $product['product_code']);
            $xml->writeElement('DELIVERY_TIME', $product['delivery']);
            $xml->startElement('DETAILS');
            $xml->writeCdata($product['description']);
            $xml->endElement();
            $xml->writeElement('CURRENCY', 'ILS');
            $xml->writeElement('PRICE', $product['sale_price'] ?? $product['price']);
            $xml->writeElement('SHIPMENT_COST', $product['ship_price'] ?? 0);
            $xml->writeElement('IMAGE', str_replace('||',',', $product['image']));
            $xml->writeElement('VIDEO', $product['video']);
            $xml->writeElement('WARRANTY', $product['warranty']);
            $xml->writeElement('MANUFACTURER', $product['manufacturer']);

            // START VARIATIONS
            if (!empty($product['options']) && $variations = @json_decode($product['options'], true)) {
                $xml->startElement('VARIATIONS');
                foreach ($variations as $variation) {
                    $xml->startElement('VARIATION');
                        $xml->writeElement('TITLE', $variation['title']);
			            foreach ($variation['options'] as $option) {
                            $xml->startElement('OPTION');
                                $xml->writeElement('NAME', $option['title']);
                                if (!empty($option['price_difference'])) {
                                    $xml->writeElement('PRICE', "+{$option['price_difference']}");
                                } elseif (!empty($option['price_increase'])) {
                                    $xml->writeElement('PRICE', "+{$option['price_increase']}");
                                } elseif (!empty($option['price_decrease'])) {
                                    $xml->writeElement('PRICE', "-{$option['price_increase']}");
                                }
                            $xml->endElement();
                        }
                    $xml->endElement();
                }
                $xml->endElement();
            }
            // END VARIATIONS

            $xml->endElement();
        }

        $xml->endElement();
    }

    protected function writeInJson($site, $category = false, $filepath = false)
    {
        $file = BASE_DIR . "exports/{$site}/config.json";
        $data = [];
        if ($fileData = @file_get_contents($file)) {
            $data = json_decode($fileData, true);
        }
        if ($category) {
            $data['categories'][$category] = $filepath;
        } else {
            $data['title'] = $site;
            $data['path'] = $site;
        }
        @file_put_contents($file, json_encode($data));
    }

    public function writeInHtml($site)
    {
        $file = BASE_DIR . "exports/{$site}/config.json";
        $data = [];
        if ($fileData = @file_get_contents($file)) {
            $data = json_decode($fileData, true);
        }

        $content = '<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
<meta http-equiv="content-type" content="text/html; charset=utf-8" />
</head>
<body>';

//        $content .= '<a href="'. $data['path'] . '.xml">'.$data['title'].'</a>' . PHP_EOL;
        foreach ($data['categories'] as $title => $path) {
            $content .= '<a href="'. $path . '">'.$title.'</a>' . PHP_EOL;
        }
        $content .= '</body></html>';
        $newHtmlFile = BASE_DIR . "exports/{$site}/{$site}.html";
        @file_put_contents($newHtmlFile, $content);
        @copy($newHtmlFile,  "/var/www/html/xmls/{$site}.html");

    }
}

