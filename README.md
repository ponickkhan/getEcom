# getEcom
A web data crawler for e-commerce in php

How to scrape here?
===================

To scrape a shop at www.XXX.com
you need to write the script src/Site/Xxx.php
You can copy one of the scripts at src/Site/ to start with.

Inside Xxx.php you need to implement 3 functions:

fetchCategories() - get all the links to products categories  pages.
fetchProducts() - get all the links to product pages
fetchProductData() - scrape information from the product page

Moreover you need to add your new package Xxx.php at:
src/Command/Fetch.php
src/Site.php

To run tests and check each of the functions independantly you can use
src/Commands/Test.php

To run the program use:
php index.php test --sites xxx

When the functions are done, we run it using:
php index.php fetch --sites xxx

# Database Table 

`CREATE TABLE `products` (
   `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
   `site` varchar(50) DEFAULT NULL,
   `url` varchar(255) DEFAULT NULL,
   `product_code` varchar(255) DEFAULT NULL,
   `title` varchar(255) DEFAULT NULL,
   `description` text,
   `image` text,
   `video` varchar(255) DEFAULT NULL,
   `model` varchar(150) DEFAULT NULL,
   `manufacturer` varchar(150) DEFAULT NULL,
   `warranty` varchar(150) DEFAULT NULL,
   `delivery` varchar(150) DEFAULT NULL,
   `price` int(11) DEFAULT NULL,
   `sale_price` int(11) DEFAULT NULL,
   `ship_price` int(11) DEFAULT NULL,
   `options` json DEFAULT NULL,
   `category` json DEFAULT NULL,
   `created_at` datetime DEFAULT NULL,
   `updated_at` datetime DEFAULT NULL,
   `visited` datetime DEFAULT NULL,
   PRIMARY KEY (`id`),
   UNIQUE KEY `site` (`site`,`url`)
 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
 
 CREATE TABLE `categories` (
   `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
   `title` varchar(255) DEFAULT NULL,
   `url` varchar(255) DEFAULT NULL,
   `site` varchar(50) DEFAULT NULL,
   `updated_at` datetime DEFAULT NULL,
   `visited` datetime DEFAULT NULL,
   PRIMARY KEY (`id`),
   UNIQUE KEY `url` (`url`,`site`)
 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;`
