<?php
require __DIR__.'/../../vendor/autoload.php';
ini_set('max_execution_time', 60);

use \Carica\CanvasGraphics\Vectorizer\Primitive;

$shape = new Primitive\Shape\Triangle(100, 100);
$canvas = $shape->rasterize();

header('Content-Type: image/png');
echo $canvas->toBlob('image/png');
