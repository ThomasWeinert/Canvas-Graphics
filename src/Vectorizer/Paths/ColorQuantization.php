<?php

namespace Carica\CanvasGraphics\Vectorizer\Paths {

  use Carica\CanvasGraphics\Canvas\ImageData;
  use Carica\CanvasGraphics\Color;
  use Carica\CanvasGraphics\Utility\Options;

  class ColorQuantization {

    public const OPTION_PALETTE = 'palette';
    public const OPTION_NUMBER_OF_COLORS = 'number_of_colors';
    public const OPTION_BACKGROUND_COLOR = 'background_color';
    public const OPTION_CYCLES = 'cycles';
    public const OPTION_MINIMUM_COLOR_RATIO = 'minimum_color_ratio';

    private static $_optionDefaults = [
      self::OPTION_PALETTE => Color\PaletteFactory::PALETTE_COLOR_THIEF,
      self::OPTION_NUMBER_OF_COLORS => 16,
      self::OPTION_BACKGROUND_COLOR => NULL,
      self::OPTION_CYCLES => 1,
      self::OPTION_MINIMUM_COLOR_RATIO => 0
    ];

    /**
     * @var ImageData
     */
    private $_imageData;
    /**
     * @var Options
     */
    private $_options;
    /**
     * @var array
     */
    private $_palette;

    /**
     * ColorQuantization constructor.
     *
     * @param ImageData $imageData
     * @param array $options
     * @param array|Color\Palette $palette
     */
    public function __construct(ImageData $imageData, array $options = [], $palette = NULL) {
      $this->_imageData = $imageData;
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
          $this->_palette = $this->_palette->toArray();
        } else {
          $this->_palette = Color\PaletteFactory::createPalette(
            $this->_options[self::OPTION_PALETTE],
            $this->_imageData,
            $this->_options[self::OPTION_NUMBER_OF_COLORS],
            $this->_options[self::OPTION_BACKGROUND_COLOR]
          )->toArray();
        }
      }
      return $this->_palette;
    }

    public function getMatrix(): array {
      $imageData = $this->_imageData;
      $width = $imageData->width;
      $height = $imageData->height;
      $pixelCount = $width * $height;
      $result = \array_fill(
        0,
        $height + 2,
        \array_fill(0, $width + 2, -1)
      );
      $palette = $this->getPalette();

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
            $index = ($y * $width + $x) * 4;
            $pixelColor = [
              'red' => $imageData->data[$index],
              'green' => $imageData->data[$index + 1],
              'blue' => $imageData->data[$index + 2],
              'alpha' => $imageData->data[$index + 3]
            ];
            $rgba =
              ($pixelColor['red'] << 24) +
              ($pixelColor['green'] << 16) +
              ($pixelColor['blue'] << 8) +
              $pixelColor['alpha'];
            if (isset($cache[$rgba])) {
              [$closestColorIndex, $pixelColor] = $cache[$rgba];
            } else {
              $closestColorIndex = $this->getClosestColorIndex($palette, $pixelColor);
              $cache[$rgba] = [$closestColorIndex, $pixelColor];
            }

            if ($isLastIteration) {
              // store color index in result
              $result[$y + 1][$x + 1] = $closestColorIndex;
            } else {
              // accumulate color values for averaging
              if (isset($accumulator[$closestColorIndex])) {
                $accumulator[$closestColorIndex]['r'] += $pixelColor['red'];
                $accumulator[$closestColorIndex]['g'] += $pixelColor['green'];
                $accumulator[$closestColorIndex]['b'] += $pixelColor['blue'];
                $accumulator[$closestColorIndex]['a'] += $pixelColor['alpha'];
                $accumulator[$closestColorIndex]['n']++;
              } else {
                $accumulator[$closestColorIndex] = [
                  'r' => $pixelColor['red'],
                  'g' => $pixelColor['green'],
                  'b' => $pixelColor['blue'],
                  'a' => $pixelColor['alpha'],
                  'n' => 1
                ];
              }
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
