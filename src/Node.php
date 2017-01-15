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

	protected function wrap($node, $class = NULL) {
		$class or $class = get_class($this);
		return new $class($node);
	}

	protected function wraps($list, $class = NULL) {
		$nodes = [];
		foreach ($list as $node) {
			if ($node->nodeType == XML_ELEMENT_NODE) {
				$nodes[] = $this->wrap($node, $class);
			}
		}

		return $nodes;
	}

	protected function expression($selector, $prefix = null) {
        $converter = new CssSelectorConverter;
		return $prefix === null ? $converter->toXPath($selector) : $converter->toXPath($selector, $prefix);
	}

	protected function css($selector) {
        $expression = $this->expression($selector);
        return $this->xpath($expression);
	}

	protected function xpath($expression) {
        $xpath = new \DOMXPath($this->element->ownerDocument ?: $this->element);
        return $xpath->query($expression, $this->element->ownerDocument ? $this->element : null);
	}

	// @todo Select elements with cross-current selector
	public function query($selector, $class = NULL) {
		foreach ($this->css($selector) as $node) {
			return $this->wrap($node, $class);
		}
	}

	// @todo Select elements with cross-current selector
	public function queryAll($selector, $class = NULL) {
        $nodes = $this->css($selector);
		return $this->wraps($nodes, $class);
	}

	static public function makePlainText($text) {
		return trim(preg_replace('#\s+#', ' ', $text));
	}

	public function getPlainText() {
		return static::makePlainText($this->element->nodeValue);
	}

	static public function makeShapeText($text) {
		// @todo Use block elements instead of arbitrary white space
		return preg_replace('#\n{3,}#', "\n\n", implode("\n", array_map(function($line) {
			return trim($line);
		}, preg_split('#(\r\n|\r|\n)+#', trim($text)))));
	}

	public function getShapeText() {
		return static::makeShapeText($this->element->nodeValue);
	}

	public function getInnerHTML() {
		$html = '';
		foreach ($this->element->childNodes as $child) {
			if ($child->nodeType == XML_ELEMENT_NODE) {
				$html .= (new static($child))->getOuterHTML();
			}
			elseif ($child->nodeType == XML_TEXT_NODE) {
				$html .= $child->wholeText;
			}
		}

		return $html;
	}

	public function getOuterHTML() {
		return $this->element->ownerDocument->saveHTML($this->element);
	}

	protected function attribute($name) {
		return $this->element->attributes->getNamedItem($name);
	}

	// @todo Select elements with cross-current selector
	protected function childrenLike($selector) {
		$curr = $this->getNodePath();
		$expr = $this->expression($selector, "$curr/");
		return $this->wraps($this->wrap($this->element->ownerDocument)->xpath($expr));
	}

	public function children($selector = null) {
		if ($selector) {
			return $this->childrenLike($selector);
		}

		return $this->wraps($this->element->childNodes);
	}

	public function child($selector = null) {
		$children = $this->children($selector);
		return @$children[0];
	}

	protected function walk($property) {
		$element = $this;
		while ($element = $element->$property) {
			if ($element->nodeType == XML_ELEMENT_NODE) {
				return new self($element);
			}
		}
	}

	/**
	 * Proxy
	 */

	public function __get($name) {
		switch ($name) {
			case 'textContent':
				return $this->getShapeText();

			case 'innerText':
				return $this->getPlainText();

			case 'innerHTML':
				return $this->getInnerHTML();

			case 'outerHTML':
				return $this->getOuterHTML();

			case 'nextElementSibling':
				return $this->walk('nextSibling');

			case 'prevElementSibling':
			case 'previousElementSibling':
				return $this->walk('previousSibling');
		}

		// @todo
		// - innerHTML

		return $this->element->$name;
	}

	public function __call($function, $arguments) {
		if (!is_callable($method = [$this->element, $function])) {
			$class = get_class($this);
			throw new \BadMethodCallException("Method $function does not exist on $class.");
		}

		return call_user_func_array($method, $arguments);
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
