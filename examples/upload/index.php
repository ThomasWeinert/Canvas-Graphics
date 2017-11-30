<?php

require __DIR__.'/../../vendor/autoload.php';
ini_set('max_execution_time', 60);

use \Carica\BitmapToSVG;
use \Carica\BitmapToSVG\SVG;
use \Carica\BitmapToSVG\Vectorizer;

if (
  isset($_FILES['bitmap']['tmp_name']) &&
  is_uploaded_file($_FILES['bitmap']['tmp_name'])
) {

  $path = __DIR__.'/images';
  $id = md5(uniqid('', TRUE));
  [,,$bitmapType] = getimagesize($_FILES['bitmap']['tmp_name']);
  $bitmapFile = $path.'/'.$id.image_type_to_extension($bitmapType);
  @mkdir($path, 0777, TRUE);
  if (
    (
      $bitmapType === IMAGETYPE_JPEG || $bitmapType = IMAGETYPE_PNG
    ) &&
    is_dir($path) &&
    move_uploaded_file($_FILES['bitmap']['tmp_name'], $bitmapFile)
  ) {
    // start converting
    $image = FALSE;
    switch ($bitmapType) {
    case IMAGETYPE_JPEG :
      $image = imagecreatefromjpeg($bitmapFile);
      break;
    case IMAGETYPE_PNG :
      $image = imagecreatefrompng($bitmapFile);
      break;
    }
    if ($image) {
      $start = microtime(TRUE);
      $paths = new Vectorizer\Paths(
        $image,
        [
          Vectorizer\Paths::OPTION_LINE_THRESHOLD => 1.0,
          Vectorizer\Paths::OPTION_QUADRATIC_SPLINE_THRESHOLD => 1.0,
          Vectorizer\Paths::OPTION_ENHANCE_RIGHT_ANGLE => FALSE,
          Vectorizer\Paths::OPTION_MINIMUM_PATH_NODES => 8,
          Vectorizer\Paths::OPTION_STROKE_WIDTH => 0.1,

          Vectorizer\Paths\ColorQuantization::OPTION_PALETTE => BitmapToSVG\Color\PaletteFactory::PALETTE_SAMPLED,
          Vectorizer\Paths\ColorQuantization::OPTION_NUMBER_OF_COLORS => 16,
          Vectorizer\Paths\ColorQuantization::OPTION_BLUR_FACTOR => 1,
          Vectorizer\Paths\ColorQuantization::OPTION_CYCLES => 3,
          Vectorizer\Paths\ColorQuantization::OPTION_MINIMUM_COLOR_RATIO => 0
        ]
      );
      $svg = new SVG\Document(
        imagesx($image),
        imagesy($image),
        [
          SVG\Document::OPTION_BLUR => 0,
          SVG\Document::OPTION_FORMAT_OUTPUT => TRUE
        ]
      );
      $svg->append($paths);
      $xml = $svg->getXML();
      file_put_contents($path.'/'.$id.'.svg', $xml);
      $timeNeeded = microtime(TRUE) - $start;
    }
  }
}

$values = [
  'bitmap' => isset($bitmapFile) ? htmlspecialchars('images/'.basename($bitmapFile)): '',
  'svg_xml' => isset($svg) ? htmlspecialchars($xml) : '',
  'svg_data' => isset($svg) ? htmlspecialchars('data:image/svg+xml;base64,'.base64_encode($xml)) : '',
  'size_bitmap' => isset($bitmapFile) ? filesize($bitmapFile) : '',
  'size_svg' => isset($bitmapFile) ? filesize($path.'/'.$id.'.svg') : '',
  'time_needed' => isset($timeNeeded) ? number_format($timeNeeded, 4) : ''
]

?>
<html>
  <head>
    <title>Upload + Convert</title>
    <style type="text/css">
      .images {
        display: flex;
      }
      .images img {
        max-width: 300px;
        margin: auto;
      }
      textarea {
        width: 100%;
        height: 600px;
      }
    </style>
  </head>
  <body>
    <form action="index.php" method="post" enctype="multipart/form-data">
      <label>Bitmap file:</label>
      <input type="file" name="bitmap">
      <button type="submit">Upload</button>
    </form>
    <ul>
      <li>Bitmap: <?=$values['size_bitmap']?></li>
      <li>SVG: <?=$values['size_svg']?> </li>
      <li>Time: <?=$values['time_needed']?>s </li>
    </ul>
    <section class="images">
      <img src="<?=$values['bitmap']?>"/>
      <img src="<?=$values['svg_data']?>"/>
    </section>
    <textarea><?=$values['svg_xml']?></textarea>
  </body>
</html>
