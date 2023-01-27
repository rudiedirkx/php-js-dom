<?php

namespace rdx\jsdom;

use ArrayAccess;
use BadMethodCallException;
use DOMDocument;
use DOMNode;
use DOMXPath;
use Exception;
use Symfony\Component\CssSelector\CssSelectorConverter;

class Node implements ArrayAccess {

	static public $formElementSelectors = ['input'];

	protected $element;

	static function create($html, ?string $encoding = null) {
		$document = new DOMDocument();
		libxml_use_internal_errors(true);
		if ($encoding && strpos($html, '<?xml') === false) {
			$html = '<?xml encoding="' . $encoding . '">' . $html;
		}
		$document->loadHTML($html);
		return new static($document);
	}

	public function __construct(DOMNode $element) {
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
		$expression = $this->expression($selector, 'descendant::'); // Instead of descendant-or-self
		return $this->xpath($expression);
	}

	protected function xpath($expression) {
		$xpath = new DOMXPath($this->element->ownerDocument ?: $this->element);
		return $xpath->query($expression, $this->element->ownerDocument ? $this->element : null);
	}

	public function closest($selector, $class = NULL) {
		$expression = $this->expression($selector, 'ancestor-or-self::');
		foreach ($this->xpath($expression) as $node) {
			return $this->wrap($node, $class);
		}
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
		return trim(preg_replace('#\s+#', ' ', str_replace(' ', ' ', $text)));
	}

	static public function makeShapeText($text) {
		// @todo Use block elements instead of arbitrary white space
		return preg_replace('#\n{3,}#', "\n\n", implode("\n", array_map(function($line) {
			return trim($line);
		}, preg_split('#(\r\n|\r|\n)+#', trim($text)))));
	}

	protected function attribute($name) {
		return $this->element->attributes->getNamedItem($name);
	}

	// @todo Select elements with cross-current selector
	protected function childrenLike($selector) {
		$curr = $this->getNodePath();
		$expr = $this->expression($selector, "$curr/");
		$expr = str_replace("$curr/*/", "$curr/", $expr);
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

	public function parent() {
		return new static($this->element->parentNode);
	}

	protected function walk($property) {
		$element = $this;
		while ($element = $element->$property) {
			if ($element->nodeType == XML_ELEMENT_NODE) {
				return new self($element);
			}
		}
	}

	public function getFormValue( $name ) {
		$selector = implode(', ', array_map(function($selector) use ($name) {
			return $selector . '[name="' . $name . '"]';
		}, static::$formElementSelectors));

		$element = $this->query($selector);
		return $element ? $this->getFormElementValue($element) : null;
	}

	public function getFormValues() {
		$elements = $this->queryAll(implode(', ', static::$formElementSelectors));

		$values = [];
		foreach ( $elements as $element ) {
			if ( $element['name'] ) {
				$values[ $element['name'] ] = $this->getFormElementValue($element);
			}
		}

		return $values;
	}

	public function getFormElementValue( self $element ) {
		return $element['value'];
	}

	/**
	 * Getters
	 */

	public function get_textContent() {
		return static::makePlainText($this->element->nodeValue);
	}

	public function get_innerText() {
		return static::makeShapeText($this->element->nodeValue);
	}

	public function get_innerHTML() {
		$html = '';
		foreach ($this->element->childNodes as $child) {
			if ($child->nodeType == XML_ELEMENT_NODE) {
				$html .= (new static($child))->outerHTML;
			}
			elseif ($child->nodeType == XML_TEXT_NODE) {
				$html .= $child->wholeText;
			}
		}

		return $html;
	}

	public function get_outerHTML() {
		return $this->element->ownerDocument->saveHTML($this->element);
	}

	public function get_nextElementSibling() {
		return $this->walk('nextSibling');
	}

	public function get_previousElementSibling() {
		return $this->walk('previousSibling');
	}

	public function get_prevElementSibling() {
		return $this->previousSibling;
	}

	/**
	 * Proxy
	 */

	public function __get($name) {
		if ( method_exists($this, $func = "get_{$name}") ) {
			return call_user_func([$this, $func]);
		}

		return $this->element->$name;
	}

	public function __isset($name) {
		return method_exists($this, "get_{$name}") || property_exists($this->element, $name);
	}

	public function __call($function, $arguments) {
		if (!is_callable($method = [$this->element, $function])) {
			$class = get_class($this);
			throw new BadMethodCallException("Method $function does not exist on $class.");
		}

		return call_user_func_array($method, $arguments);
	}

	/**
	 * ArrayAccess
	 */

	public function offsetExists($offset) : bool {
		return $this->attribute($offset) !== null;
	}

	public function offsetGet($offset) : mixed {
		$attribute = $this->attribute($offset);
		return $attribute ? trim($attribute->nodeValue) : null;
	}

	public function offsetSet($offset, $value) : void {
		throw new Exception('READ ONLY');
	}

	public function offsetUnset($offset) : void {
		throw new Exception('READ ONLY');
	}

}
