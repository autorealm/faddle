<?php namespace Faddle\Common;

use ReflectionClass;
use ReflectionProperty;

/**
 * Class Reflection
 * Reflection class is used to get reflection class variables
 * and make accessible for callee
 * @package Cygnite
 */
class Reflection
{
    public $reflectionClass;

    //properties
    private $properties;

    public $reflectionProperty;

    protected $methodScopes = [
        'public' => [],
        'protected' => [],
        'private' => []
    ];

    /**
     * Get instance of your class using Reflection api
     *
     * @access public
     * @param  $class
     * @throws \Exception
     * @return object
     */
    public static function getInstance($class= null)
    {
        $reflector = null;

        if (class_exists($class)) {
            throw new \Exception(sprintf("Class %s not found", $class));
        }

        $reflector = new ReflectionClass('\\'.$class);

        return new $reflector->name;
    }

    /**
     * Set your class to reflection api
     *
     * @access public
     * @param  $class
     * @return $this
     */
    public function setClass($class)
    {
        if (is_object($class)) {
            $class = get_class($class);
        }

        $this->reflectionClass = new ReflectionClass($class);

        return $this;
    }

    /**
     * @return null
     */
    public function getReflectionClass()
    {
        return (isset($this->reflectionClass) ? $this->reflectionClass : null);
    }

    /**
     * Make your protected or private property accessible
     *
     * @access public
     * @param  $property
     * @return string/ int property value
     *
     */
    public function makePropertyAccessible($property)
    {
        $reflectionProperty = $this->getReflectionClass()->getProperty($property);
        $this->setReflectionProperty($reflectionProperty);
        $reflectionProperty->setAccessible(true);

        return $reflectionProperty->getValue($this->getReflectionClass());
    }

    /**
     * @param $property
     */
    public function setReflectionProperty($property)
    {
        $this->reflectionProperty = $property;
    }

    /**
     * @return null
     */
    public function getReflectionProperty()
    {
        return (isset($this->reflectionProperty) ? $this->reflectionProperty : null);
    }

    /**
     * Get All methods of the reflection class, you
     * are allowed to filter out methods you required.
     *
     * @link http://stackoverflow.com/questions/12825187/get-all-public-methods-declared-in-the-class-not-inherited
     * @param        $class
     * @param bool   $inherit
     * @param null   $static
     * @param string $scope
     * @return array
     */
    public function getMethods($scope = null, $inherit = false, $static = null)
    {
        $return = $this->methodScopes;

        foreach (array_keys($return) as $key) {

            $validScope = false;
            $validScope = $this->getScopeType($key);

            if ($validScope) {
                $methods = $this->reflectionClass->getMethods($validScope);

                $return = $this->getClassMethods($methods, $inherit, $key, $static, $return);
            }
        }

        return (is_null($scope)) ? $return : $return[$scope];
    }

    /**
     * @param string $scope
     * @return mixed
     */
    public function getScopeType($scope = 'public')
    {
        switch ($scope) {
            case 'public':
                $type = \ReflectionMethod::IS_PUBLIC;
                break;
            case 'protected':
                $type = \ReflectionMethod::IS_PROTECTED;
                break;
            case 'private':
                $type = \ReflectionMethod::IS_PRIVATE;
                break;
        }

        return $type;
    }

    public function getClassMethods(&$methods, &$inherit, &$key, &$static, &$return)
    {
        foreach ($methods as $method) {

            $isStatic = $method->isStatic();

            if (!is_null($static) && $static && !$isStatic) {
                continue;
            } elseif (!is_null($static) && !$static && $isStatic) {
                continue;
            }

            if (!$inherit && $method->class === $this->reflectionClass->getName()) {
                $return[$key][] = $method->name;
            } elseif ($inherit) {
                $return[$key][] = $method->name;
            }
        }

        return $return;
    }

    const ACCESSOR_PREFIXES = ['get', 'is', 'has', 'can'];
    const MUTATOR_PREFIXES = ['set', 'add', 'remove'];
    /**
     * Gets the property name associated with an accessor method.
     *
     * @param string $methodName
     *
     * @return string|null
     */
    public function getProperty($methodName)
    {
        $pattern = implode('|', array_merge(self::ACCESSOR_PREFIXES, self::MUTATOR_PREFIXES));
        if (preg_match('/^('.$pattern.')(.+)$/i', $methodName, $matches)) {
            return $matches[2];
        }
    }
    /**
     * Gets the {@see \ReflectionProperty} from the class or its parent.
     *
     * @param \ReflectionClass $reflectionClass
     * @param string           $attributeName
     *
     * @return \ReflectionProperty
     */
    private function _getReflectionProperty(\ReflectionClass $reflectionClass, $attributeName)
    {
        if ($reflectionClass->hasProperty($attributeName)) {
            return $reflectionClass->getProperty($attributeName);
        }
        if ($parent = $reflectionClass->getParentClass()) {
            return $this->getReflectionProperty($parent, $attributeName);
        }
    }

}
