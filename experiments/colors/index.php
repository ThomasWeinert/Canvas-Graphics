<?php

require __DIR__.'/../../vendor/autoload.php';
ini_set('max_execution_time', 60);

use \Carica\CanvasGraphics;

$numberOfColors = max(
  1,
  min(128, (int)($_POST['number_of_colors'] ?? 6))
);

if (
  isset($_FILES['bitmap']['tmp_name']) &&
  is_uploaded_file($_FILES['bitmap']['tmp_name'])
) {

  $path = __DIR__.'/images';
  $id = md5(uniqid('', TRUE));
  [,,$bitmapType] = getimagesize($_FILES['bitmap']['tmp_name']);
  @mkdir($path, 0777, TRUE);
  if ($bitmapType === IMAGETYPE_JPEG || $bitmapType = IMAGETYPE_PNG) {
    // start converting
    $image = CanvasGraphics\Canvas\GD\Image::load($_FILES['bitmap']['tmp_name']);
    if ($image) {
      $start = microtime(TRUE);


      $image->filter(
        new CanvasGraphics\Canvas\GD\Filter\LimitSize(200, 200),
        new CanvasGraphics\Canvas\GD\Filter\Blur(4)
      );
      $context = $image->getContext('2d');
      $imageData = $context->getImageData();
      $palette = CanvasGraphics\Color\PaletteFactory::createPalette(
        CanvasGraphics\Color\PaletteFactory::PALETTE_COLOR_THIEF,
        $imageData,
        $numberOfColors
      );

      $colors = '';
      /** @var CanvasGraphics\Color $color */
      foreach ($palette as $color) {
        $colors .= sprintf(
          '<div class="colorSquare" style="background-color: %1$s;" title="%1$s">&nbsp;</div> ',
          $color->toHexString()
        );
      }

      $timeNeeded = microtime(TRUE) - $start;
    }
  }
}


$values = [
  'colors' => $colors ?? '',
  'number_of_colors' => $numberOfColors,
  'time_needed' => isset($timeNeeded) ? number_format($timeNeeded, 4) : ''
]

?>
<html>
  <head>
    <title>Upload + Compute Palette</title>
    <style type="text/css">
      body {
        background-color: black;
        color: white;
      }
      form {
        text-align: center;
      }
      form ul {
        list-style: none;
        display: inline-block;
      }
      form ul li {
        display: inline-block;
      }
      .colors {
        display: flex;
      }
      .colors .colorSquare {
        width: 60px;
        height: 60px;
        margin: auto;
        border: 1px solid rgba(255, 255, 255, 0.5);
        position: relative;
      }
      .colors .colorSquare::after {
        content: attr(title);
        position: absolute;
        bottom: 0;
        width: 100%;
        background-color: white;
        color: black;
        display: block;
        font-size: 10px;
        text-align: center;
      }
    </style>
  </head>
  <body>
    <form action="index.php" method="post" enctype="multipart/form-data">
      <label>Bitmap file:</label>
      <input type="file" name="bitmap">
      <label>Number of colors:</label>
      <input type="number" name="number_of_colors" min="1" max="128" step="1" value="<?=$values['number_of_colors']?>">
      <button type="submit">Upload</button>
      <hr>
      <ul>
        <li>Time: <?=$values['time_needed']?>s </li>
      </ul>
    </form>
    <section class="colors">
      <?=$values['colors']?>
    </section>
  </body>
</html>
