<?php

require 'vendor/autoload.php';

use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

try {
    $manager = new ImageManager(new Driver());
    echo "SUCCESS: ImageManager instantiated correctly.\n";
    var_dump($manager);
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
