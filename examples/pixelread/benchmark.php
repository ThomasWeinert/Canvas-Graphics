<?php

$width = 200;
$height = 200;
$repeat = 100;

$image = imagecreatetruecolor(200, 200);
imagefilledrectangle($image, 0, 0, 200, 200, imagecolorallocate($image,0,0,0));

function benchmark($repeat, Closure $callback, Closure $before = NULL) {
  $start = microtime(TRUE);
  $arguments = [];
  if (NULL !== $before) {
    $arguments = $before();
  }
  for ($i = 0; $i < $repeat; $i++) {
    $callback(...$arguments);
  }
  return microtime(TRUE) - $start;
}

function getUsingImageColorsForIndex($image, $width, $height) {
  $colors = [];
  for ($x=0;$x <$width; $x++) {
    for ($y=0;$y <$height; $y++) {
      $rgba = imagecolorat($image, $x, $y);
      $colors[] = imagecolorsforindex($image, $rgba);
    }
  }
  return $colors;
}

function getUsingBitWise($image, $width, $height) {
  $colors = [];
  for ($x=0;$x <$width; $x++) {
    for ($y=0;$y <$height; $y++) {
      $rgba = imagecolorat($image, $x, $y);
      $colors[] = [
        'red' => ($rgba >> 16) & 0xFF,
        'green' => ($rgba >> 8) & 0xFF,
        'blue' => $rgba & 0xFF,
        'alpha' =>  (127 - (($rgba & 0x7F000000) >> 24)) / 127 * 255
      ];
    }
  }
  return $colors;
}

function getUsingBitWiseWithCache($image, $width, $height) {
  $cache = [];
  $colors = [];
  for ($x=0;$x <$width; $x++) {
    for ($y=0;$y <$height; $y++) {
      $rgba = imagecolorat($image, $x, $y);
      if (isset($cache[$rgba])) {
        $colors[] = $cache[$rgba];
      } else {
        $colors[] = $cache[$rgba] = [
          'red' => ($rgba >> 16) & 0xFF,
          'green' => ($rgba >> 8) & 0xFF,
          'blue' => $rgba & 0xFF,
          'alpha' => (127 - (($rgba & 0x7F000000) >> 24)) / 127 * 255
        ];
      }
    }
  }
  return $colors;
}

function getUsingBitWiseIntoSplArray($image, $width, $height) {
  $colors = new SplFixedArray($width * $height);
  for ($x=0;$x <$width; $x++) {
    for ($y=0;$y <$height; $y++) {
      $rgba = imagecolorat($image, $x, $y);
      $colors[$x*$y] = [
        'red' => ($rgba >> 16) & 0xFF,
        'green' => ($rgba >> 8) & 0xFF,
        'blue' => $rgba & 0xFF,
        'alpha' =>  (127 - (($rgba & 0x7F000000) >> 24)) / 127 * 255
      ];
    }
  }
  return $colors;
}

function getUsingBitWiseIntoSplArrayWithCache($image, $width, $height) {
  $cache = [];
  $colors = new SplFixedArray($width * $height);
  for ($x=0;$x <$width; $x++) {
    for ($y=0;$y <$height; $y++) {
      $rgba = imagecolorat($image, $x, $y);
      if (isset($cache[$rgba])) {
        $colors[$x*$y] = $cache[$rgba];
      } else {
        $colors[$x * $y] = $cache[$rgba] = [
          'red' => ($rgba >> 16) & 0xFF,
          'green' => ($rgba >> 8) & 0xFF,
          'blue' => $rgba & 0xFF,
          'alpha' => (127 - (($rgba & 0x7F000000) >> 24)) / 127 * 255
        ];
      }
    }
  }
  return $colors;
}

function getAlphaUsingBitWise($image, $width, $height) {
  $alpha = [];
  for ($x=0;$x <$width; $x++) {
    for ($y=0;$y <$height; $y++) {
      $rgba = imagecolorat($image, $x, $y);
      $alpha[] = (127 - (($rgba & 0x7F000000) >> 24)) / 127 * 255;
    }
  }
  return $alpha;
}

function getAlphaFromColorsList($colors) {
  $alpha = [];
  foreach ($colors as $color) {
    $alpha[] = $color['alpha'];
  }
  return $alpha;
}
function getImageDataWithArray($image, $width, $height) {
  $cache = [];
  $data = [];
  for ($x=0;$x <$width; $x++) {
    for ($y=0;$y <$height; $y++) {
      $rgba = imagecolorat($image, $x, $y);
      if (isset($cache[$rgba])) {
        $pixel = $cache[$rgba];
      } else {
        $cache[$rgba] = $pixel = [
          ($rgba >> 16) & 0xFF,
          ($rgba >> 8) & 0xFF,
          $rgba & 0xFF,
          (127 - (($rgba & 0x7F000000) >> 24)) / 127 * 255
        ];
      }
      $data[] = $pixel[0];
      $data[] = $pixel[1];
      $data[] = $pixel[2];
      $data[] = $pixel[3];
    }
  }
  return $data;
}

function getImageDataWithSplFixedArray($image, $width, $height) {
  $cache = [];
  $data = new SplFixedArray($width * $height * 4);
  for ($x=0;$x <$width; $x++) {
    for ($y=0;$y <$height; $y++) {
      $index = $x * $y * 4;
      $rgba = imagecolorat($image, $x, $y);
      if (isset($cache[$rgba])) {
        $pixel = $cache[$rgba];
      } else {
        $cache[$rgba] = $pixel = [
          ($rgba >> 16) & 0xFF,
          ($rgba >> 8) & 0xFF,
          $rgba & 0xFF,
          (127 - (($rgba & 0x7F000000) >> 24)) / 127 * 255
        ];
      }
      $data[$index] = $pixel[0];
      $data[$index+1] = $pixel[1];
      $data[$index+2] = $pixel[2];
      $data[$index+3] = $pixel[3];
    }
  }
  return $data;
}
echo "getImageDataWithArray\n";
echo benchmark(
  $repeat,
  function() use ($image, $width, $height) {
    getImageDataWithArray($image, $width, $height);
  }
);
echo "\n\n";
echo "getImageDataWithSplFixedArray\n";
echo benchmark(
  $repeat,
  function() use ($image, $width, $height) {
    getImageDataWithSplFixedArray($image, $width, $height);
  }
);
echo "\n\n";

echo "imagecolorat + imagecolorsforindex\n";
echo benchmark(
  $repeat,
  function() use ($image, $width, $height) {
    getUsingImageColorsForIndex($image, $width, $height);
  }
);
echo "\n\n";

echo "imagecolorat + bitwise\n";
echo benchmark(
  $repeat,
  function() use ($image, $width, $height) {
    getUsingBitWise($image, $width, $height);
  }
);
echo "\n\n";

echo "imagecolorat + bitwise with cache\n";
echo benchmark(
  $repeat,
  function() use ($image, $width, $height) {
    getUsingBitWiseWithCache($image, $width, $height);
  }
);
echo "\n\n";

echo "alpha using imagecolorat\n";
echo benchmark(
  $repeat,
  function() use ($image, $width, $height) {
    getUsingBitWiseIntoSplArray($image, $width, $height);
  }
);
echo "\n\n";

echo "alpha with prepared bitwise into SPLFixedArray\n";
echo benchmark(
  $repeat,
  function($colors) {
    getAlphaFromColorsList($colors);
  },
  function() use ($image, $width, $height) {
    return [getUsingBitWiseIntoSplArrayWithCache($image, $width, $height)];
  }
);
echo "\n\n";

echo "alpha with prepared bitwise\n";
echo benchmark(
  $repeat,
  function($colors) {
    getAlphaFromColorsList($colors);
  },
  function() use ($image, $width, $height) {
    return [getUsingBitWiseWithCache($image, $width, $height)];
  }
);
echo "\n\n";



