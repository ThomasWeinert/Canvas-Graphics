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

    /**
     * Color constructor.
     *
     * @param int $red
     * @param int $green
     * @param int $blue
     * @param int $alpha
     * @throws \LogicException
     */
    public function __construct(int $red, int $green, int $blue, int $alpha = 255) {
      $this->setValue('red', $red);
      $this->setValue('green', $green);
      $this->setValue('blue', $blue);
      $this->setValue('alpha', $alpha);
    }

    /**
     * @return string
     */
    public function __toString(): string {
      $hsl = $this->toHSL();
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

    /**
     * @param bool $withAlpha
     * @return string
     */
    public function toHexString(bool $withAlpha = FALSE): string {
      if ($withAlpha) {
        return sprintf(
          '#%02x%02x%02x%02x',
          $this->_rgba['red'],
          $this->_rgba['green'],
          $this->_rgba['blue'],
          $this->_rgba['alpha']
        );
      }
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

    /**
     * @return int
     */
    public function toInt():int {
      return
        ($this->_rgba['red'] << 24) +
        ($this->_rgba['green'] << 16) +
        ($this->_rgba['blue'] << 8) +
        $this->_rgba['alpha'];
    }

    /**
     * @return array
     */
    public function toHSL(): array {
      if (NULL === $this->_hsl) {
        $this->_hsl = self::convertRGBToHSL(
          $this->_rgba['red'],
          $this->_rgba['green'],
          $this->_rgba['blue']
        );
      }
      return $this->_hsl;
    }

    public function removeAlpha(Color $backgroundColor = NULL) {
      $this->_rgba = iterator_to_array(
        self::removeAlphaFromColor($this, $backgroundColor),
        TRUE
      );
      return $this;
    }

    /**
     * @param int $red
     * @param int $green
     * @param int $blue
     * @param int $alpha
     * @return Color
     * @throws \LogicException
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
     * @throws \LogicException
     */
    public static function createGray(int $value, int $alpha = 255): self {
      return new self($value, $value, $value, $alpha);
    }

    /**
     * @param array $values
     * @return Color
     * @throws \LogicException
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
     * @throws \Exception
     */
    public static function createRandom(int $alpha = NULL): self {
      return new self(
        \random_int(0, 255),
        \random_int(0, 255),
        \random_int(0, 255),
        $alpha ?? \random_int(0, 255)
      );
    }

    /**
     * Create color from (hex) string
     *
     * @param string $string
     * @return \Carica\CanvasGraphics\Color
     */
    public static function createFromString(string $string): self {
      $pattern = '(#(?:[a-fA-F\\d]{3,4}|(?:[a-fA-F\\d]{1,2}){3,4}))';
      if (preg_match($pattern, $string, $matches)) {
        $c = strlen($string);
        $step = $c < 5 ? 1 : 2;
        $parts = [];
        for ($i = 1; $i < $c; $i += $step) {
          $part = substr($string, $i, $step);
          if (strlen($part) === '') {
            $part = 'ff';
          } elseif (strlen($part) < 2) {
            $part .= $part;
          }
          $parts[] = hexdec($part);
        }
        return new self(...$parts);
      }
      throw new \InvalidArgumentException(
        sprintf('Invalid color string: "%s"', $string)
      );
    }

    /**
     * Create a rbg color from HSL
     *
     * @param float $hue
     * @param float $saturation
     * @param float $lightness
     * @return Color
     * @throws \LogicException
     */
    public static function createFromHSL(float $hue, float $saturation, float $lightness): self {
      return self::createFromArray(self::convertHSLToRGB($hue, $saturation , $lightness));
    }

    /**
     * @param string $name
     * @return bool
     */
    private function hasValue(string $name): bool {
      switch ($name) {
      case '0':
      case 'r':
      case '1':
      case 'red':
      case 'g':
      case 'green':
      case '2':
      case 'b':
      case 'blue':
      case '3':
      case 'a':
      case 'alpha':
        return TRUE;
      }
      return FALSE;
    }

    /**
     * @param string $name
     * @param int|float $value
     * @throws \LogicException
     */
    private function setValue(string $name, $value) {
      switch ($name) {
      case '0':
      case 'r':
      case 'red':
        $this->validateValue($value, 0, 255);
        $this->_rgba['red'] = $value;
        $this->_hsl = NULL;
        return;
      case '1':
      case 'g':
      case 'green':
        $this->validateValue($value, 0, 255);
        $this->_rgba['green'] = $value;
        $this->_hsl = NULL;
        return;
      case '2':
      case 'b':
      case 'blue':
        $this->validateValue($value, 0, 255);
        $this->_rgba['blue'] = $value;
        $this->_hsl = NULL;
        return;
      case '3':
      case 'a':
      case 'alpha':
        $this->validateValue($value, 0, 255);
        $this->_rgba['alpha'] = $value;
        return;
      }
      throw new \LogicException('Invalid property name: '.$name);
    }

    /**
     * @param int|float $value
     * @param int|float $minimum
     * @param int|float $maximum
     * @throws \OutOfRangeException
     */
    private function validateValue($value, $minimum, $maximum): void {
      if ($value < $minimum || $value > $maximum) {
        throw new \OutOfRangeException("Value needs to be between $minimum and $maximum.");
      }
    }

    /**
     * @param string $name
     * @return int|float
     * @throws \LogicException
     */
    private function getValue(string $name) {
      switch ($name) {
      case '0':
      case 'r':
      case 'red':
        return $this->_rgba['red'];
      case '1':
      case 'g':
      case 'green':
        return $this->_rgba['green'];
      case '2':
      case 'b':
      case 'blue':
        return $this->_rgba['blue'];
      case '3':
      case 'a':
      case 'alpha':
        return $this->_rgba['alpha'];
      case 'h':
      case 'hue':
        return $this->toHSL()['hue'];
      case 's':
      case 'saturation':
        return $this->toHSL()['saturation'];
      case 'l':
      case 'lightness':
        return $this->toHSL()['lightness'];
      }
      throw new \LogicException('Invalid property name: '.$name);
    }

    /**
     * @param string|int $offset
     * @return bool
     */
    public function offsetExists($offset): bool {
      return $this->hasValue((string)$offset);
    }

    /**
     * @param string|int $offset
     * @return int|float
     */
    public function offsetGet($offset) {
      return $this->getValue((string)$offset);
    }

    /**
     * @param string|int $offset
     * @param int|float $value
     * @throws \LogicException
     */
    public function offsetSet($offset, $value): void {
      $this->setValue((string)$offset, $value);
    }

    /**
     * @param string|int $offset
     * @throws \LogicException
     */
    public function offsetUnset($offset): void {
      throw new \LogicException('Can not unset color parts.');
    }

    /**
     * @param string $offset
     * @return bool
     */
    public function __isset($offset): bool {
      return $this->hasValue($offset);
    }

    /**
     * @param string $offset
     * @return int|float
     * @throws \LogicException
     */
    public function __get($offset) {
      return $this->getValue($offset);
    }

    /**
     * @param string $offset
     * @param int|float $value
     * @throws \LogicException
     */
    public function __set($offset, $value) {
      $this->setValue($offset, $value);
    }

    /**
     * @param $offset
     * @throws \LogicException
     */
    public function __unset($offset) {
      throw new \LogicException('Can not unset color parts.');
    }

    /**
     * @param int $red
     * @param int $green
     * @param int $blue
     * @return array
     */
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

    /**
     * @param float $hue
     * @param float $saturation
     * @param float $lightness
     * @return array
     */
    public static function convertHSLToRGB(float $hue, float $saturation, float $lightness): array {
      if ($saturation < self::FLOAT_DELTA) {
        // achromatic
        $value = \round($lightness * 255);
        return [
          'red' => $value, 'green' => $value, 'blue' => $value
        ];
      }
      $m2 = ($lightness < 0.5)
        ? $lightness * (1 + $saturation)
        : $lightness + $saturation - ($lightness * $saturation);
      $m1 = 2 * $lightness - $m2;
      return [
        'red' => \round(self::convertHueToRGB($m1, $m2, $hue + 1 / 3)),
        'green' =>  \round(self::convertHueToRGB($m1, $m2, $hue)),
        'blue' => \round(self::convertHueToRGB($m1, $m2, $hue - (1 / 3)))
      ];
    }

    /**
     * @param float $m1
     * @param float $m2
     * @param float $hue
     * @return float
     */
    private static function convertHueToRGB(float $m1, float $m2, float $hue): float {
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
     * @param array|Color $colorOne
     * @param array|Color $colorTwo
     * @param NULL|array|Color $backgroundColor
     * @return float
     */
    public static function computeDistance($colorOne, $colorTwo, $backgroundColor = NULL): float {
      $colorOne = self::removeAlphaFromColor($colorOne, $backgroundColor);
      $colorTwo = self::removeAlphaFromColor($colorTwo, $backgroundColor);
      $difference = 0;
      $difference += ($colorOne['red'] - $colorTwo['red']) ** 2 * 2;
      $difference += ($colorOne['green'] - $colorTwo['green']) ** 2 * 4;
      $difference += ($colorOne['blue'] - $colorTwo['blue']) ** 2 * 3;
      return sqrt($difference) / (255 * 3);
    }

    /**
     * @param array|Color $color
     * @param NULL|array|Color $backgroundColor
     * @return array|Color
     */
    public static function removeAlphaFromColor($color, $backgroundColor = NULL) {
      $backgroundColor = $backgroundColor ?? ['red' => 255, 'green' => 255, 'blue' => 255];
      if ($color['alpha'] < 255) {
        $backgroundRed = $backgroundColor['red'] ?? $backgroundColor[0];
        $backgroundGreen = $backgroundColor['green'] ?? $backgroundColor[1];
        $backgroundBlue = $backgroundColor['blue'] ?? $backgroundColor[2];
        $factor = (float)$color['alpha'] / 255.0;
        $red = $backgroundRed * (1 - $factor) + $color['red'] * $factor;
        $green = $backgroundGreen * (1 - $factor) + $color['green'] * $factor;
        $blue = $backgroundBlue * (1 - $factor) + $color['blue'] * $factor;
        if ($color instanceof self) {
          return self::create($red, $green, $blue);
        }
        return ['red' => $red, 'green' =>  $green, 'blue' => $blue, 'alpha' => 255];
      }
      return $color;
    }
  }
}
