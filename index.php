<?php
require_once ('vendor/autoload.php');

$dotenv = Dotenv\Dotenv::create(__DIR__);
$dotenv->overload();
try {
    $dotenv->required(['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS', 'HTML_WHITELIST_TAGS']);
} catch (\Exception $e) {
    die("\nYou are missing required .env variables!\n\n");
}
define('DB_HOST', getenv('DB_HOST'));
define('DB_USER', getenv('DB_USER'));
define('DB_PASS', getenv('DB_PASS'));
define('DB_NAME', getenv('DB_NAME'));

define('BASE_DIR', __DIR__ . '/');
define('HTML_WHITELIST_TAGS',
    explode(',', getenv('HTML_WHITELIST_TAGS', 'strong,small,ul,li,ol,p,h1,h2,h3,h4,h5,h6,span,sub,sup,table,tbody,td,thead,th,tfoot,tr,tt,u,ul,br,div'))
);

$application = new \Symfony\Component\Console\Application();

$application->add(new \App\Commands\Fetch());
$application->add(new \App\Commands\Export());
$application->add(new \App\Commands\Test());

$application->run();

