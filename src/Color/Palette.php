<?php

namespace Carica\CanvasGraphics\Color {

  use Carica\CanvasGraphics\Color;

  abstract class Palette implements \ArrayAccess, \Countable, \IteratorAggregate {

    private $_colors;

    abstract public function generate(): array;

    public function toArray(): array {
      if (NULL === $this->_colors) {
        $this->_colors = $this->generate();
      }
      return $this->_colors;
    }

    public function count() {
      return \count($this->toArray());
    }

    public function getIterator() {
      return new \ArrayIterator($this->toArray());
    }

    public function offsetExists($offset) {
      return isset($this->_colors[$offset]);
    }

    public function offsetGet($offset) {
      return $this->_colors[$offset];
    }

    public function offsetSet($offset, $value) {
      throw new \BadMethodCallException('Can not Change colors in palette.');
    }

    public function offsetUnset($offset) {
      throw new \BadMethodCallException('Can not remove colors from palette.');
    }

    public function toHexStrings() {
      return \array_map(
        function(Color $color) {
          return $color->toHexString();
        },
        $this->_colors
      );
    }

    public function getNearestColor($color): ?Color {
      $index = $this->getNearestColorIndex($color);
      if ($index >= 0) {
        return $this->_colors[$index];
      }
      return NULL;
    }

    /**
     * find closest color from palette by measuring (rectilinear) color distance
     * between this pixel and all palette colors
     *
     * @param array|Color $color
     * @return int
     */
    public function getNearestColorIndex($color): int {
      $closestColorIndex = -1;
      $closestDistance = 2;
      foreach ($this as $paletteIndex => $paletteColor) {
        $colorDistance = Color::computeDistance($color, $paletteColor);
        if ($colorDistance < $closestDistance) {
          $closestDistance = $colorDistance;
          $closestColorIndex = $paletteIndex;
        }
      }
      return $closestColorIndex;
    }
  }
}
