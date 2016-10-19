<?php

namespace rdx\jsdom;

use Symfony\Component\CssSelector\CssSelectorConverter;

class Node implements \ArrayAccess {

	protected $element;

	static function create($html) {
		$document = new \DOMDocument;
		libxml_use_internal_errors(true);
		$document->loadHTML($html);
		return new static($document);
	}

	public function __construct(\DOMNode $element) {
		$this->element = $element;
	}

	protected function wrap($node) {
		return new static($node);
	}

	protected function wraps($list) {
		$nodes = [];
		foreach ($list as $node) {
			if ($node->nodeType == XML_ELEMENT_NODE) {
				$nodes[] = $this->wrap($node);
			}
		}

		return $nodes;
	}

	protected function css($selector) {
        $converter = new CssSelectorConverter;
        $expression = $converter->toXPath($selector);

        return $this->xpath($expression);
	}

	protected function xpath($expression) {
        $xpath = new \DOMXPath($this->element->ownerDocument ?: $this->element);
        return $xpath->query($expression, $this->element->ownerDocument ? $this->element : null);
	}

	public function query($selector) {
		foreach ($this->css($selector) as $node) {
			return $this->wrap($node);
		}
	}

	public function queryAll($selector) {
        $nodes = $this->css($selector);
		return $this->wraps($nodes);
	}

	public function getText() {
		return trim(preg_replace('#\s+#', ' ', $this->element->textContent));
	}

	protected function attribute($name) {
		return $this->element->attributes->getNamedItem($name);
	}

	// @todo
	// function children($selector = null);
	// function child($selector = null);

	/**
	 * Proxy properties
	 */

	public function __get($name) {
		return $this->element->$name;
	}

	/**
	 * ArrayAccess
	 */

	public function offsetExists($offset) {
		return $this->attribute($offset) !== null;
	}

	public function offsetGet($offset) {
		$attribute = $this->attribute($offset);
		return $attribute ? trim($attribute->nodeValue) : null;
	}

	public function offsetSet($offset, $value) {
		throw new \Exception('READ ONLY');
	}

	public function offsetUnset($offset) {
		throw new \Exception('READ ONLY');
	}

}
