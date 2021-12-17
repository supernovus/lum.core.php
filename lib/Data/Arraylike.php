<?php

namespace Lum\Data;

/**
 * A trait providing all the methods needed for a data object to act more
 * like a PHP array.
 *
 * You'll still need a `implements \Iterator, \ArrayAccess, \Countable` 
 * statement in your class to actually have the functionality work.
 *
 * It currently expects an internal array property called $data will contain 
 * the actual stored data, but a future version may change that requirement.
 */
trait Arraylike
{
  /**
   * Add an item to our data.
   */
  public function append ($item)
  {
    $this->data[] = $item;
  }

  public function insert ($item, $pos=0)
  {
    if ($pos)
    {
      if (!is_array($item))
      { // Wrap singletons into an array.
        $item = array($item);
      }
      array_splice($this->data, $pos, 0, $item);
    }
    else
    {
      array_unshift($this->data, $item);
    }
  }

  public function swap ($pos1, $pos2)
  {
    $new1 = $this->data[$pos2];
    $this->data[$pos2] = $this->data[$pos1];
    $this->data[$pos1] = $new1;
  }

  // Iterator interface.
  public function current (): mixed
  {
    return current($this->data);
  }

  public function key (): mixed
  {
    return key($this->data);
  }

  public function next (): void
  {
    next($this->data);
  }

  public function rewind (): void
  {
    reset($this->data);
  }

  public function valid (): bool
  {
    return key($this->data) !== NULL;
  }

  // ArrayAccess Interface.
  public function offsetExists ($offset): bool
  {
    return array_key_exists($offset, $this->data);
  }

  public function offsetGet ($offset): mixed
  {
    if (isset($this->data[$offset]))
      return $this->data[$offset];
  }

  public function offsetSet ($offset, $value): void
  {
    $this->data[$offset] = $value;
  }

  public function offsetUnset ($offset): void
  {
    unset($this->data[$offset]);
  }

  // Countable interface.
  public function count (): int
  {
    return count($this->data);
  }

  // Finally, the is() method is separate from offsetExists.
  public function is ($key)
  {
    return isset($this->data[$key]);
  }

  // Property interface, maps to the ArrayAccess interface.
  public function __get ($name)
  {
    return $this->offsetGet($name);
  }

  public function __isset ($name)
  {
    return $this->offsetExists($name);
  }

  public function __unset ($name)
  {
    $this->offsetUnset($name);
  }

  public function __set ($name, $value)
  {
    $this->offsetSet($name, $value);
  }

  // A quick helper. Override as needed.
  public function array_keys ()
  {
    return array_keys($this->data);
  }

}