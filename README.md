# ssocr-php
A package implements [ssocr](https://github.com/auerswal/ssocr) with pure php.

## Requirement
- Imagick extension
## Installation
Make sure you have installed Imagick extension, and run the command below and you will get the latest version:
```
composer require louissu/ssocr-php
```

## Document

### setThreshold
This function sets Threshold for binarization, the value is between 0 to 1. If the result of recognizing is not satisfactory, you can adjust this value.

### setScale
This function sets the scale parameter of resize, the value means the denominator of width and height. For example, the below example means the width and height of 87.png will divide by 2.

If your image is too large to make it slow, you can adjust this value.

## Example
```php
<?php
require "vendor/autoload.php";

use Louissu\SSOCR;

$ssocr  = new SSOCR('87.png');
$result = $ssocr
    ->setThreshold(0.1)
    ->setScale(2)
    ->run();

echo $result;
```