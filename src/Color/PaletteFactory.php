<?php

namespace Carica\CanvasGraphics\Color {

  class PaletteFactory {

    public const PALETTE_GENERATED = 1;
    public const PALETTE_SAMPLED = 2;
    public const PALETTE_COLOR_THIEF = 3;

    public static function createPalette(int $type, $image, int $numberOfColors) {
      switch ($type) {
      case self::PALETTE_GENERATED:
        return new Palette\Generated($numberOfColors);
      case self::PALETTE_SAMPLED:
        return new Palette\Sampled($image, $numberOfColors);
      case self::PALETTE_COLOR_THIEF:
        return new Palette\ColorThief($image, $numberOfColors);
      default:
        throw new \InvalidArgumentException('Unknown palette type.');
      }
    }
  }
}
