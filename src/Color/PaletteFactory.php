<?php

namespace Carica\CanvasGraphics\Color {

  use Carica\CanvasGraphics\Canvas\ImageData;
  use Carica\CanvasGraphics\Color;

  class PaletteFactory {

    public const PALETTE_GENERATED = 1;
    public const PALETTE_COLOR_THIEF = 3;

    public static function createPalette(
      int $type, ImageData $imageData, int $numberOfColors, Color $backgroundColor = NULL
    ) {
      switch ($type) {
      case self::PALETTE_GENERATED:
        return new Palette\Generated($numberOfColors);
      case self::PALETTE_COLOR_THIEF:
        return new Palette\ColorThief($imageData, $numberOfColors, $backgroundColor);
      default:
        throw new \InvalidArgumentException('Unknown palette type.');
      }
    }
  }
}
