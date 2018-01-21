<?php

require __DIR__.'/../../vendor/autoload.php';
ini_set('max_execution_time', -1);

use \Carica\CanvasGraphics;
use \Carica\CanvasGraphics\SVG;
use \Carica\CanvasGraphics\Vectorizer;

header('Content-Type: text/plain');

$numberOfShapes = max(1, min(2000, (int)($_POST['number_of_shapes'] ?? 10)));
$shapeType = $_POST['shape_type'] ?? Vectorizer\Primitive::SHAPE_TRIANGLE;
$randomStartShapes = max(1, min(100, (int)($_POST['random_start_shapes'] ?? 15)));
$mutationFailureLimit = max(1, min(50, (int)($_POST['mutation_failure_limit'] ?? 30)));
$blurFactor = max(0, min(20, (int)($_POST['blur_factor'] ?? 12)));
$alphaTransparency = max(0.5, min(1, (float)($_POST['alpha_transparency'] ?? 0.8)));
$adjustAlphaTransparency = (bool)($_POST['adjust_alpha_transparency'] ?? FALSE);
$incremental = (bool)$_POST['incremental_result'];

if (
  isset($_FILES['bitmap']['tmp_name']) &&
  is_uploaded_file($_FILES['bitmap']['tmp_name'])
) {

  [,,$bitmapType] = getimagesize($_FILES['bitmap']['tmp_name']);
  if ($bitmapType === IMAGETYPE_JPEG || $bitmapType = IMAGETYPE_PNG) {
    // start converting
    $image = CanvasGraphics\Canvas\GD\Image::load($_FILES['bitmap']['tmp_name']);
    if ($image) {

      $image->filter(
        new CanvasGraphics\Canvas\GD\Filter\LimitSize(150, 150)
      );
      $start = microtime(TRUE);
      $context = $image->getContext('2d');
      $imageData = $context->getImageData();
      $primitive = new Vectorizer\Primitive(
        $imageData,
        [
          Vectorizer\Primitive::OPTION_NUMBER_OF_SHAPES => $numberOfShapes, //10,
          Vectorizer\Primitive::OPTION_ITERATION_START_SHAPES => $randomStartShapes, //15, //200,
          Vectorizer\Primitive::OPTION_ITERATION_STOP_MUTATION_FAILURES => $mutationFailureLimit, //30
          Vectorizer\Primitive::OPTION_SHAPE_TYPE => $shapeType,
          Vectorizer\Primitive::OPTION_OPACITY_START => $alphaTransparency,
          Vectorizer\Primitive::OPTION_OPACITY_ADJUST => $adjustAlphaTransparency
        ]
      );
      $svg = new SVG\Document(
        $imageData->width,
        $imageData->height,
        [
          SVG\Document::OPTION_BLUR => 12,
          SVG\Document::OPTION_FORMAT_OUTPUT => FALSE
        ]
      );
      $emit = function ($event, array $values = []) use ($start) {
        $data= [
          'event' => $event,
          'time' => number_format(microtime(TRUE) - $start, 2).'s'
        ];
        echo json_encode(
          array_merge($data, $values)
        ), "\n";
        flush();
        ob_flush();
      };

      $primitive->onBeforeCreateShapes(
        function() use ($emit) {
          $emit('before-create-shapes');
        }
      );
      $primitive->onShapeAdded(
        function($index, Vectorizer\Primitive\Shape $shape, CanvasGraphics\Canvas\ImageData $targetData) use ($svg, $emit) {
          $emit(
            'shape-added',
            [
              'shape' => $index + 1,
              'svg' => $svg->getXML()
            ]
          );
        }
      );
      $primitive->onShapeDiscarded(
        function($index, Vectorizer\Primitive\Shape $shape, $change) use ($emit) {
          $emit(
            'shape-discarded',
            [
              'shape' => $index + 1,
              'change' => $change
            ]
          );
        }
      );
      $primitive->onShapeImproved(
        function ($index, $mutations, $failures, $change) use ($emit) {
          $emit(
            'shape-improved',
            [
              'shape' => $index + 1,
              'mutations' => $mutations,
              'failures' => $failures,
              'change' => $change
            ]
          );
        }
      );
      $svg->append($primitive);
      $timeNeeded = microtime(TRUE) - $start;

    }
  }
}
if (!$incremental) {
  echo $svg->getXML();
}
