<?php

namespace Carica\CanvasGraphics\Vectorizer {

  use Carica\CanvasGraphics\Canvas\ImageData;
  use Carica\CanvasGraphics\Color;
  use Carica\CanvasGraphics\SVG;
  use Carica\CanvasGraphics\Vectorizer\Paths\ColorQuantization;
  use Carica\CanvasGraphics\Utility\Options;

  /**
   * Vectorize an image by tracing paths
   *
   * This is a port/adaption of https://github.com/jankovicsandras/imagetracerjs
   */
  class Paths implements SVG\Appendable {

    private const PATH_SCAN_LOOKUP = [
      [[-1,-1,-1,-1], [-1,-1,-1,-1], [-1,-1,-1,-1], [-1,-1,-1,-1]],
      [[ 0, 1, 0,-1], [-1,-1,-1,-1], [-1,-1,-1,-1], [ 0, 2,-1, 0]],
      [[-1,-1,-1,-1], [-1,-1,-1,-1], [ 0, 1, 0,-1], [ 0, 0, 1, 0]],
      [[ 0, 0, 1, 0], [-1,-1,-1,-1], [ 0, 2,-1, 0], [-1,-1,-1,-1]],

      [[-1,-1,-1,-1], [ 0, 0, 1, 0], [ 0, 3, 0, 1], [-1,-1,-1,-1]],
      [[13, 3, 0, 1], [13, 2,-1, 0], [ 7, 1, 0,-1], [ 7, 0, 1, 0]],
      [[-1,-1,-1,-1], [ 0, 1, 0,-1], [-1,-1,-1,-1], [ 0, 3, 0, 1]],
      [[ 0, 3, 0, 1], [ 0, 2,-1, 0], [-1,-1,-1,-1], [-1,-1,-1,-1]],

      [[ 0, 3, 0, 1], [ 0, 2,-1, 0], [-1,-1,-1,-1], [-1,-1,-1,-1]],
      [[-1,-1,-1,-1], [ 0, 1, 0,-1], [-1,-1,-1,-1], [ 0, 3, 0, 1]],
      [[11, 1, 0,-1], [14, 0, 1, 0], [14, 3, 0, 1], [11, 2,-1, 0]],
      [[-1,-1,-1,-1], [ 0, 0, 1, 0], [ 0, 3, 0, 1], [-1,-1,-1,-1]],

      [[ 0, 0, 1, 0], [-1,-1,-1,-1], [ 0, 2,-1, 0], [-1,-1,-1,-1]],
      [[-1,-1,-1,-1], [-1,-1,-1,-1], [ 0, 1, 0,-1], [ 0, 0, 1, 0]],
      [[ 0, 1, 0,-1], [-1,-1,-1,-1], [-1,-1,-1,-1], [ 0, 2,-1, 0]],
      [[-1,-1,-1,-1], [-1,-1,-1,-1], [-1,-1,-1,-1], [-1,-1,-1,-1]]
    ];

    private const DIRECTION_EAST = 0;
    private const DIRECTION_SOUTH_EAST = 1;
    private const DIRECTION_SOUTH = 2;
    private const DIRECTION_SOUTH_WEST = 3;
    private const DIRECTION_WEST = 4;
    private const DIRECTION_NORTH_WEST = 5;
    private const DIRECTION_NORTH = 6;
    private const DIRECTION_NORTH_EAST = 7;
    private const DIRECTION_CENTER = 8;

    private const SEGMENT_LINE = 'L';
    private const SEGMENT_CURVE = 'Q';

    public const OPTION_MINIMUM_PATH_NODES = 'minimum_path_nodes';
    public const OPTION_ENHANCE_RIGHT_ANGLE = 'enhance_right_angle';
    public const OPTION_LINE_THRESHOLD = 'line_threshold';
    public const OPTION_QUADRATIC_SPLINE_THRESHOLD = 'quadratic_spline_threshold';
    public const OPTION_COORDINATE_PRECISION = 'coordinate_precision';
    public const OPTION_STROKE_WIDTH = 'stroke_width';

    private static $_optionDefaults = [
      self::OPTION_MINIMUM_PATH_NODES => 8,
      self::OPTION_ENHANCE_RIGHT_ANGLE => FALSE,
      self::OPTION_LINE_THRESHOLD => 1.0,
      self::OPTION_QUADRATIC_SPLINE_THRESHOLD => 1.0,
      self::OPTION_COORDINATE_PRECISION => 2,
      self::OPTION_STROKE_WIDTH => 0.4
    ];
    /**
     * @var Options
     */
    private $_options;

    /**
     * @var ImageData
     */
    private $_imageData;

    /**
     * @var Color\Palette
     */
    private $_palette;

    public function __construct(
      ImageData $imageData, Color\Palette $palette, array $options = []
    ) {
      $this->_imageData = $imageData;
      $this->_palette = $palette;
      $this->_options = new Options(self::$_optionDefaults, $options);
    }

    public function appendTo(SVG\Document $svg): void {
      $width = $this->_imageData->width;
      $height = $this->_imageData->height;
      $palette = $this->_palette;

      $layers = $this->trace(
        $this->interpolate(
          $this->scan(
            $this->createLayers(
              $this->createMatrix($this->_imageData, $palette)
            ),
            $this->_options[self::OPTION_MINIMUM_PATH_NODES]
          )
        )
      );

      $precision = $this->_options[self::OPTION_COORDINATE_PRECISION];
      $strokeWidth = $this->_options[self::OPTION_STROKE_WIDTH];
      $scaleX = $svg->getWidth() / $width;
      $scaleY = $svg->getHeight() / $height;

      $parent = $svg->getShapesNode();
      $document = $parent->ownerDocument;
      $svg->appendStyle(
        'path',
        [
          'stroke-width' => $strokeWidth
        ]
      );
      $addBackground = TRUE;
      foreach ($layers as $colorIndex => $paths) {
        $color = $palette[$colorIndex];
        $rgb = $color->toHexString();
        $opacity = $color['alpha'] < 255 ? number_format($color['alpha'] / 255, 2) : NULL;

        $styleSelector = 'p'.$colorIndex;
        $svg->appendStyle(
          '#'.$styleSelector,
          [
            'fill' => $rgb,
            'stroke' => $rgb,
            'opacity' => $opacity,
          ]
        );

        $groupNode = $parent;
        if ($addBackground) {
          $groupNode->appendChild(
            $rectNode = $document->createElementNS(self::XMLNS_SVG, 'rect')
          );
          $rectNode->setAttribute('id', $styleSelector);
          $rectNode->setAttribute('x', 0);
          $rectNode->setAttribute('y', 0);
          $rectNode->setAttribute('width', number_format(round($width * $scaleX, $precision), $precision));
          $rectNode->setAttribute('height', number_format(round($height * $scaleY, $precision), $precision));
          $addBackground = FALSE;
          continue;
        } elseif (\count($paths) > 1) {
          /** @var \DOMElement $groupNode */
          $groupNode = $parent->appendChild(
            $document->createElementNS(self::XMLNS_SVG, 'g')
          );
          $groupNode->setAttribute('id', $styleSelector);
        }

        foreach ($paths as $path) {
          if ($path['is_hole']) {
            continue;
          }
          /** @var \DOMElement $pathNode */
          $pathNode = $groupNode->appendChild(
            $document->createElementNS(self::XMLNS_SVG, 'path')
          );
          if ($groupNode === $parent) {
            $pathNode->setAttribute('id', $styleSelector);
          }
          // Creating non-hole path string
          $segments = $path['segments'];
          $pointToString = function(float $x, float $y) use ($scaleX, $scaleY, $precision) {
            return
              number_format(round($x * $scaleX, $precision), $precision).' '.
              number_format(round($y * $scaleY, $precision), $precision);
          };
          $dimensions = 'M '.$pointToString($segments[0]['x1'], $segments[0]['y1']).' ';
          foreach ($segments as $index => $segment) {
            $dimensions .= $segment['type'].' '.$pointToString($segment['x2'], $segment['y2']).' ';
            if (isset($segment['x3'])) {
              $dimensions .= $pointToString($segment['x3'], $segment['y3']).' ';
            }
          }
          $dimensions .= 'Z';
          foreach ($path['holes'] as $holePathIndex) {
            if (!isset($paths[$holePathIndex])) {
              continue;
            }
            $holePath = $paths[$holePathIndex];
            $holeSegments = $holePath['segments'];
            $lastIndex = \count($holeSegments) - 1;

            if (isset($holeSegments[$lastIndex]['x3'])) {
              $dimensions .= ' M '.$pointToString($holeSegments[$lastIndex]['x3'], $holeSegments[$lastIndex]['y3']).' ';
            } else {
              $dimensions .= ' M '.$pointToString($holeSegments[$lastIndex]['x2'], $holeSegments[$lastIndex]['y2']).' ';
            }
            for ($index = $lastIndex; $index >= 0; $index--) {
              $dimensions .= $holeSegments[$index]['type'].' ';
              if (isset($holeSegments[$index]['x3'])) {
                $dimensions .= $pointToString($holeSegments[$index]['x2'], $holeSegments[$index]['y2']).' ';
              }
              $dimensions .= $pointToString($holeSegments[$index]['x1'], $holeSegments[$index]['y1']).' ';
            }
            $dimensions .= 'Z';
          }
          $pathNode->setAttribute('d', $dimensions);
        }
      }
    }

    public function createMatrix(ImageData $imageData, Color\Palette $palette): array {
      $width = $imageData->width;
      $height = $imageData->height;
      $result = \array_fill(
        0,
        $height + 2,
        \array_fill(0, $width + 2, -1)
      );
      $cache = [];
      for ($y = 0; $y < $height; $y++) {
        for ($x = 0; $x < $width; $x++) {
          $index = ($y * $width + $x) * 4;
          $pixelColor = [
            'red' => $imageData->data[$index],
            'green' => $imageData->data[$index + 1],
            'blue' => $imageData->data[$index + 2],
            'alpha' => $imageData->data[$index + 3]
          ];
          $rgba =
            ($pixelColor['red'] << 24) +
            ($pixelColor['green'] << 16) +
            ($pixelColor['blue'] << 8) +
            $pixelColor['alpha'];
          if (isset($cache[$rgba])) {
            $closestColorIndex = $cache[$rgba];
          } else {
            $closestColorIndex = $palette->getNearestColorIndex($pixelColor);
            $cache[$rgba] = $closestColorIndex;
          }
          // store color index in result
          $result[$y + 1][$x + 1] = $closestColorIndex;
        }
      }
      return $result;
    }

    /**
     * Layer separation and edge detection
     *
     * Edge node types ( ▓: this layer or 1; ░: not this layer or 0 )
     *  12  ░░  ▓░  ░▓  ▓▓  ░░  ▓░  ░▓  ▓▓  ░░  ▓░  ░▓  ▓▓  ░░  ▓░  ░▓  ▓▓
     *  48  ░░  ░░  ░░  ░░  ░▓  ░▓  ░▓  ░▓  ▓░  ▓░  ▓░  ▓░  ▓▓  ▓▓  ▓▓  ▓▓
     *      0   1   2   3   4   5   6   7   8   9   10  11  12  13  14  15
     *
     * @param array $matrix
     * @return array
     */
    private function createLayers(array $matrix): array {
      $layers = [];
      $maximumY = \count($matrix) - 1;
      $maximumX = \count($matrix[0]) - 1;

      // Looping through all pixels and calculating edge node type
      for ($y = 1; $y < $maximumY; $y++) {
        for ($x = 1; $x < $maximumX; $x++) {
          // current color
          $colorIndex = $matrix[$y][$x];

          // Are neighbor pixel colors the same?
          $topLeft = $matrix[$y-1][$x-1] === $colorIndex ? 1 : 0;
          $top = $matrix[$y-1][$x] === $colorIndex ? 1 : 0;
          $topRight = $matrix[$y-1][$x+1] === $colorIndex ? 1 : 0;
          $left = $matrix[$y][$x-1] === $colorIndex ? 1 : 0;
          $right = $matrix[$y][$x+1] === $colorIndex ? 1 : 0;
          $bottomLeft = $matrix[$y+1][$x-1] === $colorIndex ? 1 : 0;
          $bottom = $matrix[$y+1][$x] === $colorIndex ? 1 : 0;
          $bottomRight = $matrix[$y+1][$x+1] === $colorIndex ? 1 : 0;

          // Create new layer if there's no one with this indexed color
          if (!isset($layers[$colorIndex])) {
            $layers[$colorIndex] = \array_fill(
              0, $maximumY, \array_fill(0, $maximumX, 0)
            );
          }
          $layer = &$layers[$colorIndex];

          // this pixel's type and looking back on previous pixels
          $layer[$y+1][$x+1] = 1 + ($right * 2) + ($bottomRight * 4) + ($bottom * 8);
          if (!$left) {
            $layer[$y+1][$x] = 2 + ($bottom * 4) + ($bottomLeft * 8);
          }
          if (!$top) {
            $layer[$y][$x+1] = ($topRight * 2) + ($right * 4) + 8;
          }
          if (!$topLeft) {
            $layer[$y][$x] = ($top * 2) + 4 + $left * 8;
          }
        }
      }
      return $layers;
    }

    private function scan(array $layers, int $minimumPathNodes): array {
      $result = [];
      foreach ($layers as $index => $layer) {
        $result[$index] = $this->scanLayer($layer, $minimumPathNodes);
      }
      return $result;
    }

    private function isBoxInBox(array $parentBox, array $childBox): bool {
      return (
        ($parentBox[0] < $childBox[0]) &&
        ($parentBox[1] < $childBox[1]) &&
        ($parentBox[2] > $childBox[2]) &&
        ($parentBox[3] > $childBox[3])
      );
    }

    private function scanLayer(array $layer, int $minimumPathNodes = 8): array {
      $minimumPathNodes = $minimumPathNodes > 4 ? $minimumPathNodes : 4;
      $paths = [];
      $height = \count($layer);
      $width = \count($layer[0]);

      /** @var array $row */
      foreach ($layer as $y => $row) {
        foreach ($row as $x => $nodeType) {
          if ($nodeType === 4 || $nodeType === 11) {

            $pathIndex = \count($paths);
            $path = [
              'points' => [],
              'box' => [$x, $y, $x, $y],
              'holes' => []
            ];
            $isHolePath = $nodeType === 11;
            $direction = 1;

            $pathX = $x;
            $pathY = $y;
            while (TRUE) {
              $pointX = $pathX - 1;
              $pointY = $pathY - 1;
              $type = $layer[$pathY][$pathX];

              $point = [
                'x' => $pointX,
                'y' => $pointY,
                'type' => $type,
              ];
              // Bounding box
              if ($pointX < $path['box'][0]) { $path['box'][0] = $pointX; }
              if ($pointX > $path['box'][2]) { $path['box'][2] = $pointX; }
              if ($pointY < $path['box'][1]) { $path['box'][1] = $pointY; }
              if ($pointY > $path['box'][3]) { $path['box'][3] = $pointY; }

              // Next: look up the replacement, direction and coordinate changes = clear this cell, turn if required, walk forward
              if ($direction === -1) {
                break;
              }
              $lookupRow = self::PATH_SCAN_LOOKUP[$type][$direction];
              [$layer[$pathY][$pathX], $direction] = $lookupRow;
              $pathX += $lookupRow[2];
              $pathY += $lookupRow[3];

              // Close and add path
              if(
                isset($path['points'][0]) &&
                ($pointX === $path['points'][0]['x']) &&
                ($pointY === $path['points'][0]['y'])
              ) {
                $path['is_hole'] = $isHolePath ? TRUE : FALSE;
                if (\count($path['points']) >= $minimumPathNodes) {
                  $paths[$pathIndex] = $path;
                  if ($isHolePath) {
                    // find hole path parent and add child
                    $parentIndex = 0;
                    $parentBox = [0, 0, $width, $height];
                    for ($i = 0; $i < $pathIndex; $i++) {
                      if ($paths[$i]['is_hole']) {
                        continue;
                      }
                      if (
                        $this->isBoxInBox($parentBox, $paths[$i]['box']) &&
                        $this->isBoxInBox($paths[$i]['box'], $path['box'])
                      ) {
                        $parentIndex = $i;
                        $parentBox = $paths[$i]['box'];
                      }
                    }
                    $paths[$parentIndex]['holes'][] = $pathIndex;
                  }
                }
                break;
              }
              $path['points'][] = $point;
            }
          }
        }
      }
      return $paths;
    }

    private function interpolate(array $layers): array {
      $result = [];
      foreach ($layers as $index => $paths) {
        $result[$index] = $this->interpolateLayer($paths);
      }
      return $result;
    }

    private function interpolateLayer(array $paths): array {
      $result = [];
      $enhanceRightAngle = $this->_options[self::OPTION_ENHANCE_RIGHT_ANGLE];

      foreach ($paths as $pathIndex => $path) {
        $optimizedPath = [
          'points' => [],
          'box' => $path['box'],
          'holes' => $path['holes'],
          'is_hole' => $path['is_hole']
        ];

        /** @noinspection ForeachSourceInspection */
        foreach ($path['points'] as $pointIndex => $point) {
          $pathLength = \count($path['points']);
          $nextIndex = ($pointIndex + 1) % $pathLength;
          $nextIndex2 = ($pointIndex + 2) % $pathLength;
          $previousIndex = ($pointIndex - 1 + $pathLength) % $pathLength;
          $previousIndex2 = ($pointIndex - 2 + $pathLength) % $pathLength;

          if (
            $enhanceRightAngle &&
            $this->isRightAngle(
              $path['points'], $previousIndex2, $previousIndex, $pointIndex, $nextIndex, $nextIndex2
            )
          ) {
            // Fix previous direction
            if (\count($optimizedPath['points']) > 0) {
              $lastIndex = \count($optimizedPath['points']) - 1;
              $optimizedPath['points'][$lastIndex]['line_segment'] = $this->getPathDirection(
                $optimizedPath['points'][$lastIndex]['x'],
                $optimizedPath['points'][$lastIndex]['y'],
                $point['x'],
                $point['y']
              );
            }

            // Add corner point
            $optimizedPath['points'][] = [
              'x' => $point['x'],
              'y' => $point['y'],
              'line_segment' => $this->getPathDirection(
                $point['x'],
                $point['y'],
                ($point['x'] + $path['points'][$nextIndex]['x']) / 2,
                ($point['y'] + $path['points'][$nextIndex]['y']) / 2
              )
            ];
          }

          // interpolate between two path points
          $optimizedPath['points'][] = [
            'x' => ($point['x'] + $path['points'][$nextIndex]['x']) / 2,
            'y' => ($point['y'] + $path['points'][$nextIndex]['y']) / 2,
            'line_segment' => $this->getPathDirection(
              ($point['x'] + $path['points'][$nextIndex]['x']) / 2,
              ($point['y'] + $path['points'][$nextIndex]['y']) / 2,
              ($path['points'][$nextIndex]['x'] + $path['points'][$nextIndex2]['x']) / 2,
              ($path['points'][$nextIndex]['y'] + $path['points'][$nextIndex2]['y']) / 2
            )
          ];
        }
        $result[$pathIndex] = $optimizedPath;
      }
      return $result;
    }

    private function isRightAngle(array $points, ...$keys): bool {
      return (
        (
          ($points[$keys[2]]['x'] === $points[$keys[0]]['x']) &&
          ($points[$keys[2]]['x'] === $points[$keys[1]]['x']) &&
          ($points[$keys[2]]['y'] === $points[$keys[3]]['y']) &&
          ($points[$keys[2]]['y'] === $points[$keys[4]]['y'])
        ) ||
        (
          ($points[$keys[2]]['y'] === $points[$keys[0]]['y']) &&
          ($points[$keys[2]]['y'] === $points[$keys[1]]['y']) &&
          ($points[$keys[2]]['x'] === $points[$keys[3]]['x']) &&
          ($points[$keys[2]]['x'] === $points[$keys[4]]['x'])
        )
      );
    }

    private function getPathDirection($originX, $originY, $targetX, $targetY): int {
      if ($originX < $targetX) {
        if ($originY < $targetY) { return self::DIRECTION_SOUTH_EAST; }
        if ($originY > $targetY) { return self::DIRECTION_NORTH_EAST; }
        return self::DIRECTION_EAST;
      }
      if ($originX > $targetX) {
        if ($originY < $targetY) { return self::DIRECTION_SOUTH_WEST; }
        if ($originY > $targetY) { return self::DIRECTION_NORTH_WEST; }
        return self::DIRECTION_WEST;
      }
      if ($originY < $targetY) { return self::DIRECTION_SOUTH; }
      if ($originY > $targetY) { return self::DIRECTION_NORTH; }
      return self::DIRECTION_CENTER; // center, this should not happen
    }

    private function trace(array $layers): array {
      $result = [];
      $lineThreshold = $this->_options[self::OPTION_LINE_THRESHOLD];
      $splineThreshold = $this->_options[self::OPTION_QUADRATIC_SPLINE_THRESHOLD];
      foreach ($layers as $index => $paths) {
        $result[$index] = $this->tracePaths($paths, $lineThreshold, $splineThreshold);
      }
      return $result;
    }

    private function tracePaths(array $paths, float $lineThreshold, float $splineThreshold): array {
      $result = [];
      foreach ($paths as $path) {
        if ($path = $this->tracePath($path, $lineThreshold, $splineThreshold)) {
          $result[] = $path;
        }
      }
      return $result;
    }

    /**
     * Trace path step by step
     *
     * 1. Find sequences of points with only 2 segment types
     * 2. Fit a straight line on the sequence
     * 3. If the straight line fails (distance error > line threshold), find the point with the biggest error
     * 4. Fit a quadratic spline through errorpoint (project this to get controlpoint), then measure errors on every point in the sequence
     * 5. If the spline fails (distance error >  spline threshold), find the point with the biggest error, set splitpoint = fitting point
     * 6. Split sequence and recursively apply 5.2. - 5.6. to startpoint-splitpoint and splitpoint-endpoint sequences
     *
     * @param array $path
     * @param float $lineThreshold
     * @param float $splineThreshold
     * @return array
     */
    private function tracePath(array $path, float $lineThreshold, float $splineThreshold): ?array {
      $tracedPath = [
        'segments' => [],
        'box' => $path['box'],
        'holes' => $path['holes'],
        'is_hole' => $path['is_hole']
      ];

      $points = $path['points'];
      $offset = 0;
      $length = \count($points);
      while ($offset < $length) {
        $segmentType1 = $points[$offset]['line_segment'];
        $segmentType2 = -1;
        $sequenceEnd = $offset + 1;
        while (
          (
            $segmentType2 === -1 ||
            $points[$sequenceEnd]['line_segment'] === $segmentType1 ||
            $points[$sequenceEnd]['line_segment'] === $segmentType2
          ) &&
          $sequenceEnd < $length - 1
        ) {
          if ($segmentType2 === -1 && $points[$sequenceEnd]['line_segment'] !== $segmentType1) {
            $segmentType2 = $points[$sequenceEnd]['line_segment'];
          }
          $sequenceEnd++;
        }
        if ($sequenceEnd === $length - 1) {
          $sequenceEnd = 0;
        }

        /* Split sequence and recursively apply steps 2-6 to startpoint-splitpoint and splitpoint-endpoint sequences */
        /** @noinspection SlowArrayOperationsInLoopInspection */
        $tracedPath['segments'] = array_merge(
          $tracedPath['segments'],
          $this->fitSequence($path['points'], $offset, $sequenceEnd, $lineThreshold, $splineThreshold)
        );
        $offset = ($sequenceEnd > 0) ? $sequenceEnd : $length;
      }
      return $this->optimizePath($tracedPath);
    }

    private function optimizePath($path) {
      $segmentCount = \count($path['segments']);
      if ($segmentCount < 2) {
        return NULL;
      }
      $lastSegment = $path['segments'][$segmentCount-1];
      if ($lastSegment['type'] === self::SEGMENT_LINE) {
        // if the last segment is a straight line, remove it (closing the path will be enough)
        array_pop($path['segments']);
      }
      $typeCount = \array_reduce(
        $path['segments'],
        function ($carry, $segment) {
          $carry[$segment['type']]++;
          return $carry;
        },
        [self::SEGMENT_LINE => 0, self::SEGMENT_CURVE => 0]
      );
      if (
        // at least 3 segments (triangle)
        $segmentCount > 2 ||
        // or two curves
        ($typeCount[self::SEGMENT_CURVE] > 1)
      ) {
        return $path;
      }
      return NULL;
    }

    private function fitSequence(array $points, int $start, int $end, float $lineThreshold, float $splineThreshold): array {
      $pathLength = \count($points);

      if ($end > $pathLength || $end < 0) {
        return [];
      }

      $errorIndex = $start;
      $errorValue = 0;
      $curvePass = TRUE;

      $traceLength = $end - $start;
      if ($traceLength < 0) {
        $traceLength += $pathLength;
      }
      $vx = ($points[$end]['x'] - $points[$start]['x']) / $traceLength;
      $vy = ($points[$end]['y'] - $points[$start]['y']) / $traceLength;

      // 2. Fit a straight line on the sequence
      $index = ($start + 1) % $pathLength;
      while ($index !== $end) {
        $pl = $index - $start;
        if ($pl < 0) {
          $pl += $pathLength;
        }
        $px = $points[$start]['x'] + $vx * $pl;
        $py = $points[$start]['y'] + $vy * $pl;
        $distance = (
          ($points[$index]['x'] - $px) * ($points[$index]['x'] - $px) +
          ($points[$index]['y'] - $py) * ($points[$index]['y'] - $py)
        );
        if ($distance > $lineThreshold) {
          $curvePass = FALSE;
        }
        if ($distance > $errorValue) {
          $errorIndex = $index;
          $errorValue = $distance;
        }
        $index = ($index + 1) % $pathLength;
      }

      // return straight line if fits
		  if ($curvePass) {
        return [
          [
            'type' => self::SEGMENT_LINE,
            'x1' => $points[$start]['x'],
            'y1' => $points[$start]['y'],
            'x2' => $points[$end]['x'],
            'y2' => $points[$end]['y']
          ]
        ];
      }

      // 3. If the straight line fails (distance error>ltres), find the point with the biggest error
      $curvePass = TRUE;
      $fitPoint = $errorIndex;
      $errorValue = 0;

		  // 4. Fit a quadratic spline through this point, measure errors on every point in the sequence

	   	// helpers and projecting to get control point
      $t = ($fitPoint - $start) / $traceLength;
      $t1 = (1 - $t) * (1 - $t);
      $t2 = 2 * (1 - $t) * $t;
      $t3 = $t * $t;

      $cpx = ($t1 * $points[$start]['x'] + $t3 * $points[$end]['x'] - $points[$fitPoint]['x']) / - $t2;
      $cpy = ($t1 * $points[$start]['y'] + $t3 * $points[$end]['y'] - $points[$fitPoint]['y']) / - $t2;

      for ($index = $start + 1; $index <= $end; $index++) {
        $t = ($index - $start) / $traceLength;
        $t1 = (1 - $t) * (1 - $t);
        $t2 = 2 * (1 - $t) * $t;
        $t3 = $t * $t;
        $px = $t1 * $points[$start]['x'] + $t2 * $cpx + $t3 * $points[$end]['x'];
        $py = $t1 * $points[$start]['y'] + $t2 * $cpy + $t3 * $points[$end]['y'];
        $distance =(
          ($points[$index]['x'] - $px) * ($points[$index]['x'] - $px) +
          ($points[$index]['y'] - $py) * ($points[$index]['y'] - $py)
        );
        if ($distance > $splineThreshold) {
          $curvePass = FALSE;
        }
        if ($distance > $errorValue) {
          $errorIndex = $index;
          $errorValue = $distance;
        }
      }

		  // return spline if fit
      if ($curvePass) {
        return [
          [
            'type' => self::SEGMENT_CURVE,
            'x1' => $points[$start]['x'],
            'y1' => $points[$start]['y'],
            'x2' => $cpx,
            'y2' => $cpy,
            'x3' => $points[$end]['x'],
            'y3' => $points[$end]['y']
          ]
        ];
      }

      // 5. If the spline fails (distance error>qtres), find the point with the biggest error
		  $splitPoint = $errorIndex;

		  // 6. Split sequence and recursively apply step 2-6 to startpoint-splitpoint and splitpoint-endpoint sequences
		  return array_merge(
		    $this->fitSequence($points, $start, $splitPoint, $lineThreshold, $splineThreshold),
        $this->fitSequence($points, $splitPoint, $end, $lineThreshold, $splineThreshold)
      );
    }
  }
}
