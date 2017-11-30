<?php

namespace Carica\BitmapToSVG\SVG {


  use Carica\BitmapToSVG\Utility\Options;

  class Document {

    public const XMLNS_SVG = Appendable::XMLNS_SVG;

    private $_document;
    private $_shapesNode;
    private $_styleNode;

    private $_width;
    private $_height;

    public const OPTION_BLUR = 'blur';
    public const OPTION_FORMAT_OUTPUT = 'format_output';

    private static $_optionDefaults = [
      self::OPTION_BLUR => 0,
      self::OPTION_FORMAT_OUTPUT => FALSE
    ];
    private $_options;

    public function __construct($width, $height, array $options = []) {
      $this->_width = $width;
      $this->_height = $height;
      $this->_options = new Options(self::$_optionDefaults, $options);
    }

    public function getShapesNode() {
      if (NULL === $this->_document) {
        $this->_document = $document = new \DOMDocument();
        $document->formatOutput = $this->_options[self::OPTION_FORMAT_OUTPUT];
        $document->appendChild(
          $svg = $document->createElementNS(self::XMLNS_SVG, 'svg')
        );
        $svg->setAttribute('version', '1.1');
        $svg->setAttribute('width', $this->_width);
        $svg->setAttribute('height', $this->_height);
        $svg->appendChild(
          $group = $document->createElementNS(self::XMLNS_SVG, 'g')
        );
        $blurDeviation = $this->_options[self::OPTION_BLUR];
        if ($blurDeviation > 0) {
          $svg->insertBefore(
            $filter = $document->createElementNS(self::XMLNS_SVG, 'filter'),
            $group
          );
          $filter->setAttribute('id', 'b');
          $filter->appendChild(
            $blur = $document->createElementNS(self::XMLNS_SVG, 'feGaussianBlur')
          );
          $blur->setAttribute('stdDeviation', $blurDeviation);
          $group->setAttribute('filter', 'url(#b)');
        }
        $this->_shapesNode = $group;
      }
      return $this->_shapesNode;
    }

    public function appendStyle($selector, array $properties) {
      if (NULL === $this->_styleNode) {
        $document = $this->getShapesNode()->ownerDocument;
        $document->documentElement->insertBefore(
          $this->_styleNode = $document->createElement('style', "\n"),
          $document->documentElement->firstChild
        );
        $this->_styleNode->setAttribute('type', 'text/css');
      }
      $lf = '';
      $indent = '';
      $propertyIndent = ' ';
      if ($this->_options[self::OPTION_FORMAT_OUTPUT]) {
        $lf = "\n";
        $indent = '    ';
        $propertyIndent = $indent.'  ';
      }
      $style = $indent.$selector.' {'.$lf;
      foreach ($properties as $name=>$value) {
        if (!empty($value)) {
          $style .= $propertyIndent.$name.': '.$value.';'.$lf;
        }
      }
      $style .= $indent.'}'.$lf;
      $this->_styleNode->textContent .= $style;
    }

    public function getWidth() {
      return $this->_width;
    }

    public function getHeight() {
      return $this->_height;
    }

    public function append(Appendable $shape) {
      $shape->appendTo($this);
    }

    public function getXML() {
      return $this->getShapesNode()->ownerDocument->saveXML();
    }
  }
}


