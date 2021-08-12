# ssocr-php
A package implements ssocr with pure php.

## Installation
Make sure you have installed Imagick extension, and run the command below and you will get the latest version:
```
composer require louissu/ssocr-php
```

## Document
TODO

## Example
```php
<?php
require "vendor/autoload.php";

use Louissu\SSOCR;

$ssocr  = new SSOCR('87.png');
$result = $ssocr
    ->setThreshold(0.1)
    ->setScale(0.5)
    ->run();

echo $result;
```