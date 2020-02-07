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
