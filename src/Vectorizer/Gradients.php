<?php

namespace Carica\CanvasGraphics\Vectorizer {

  use Carica\CanvasGraphics\Canvas\ImageData;
  use Carica\CanvasGraphics\Color;
  use Carica\CanvasGraphics\Color\Palette\ColorThief;
  use Carica\CanvasGraphics\SVG\Appendable;
  use Carica\CanvasGraphics\SVG\Document;

  /**
   * Create an SVG with gradients from an image
   */
  class Gradients implements Appendable {

    private const DIRECTION_MAP = [
      0 => 'to top left',
      1 => 'to top right',
      2 => 'to bottom left',
      3 => 'to bottom right'
    ];

    private const STOPS =[
      [0, 0],
      [1, 1]
    ];

    /**
     * @var ImageData
     */
    private $_imageData;
    private $_gradients;

    public function __construct(ImageData $imageData) {
      $this->_imageData = $imageData;
    }

    public function appendTo(Document $svg): void {
      $gradients = $this->getGradients();
      $shapes = $svg->getShapesNode();
      $document = $shapes->ownerDocument;

      $definitions = $svg->getDefinitionsNode();
      /**
       * @var int $quadIndex
       * @var Color $color
       */
      foreach (\array_reverse($gradients, TRUE) as $quadIndex => $color) {
        /** @var \DOMElement $gradient */
        $gradient = $definitions->appendChild(
          $document->createElementNS(self::XMLNS_SVG, 'linearGradient')
        );
        $gradient->setAttribute('id', 'quad'.$quadIndex);
        switch ($quadIndex) {
        case 0:
          $gradient->setAttribute('x1', 1);
          $gradient->setAttribute('y1', 1);
          $gradient->setAttribute('x2', 0);
          $gradient->setAttribute('y2', 0);
          break;
        case 1:
          $gradient->setAttribute('x1', 0);
          $gradient->setAttribute('y1', 1);
          $gradient->setAttribute('x2', 1);
          $gradient->setAttribute('y2', 0);
          break;
        case 2:
          $gradient->setAttribute('x1', 1);
          $gradient->setAttribute('y1', 0);
          $gradient->setAttribute('x2', 0);
          $gradient->setAttribute('y2', 1);
          break;
        case 3:
          $gradient->setAttribute('x1', 0);
          $gradient->setAttribute('y1', 0);
          $gradient->setAttribute('x2', 1);
          $gradient->setAttribute('y2', 1);
          break;
        }

        $colorString = $color->toHexString();
        foreach (self::STOPS as $stopValues) {
          [$offset, $opacity] = $stopValues;
          /** @var \DOMElement $stop */
          $stop = $gradient->appendChild(
            $document->createElementNS(self::XMLNS_SVG, 'stop')
          );
          $stop->setAttribute('offset', $offset);
          $stop->setAttribute('stop-color', $colorString);
          $stop->setAttribute('stop-opacity', $opacity);
        }

        /** @var \DOMElement $rectNode */
        $rectNode = $shapes->appendChild(
          $document->createElementNS(self::XMLNS_SVG, 'rect')
        );
        $rectNode->setAttribute('x', 0);
        $rectNode->setAttribute('y', 0);
        $rectNode->setAttribute('width', $svg->getWidth());
        $rectNode->setAttribute('height', $svg->getHeight());
        $rectNode->setAttribute('fill', 'url(#quad'.$quadIndex.')');
      }
    }

    public function getStyleProperty() {
      $style = '';
      $gradients = $this->getGradients();
      foreach ($gradients as $quadIndex => $color) {
        $style .= \sprintf(', linear-gradient(%1$s', self::DIRECTION_MAP[$quadIndex]);
        foreach (self::STOPS as $stopValues) {
          [$offset, $opacity] = $stopValues;
          $style .= sprintf(
            ', rgba(%1$d, %2$d, %3$d, %4$d) %5$d%%',
            $color->red,
            $color->green,
            $color->blue,
            $opacity,
            $offset * 100
          );
        }
        $style .= ')';
      }
      return \substr($style, 2);
    }

    private function getGradients() {
      if (NULL !== $this->_gradients) {
        return $this->_gradients;
      }
      $data = $this->_imageData->data;
      $width = $this->_imageData->width;
      $height = $this->_imageData->height;
      $palette = new ColorThief($this->_imageData, 4);
      $cache = [];
      $quads = [0 => [], 1 => [], 2 => [], 3 => []];
      // count nearest colors for each quad
      for ($i = 0, $c = \count($this->_imageData->data); $i < $c; $i += 4) {
        $pixelIndex = (int)($i / 4);
        $y = \floor($pixelIndex / $width);
        $x = $pixelIndex - ($y * $width);
        $quadIndex = \floor($y / ($height / 2)) * 2 + \floor($x / ($width / 2));
        $color = Color::removeAlphaFromColor(
          Color::create($data[$i], $data[$i + 1], $data[$i + 2], $data[$i + 3])
        );
        $colorIndex = $color->toInt();
        if (isset($cache[$colorIndex])) {
          $closestColorIndex = $cache[$colorIndex];
        } else {
          $closestColorIndex = $palette->getNearestColorIndex($color);
        }
        $quads[$quadIndex][$closestColorIndex] = ($quads[$quadIndex][$closestColorIndex] ?? 0) + 1;
      }
      // sort color usage in quads (most used first)
      $rankings = $this->getRankings($quads);
      $quadColors = [];
      $usedColors = [];
      // try to assign a unique color to each quad, that is used in that quad
      foreach ($rankings as $rankedColor) {
        if (isset($usedColors[$rankedColor[1]]) || isset($quadColors[$rankedColor[0]])) {
          continue;
        }
        $quadColors[$rankedColor[0]] = $rankedColor[1];
        $usedColors[$rankedColor[1]] = TRUE;
        if (\count($quadColors) > 3) {
          break;
        }
      }
      // if that fails for a quad, assign the most used color of that quad.
      foreach ($rankings as $rankedColor) {
        if (!isset($quadColors[$rankedColor[0]])) {
          $quadColors[$rankedColor[0]] = $rankedColor[1];
        }
        if (\count($quadColors) > 3) {
          break;
        }
      }
      return \array_map(
        function($colorIndex) use ($palette) {
          return $palette[$colorIndex];
        },
        $quadColors
      );
    }

    private function getRankings($quads) {
      $rankings = [];
      foreach ($quads as $quad => $colors) {
        foreach ($colors as $color => $count) {
          $rankings[] = [$quad, $color, $count];
        }
      }
      \usort(
        $rankings,
        function ($a, $b) {
          if ($a[2] !== $b[2]) {
            return ($a[2] > $b[2]) ? -1 : 1;
          }
          return 0;
        }
      );
      return $rankings;
    }
  }
}
