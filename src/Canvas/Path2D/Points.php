<?php

namespace Carica\CanvasGraphics\Canvas\Path2D {

  abstract class Points implements Segment, \ArrayAccess {

    /**
     * @var string
     */
    private $_type;

    /**
     * @var array
     */
    private $_points;

    public function __construct(string $type, ...$points) {
      $this->_type = $type;
      $this->_points = $points;
    }

    public function __toString() {
      $result = $this->_type;
      foreach ($this->_points as $point) {
        $result .= ' '.$point[0].' '.$point[1];
      }
      return $result;
    }

    public function offsetExists($offset) {
      return isset($this->_points[$offset]);
    }

    public function offsetGet($offset) {
      return $this->_points[$offset];
    }

    public function offsetSet($offset, $value) {
      throw new \BadMethodCallException(sprintf('%s are immutable', __CLASS__));
    }

    public function offsetUnset($offset) {
      throw new \BadMethodCallException(sprintf('%s are immutable', __CLASS__));
    }
  }
}
