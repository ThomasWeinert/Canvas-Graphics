<?php

namespace Carica\BitmapToSVG\Vectorizer\Paths {

  use Carica\BitmapToSVG\Color;
  use Carica\BitmapToSVG\Filter\Blur;
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
      self::OPTION_PALETTE => self::PALETTE_COLOR_THIEF,
      self::OPTION_NUMBER_OF_COLORS => 16,
      self::OPTION_BLUR_FACTOR => 0,
      self::OPTION_CYCLES => 1,
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
      $image = $this->_image;
      (new Blur($this->_options[self::OPTION_BLUR_FACTOR]))->apply($image);

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
      $closestDistance = 1;
      foreach ($palette as $paletteIndex => $paletteColor) {
        $colorDistance = Color::computeDistance($pixelColor, $paletteColor);
        if ($colorDistance < $closestDistance) {
          $closestDistance = $colorDistance;
          $closestColorIndex = $paletteIndex;
        }
      }
      return $closestColorIndex;
    }
  }
}
