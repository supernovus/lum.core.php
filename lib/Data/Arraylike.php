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
   * Add an item to our data, at the end of the array.
   *
   * @param mixed $item  The item to add.
   */
  public function append ($item): void
  {
    $this->data[] = $item;
  }

  /**
   * Splice items into the data.
   *
   * @param int $pos  The starting offset position to place items.
   * @param int $len  See PHP `array_splice()` for how this works.
   * @param mixed ...$items  Anything other parameters are items to add.
   *
   * @return array  If items were removed, they'll be in here.
   */
  public function splice (int $pos, ?int $len=null, ...$items): array
  {
    return array_splice($this->data, $pos, $len, $items);
  }

  /**
   * Insert a single item into the data.
   *
   * @param mixed $item  The item to insert.
   * @param int $pos     Offset to put item (optional, default `0`)
   *
   *   If the offset is `0` this will use direct array manipulation.
   *   If it's anything other than `0`, it'll defer to the `splice` method.
   *
   * @return void
   */
  public function insert ($item, int $pos=0): void
  {
    if ($pos)
    { // If pos is anything but zero, we use splice() to insert the item(s).
      $this->splice($pos, 0, $item);
    }
    else
    { // This is a quick shortcut that's faster than the above.
      array_unshift($this->data, $item);
    }
  }

  /**
   * Insert multiple items into the data.
   *
   * @param array $items  The items to insert.
   * @param int $pos      Offset to put item (optional, default `0`)
   *
   *   Same logic as `insert` applies here as well.
   *
   */
  public function insertAll (array $items, int $pos=0): void
  {
    if ($pos)
    { 
      $this->splice($pos, 0, ...$items);
    }
    else
    { 
      array_unshift($this->data, ...$items);
    }
  }

  /**
   * Swap two items by their position/key.
   *
   * @param int|string $pos1  The position or key of the first item.
   * @param int|string $pos2  The position or key of the second item.
   */
  public function swap (int|string $pos1, int|string $pos2)
  {
    $new1 = $this->data[$pos2];
    $this->data[$pos2] = $this->data[$pos1];
    $this->data[$pos1] = $new1;
  }

  /**
   * See if a key in the data exists and is non-null.
   */
  public function is ($key): bool
  {
    return isset($this->data[$key]);
  }

  /**
   * See if a key in the data exists, even if the value is null.
   */
  public function keyExists ($key): bool
  {
    return array_key_exists($key, $this->data);
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

  /** @see \Lum\Data\Arraylike::keyExists() */ 
  public function offsetExists ($offset): bool
  { 
    return $this->keyExists($offset);
  }

  /** Get an item with a specific position or key. */
  public function offsetGet ($offset): mixed
  {
    return ($this->data[$offset] ?? null);
  }

  /** Set an item using a specific position or key. */
  public function offsetSet ($offset, $value): void
  {
    $this->data[$offset] = $value;
  }

  /** Remove an item from a specific position or key. */
  public function offsetUnset ($offset): void
  {
    unset($this->data[$offset]);
  }

  // Countable interface.

  /** The number of items in our data. */
  public function count (): int
  {
    return count($this->data);
  }

  // Property interface.

  /** @see \Lum\Data\Arraylike::offsetGet() */
  public function __get ($name)
  {
    return $this->offsetGet($name);
  }

  /** @see \Lum\Data\Arraylike::offsetExists() */
  public function __isset ($name)
  {
    return $this->offsetExists($name);
  }

  /** @see \Lum\Data\Arraylike::offsetUnset() */
  public function __unset ($name)
  {
    $this->offsetUnset($name);
  }

  /** @see \Lum\Data\Arraylike::offsetSet() */
  public function __set ($name, $value)
  {
    $this->offsetSet($name, $value);
  }

  /** Get a full list of keys/indexes in our data. */
  public function array_keys ()
  {
    return array_keys($this->data);
  }

}