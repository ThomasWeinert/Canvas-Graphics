<?php

namespace Carica\CanvasGraphics\Canvas {


  /**
   * 2D canvas context interface
   *
   * @property array|string $strokeColor
   * @property array|string $fillColor
   */
  interface CanvasContext2D {

    /* pixel manipulation */

    public function createImageData(int $width, int $height): ImageData;

    public function getImageData(): ImageData;

    public function putImageData(
      ImageData $imageData,
      int $dx, int $dy,
      int $dirtyX = 0, int $dirtyY = 0, int $dirtyWidth = NULL, int $dirtyHeight = NULL
    ): void;

    /* translate & transform */

    public function translate(int $x, int $y);

    public function transform(float $hScale, float $hSkew, float $vSkew, float $vScale, int $hMove, int $vMove);

    public function setTransform(float $hScale, float $hSkew, float $vSkew, float $vScale, int $hMove, int $vMove);

    public function resetTransform();

    public function drawImage(
      $image,
      int $x = NULL, int $y = NULL, int $width = NULL, int $height = NULL,
      int $dx = NULL, int $dy = NULL, int $dWidth = NULL, int $dHeight = NULL
    );

    /* path methods */

    public function stroke();

    public function fill();

    public function beginPath(): void;

    public function closePath(): void;

    public function moveTo(int $x, int $y);

    public function lineTo(int $x, int $y);

    public function ellipse(int $centerX, int $centerY, int $radiusX, int $radiusY);

    public function rect(int $x, int $y, int $width, int $height);

    /* Rectangle methods */

    public function clearRect(int $x, int $y, int $width, int $height);

    public function fillRect(int $x, int $y, int $width, int $height);

    public function strokeRect(int $x, int $y, int $width, int $height);

  }
}
