<?php

namespace Carica\CanvasGraphics {

  /**
   * Class Color
   *
   * @property int $red
   * @property int $green
   * @property int $blue
   * @property int $alpha
   * @property-read float $hue
   * @property-read float $saturation
   * @property-read float $lightness
   */
  class Color implements \ArrayAccess {

    private const FLOAT_DELTA = 0.000001;

    private $_rgba = [
      'red' => 0,
      'green' => 0,
      'blue' => 0,
      'alpha' => 255
    ];

    private $_hsl;

    public function __construct(int $red, int $green, int $blue, int $alpha = 255) {
      $this->setValue('red', $red);
      $this->setValue('green', $green);
      $this->setValue('blue', $blue);
      $this->setValue('alpha', $alpha);
    }

    public function __toString() {
      $hsl = $this->asHSL();
      return sprintf(
        'rgba(%d, %d, %d, %d), hsl(%01.2f, %01.2f, %01.2f)',
        $this->_rgba['red'],
        $this->_rgba['green'],
        $this->_rgba['blue'],
        $this->_rgba['alpha'],
        $hsl['hue'],
        $hsl['saturation'],
        $hsl['lightness']
      );
    }

    public function asHexString($withAlpha = FALSE) {
      if ($withAlpha) {
        return sprintf(
          '#%02x%02x%02x%02x',
          $this->_rgba['red'],
          $this->_rgba['green'],
          $this->_rgba['blue'],
          $this->_rgba['alpha']
        );
      } else {
        $result = sprintf(
          '#%02x%02x%02x',
          $this->_rgba['red'],
          $this->_rgba['green'],
          $this->_rgba['blue']
        );
        if (preg_match('(^#(([A-Fa-f\d])\g{-1}){3}$)', $result)) {
          return $result[0].$result[1].$result[3].$result[5];
        }
        return $result;
      }
    }

    public function asHSL() {
      if (NULL === $this->_hsl) {
        $this->_hsl = self::convertRGBToHSL($this->_rgba['red'], $this->_rgba['green'], $this->_rgba['blue']);
      }
      return $this->_hsl;
    }

    /**
     * @param int $red
     * @param int $green
     * @param int $blue
     * @param int $alpha
     * @return Color
     */
    public static function create(int $red, int $green, int $blue, int $alpha = 255): self {
      return new self($red, $green, $blue, $alpha);
    }

    /**
     * All color parts of the color (rgb) will get the same value
     *
     * @param int $value
     * @param int $alpha
     * @return Color
     */
    public static function createGray(int $value, int $alpha = 255): self {
      return new self($value, $value, $value, $alpha);
    }

    /**
     * @param array $values
     * @return Color
     */
    public static function createFromArray(array $values): self {
      return new self(
        $values['red'] ?? $values['r'] ?? $values[0] ?? 0,
        $values['green'] ?? $values['g'] ?? $values[1] ?? 0,
        $values['blue'] ?? $values['b'] ?? $values[2] ?? 0,
        $values['alpha'] ?? $values['a'] ?? $values[3] ?? 255
      );
    }

    /**
     * Create a random color - the transparency can be specified
     *
     * @param int|NULL $alpha
     * @return Color
     */
    public static function createRandom(int $alpha = NULL): self {
      return new self(\random_int(0, 255), \random_int(0, 255), \random_int(0, 255), $alpha ?? \random_int(0, 255));
    }

    /**
     * Create a rbg color from HSL
     *
     * @param float $hue
     * @param float $saturation
     * @param float $lightness
     * @return Color
     */
    public static function createFromHSL(float $hue, float $saturation, float $lightness): self {
      return self::createFromArray(self::convertHSLToRGB($hue, $saturation , $lightness));
    }

    /**
     * @param string $name
     * @param int $value
     * @throws \LogicException
     */
    private function setValue(string $name, int $value) {
      if ($value < 0 || $value > 255) {
        throw new \OutOfRangeException('Value needs to be between 0 and 255.');
      }
      switch ($name) {
      case 'r':
      case 'red':
        $this->_rgba['red'] = $value;
        $this->_hsl = NULL;
        return;
      case 'g':
      case 'green':
        $this->_rgba['green'] = $value;
        $this->_hsl = NULL;
        return;
      case 'b':
      case 'blue':
        $this->_rgba['blue'] = $value;
        $this->_hsl = NULL;
        return;
      case 'a':
      case 'alpha':
        $this->_rgba['alpha'] = $value;
        return;
      }
      throw new \LogicException('Invalid property name: '.$name);
    }

    private function getValue(string $name) {
      switch ($name) {
      case 'r':
      case 'red':
        return $this->_rgba['red'];
      case 'g':
      case 'green':
        return $this->_rgba['green'];
      case 'b':
      case 'blue':
        return $this->_rgba['blue'];
      case 'a':
      case 'alpha':
        return $this->_rgba['alpha'];
      case 'h':
      case 'hue':
        return $this->asHSL()['hue'];
      case 's':
      case 'saturation':
        return $this->asHSL()['saturation'];
      case 'l':
      case 'lightness':
        return $this->asHSL()['lightness'];
      }
      throw new \LogicException('Invalid property name: '.$name);
    }

    public function offsetExists($offset) {
      return isset($this->_rgba[$offset]);
    }

    public function offsetGet($offset) {
      return $this->getValue($offset);
    }

    public function offsetSet($offset, $value): void {
      $this->setValue($offset, $value);
    }

    public function offsetUnset($offset): void {
      throw new \LogicException('Can not unset color parts.');
    }

    public function __isset($offset) {
      return isset($this->_rgba[$offset]);
    }

    public function __get($offset) {
      return $this->getValue($offset);
    }

    public function __set($offset, $value) {
      $this->setValue($offset, $value);
    }

    public function __unset($offset) {
      throw new \LogicException('Can not unset color parts.');
    }

    public static function convertRGBToHSL(int $red, int $green, int $blue): array {
      $red /= 255;
      $green /= 255;
      $blue /= 255;
      $minimum = \min([$red, $green, $blue]);
      $maximum = \max([$red, $green, $blue]);
      $lightness = ($maximum + $minimum) / 2;
      $hue = 0;
      $saturation = 0;
      if ($maximum - $minimum > self::FLOAT_DELTA) {
        $d = $maximum + $minimum;
        $saturation = ($lightness > 0.5) ? $d / (2 - $maximum - $minimum) : $d / ($maximum + $minimum);
        if ($maximum - $red < 0.000001) {
          $hue = ($green - $blue) / $d + ($green < $blue ? 6 : 0);
        } elseif ($maximum - $green < 0.000001) {
          $hue = ($blue - $red) / $d + 2;
        } else {
          $hue = ($red - $green) / $d + 4;
        }
        $hue /= 6;
      }
      return [
        'hue'=> $hue, 'saturation' => $saturation , 'lightness' => $lightness
      ];
    }

    public static function convertHSLToRGB(float $hue, float $saturation, float $lightness) {
      if ($saturation < self::FLOAT_DELTA) {
        // achromatic
        $value = \round($lightness * 255);
        return [
          'red' => $value, 'green' => $value, 'blue' => $value
        ];
      }
      $m2 = ($lightness < 0.5) ? $lightness * (1 + $saturation) : $lightness + $saturation - ($lightness * $saturation);
      $m1 = 2 * $lightness - $m2;
      return [
        'red' => \round(self::convertHueToRGB($m1, $m2, $hue + 1 / 3)),
        'green' =>  \round(self::convertHueToRGB($m1, $m2, $hue)),
        'blue' => \round(self::convertHueToRGB($m1, $m2, $hue - (1 / 3)))
      ];
    }

    private static function convertHueToRGB(float $m1, float $m2, float $hue) {
      if ($hue < 0) {
        ++$hue;
      }
      if ($hue > 1) {
        --$hue;
      }
      if ($hue < 1 / 6) {
        return $m1 + ($m2 - $m1) * 6 * $hue;
      }
      if ($hue < 1 / 2) {
        return $m2;
      }
      if ($hue < 2 / 3) {
        return $m1 + ($m2 - $m1) * (2 / 3 - $hue) * 6;
      }
      return $m1;
    }

    /**
     * @param $colorOne
     * @param $colorTwo
     * @param null|array $backgroundColor
     * @return float|int
     */
    public static function computeDistance($colorOne, $colorTwo, $backgroundColor = NULL) {
      $colorOne = self::removeAlphaFromColor($colorOne, $backgroundColor);
      $colorTwo = self::removeAlphaFromColor($colorTwo, $backgroundColor);
      $difference = 0;
      $difference += ($colorOne['red'] - $colorTwo['red']) ** 2 * 2;
      $difference += ($colorOne['green'] - $colorTwo['green']) ** 2 * 4;
      $difference += ($colorOne['blue'] - $colorTwo['blue']) ** 2 * 3;
      return sqrt($difference) / (255 * 3);
    }

    /**
     * @param $color
     * @param null|array|Color $backgroundColor
     * @return array|Color
     */
    public static function removeAlphaFromColor($color, $backgroundColor = NULL) {
      $backgroundColor = $backgroundColor ?? ['red' => 255, 'green' => 255, 'blue' => 255];
      if ($color['alpha'] < 255) {
        $factor = (float)$color['alpha'] / 255.0;
        if ($color instanceof self) {
          return self::create(
            $backgroundColor['red'] * (1 - $factor) + $color['red'] * $factor,
            $backgroundColor['green'] * (1 - $factor) + $color['green'] * $factor,
            $backgroundColor['blue'] * (1 - $factor) + $color['blue'] * $factor
          );
        }
        return [
          'red' => $backgroundColor['red'] * (1 - $factor) + $color['red'] * $factor,
          'green' =>  $backgroundColor['green'] * (1 - $factor) + $color['green'] * $factor,
          'blue' => $backgroundColor['blue'] * (1 - $factor) + $color['blue'] * $factor,
          'alpha' => 255
        ];
      }
      return $color;
    }
  }
}
