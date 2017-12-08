<?php

namespace Carica\CanvasGraphics\Canvas\GD {

  class Image {

    private $_contexts = [];
    private $_imageResource;

    public function __construct($imageResource) {
      $this->_imageResource = $imageResource;
    }

    public static function create($width, $height): Image {
      $imageResource = \imagecreatetruecolor($width, $height);
      \imagealphablending($imageResource, FALSE);
      \imagefilledrectangle(
        $imageResource,
        0,
        0,
        $width,
        $height,
        \imagecolorallocatealpha($imageResource, 0,0,0,127)
      );
      \imagealphablending($imageResource, TRUE);
      return new self($imageResource);
    }

    public static function load($fileName): Image {
      [, , $type] = \getimagesize($fileName);
      $methods = [
        IMAGETYPE_GIF => '\\imagecreatefromgif',
        IMAGETYPE_JPEG => '\\imagecreatefromjpeg',
        IMAGETYPE_PNG => '\\imagecreatefrompng',
        IMAGETYPE_WEBP => '\\imagecreatefromwebp',
        IMAGETYPE_WBMP => '\\imagecreatefromwbmp',
        IMAGETYPE_XBM => '\\imagecreatefromxbm'
      ];
      if (isset($methods[$type])) {
        $imageResource = $methods[$type]($fileName);
        return new self($imageResource);
      }
      throw new \LogicException(sprintf('Can not load "%s", type not supported.', $fileName));
    }

    public function filter(Filter ...$filters): void {
      foreach ($filters as $filter) {
        $filter->applyTo($this->_imageResource);
      }
      unset($this->_contexts['2d']);
    }

    public function toBlob(string $type = 'image/png', float $encoderOptions = NULL) {
      return $this->getContext()->toBlob($type, $encoderOptions);
    }

    public function getContext($type = '2d') {
      if ($type !== '2d') {
        return new \LogicException('Only 2d canvas context is supported at the moment.');
      }
      if (isset($this->_contexts[$type])) {
        return $this->_contexts[$type];
      }
      return $this->_contexts[$type] = new CanvasContext($this->_imageResource);
    }
  }
}
