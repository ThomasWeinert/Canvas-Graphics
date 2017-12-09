<?php

namespace Carica\CanvasGraphics\Color\Palette {

  use Carica\CanvasGraphics\Canvas\ImageData;
  use Carica\CanvasGraphics\Color;

  class ColorThief extends Color\Palette {

    public const SIGBITS = 5;
    public const RSHIFT = 3;

    private const MAX_ITERATIONS = 1000;
    private const FRACT_BY_POPULATIONS = 0.75;

    private $_numberOfColors;
    private $_imageData;
    private $_backgroundColor;

    public function __construct(ImageData $imageData, int $numberOfColors, Color $backgroundColor = NULL) {
      if ($numberOfColors < 2 || $numberOfColors > 256) {
        throw new \OutOfRangeException('The number of palette colors must be between 2 and 256 inclusive.');
      }
      $this->_imageData = $imageData;
      $this->_numberOfColors = $numberOfColors;
      $this->_backgroundColor = $backgroundColor ?? Color::createGray(255);
    }

    public function generate(): array {
      $data = $this->_imageData->data;
      $pixelArray = [];
      for ($i = 0, $c = \count($this->_imageData->data); $i < $c; $i += 4) {
        $color = $this->colorToInt(
          Color::removeAlphaFromColor(
            [
              'red' => $data[$i],
              'green' => $data[$i + 1],
              'blue' => $data[$i + 2],
              'alpha' => $data[$i + 3]
            ],
            $this->_backgroundColor
          )
        );
        $pixelArray[] = $color;
      }
      $cmap = $this->quantize($pixelArray, $this->_numberOfColors);

      $palette = [];
      foreach ($cmap->palette() as $rgb) {
        $color = Color::create(...$rgb);
        $palette[$color->toInt()] = $color;
      }
      return $palette;
    }

    private function colorToInt($color) {
      return ($color['red'] << 16) + ($color['green'] << 8) + $color['blue'];
    }


    /**
     * @param array $pixels
     * @param int $numberOfColors
     * @return bool|ColorThief\CMap
     */
    public function quantize(array $pixels, int $numberOfColors): ColorThief\CMap {
      if ($numberOfColors < 2 || $numberOfColors > 256 || \count($pixels) < 1) {
        // echo 'wrong number of maxcolors'."\n";
        return FALSE;
      }
      $histogram = $this->getHistogram($pixels);
      $vBox = $this->createVBoxFromHistogram($histogram);

      $priorityQueue = new ColorThief\PQueue(
        function (ColorThief\VBox $a, ColorThief\VBox $b) {
          return self::compareNumbers($a->count(), $b->count());
        }
      );
      $priorityQueue->push($vBox);

      // first set of colors, sorted by population
      $this->quantizeQueue($priorityQueue, static::FRACT_BY_POPULATIONS * $numberOfColors, $histogram);

      // Re-sort by the product of pixel occupancy times the size in color space.
      $priorityQueue->setComparator(
        function (ColorThief\VBox $a, ColorThief\VBox $b) {
          return self::compareNumbers($a->count() * $a->volume(), $b->count() * $b->volume());
        }
      );

      // next set - generate the median cuts using the (npix * vol) sorting.
      $this->quantizeQueue($priorityQueue, $numberOfColors - \count($priorityQueue), $histogram);

      // calculate the actual colors
      $cmap = new ColorThief\CMap();
      for ($i = \count($priorityQueue); $i > 0; $i--) {
        $cmap->push($priorityQueue->pop());
      }

      return $cmap;
    }

    private function quantizeQueue(ColorThief\PQueue $priorityQueue, $target, $histogram) {
      $nColors = 1;
      $nIterations = 0;

      while ($nIterations < static::MAX_ITERATIONS) {
        /** @var ColorThief\VBox $vBox */
        $vBox = $priorityQueue->pop();

        if (!$vBox->count()) { /* just put it back */
          $priorityQueue->push($vBox);
          $nIterations++;
          continue;
        }
        // do the cut
        $vBoxes = static::medianCutApply($histogram, $vBox);

        if (!(\is_array($vBoxes) && isset($vBoxes[0]))) {
          // echo "vbox1 not defined; shouldn't happen!"."\n";
          return;
        }

        $priorityQueue->push($vBoxes[0]);

        if (isset($vBoxes[1])) { /* vbox2 can be null */
          $priorityQueue->push($vBoxes[1]);
          $nColors++;
        }

        if ($nColors >= $target) {
          return;
        }

        if ($nIterations++ > static::MAX_ITERATIONS) {
          // echo "infinite loop; perhaps too few pixels!"."\n";
          return;
        }
      }
    }

    private function getHistogram(array $pixels) {
      $histogram = [];
      foreach ($pixels as $rgb) {
        $index = static::getColorIndex(...static::getColorsFromIndex($rgb));
        $histogram[$index] = ($histogram[$index] ?? 0) + 1;
      }
      return $histogram;
    }

    private function createVBoxFromHistogram(array $histogram) {
      $rgbMin = [PHP_INT_MAX, PHP_INT_MAX, PHP_INT_MAX];
      $rgbMax = [0, 0, 0];

      // find min/max
      foreach ($histogram as $index => $count) {
        $rgb = static::getColorsFromIndex($index, 0, self::SIGBITS);

        // For each color components
        for ($i = 0; $i < 3; ++$i) {
          if ($rgb[$i] < $rgbMin[$i]) {
            $rgbMin[$i] = $rgb[$i];
          } elseif ($rgb[$i] > $rgbMax[$i]) {
            $rgbMax[$i] = $rgb[$i];
          }
        }
      }

      return new ColorThief\VBox($rgbMin[0], $rgbMax[0], $rgbMin[1], $rgbMax[1], $rgbMin[2], $rgbMax[2], $histogram);
    }

    /**
     * Get reduced-space color index for a pixel
     *
     * @param int $red
     * @param int $green
     * @param int $blue
     * @param int $sigBits
     * @return int
     */
    public static function getColorIndex($red, $green, $blue, $sigBits = self::SIGBITS): int {
      return ($red << (2 * $sigBits)) + ($green << $sigBits) + $blue;
    }

    /**
     * Get red, green and blue components from reduced-space color index for a pixel
     *
     * @param int $index
     * @param int $rightShift
     * @param int $sigBits
     * @return array
     */
    public static function getColorsFromIndex($index, $rightShift = self::RSHIFT, $sigBits = 8): array {
      $mask = (1 << $sigBits) - 1;
      $red = (($index >> (2 * $sigBits)) & $mask) >> $rightShift;
      $green = (($index >> $sigBits) & $mask) >> $rightShift;
      $blue = ($index & $mask) >> $rightShift;
      return [$red, $green, $blue];
    }

    /**
     * Natural sorting
     *
     * @param int|float $a
     * @param int|float $b
     * @return int
     */
    public static function compareNumbers($a, $b): int {
      return ($a < $b) ? -1 : (($a > $b) ? 1 : 0);
    }


    /**
     * @param string $color
     * @param ColorThief\VBox $vBox
     * @param array $partialSum
     * @param int $total
     *
     * @return array|NULL
     */
    private static function doCut(string $color, ColorThief\VBox $vBox, array $partialSum, int $total): ?array {
      $dim1 = $color.'1';
      $dim2 = $color.'2';

      for ($i = $vBox->$dim1; $i <= $vBox->$dim2; $i++) {
        if ($partialSum[$i] > $total / 2) {
          $vBox1 = $vBox->copy();
          $vBox2 = $vBox->copy();
          $left = $i - $vBox->$dim1;
          $right = $vBox->$dim2 - $i;

          // Choose the cut plane within the greater of the (left, right) sides
          // of the bin in which the median pixel resides
          if ($left <= $right) {
            $d2 = min($vBox->$dim2 - 1, (int)($i + $right / 2));
          } else { /* left > right */
            $d2 = max($vBox->$dim1, (int)($i - 1 - $left / 2));
          }

          while (empty($partialSum[$d2])) {
            $d2++;
          }
          // Avoid 0-count boxes
          while ($partialSum[$d2] >= $total && !empty($partialSum[$d2 - 1])) {
            --$d2;
          }

          // set dimensions
          $vBox1->$dim2 = $d2;
          $vBox2->$dim1 = $d2 + 1;

          return [$vBox1, $vBox2];
        }
      }
      return NULL;
    }

    /**
     * @param array $histogram
     * @param ColorThief\VBox $vBox
     * @return array|NULL
     */
    private static function medianCutApply(array $histogram, ColorThief\VBox $vBox): ?array {
      if (!$vBox->count()) {
        return NULL;
      }

      // If the vbox occupies just one element in color space, it can't be split
      if ($vBox->count() === 1) {
        return [
          $vBox->copy()
        ];
      }

      // Select the longest axis for splitting
      $cutColor = $vBox->longestAxis();

      // Find the partial sum arrays along the selected axis.
      [$total, $partialSum] = static::sumColors($cutColor, $histogram, $vBox);

      return static::doCut($cutColor, $vBox, $partialSum, $total);
    }

    /**
     * Find the partial sum arrays along the selected axis.
     *
     * @param string $axis r|g|b
     * @param array $histogram
     * @param ColorThief\VBox $vBox
     * @return array [$total, $partialSum]
     */
    private static function sumColors(string $axis, array $histogram, ColorThief\VBox $vBox): array {
      $total = 0;
      $partialSum = [];

      // The selected axis should be the first range
      $colorIterateOrder = \array_diff(['r', 'g', 'b'], [$axis]);
      \array_unshift($colorIterateOrder, $axis);

      // Retrieves iteration ranges
      [$firstRange, $secondRange, $thirdRange] = static::getVBoxColorRanges($vBox, $colorIterateOrder);

      foreach ($firstRange as $firstColor) {
        $sum = 0;
        foreach ($secondRange as $secondColor) {
          foreach ($thirdRange as $thirdColor) {
            $rgb = static::rearrangeColors(
              $colorIterateOrder,
              $firstColor,
              $secondColor,
              $thirdColor
            );
            $index = static::getColorIndex(...$rgb);
            if (isset($histogram[$index])) {
              $sum += $histogram[$index];
            }
          }
        }
        $total += $sum;
        $partialSum[$firstColor] = $total;
      }
      return [$total, $partialSum];
    }

    /**
     * @param array $order
     * @param int $color1
     * @param int $color2
     * @param int $color3
     * @return array
     */
    private static function rearrangeColors(array $order, int $color1, int $color2, int $color3): array {
      $data = [
        $order[0] => $color1,
        $order[1] => $color2,
        $order[2] => $color3,
      ];
      return [
        $data['r'],
        $data['g'],
        $data['b']
      ];
    }

    /**
     * @param ColorThief\VBox $vBox
     * @param array $order
     * @return array
     */
    private static function getVBoxColorRanges(ColorThief\VBox $vBox, array $order): array {
      $ranges = [
        'r' => range($vBox->r1, $vBox->r2),
        'g' => range($vBox->g1, $vBox->g2),
        'b' => range($vBox->b1, $vBox->b2)
      ];
      return [
        $ranges[$order[0]],
        $ranges[$order[1]],
        $ranges[$order[2]],
      ];
    }
  }
}
