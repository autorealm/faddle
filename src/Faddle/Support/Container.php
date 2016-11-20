<?php namespace Faddle\Support;

/**
 * Container main class.
 *
 * @author  Fabien Potencier
 */
class Container implements \ArrayAccess {
    private $values = array();
    private $factories;
    private $protected;
    private $frozen = array();
    private $raw = array();
    private $keys = array();

    /**
     * Instantiate the container.
     *
     * Objects and parameters can be passed as argument to the constructor.
     *
     * @param array $values The parameters or objects.
     */
    public function __construct(array $values = array())
    {
        $this->factories = new \SplObjectStorage();
        $this->protected = new \SplObjectStorage();

        foreach ($values as $key => $value) {
            $this->offsetSet($key, $value);
        }
    }

    /**
     * Sets a parameter or an object.
     *
     * Objects must be defined as Closures.
     *
     * Allowing any PHP callable leads to difficult to debug problems
     * as function names (strings) are callable (creating a function with
     * the same name as an existing parameter would break your container).
     *
     * @param string $id    The unique identifier for the parameter or object
     * @param mixed  $value The value of the parameter or a closure to define an object
     *
     * @throws \RuntimeException Prevent override of a frozen service
     */
    public function offsetSet($id, $value)
    {
        if (isset($this->frozen[$id])) {
            throw new \RuntimeException(sprintf('Cannot override frozen service "%s".', $id));
        }

        $this->values[$id] = $value;
        $this->keys[$id] = true;
    }

    /**
     * Gets a parameter or an object.
     *
     * @param string $id The unique identifier for the parameter or object
     *
     * @return mixed The value of the parameter or an object
     *
     * @throws \InvalidArgumentException if the identifier is not defined
     */
    public function offsetGet($id)
    {
        if (!isset($this->keys[$id])) {
            throw new \InvalidArgumentException(sprintf('Identifier "%s" is not defined.', $id));
        }

        if (
            isset($this->raw[$id])
            || !is_object($this->values[$id])
            || isset($this->protected[$this->values[$id]])
            || !method_exists($this->values[$id], '__invoke')
        ) {
            return $this->values[$id];
        }

        if (isset($this->factories[$this->values[$id]])) {
            return $this->values[$id]($this);
        }

        $raw = $this->values[$id];
        $val = $this->values[$id] = $raw($this);
        $this->raw[$id] = $raw;

        $this->frozen[$id] = true;

        return $val;
    }

    /**
     * Checks if a parameter or an object is set.
     *
     * @param string $id The unique identifier for the parameter or object
     *
     * @return bool
     */
    public function offsetExists($id)
    {
        return isset($this->keys[$id]);
    }

    /**
     * Unsets a parameter or an object.
     *
     * @param string $id The unique identifier for the parameter or object
     */
    public function offsetUnset($id)
    {
        if (isset($this->keys[$id])) {
            if (is_object($this->values[$id])) {
                unset($this->factories[$this->values[$id]], $this->protected[$this->values[$id]]);
            }

            unset($this->values[$id], $this->frozen[$id], $this->raw[$id], $this->keys[$id]);
        }
    }

    /**
     * Marks a callable as being a factory service.
     *
     * @param callable $callable A service definition to be used as a factory
     *
     * @return callable The passed callable
     *
     * @throws \InvalidArgumentException Service definition has to be a closure of an invokable object
     */
    public function factory($callable)
    {
        if (!method_exists($callable, '__invoke')) {
            throw new \InvalidArgumentException('Service definition is not a Closure or invokable object.');
        }

        $this->factories->attach($callable);

        return $callable;
    }

    /**
     * Protects a callable from being interpreted as a service.
     *
     * This is useful when you want to store a callable as a parameter.
     *
     * @param callable $callable A callable to protect from being evaluated
     *
     * @return callable The passed callable
     *
     * @throws \InvalidArgumentException Service definition has to be a closure of an invokable object
     */
    public function protect($callable)
    {
        if (!method_exists($callable, '__invoke')) {
            throw new \InvalidArgumentException('Callable is not a Closure or invokable object.');
        }

        $this->protected->attach($callable);

        return $callable;
    }

    /**
     * Gets a parameter or the closure defining an object.
     *
     * @param string $id The unique identifier for the parameter or object
     *
     * @return mixed The value of the parameter or the closure defining an object
     *
     * @throws \InvalidArgumentException if the identifier is not defined
     */
    public function raw($id)
    {
        if (!isset($this->keys[$id])) {
            throw new \InvalidArgumentException(sprintf('Identifier "%s" is not defined.', $id));
        }

        if (isset($this->raw[$id])) {
            return $this->raw[$id];
        }

        return $this->values[$id];
    }

    /**
     * Extends an object definition.
     *
     * Useful when you want to extend an existing object definition,
     * without necessarily loading that object.
     *
     * @param string   $id       The unique identifier for the object
     * @param callable $callable A service definition to extend the original
     *
     * @return callable The wrapped callable
     *
     * @throws \InvalidArgumentException if the identifier is not defined or not a service definition
     */
    public function extend($id, $callable)
    {
        if (!isset($this->keys[$id])) {
            throw new \InvalidArgumentException(sprintf('Identifier "%s" is not defined.', $id));
        }

        if (!is_object($this->values[$id]) || !method_exists($this->values[$id], '__invoke')) {
            throw new \InvalidArgumentException(sprintf('Identifier "%s" does not contain an object definition.', $id));
        }

        if (!is_object($callable) || !method_exists($callable, '__invoke')) {
            throw new \InvalidArgumentException('Extension service definition is not a Closure or invokable object.');
        }

        $factory = $this->values[$id];

        $extended = function ($c) use ($callable, $factory) {
            return $callable($factory($c), $c);
        };

        if (isset($this->factories[$factory])) {
            $this->factories->detach($factory);
            $this->factories->attach($extended);
        }

        return $this[$id] = $extended;
    }

    /**
     * Returns all defined value names.
     *
     * @return array An array of value names
     */
    public function keys()
    {
        return array_keys($this->values);
    }

    /**
     * Registers a service provider.
     *
     * @param ServiceProviderInterface $provider A ServiceProviderInterface instance
     * @param array                    $values   An array of values that customizes the provider
     *
     * @return static
     */
    public function register(ServiceProviderInterface $provider, array $values = array())
    {
        $provider->register($this);

        foreach ($values as $key => $value) {
            $this[$key] = $value;
        }

        return $this;
    }
}


/**
 * Pimple service provider interface.
 *
 * @author  Fabien Potencier
 * @author  Dominik Zogg
 */
interface ServiceProviderInterface {

    /**
     * Registers services on the given container.
     *
     * This method should only be used to configure services and parameters.
     * It should not get services.
     *
     * @param Container $pimple A container instance
     */
    public function register(Container $pimple);
}


/**
 * Fiber
 * dependency injector container and manager
 */
class Fiber implements \ArrayAccess
{
    /**
     * @var array Injectors
     */
    protected $injectors = array();

    /**
     * @var array Injectors alias map for functions
     */
    protected $injectorsMap = array();

    /**
     * @var bool Auto use define function
     */
    protected $autoDefine = false;

    /**
     * @param array $injectors
     */
    public function __construct(array $injectors = array())
    {
        $this->injectors = $injectors + $this->injectors;

        // Apply the mapping
        foreach ($this->injectorsMap as $k => $v) {
            $k = is_numeric($k) ? $v : $k;
            $this->injectors[$k] = array($v, 'F$' => 2);
        }
    }

    /**
     * Add injector
     *
     * @param string   $key
     * @param \Closure $value
     */
    public function __set($key, $value)
    {
        // Auto define setter support
        if ($this->autoDefine && method_exists($this, 'set' . $key)) {
            $this->injectors[$key] = $this->{'set' . $key} ($value);
            return;
        }

        $this->injectors[$key] = $value instanceof \Closure ? array($value, 'F$' => 1) : $value;
    }

    /**
     * Get injector
     *
     * @param string $key
     * @return mixed|\Closure
     */
    public function &__get($key)
    {
        if (!isset($this->injectors[$key])) {
            trigger_error('Undefined index "' . $key . '" of ' . get_class($this), E_USER_NOTICE);
            return null;
        }

        // Inject or share
        if (is_array($this->injectors[$key])
            && isset($this->injectors[$key]['F$'])
            && isset($this->injectors[$key][0])
        ) {
            switch ($this->injectors[$key]['F$']) {
                case 2:
                    // Share with mapping
                    $this->injectors[$key] = $this->{$this->injectors[$key][0]}();
                    $tmp = & $this->injectors[$key];
                    break;
                case 1:
                    // Share
                    $this->injectors[$key] = call_user_func($this->injectors[$key][0]);
                    $tmp = & $this->injectors[$key];
                    break;
                case 0:
                    // Factory
                    $tmp = call_user_func($this->injectors[$key][0]);
                    break;
                default:
                    $tmp = null;
            }
        } else {
            $tmp = & $this->injectors[$key];
        }

        // Auto define getter support
        if ($this->autoDefine && method_exists($this, 'get' . $key)) {
            $tmp = $this->{'get' . $key} ($tmp);
        }


        return $tmp;
    }

    /**
     * Exists injector
     *
     * @param string $key
     * @return bool
     */
    public function __isset($key)
    {
        return isset($this->injectors[$key]);
    }

    /**
     * Delete injector
     *
     * @param string $key
     */
    public function __unset($key)
    {
        unset($this->injectors[$key]);
    }

    /**
     * Factory a service
     *
     * @param \Closure|String $key
     * @param \Closure|String $closure
     * @return \Closure
     */
    public function protect($key, \Closure $closure = null)
    {
        if (!$closure) {
            $closure = $key;
            $key = false;
        }

        return $key ? ($this->injectors[$key] = array($closure, 'F$' => 0)) : array($closure, 'F$' => 0);
    }

    /**
     * Inject the function
     *
     * @param string   $key
     * @param callable $closure
     */
    public function inject($key, \Closure $closure)
    {
        $this->injectors[$key] = $closure;
    }

    /**
     * Share service
     *
     * @param \Closure|string $key
     * @param \Closure        $closure
     * @return \Closure
     */
    public function share($key, \Closure $closure = null)
    {
        if (!$closure) {
            $closure = $key;
            $key = false;
        }

        return $key ? ($this->injectors[$key] = array($closure, 'F$' => 1)) : array($closure, 'F$' => 1);
    }

    /**
     * Extend the injector
     *
     * @param string   $key
     * @param \Closure $closure
     * @return \Closure
     */
    public function extend($key, \Closure $closure)
    {
        $factory = isset($this->injectors[$key]) ? $this->injectors[$key] : null;
        $that = $this;
        return $this->injectors[$key] = array(function () use ($closure, $factory, $that) {
            return $closure(isset($factory[0]) && isset($factory['F$']) && $factory[0] instanceof \Closure ? $factory[0]() : $factory, $that);
        }, 'F$' => isset($factory['F$']) ? $factory['F$'] : 0);
    }

    /**
     * Call injector
     *
     * @param string $method
     * @param array  $args
     * @return mixed
     * @throws \BadMethodCallException
     */
    public function __call($method, $args)
    {
        if (!isset($this->injectors[$method]) || !($closure = $this->injectors[$method]) instanceof \Closure) {
            throw new \BadMethodCallException(sprintf('Call to undefined method "%s::%s()', get_called_class(), $method));
        }

        return call_user_func_array($closure, $args);
    }

    /**
     * Get injector
     *
     * @param string $key
     * @param mixed  $value
     * @return bool|mixed
     */
    public function raw($key = null, $value = null)
    {
        if ($value !== null) {
            return $this->injectors[$key] = $value;
        } else if ($key === null) {
            return $this->injectors;
        }

        return isset($this->injectors[$key]) ? $this->injectors[$key] : null;
    }

    /**
     * Get all defined
     *
     * @return array
     */
    public function keys()
    {
        return array_keys($this->injectors);
    }

    /**
     * Has the key?
     *
     * @param string $key
     * @return bool
     */
    public function has($key)
    {
        return array_key_exists($key, $this->injectors);
    }

    /**
     * Append the multiple injectors
     *
     * @param array $injectors
     * @return $this
     */
    public function append(array $injectors)
    {
        $this->injectors = $injectors + $this->injectors;
        return $this;
    }

    /*
     * Implements the interface to support array access
     */

    public function offsetExists($offset)
    {
        return $this->__isset($offset);
    }

    public function &offsetGet($offset)
    {
        return $this->__get($offset);
    }

    public function offsetSet($offset, $value)
    {
        return $this->__set($offset, $value);
    }

    public function offsetUnset($offset)
    {
        return $this->__unset($offset);
    }
}