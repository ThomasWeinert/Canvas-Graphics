<?php

namespace Carica\CanvasGraphics\Color {

  use Carica\CanvasGraphics\Color;

  abstract class Palette implements \Countable, \IteratorAggregate {

    private $_colors;

    abstract public function generate(): array;

    public function asArray(): array {
      if (NULL === $this->_colors) {
        $this->_colors = $this->generate();
      }
      return $this->_colors;
    }

    public function count() {
      return \count($this->asArray());
    }

    public function getIterator() {
      return new \ArrayIterator($this->asArray());
    }

    public function asHexStrings() {
      return \array_map(
        function(Color $color) {
          return $color->asHexString();
        },
        $this->_colors
      );
    }
  }
}
