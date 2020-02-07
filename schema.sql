DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS categories;

CREATE TABLE `products` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
