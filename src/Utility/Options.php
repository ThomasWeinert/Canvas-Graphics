<?php
namespace Carica\CanvasGraphics\Utility {

  class Options implements \ArrayAccess {

    private $_values;
    private $_defaults;

    public function __construct(array $defaults, array $values = []) {
      $this->_values = $this->_defaults = $defaults;
      foreach ($values as $name => $value) {
        $this->offsetSet($name, $value);
      }
    }

    public function offsetExists($offset): bool {
      return \array_key_exists($offset, $this->_values);
    }

    public function offsetSet($offset, $value): void {
      if (\array_key_exists($offset, $this->_defaults)) {
        if (NULL === $this->_defaults[$offset]) {
          $this->_values[$offset] = $value;
        } elseif (\settype($value, \gettype($this->_defaults[$offset]))) {
          $this->_values[$offset] = $value;
        }
      }
    }

    public function offsetGet($offset) {
      return $this->_values[$offset];
    }

    public function offsetUnset($offset): void {
      $this->_values[$offset] = $this->_defaults[$offset];
    }

    public function asArray() {
      return $this->_values;
    }
  }
}
