<?php

namespace Carica\CanvasGraphics\Canvas\Context2D {

  use Carica\CanvasGraphics\Canvas\CanvasContext2D;
  use Carica\CanvasGraphics\Canvas\ImageData;
  use Carica\CanvasGraphics\Canvas\Path2D;

  /**
   * Class GDContext
   *
   */
  class GDCanvasContext implements CanvasContext2D {

    private $_imageResource;

    /**
     * @var ImageData
     */
    private $_imageData;

    /**
     * @var Path2D
     */
    private $_currentPath;


    private $_properties = [
      'fillcolor' => [0, 0, 0, 0],
      'strokecolor' => [255, 255, 255, 255]
    ];

    private const SCALE_HORIZONTAL = 'a';
    private const SKEW_HORIZONTAL = 'b';
    private const SKEW_VERTICAL = 'c';
    private const SCALE_VERTICAL = 'd';
    private const MOVE_HORIZONTAL = 'e';
    private const MOVE_VERTICAL = 'f';
    private $_matrix = [
      self::SCALE_HORIZONTAL => 1,
      self::SKEW_HORIZONTAL => 0,
      self::SKEW_VERTICAL => 0,
      self::SCALE_VERTICAL => 1,
      self::MOVE_HORIZONTAL => 0,
      self::MOVE_VERTICAL => 0
    ];

    public function __construct(int $width, int $height) {
      $this->_imageResource = \imagecreatetruecolor($width, $height);
      $this->clearRect(0, 0, $width, $height);
    }

    public function __destruct() {
      \imagedestroy($this->_imageResource);
    }

    public function __isset($name) {
      $name = \strtolower($name);
      return isset($this->_properties[$name]);
    }

    public function __get($name) {
      $name = \strtolower($name);
      if (\method_exists($this, 'get'.$name)) {
        return $this->{'get'.$name}();
      }
      return $this->_properties[$name];
    }

    public function __set($name, $value) {
      $name = \strtolower($name);
      if (\method_exists($this, 'set'.$name)) {
        $this->{'set'.$name}($value);
        return;
      }
      $this->_properties[$name] = $value;
    }

    public function __unset($name) {
      throw new \LogicException(
        \sprintf(
          'You can not unset properties on a %s',
          __CLASS__
        )
      );
    }

    public function toBlob(string $type = 'image/png', float $encoderOptions = NULL) {
      switch($type) {
      case 'image/png' :
        return \imagepng(
          $this->_imageResource,
          NULL,
          (NULL !== $encoderOptions && $encoderOptions >= 0 && $encoderOptions <= 1)
            ? round($encoderOptions * 10) : 0
        );
      case 'image/jpeg' :
        return \imagejpeg(
          $this->_imageResource,
          NULL,
          (NULL !== $encoderOptions && $encoderOptions >= 0 && $encoderOptions <= 1)
            ? round($encoderOptions * 100) : 80
        );
      }
      throw new \InvalidArgumentException(sprintf('%s is not supported as a target format.', $type));
    }

    /* ImageData */

    public function createImageData(int $width, int $height): ImageData {
      return new ImageData(
        \array_fill(0, $width * $height * 4, 0),
        $width,
        $height
      );
    }

    public function getImageData(): ImageData {
      if (NULL === $this->_imageData) {
        $cache = [];
        $data = [];
        $width = \imagesx($this->_imageResource);
        $height = \imagesy($this->_imageResource);
        for ($y = 0; $y <$height; $y++) {
          for ($x = 0; $x <$width; $x++) {
            $rgba = \imagecolorat($this->_imageResource, $x, $y);
            if (isset($cache[$rgba])) {
              $pixel = $cache[$rgba];
            } else {
              $cache[$rgba] = $pixel = [
                ($rgba >> 16) & 0xFF,
                ($rgba >> 8) & 0xFF,
                $rgba & 0xFF,
                (127 - (($rgba & 0x7F000000) >> 24)) / 127 * 255
              ];
            }
            $data[] = $pixel[0];
            $data[] = $pixel[1];
            $data[] = $pixel[2];
            $data[] = $pixel[3];
          }
        }
        $this->_imageData = new ImageData($data, $width, $height);
      }
      return $this->_imageData;
    }

    public function putImageData(
      ImageData $imageData,
      int $dx, int $dy,
      int $dirtyX = 0, int $dirtyY = 0, int $dirtyWidth = NULL, int $dirtyHeight = NULL
    ): void {
      $data = $imageData->data;
      $height = $imageData->height;
      $width = $imageData->width;
      $dirtyX = $dirtyX ?? 0;
      $dirtyY = $dirtyY ?? 0;
      $dirtyWidth = $dirtyWidth ?? $width;
      $dirtyHeight = $dirtyHeight ?? $height;
      $limitBottom = \min($dirtyHeight, $height);
      $limitRight = \min($dirtyWidth, $width);
      for ($y = $dirtyY; $y < $limitBottom; $y++) {
        for ($x = $dirtyX; $x < $limitRight; $x++) {
          $sourceOffset = $y * $width + $x;
          $rgba = [
            $data[$sourceOffset],
            $data[$sourceOffset + 1],
            $data[$sourceOffset + 2],
            $data[$sourceOffset + 3],
          ];
          if (NULL !== $this->_imageData) {
            $targetOffset = ($x + $dx) * ($y + $dy) * 4;
            $this->_imageData->data[$targetOffset] = $rgba[0];
            $this->_imageData->data[$targetOffset+1] = $rgba[1];
            $this->_imageData->data[$targetOffset+2] = $rgba[2];
            $this->_imageData->data[$targetOffset+3] = $rgba[3];
          }
          imagesetpixel(
            $this->_imageResource, $x + $dx, $y + $dy, $this->getColorIndex(...$rgba)
          );
        }
      }
    }

    private function getColorIndex($red, $green, $blue, $alpha) {
      $gdAlpha = 127 - ($alpha / 255 * 127);
      $color = imagecolorallocatealpha(
        $this->_imageResource, $red, $green, $blue, $gdAlpha
      );
      if (FALSE === $color) {
        return imagecolorresolvealpha(
          $this->_imageResource, $red, $green, $blue, $gdAlpha
        );
      }
      return $color;
    }

    /* Coordinates translate and transfom */

    public function transform(float $hScale, float $hSkew, float $vSkew, float $vScale, int $hMove, int $vMove) {
      $m = $this->_matrix;
      $this->_matrix = [
        self::SCALE_HORIZONTAL =>
          $m[self::SCALE_HORIZONTAL] * $hScale + $m[self::SKEW_VERTICAL] * $hSkew,
        self::SKEW_HORIZONTAL =>
          $m[self::SKEW_HORIZONTAL] * $hScale + $m[self::SCALE_VERTICAL] * $hSkew,
        self::SKEW_VERTICAL =>
          $m[self::SKEW_VERTICAL] * $vScale + $m[self::SCALE_HORIZONTAL] * $vSkew,
        self::SCALE_VERTICAL =>
          $m[self::SCALE_VERTICAL] * $vScale + $m[self::SKEW_HORIZONTAL] * $vSkew,
        self::MOVE_HORIZONTAL =>
          $m[self::SCALE_HORIZONTAL] * $hMove + $m[self::SKEW_VERTICAL] * $vMove + $m[self::MOVE_HORIZONTAL],
        self::MOVE_VERTICAL =>
          $m[self::SCALE_VERTICAL] * $vMove + $m[self::SKEW_HORIZONTAL] * $hMove + $m[self::MOVE_VERTICAL],
      ];
      return $this;
    }

    public function setTransform(float $hScale, float $hSkew, float $vSkew, float $vScale, int $hMove, int $vMove) {
      $this->_matrix = [
        self::SCALE_HORIZONTAL => $hScale,
        self::SKEW_HORIZONTAL => $hSkew,
        self::SKEW_VERTICAL => $vSkew,
        self::SCALE_VERTICAL => $vScale,
        self::MOVE_HORIZONTAL => $hMove,
        self::MOVE_VERTICAL => $vMove
      ];
      return $this;
    }

    public function resetTransform() {
      $this->setTransform(1, 0,0, 1, 0,0);
      return $this;
    }

    public function translate(int $tx, int $ty) {
      $this->transform(1, 0, 0, 1, $tx, $ty);
      return $this;
    }

    private function applyToPoint($x, $y) {
      return [
        \round(
          $x * $this->_matrix[self::SCALE_HORIZONTAL] +
          $y * $this->_matrix[self::SKEW_VERTICAL] +
          $this->_matrix[self::MOVE_HORIZONTAL]
        ),
        \round(
          $y * $this->_matrix[self::SCALE_VERTICAL] +
          $x * $this->_matrix[self::SKEW_HORIZONTAL] +
          $this->_matrix[self::MOVE_VERTICAL])
      ];
    }

    private function applyToPoints(...$points) {
      return \array_map(
        function($point) {
          return $this->applyToPoint(...$point);
        },
        $points
      );
    }

    /* Draw image */

    public function drawImage(
      $image,
      int $x = NULL, int $y = NULL, int $width = NULL, int $height = NULL,
      int $dx = NULL, int $dy = NULL, int $dWidth = NULL, int $dHeight = NULL
    ) {
      if (\is_resource($image) && \get_resource_type($image) === 'gd') {
        $destinationX = $dx ?? $x ?? 0;
        $destinationY = $dy ?? $y ?? 0;
        $sourceX = (NULL !== $dx) ? ($x ?? 0) : 0;
        $sourceY = (NULL !== $dy) ? ($y ?? 0) : 0;
        $destinationWidth = $dWidth ?? $width ?? imagesx($image);
        $destinationHeight = $dHeight ?? $height ?? imagesy($image);
        $sourceWidth = (NULL !== $dWidth) ? ($width ?? imagesx($image)) : imagesx($image);
        $sourceHeight = (NULL !== $dHeight) ? ($height ?? imagesy($image)) : imagesy($image);

        [$destinationX, $destinationY] = $this->applyToPoint($destinationX, $destinationY);

        \imagecopyresampled(
          $this->_imageResource,
          $image,
          $destinationX,
          $destinationY,
          $sourceX,
          $sourceY,
          $destinationWidth,
          $destinationHeight,
          $sourceWidth,
          $sourceHeight
        );
      }
    }


    /* Rectangle */

    public function clearRect(int $x, int $y, int $width, int $height) {
      [$topLeft, $bottomRight] = $this->applyToPoints([$x, $y], [$x + $width, $y + $height]);
      \imagealphablending($this->_imageResource, FALSE);
      \imagefilledrectangle(
        $this->_imageResource,
        $topLeft[0],
        $topLeft[1],
        $bottomRight[0],
        $bottomRight[1],
        $this->getColorIndex(0,0,0,0)
      );
      \imagealphablending($this->_imageResource, TRUE);
      $this->_imageData = NULL;
    }

    public function fillRect(int $x, int $y, int $width, int $height) {
      [$topLeft, $bottomRight] = $this->applyToPoints([$x, $y], [$x + $width, $y + $height]);
      \imagefilledrectangle(
        $this->_imageResource,
        $topLeft[0],
        $topLeft[1],
        $bottomRight[0],
        $bottomRight[1],
        $this->getColorIndex(...$this->_properties['fillcolor'])
      );
      $this->_imageData = NULL;
    }

    public function strokeRect(int $x, int $y, int $width, int $height) {
      [$topLeft, $bottomRight] = $this->applyToPoints([$x, $y], [$x + $width, $y + $height]);
      \imagerectangle(
        $this->_imageResource,
        $topLeft[0],
        $topLeft[1],
        $bottomRight[0],
        $bottomRight[1],
        $this->getColorIndex(...$this->_properties['strokecolor'])
      );
      $this->_imageData = NULL;
    }

    /* Paths */

    public function stroke(): void {
      if ($points = $this->getPolygonPoints($this->getCurrentPath())) {
        $list = array_reduce(
          $points,
          function($carry, $point) {
            [$x, $y] = $this->applyToPoint(...$point);
            $carry[] = $x;
            $carry[] = $y;
            return $carry;
          },
          []
        );
        \imagepolygon(
          $this->_imageResource, $list, \count($points), $this->getColorIndex(...$this->_properties['strokecolor'])
        );
      } else {
        $colorIndex = $this->getColorIndex(...$this->_properties['strokecolor']);
        $position = [0, 0];
        foreach ($this->getCurrentPath() as $segment) {
          if ($segment instanceof Path2D\Move) {
            $position = $this->applyToPoint(...$segment->getTargetPoint());
          } elseif ($segment instanceof Path2D\Line) {
            $targetPosition = $this->applyToPoint(...$segment->getTargetPoint());
            \imageline(
              $this->_imageResource, $position[0], $position[1], $targetPosition[0], $targetPosition[1], $colorIndex
            );
            $position = $targetPosition;
          }
        }
      }
      $this->_imageData = NULL;
    }

    public function fill(): void {
      if ($points = $this->getPolygonPoints($this->getCurrentPath())) {
        $list = array_reduce(
          $points,
          function($carry, $point) {
            [$x, $y] = $this->applyToPoint(...$point);
            $carry[] = $x;
            $carry[] = $y;
            return $carry;
          },
          []
        );
        \imagefilledpolygon(
          $this->_imageResource, $list, \count($points) - 1, $this->getColorIndex(...$this->_properties['strokecolor'])
        );
      } else {
        throw new \LogicException('Only polygon paths can be filled.');
      }
      $this->_imageData = NULL;
    }

    private function getPolygonPoints(Path2D $path) {
      $points = [];
      foreach ($path as $index => $segment) {
        if (
          ($index === 0 && $segment instanceof Path2D\Move) ||
          ($index > 0 && $segment instanceof Path2D\Line)) {
          $points[] = $segment[0];
        } else {
          return FALSE;
        }
      }
      $count = \count($points);
      if ($count > 3 && $points[0] === $points[$count - 1]) {
        return $points;
      }
      return FALSE;
    }

    public function beginPath(): void {
      $this->_currentPath = new Path2D();
    }

    public function closePath(): void {
      if (NULL !== $this->_currentPath) {
        $this->_currentPath->closePath();
      }
    }

    private function getCurrentPath() {
      if (NULL === $this->_currentPath) {
        $this->_currentPath = new Path2D();
      }
      return $this->_currentPath;
    }

    public function moveTo(int $x, int $y) {
      $this->getCurrentPath()->moveTo($x, $y);
    }

    public function lineTo(int $x, int $y) {
      $this->getCurrentPath()->lineTo($x, $y);
    }
  }
}
