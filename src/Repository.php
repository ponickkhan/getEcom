<?php

namespace App;

use Carbon\Carbon;

class Repository
{
    protected $con;
    protected static $connection;

    public function __construct()
    {
        if (empty(self::$connection)) {
            self::$connection = new \PDO(
                'mysql:host='.DB_HOST.';dbname='.DB_NAME. ';charset=utf8',
                DB_USER,DB_PASS,
                [\PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'utf8'"]
            );
        }
        $this->con = self::$connection;
    }

    public function getAllProducts($site, $exportable = false, $categoryId = false, $select = '*')
    {
        $stmt = $this->con->prepare('SELECT '. $select .
				' FROM products 
				  WHERE '. ( $categoryId ? ' JSON_CONTAINS(category, :category) AND ' : '') .
					'  site = :site '.
					( $exportable ? 'AND title IS NOT NULL AND price IS NOT NULL AND image IS NOT NULL AND (out_of_stock is NULL or out_of_stock != 1) ' : '') .
				' ORDER BY id ASC');
        if ($categoryId) {
            $stmt->bindValue(':category', json_encode([$categoryId]));
        }
        $stmt->bindValue(':site', $site);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function updateProduct($id, $productData = [])
    {
        $updates = [];
        $params = [
            'id' => $id,
            'updated_at' => Carbon::now()->toDateTimeString()
        ];

        if (count($productData) == 1 && !empty($productData['visited'])) {
            $stmt = $this->con->prepare('UPDATE products SET  updated_at = :updated_at, visited = :updated_at WHERE id = :id');
            $stmt->execute($params);
        }

        foreach ($productData as $key => $value) {
            if (!in_array($key, ['options', 'manufacturer', 'warranty', 'ship_order', 'model', 'description', 'title', 'image', 'price', 'sale_price', 'ship_price', 'delivery', 'product_code', 'category', 'video'])) continue;

            $updates [] = " {$key} = :{$key} ";
            $params [$key] = $value;
        }

        if (empty($updates)) {
            return false;
        }

        $stmt = $this->con->prepare('UPDATE products SET ' . implode(',' , $updates) . ' , updated_at = :updated_at, visited = :updated_at WHERE id = :id');
        $stmt->execute($params);
    }

    public function countProductsForParsing($site)
    {
        $stmt = $this->con->prepare("SELECT COUNT(*) FROM products WHERE site=:site AND visited IS NULL ORDER BY id ASC");
        $stmt->execute([
            'site' => $site
        ]);

        return $stmt->fetchColumn();
    }

    public function getProductForParsing($site)
    {
        $stmt = $this->con->prepare("SELECT * FROM products WHERE site=:site AND visited IS NULL ORDER BY id ASC LIMIT 1");
        $stmt->execute([
            'site' => $site
        ]);

        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    public function addNewProductUrls($site, $products = [], $category_id = 0)
    {
        foreach ($products as $productLink) {
            $time = Carbon::now()->toDateTimeString();
            if ($product = $this->getProductByUrl($site, $productLink, 'id, category')) {
                if ($category_id) {
                    if ($categories = @json_decode($product['category'], true)) {
                        $categories[] = $category_id;
                    } else {
                        $categories = [$category_id];
                    }
                    $this->updateProduct($product['id'], ['category' => @json_encode(array_unique($categories))]);
		}
            } else {
                $stmt = $this->con->prepare("INSERT IGNORE INTO products (url, site, category, created_at) VALUES (:url, :site, :category, :created_at)");
                $stmt->execute([
                    'url' => $productLink, 'site' => $site, 'created_at' => $time, 'category' => json_encode([$category_id])
                ]);
            }
        }
    }

    public function getCategory($url, $site)
    {
        $stmt = $this->con->prepare("SELECT * FROM categories WHERE url=:url AND site=:site");
        $stmt->execute([
            'url' => $url, 'site' => $site
        ]);

        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    public function createCategory($url, $title, $site)
    {
        $stmt = $this->con->prepare("INSERT INTO categories (url, title, site, updated_at) VALUES (:url, :title, :site, :updated_at)");
        return $stmt->execute([
            'url' => $url, 'title' => $title, 'site' => $site, 'updated_at' => Carbon::now()->toDateTimeString()
        ]);
    }

    public function updateCategory($url, $title, $site, $visited = false)
    {
        $stmt = $this->con->prepare("UPDATE categories SET title = :title, updated_at = :updated_at, visited = :visited WHERE url = :url AND site = :site");
        return $stmt->execute([
            'url' => $url, 'title' => $title, 'site' => $site,
            'updated_at' => Carbon::now()->toDateTimeString(),
            'visited' => ($visited ? Carbon::now()->toDateTimeString() : null)
        ]);
    }

    public function getCategories($site, $notVisited = false, $limit = 100, $offset = 0)
    {
        $stmt = $this->con->prepare('SELECT * FROM categories WHERE site = :site '. ($notVisited ? 'AND visited IS NULL ' : '') .' LIMIT :limit OFFSET :offset');
        $stmt->bindValue(':site', $site);
        $stmt->bindValue(':limit', intval($limit ?? 100), \PDO::PARAM_INT);
        $stmt->bindValue(':offset', intval($offset ?? 0), \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getCategoryForParsing($site)
    {
        $stmt = $this->con->prepare("SELECT * FROM categories WHERE site=:site ORDER BY id ASC");
        $stmt->execute([
            'site' => $site
        ]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function deleteUnusedCategories($site, $exceptForUrls)
    {
        $stmt = $this->con->prepare("DELETE FROM categories WHERE site = :site AND url not in (".implode(',', $exceptForUrls).")");
        $stmt->execute([
		'site' => $site
        ]);
    }

    public function resetAll($site = '')
    {
        $stmt = $this->con->prepare('UPDATE products SET visited = null WHERE site = :site');
        $stmt->execute(['site' => $site]);
        $stmt = $this->con->prepare('UPDATE categoris SET visited = null WHERE site = :site');
        $stmt->execute(['site' => $site]);
    }

    public function test()
    {

    }

    public function getProductByUrl($site, $url, $select = '*')
    {
        $stmt = $this->con->prepare('SELECT ' . $select .' FROM products WHERE url = :url AND site = :site');
        $stmt->execute([
            'url' => $url, 'site' => $site
        ]);

        return $stmt->fetch(\PDO::FETCH_ASSOC);

    }


    public function isRunnerActive()
    {
        $stmt = $this->con->prepare("SELECT MAX(visited) FROM products");
        $stmt->execute();
        if (($max1 = $stmt->fetchColumn(0)) && ( time() -  strtotime($max1)) >  5*60) {
            return false;
        }
        $stmt = $this->con->prepare("SELECT MAX(visited) FROM categories");
        $stmt->execute();
        if (($max2 = $stmt->fetchColumn(0)) && ( time() -  strtotime($max2)) >  5*60) {
            return false;
        }
        return true;
    }
}

