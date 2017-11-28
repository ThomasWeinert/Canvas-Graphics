<?php

namespace Carica\BitmapToSVG\Vectorizer\Paths {

  use Carica\BitmapToSVG\Color;
  use Carica\BitmapToSVG\Utility\Options;

  class ColorQuantization {

    public const OPTION_PALETTE = 'palette';
    public const OPTION_NUMBER_OF_COLORS = 'number_of_colors';
    public const OPTION_BLUR_FACTOR = 'blur_factor';
    public const OPTION_CYCLES = 'cycles';
    public const OPTION_MINIMUM_COLOR_RATIO = 'minimum_color_ratio';

    public const PALETTE_GENERATED = Color\PaletteFactory::PALETTE_GENERATED;
    public const PALETTE_SAMPLED = Color\PaletteFactory::PALETTE_SAMPLED;
    public const PALETTE_COLOR_THIEF = Color\PaletteFactory::PALETTE_COLOR_THIEF;

    private static $_optionDefaults = [
      self::OPTION_PALETTE => self::PALETTE_SAMPLED,
      self::OPTION_NUMBER_OF_COLORS => 16,
      self::OPTION_BLUR_FACTOR => 0,
      self::OPTION_CYCLES => 3,
      self::OPTION_MINIMUM_COLOR_RATIO => 0
    ];

    private $_image;
    private $_options;
    private $_palette;

    /**
     * ColorQuantization constructor.
     *
     * @param $image
     * @param array $options
     * @param array|Color\Palette $palette
     */
    public function __construct($image, array $options = [], $palette = NULL) {
      $this->_image = $image;
      $this->_options = new Options(self::$_optionDefaults, $options);

    }

    public function setPalette($palette): void {
      if (\is_array($palette) || $palette instanceof Color\Palette) {
        $this->_palette = $palette;
      } else {
        throw new \InvalidArgumentException(
          sprintf(
            '$palette need to be an array or a %s',
            Color\Palette::class
          )
        );
      }
    }

    public function getPalette(): array {
      if (!\is_array($this->_palette)) {
        if ($this->_palette instanceof Color\Palette) {
          $this->_palette = $this->_palette->asArray();
        } else {
          $this->_palette = Color\PaletteFactory::createPalette(
            $this->_options[self::OPTION_PALETTE],
            $this->_image,
            $this->_options[self::OPTION_NUMBER_OF_COLORS]
          )->asArray();
        }
      }
      return $this->_palette;
    }

    public function getMatrix(): array {
      $width = \imagesx($this->_image);
      $height = \imagesy($this->_image);
      $pixelCount = $width * $height;
      $result = \array_fill(
        0,
        $height + 2,
        \array_fill(0, $width + 2, -1)
      );

      $palette = $this->getPalette();
      $image = $this->getBlurred($this->_image, $this->_options[self::OPTION_BLUR_FACTOR]);

      $accumulator = [];
      $numberOfCycles = $this->_options[self::OPTION_CYCLES] > 0 ? $this->_options[self::OPTION_CYCLES] : 1;
      $minimumColorRatio = $this->_options[self::OPTION_MINIMUM_COLOR_RATIO];
      $numberOfColors = \count($palette);
      for ($cycle = 0; $cycle < $numberOfCycles; $cycle++) {
        $isLastIteration = $cycle >= ($numberOfCycles - 1);
        /*
         * Average colors starting with the second iteration.
         * $accumulator is an empty array in the first iteration
         */
        foreach ($accumulator as $index => $data) {
          // Randomizing a color, if there are too few pixels and there will be a new cycle
          if (
            (!$isLastIteration) &&
            ($data['n'] / $pixelCount < $minimumColorRatio)
          ) {
            $palette[$index] = Color::createRandom();
          } elseif ($data['n'] > 0) {
            // averaging color
            $palette[$index] = Color::create(
              \floor($data['r'] / $data['n']),
              \floor($data['g'] / $data['n']),
              \floor($data['b'] / $data['n']),
              \floor($data['a'] / $data['n'])
            );
          }
        }
        // Reset palette accumulator for averaging
        $accumulator = \array_fill(0, $numberOfColors, ['r' => 0, 'g' => 0, 'b' => 0, 'a' => 0, 'n' => 0]);
        $cache = [];
        for ($y = 0; $y < $height; $y++) {
          for ($x = 0; $x < $width; $x++) {
            $rgba = \imagecolorat($image, $x, $y);
            if (array_key_exists($rgba, $cache)) {
              $closestColorIndex = $cache[$rgba];
            } else {
              $pixelColor = [
                'red' => ($rgba >> 16) & 0xFF,
                'green' => ($rgba >> 8) & 0xFF,
                'blue' => $rgba & 0xFF,
                'alpha' =>  (127 - (($rgba & 0x7F000000) >> 24)) / 127 * 255
              ];
              $closestColorIndex = $this->getClosestColorIndex($palette, $pixelColor);
              $cache[$rgba] = $closestColorIndex;
            }

            if ($isLastIteration) {
              // store color index in result
              $result[$y + 1][$x + 1] = $closestColorIndex;
            } else {
              // accumulate color values for averaging
              $accumulator[$closestColorIndex]['r'] += $pixelColor['red'];
              $accumulator[$closestColorIndex]['g'] += $pixelColor['green'];
              $accumulator[$closestColorIndex]['b'] += $pixelColor['blue'];
              $accumulator[$closestColorIndex]['a'] += $pixelColor['alpha'];
              $accumulator[$closestColorIndex]['n']++;
            }
          }
        }
      }
      return $result;
    }

    /**
     * find closest color from palette by measuring (rectilinear) color distance between this pixel and all palette colors
     *
     * @param $palette
     * @param $pixelColor
     * @return int|string
     */
    private function getClosestColorIndex(array $palette, array $pixelColor): int {
      $closestColorIndex = 0;
      $closestDistance = PHP_INT_MAX;
      foreach ($palette as $paletteIndex => $paletteColor) {
        $colorDistance =
          \abs($paletteColor['red'] - $pixelColor['red']) +
          \abs($paletteColor['green'] - $pixelColor['green']) +
          \abs($paletteColor['blue'] - $pixelColor['blue']) +
          \abs($paletteColor['alpha'] - $pixelColor['alpha']);

        if ($colorDistance < $closestDistance) {
          $closestDistance = $colorDistance;
          $closestColorIndex = $paletteIndex;
        }
      }
      return $closestColorIndex;
    }

    /**
     * @param $image
     * @param int $blurFactor
     * @return mixed
     *
     * @author Martijn Frazer, idea based on http://stackoverflow.com/a/20264482
     */
    private function getBlurred($image, int $blurFactor = 0) {
      $originalWidth = \imagesx($image);
      $originalHeight = \imagesy($image);

      $smallestWidth = \ceil($originalWidth * (0.5 ** $blurFactor));
      $smallestHeight = \ceil($originalHeight * (0.5 ** $blurFactor));

      // for the first run, the previous image is the original input
      $prevImage = $nextImage = $image;
      $prevWidth = $nextWidth = $originalWidth;
      $prevHeight = $nextHeight = $originalHeight;

      // scale way down and gradually scale back up, blurring all the way
      for($i = 1; $i < $blurFactor; $i++) {
        // determine dimensions of next image
        $nextWidth = $smallestWidth * (2 ** $i);
        $nextHeight = $smallestHeight * (2 ** $i);

        // resize previous image to next size
        $nextImage = \imagecreatetruecolor($nextWidth, $nextHeight);
        \imagecopyresized(
          $nextImage, $prevImage,
          0, 0, 0, 0,
          $nextWidth, $nextHeight, $prevWidth, $prevHeight
        );

        // apply blur filter
        \imagefilter($nextImage, IMG_FILTER_GAUSSIAN_BLUR);

        // cleanup $prevImage
        if ($prevImage !== $image) {
          \imagedestroy($prevImage);
        }

        // now the new image becomes the previous image for the next step
        $prevImage = $nextImage;
        $prevWidth = $nextWidth;
        $prevHeight = $nextHeight;
      }

      $result = \imagecreatetruecolor($originalWidth, $originalHeight);
      // scale back to original size and blur one more time
      \imagecopyresized(
        $result, $nextImage,
        0, 0, 0, 0,
        $originalWidth, $originalHeight, $nextWidth, $nextHeight
      );
      if ($blurFactor > 0) {
        \imagefilter($result, IMG_FILTER_GAUSSIAN_BLUR);
      }

      // clean up
      if ($nextImage !== $image) {
        \imagedestroy($nextImage);
      }

      // return result
      return $result;
    }
  }
}
