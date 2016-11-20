<?php namespace Faddle\Support;

/**
 * The Collection trait allows you to access a set of data
 * using both array and object notation.
 */
class Collection implements \ArrayAccess, \Iterator, \Countable {

    /**
     * Collection data.
     *
     * @var array
     */
    private $data = array();

    /**
     * Gets an item.
     *
     * @param string $key Key
     * @return mixed Value
     */
    public function __get($key) {
        return isset($this->data[$key]) ? $this->data[$key] : null;
    }

    /**
     * Set an item.
     *
     * @param string $key Key
     * @param mixed $value Value
     */
    public function __set($key, $value) {
        $this->data[$key] = $value;
    }

    /**
     * Checks if an item exists.
     *
     * @param string $key Key
     * @return bool Item status
     */
    public function __isset($key) {
        return isset($this->data[$key]);
    }

    /**
     * Removes an item.
     *
     * @param string $key Key
     */
    public function __unset($key) {
        unset($this->data[$key]);
    }

    /**
     * Gets an item at the offset.
     *
     * @param string $offset Offset
     * @return mixed Value
     */
    public function offsetGet($offset) {
        return isset($this->data[$offset]) ? $this->data[$offset] : null;
    }

    /**
     * Sets an item at the offset.
     *
     * @param string $offset Offset
     * @param mixed $value Value
     */
    public function offsetSet($offset, $value) {
        if (is_null($offset)) {
            $this->data[] = $value;
        }
        else {
            $this->data[$offset] = $value;
        }
    }

    /**
     * Checks if an item exists at the offset.
     *
     * @param string $offset Offset
     * @return bool Item status
     */
    public function offsetExists($offset) {
        return isset($this->data[$offset]);
    }

    /**
     * Removes an item at the offset.
     *
     * @param string $offset Offset
     */
    public function offsetUnset($offset) {
        unset($this->data[$offset]);
    }

    /**
     * Resets the collection.
     */
    public function rewind() {
        reset($this->data);
    }
 
    /**
     * Gets current collection item.
     *
     * @return mixed Value
     */ 
    public function current() {
        return current($this->data);
    }
 
    /**
     * Gets current collection key.
     *
     * @return mixed Value
     */ 
    public function key() {
        return key($this->data);
    }
 
    /**
     * Gets the next collection value.
     *
     * @return mixed Value
     */ 
    public function next() 
    {
        return next($this->data);
    }
 
    /**
     * Checks if the current collection key is valid.
     *
     * @return bool Key status
     */ 
    public function valid()
    {
        $key = key($this->data);
        return ($key !== NULL && $key !== FALSE);
    }

    /**
     * Gets the size of the collection.
     *
     * @return int Collection size
     */
    public function count() {
        return sizeof($this->data);
    }

    /**
     * Get an iterator for this object.
     * 
     * @return \ArrayIterator
     */
    public function getIterator()
    {
        return new \ArrayIterator($this->data);
    }

    /**
     * Return CachingIterator instance.
     *
     * @param  int  $flags
     * @return \CachingIterator
     */
    public function getCachingIterator($flags = \CachingIterator::CALL_TOSTRING)
    {
        return new \CachingIterator($this->getIterator(), $flags);
    }

    /**
     * Gets the item keys.
     *
     * @return array Collection keys
     */
    public function keys() {
        return array_keys($this->data);
    }

    /**
     * Gets the collection data.
     *
     * @return array Collection data
     */
    public function getData() {
        return $this->data;
    }

    /**
     * Sets the collection data.
     *
     * @param array $data New collection data
     */
    public function setData(array $data) {
        $this->data = $data;
    }

    /**
     * Removes all items from the collection.
     */
    public function clear() {
        $this->data = array();
    }
    /**
     * @return mixed
     */
    public function first()
    {
        return reset($this->data);
    }

    /**
     * @return mixed
     */
    public function last()
    {
        return end($this->data);
    }

    /**
     * @param mixed $key
     *
     * @return mixed|null
     */
    public function remove($key)
    {
        if (! $this->containsKey($key)) {
            return null;
        }
        $removed = $this->data[$key];
        unset($this->data[$key]);
        return $removed;
    }

    function contains($value)
    {
        return in_array($value, $this->data);
    }

    /**
     * @param mixed $key
     *
     * @return bool
     */
    public function containsKey($key)
    {
        return isset($this->data[$key]) || array_key_exists($key, $this->data);
    }

    /**
     * Check key exists in the Collection object
     *
     * @param $key
     * @return bool
     */
    public function has($key)
    {
        return $this->offsetExists($key);
    }

    function join($string)
    {
        return new String(implode($string, $this->data));
    }

    public function singleton($key, $value) {
        $this->set($key, function ($c) use ($value) {
            static $object;
            if (null === $object) {
                $object = $value($c);
            }
            return $object;
        });
    }

    /**
     * Returns a subset of the array
     * @param int      $offset
     * @param int|null $length
     *
     * @return array
     */
    public function slice($offset, $length = null)
    {
        return array_slice($this->data, $offset, $length, true);
    }

    /**
     * Remove a subset of the array
     *
     * The removed part will be returned
     *
     * @param int      $offset
     * @param int|null $length
     * @param array    $replacement
     *
     * @return array The removed subset
     */
    public function splice($offset, $length = null, $replacement = array())
    {
        if ($length === null) {
            $length = count($this->data);
        }
        return array_splice($this->data, $offset, $length, $replacement);
    }

    /**
     * Apply callback over each data element
     *
     * @param \Closure $callback
     * @return $this
     */
    public function each(\Closure $callback)
    {
        array_map($callback, $this->data);

        return $this;
    }

    /**
     * Map array elements and return as Collection object
     *
     * @param  \Closure $callback
     * @return static
     */
    public function map(\Closure $callback)
    {
        $keys = array_keys($this->data);
        $values = array_map($callback, $this->data, $keys);
        $this->data = (array_combine($keys, $values));
        return $this;
    }

}