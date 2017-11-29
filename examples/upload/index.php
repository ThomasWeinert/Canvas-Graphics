<?php

require __DIR__.'/../../vendor/autoload.php';
ini_set('max_execution_time', 60);

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
      $svg = new \Carica\BitmapToSVG\SVG\Document(
        imagesx($image),
        imagesy($image)
      );
      $svg->append(new \Carica\BitmapToSVG\Vectorizer\Paths($image));
      $xml = $svg->getXML();
      file_put_contents($path.'/'.$id.'.svg', $xml);
    }
  }
}

$values = [
  'bitmap' => isset($bitmapFile) ? htmlspecialchars('images/'.basename($bitmapFile)): '',
  'svg_xml' => isset($svg) ? htmlspecialchars($xml) : '',
  'svg_data' => isset($svg) ? htmlspecialchars('data:image/svg+xml;base64,'.base64_encode($xml)) : '',
  'size_bitmap' => isset($bitmapFile) ? filesize($bitmapFile) : '',
  'size_svg' => isset($bitmapFile) ? filesize($path.'/'.$id.'.svg') : ''
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
    </ul>
    <section class="images">
      <img src="<?=$values['bitmap']?>"/>
      <img src="<?=$values['svg_data']?>"/>
    </section>
    <textarea><?=$values['svg_xml']?></textarea>
  </body>
</html>
