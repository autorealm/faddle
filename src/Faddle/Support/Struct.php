<?php namespace Faddle\Support;


/**
 * Class Struct
 */
class Struct {

	/**
	 * @var string
	 */
	protected $name;
	/**
	 * @var array
	 */
	protected $properties;
	/**
	 * @var array
	 */
	protected $methods;
	/**
	 * @var bool
	 */
	public static $strict = false;
	public static $ucfirst_property = false;
	/**
	 * @var string
	 */
	protected $src;

	/**
	 * @param string $name
	 * @param array $properties
	 * @param array $methods
	 */
	public function __construct($name, array $properties, $methods = array()) {
		$this->name = $name;
		$this->properties = $properties;
		$this->methods = $methods;
		$this->src = '';
		if (!preg_match('/^[A-Z]\w+/', $name)) {
			throw new \InvalidArgumentException('Invalid struct name: ' . $name);
		}
		
	}

	/**
	 * static struct function
	 *
	 * @param $name
	 * @param $properties
	 * @param array $methods
	 * @return Struct
	 */
	public static function struct($name, array $properties, $methods = array()) {
		$struct = new self($name, $properties, $methods);
		return $struct->getStruct();
	}

	public function getStruct($struct=null) {
		$class = $this->name;
		$this->compile();
		
		eval('?>'.$this->src);
		
		return new $class($struct);
	}

	/**
	 * @return string
	 */
	public function getSource() {
		return $this->src;
	}

	/**
	 * @param $property
	 * @param $type
	 */
	public function addProperty($property, $type) {
		$this->properties[$property] = $type;
	}

	/**
	 * Compile properties and methods into a string that resembles a PHP class.
	 *
	 * @return void
	 */
	protected function compile() {
		$this->classHeader();
		$this->arrayHelpers();
		$this->properties();
		$this->methods();
		$this->classFooter();
	}

	/**
	 * Provide array hydrate/export functionality
	 *
	 * @return void
	 */
	protected function arrayHelpers() {
		$this->src .= <<<TOARRAYHELPER

public function toArray() {
    \$array = array();
    foreach (\$this as \$prop => \$val) {
        \$array[\$prop] = \$val;
    }
    return \$array;
}
TOARRAYHELPER;
		
		$existCheck = 'if (!$this->offsetExists($prop)) {';
		if (self::$strict) {
			$existCheck .= 'throw new \InvalidArgumentException(\'Struct does not contain property `\' . $prop . \'`\');';
		} else {
			$existCheck .= 'continue;';
		}
		$existCheck .= '}';
		$this->src .= <<<FROMARRAYHELPER

public function fromArray(\$array) {
    if (empty(\$array) || !is_array(\$array)) return;
    foreach (\$array as \$prop => \$val) {
        {$existCheck}
        
        \$this->offsetSet(\$prop,\$val);
    }
}

FROMARRAYHELPER;
	}

	/**
	 * Attach methods to PHP class
	 *
	 * @return void
	 */
	protected function methods() {
		foreach ($this->methods as $name => $fn) {
			$ref = new \ReflectionFunction($fn);
			$filename = $ref->getFileName();
			$start_line = $ref->getStartLine();
			$end_line = $ref->getEndLine()-1;
			$length = $end_line - $start_line;
			$source = file($filename);
			$body = implode("", array_slice($source, $start_line, $length));
			
			$this->src .= <<<METHOD

public function {$name}() {
{$body}
}
METHOD;
		}
	}

	/**
	 * Attach properties to PHP class
	 *
	 * @return void
	 */
	protected function properties() {
		$propArray = array();
		foreach ($this->properties as $property => $type) {
			if (!in_array($type, array('int','string','float','bool')) && !class_exists($type)) {
				throw new \InvalidArgumentException('Unknown property type for ' . $property . ': ' . $type);
			}
			if (!preg_match('/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*$/', $property)) {
				throw new \InvalidArgumentException('Invalid property name: ' . $property);
			}
			$propArray[] = "'{$property}'";
			
			$this->property($property, $type);
		}
		$this->src .= PHP_EOL . 'private $properties = array(' . implode(',', $propArray) . ');' . PHP_EOL;
	}

	/**
	 * Attach a property to PHP class source
	 *
	 * @param $name
	 * @param $type
	 */
	protected function property($name, $type) {
		if (static::$ucfirst_property) $pname = ucfirst($name);
		else $pname = '_' . $name;
		$this->src .= <<<PROPERTY

private \${$name};

/** @param:{$type} */ 
public function set{$pname}(\$val) {
    \$this->{$name} = \$val;
}

/** @return:{$type} */
public function get{$pname}() {
    return \$this->{$name};
}

PROPERTY;
	}

	/**
	 * End PHP class
	 *
	 * @return void
	 */
	protected function classFooter() {
		$this->src .= PHP_EOL . PHP_EOL . '}';
	}

	/**
	 * Boilerplate header for PHP class
	 *
	 * @return void
	 */
	protected function classHeader() {
		$prepend = '<?php' . PHP_EOL . PHP_EOL;
		if (self::$strict === true) {
			$prepend .= 'declare(strict_types=1);' . PHP_EOL . PHP_EOL;
		}
		
		$this->src .= sprintf('%sclass %s implements \ArrayAccess, \Iterator {', $prepend, $this->name) . PHP_EOL . PHP_EOL;
		$this->src .= <<<BOILERPLATE
private \$idx;

public function __construct(array \$struct=null) {
    \$this->idx = 0;
    \$this->fromArray(\$struct);
}

public function __get(\$name) {
    if (array_key_exists(\$name, \$this->properties))
        return \$this->\$name;
    else
        return null;
}

public function __set(\$name, \$value) {
    if (array_key_exists(\$name, \$this->properties))
        \$this->\$name = \$value;
}

public function __isset(\$name) {
    return isset(\$this->\$name);
}

public function current() {
    \$name = \$this->properties[\$this->idx];
    return \$this->{\$name};
}

public function key() {
    return \$this->properties[\$this->idx];
}
public function next() {
    ++\$this->idx;
}
public function rewind() {
    \$this->idx = 0;
}
public function valid() {
    return isset(\$this->properties[\$this->idx]);
}

public function offsetSet(\$key, \$value) {
    if (!\$this->offsetExists(\$key)) {
        throw new \InvalidArgumentException('Struct does not contain property `' . \$key . '`');
    }
    \$this->{\$key} = \$value;
}

public function offsetExists(\$key) {
    return property_exists(\$this, \$key);
}

public function offsetUnset(\$key) {
    if (\$this->offsetExists(\$key)) {
        \$this->{\$key} = null;
    }
}

public function offsetGet(\$key) {
    if (!\$this->offsetExists(\$key)) {
        return null;
    }
    return \$this->{\$key};
}
BOILERPLATE;
	}

}