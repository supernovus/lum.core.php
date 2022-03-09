<?php

namespace Lum\Data;

/**
 * Data Container.
 *
 * Represents a "container" which contains objects.
 * The default implementation expects that each member has the same class.
 * Feel free to override this behaviour in your own classes.
 *
 */
abstract class Container extends Arrayish
{
  protected $data_itemclass;          // Wrap our items in this class.
  protected $data_index = [];         // Index for hash access.
  protected $data_allow_null = false; // Default option for allowing nulls.

  protected $data_id_methods = ['data_identifier'];
  protected $data_id_props   = ['id', '_id'];
  protected $data_id_keys    = ['id', '_id'];

  // Clear our data, including index.
  public function clear ($opts=[])
  {
    $this->data = [];
    $this->data_index = [];
  }

  // Find the position of the child object.
  public function position_of ($item)
  {
    return array_search($item, $this->data, true);
  }

  // Get the item at position x
  public function at_position ($offset)
  {
    return $this->data[$offset] ?? null;
  }

  // Add an item to our index. Override this if you need anything
  // more complex than the rules below.
  protected function add_data_index ($item, $indexname=null)
  {
    // First and foremost, if indexname is specified, it overrides all else.
    if (isset($indexname))
    {
      $this->data_index[$indexname] = $item;
      return true;
    }

    $id = $this->get_data_id($item);
    if (isset($id))
    { // A valid id was found.
      #error_log("Adding item with data id: $id");
      $this->data_index[$id] = $item;
      return true;
    }

    error_log("add_data_index: no valid id could be determined");

    // No id could be determined.
    return false;
  }

  public function get_data_id ($item)
  {
    #error_log("get_data_id()");

    if (is_object($item))
    {
      // Check for methods first.
      foreach ($this->data_id_methods as $name)
      {
        $meth = [$item, $name];
        if (is_callable($meth))
        { // A method exists, call it.
          #error_log("calling \$item->$name()");
          $id = $meth();
          #error_log("id = ".json_encode($id));
          if (is_string($id) || is_numeric($id))
          {
            return $id;
          }
        }
      }

      // Then for public properties.
      foreach ($this->data_id_props as $name)
      {
        try
        {
          #error_log("checking for \$item->$name property");
          $id = $item->$name ?? null;
          #error_log("id = ".json_encode($id));
          if (is_string($id) || is_numeric($id))
          {
            return $id;
          }
        }
        catch (\Throwable $e)
        {
          error_log("get_data_id~exception: ".$e->getMessage());
        }
      }
    }
    elseif (is_array($item))
    {
      foreach ($this->data_id_keys as $name)
      {
        #error_log("checking for \$item[$name] value");
        if (isset($item[$name]))
        {
          $id = $item[$name];
          #error_log("id = ".json_encode($id));
          if (is_string($id) || is_numeric($id))
          {
            return $id;
          }
        }
      }
    }

    error_log("no data id was found");

    // If we reached here, nothing matched.
    return null;
  }

  // Get the index number based on key.
  // Only useful if we're using numbered indexing.
  public function get_data_index ($key)
  {
    $dcount = count($this->data);
    for ($i=0; $i < $dcount; $i++)
    {
      $item = $this->data[$i];
      $id = $this->get_data_id($item);
      if (isset($id) && $id === $key)
      { // We found it.
        return $i;
      }
    }
    return null; // Sorry, we did not find that key.
  }

  public function get_itemclass ()
  {
    if (isset($this->data_itemclass))
    {
      $class = $this->data_itemclass;
      if (class_exists($class))
      {
        return $class;
      }
    }
    return null;
  }

  // Overridden load_[] method.
  // If we have a itemclass, all items in the array will be
  // passed to the itemclass's constructor, with 'parent' set to this
  // object. If a validate method exists in the child class,
  // it will be called to ensure the item is valid.
  // Only valid items will be added to our data.
  public function load_array ($array, $opts=[])
  {
    $opts['parent'] = $this;
    $class = $this->get_itemclass();
    foreach ($array as $item)
    {
      if (isset($class))
      { // Wrap our item in a class.
        $item = new $class($item, $opts);
        if (is_callable([$item, 'validate']))
        {
          if (!$item->validate())
          { // Oops, we didn't pass validation.
            if (is_callable([$this, 'invalid_data']))
            {
              $this->invalid_data($item);
            }
            continue; // Skip invalid items.
          }
        }
      }
      $this->append($item);
    }
  }

  // Overridden to_[] method.
  public function to_array ($opts=[])
  {
    $unwrap = isset($opts['unwrap']) ? $opts['unwrap'] : true;
    if (isset($opts['null']))
    {
      $allownull = $opts['null'];
    }
    else
    {
      $allownull = $this->data_allow_null;
    }
    $array = [];
    foreach ($this->data as $val)
    {
      if ($unwrap && is_object($val) && is_callable([$val, 'to_array']))
      { // Unwrap Data objects.
        $val = $val->to_array($opts);
      }
      if (isset($val) || $allownull)
      { // Add the data.
        $array[] = $val;
      }
    }
    return $array;
  }

  public function append ($item, $indexname=null): void
  {
    $this->data[] = $item;
    $this->add_data_index($item, $indexname);
  }

  public function insert ($item, int $pos=0, $indexname=null): void
  {
    parent::insert($item, $pos);
    $this->add_data_index($item, $indexname);
  }

  public function insertAll (array $items, int $pos=0): void
  {
    parent::insertAll($items, $pos);
    foreach ($items as $item)
    {
      $this->add_data_index($item);
    }
  }

  // We override ArrayAccess to use $this->data_index for its source.

  public function offsetGet ($offset): mixed
  {
    return $this->data_index[$offset] ?? null;
  }

  public function offsetSet ($offset, $value): void
  {
    $index = $this->get_data_index($offset);
    if (isset($index))
    {
      $this->data[$index] = $value;
    }
    $this->data_index[$offset] = $value;
  }

  public function offsetUnset ($offset): void
  {
    $index = $this->get_data_index($offset);
    if (isset($index))
    {
      array_splice($this->data, $index, 1);
    }
    unset($this->data_index[$offset]);
  }

  public function keyExists ($key): bool
  {
    #error_log("Container::keyExists($key)");
    if (!is_string($key) && !is_numeric($key))
    {
      error_log("Invalid offset: ".json_encode($key));
      return false;
    }

    return array_key_exists($key, $this->data_index);
  }

  public function is ($key): bool
  {
    return isset($this->data_index[$key]);
  }

  // Find item matching certain rules.
  // The item must either be an array, or implement ArrayAccess.
  public function find ($query, $single=false, $spawn=true)
  {
    if ($single)
    {
      $found = null;
    }
    elseif ($spawn)
    {
      $found = $this->spawn();
    }
    else
    {
      $found = [];
    }
    // If there is more than 1 query, we need to match all of them.
    $matchAll = count($query) > 1;
    if (!$matchAll)
    { // If we're not using matchAll, extract the query.
      $keys = array_keys($query);
      $key  = $keys[0];
      $val  = $query[$key];
    }
    foreach ($this->data as $item)
    { // Only proceed if we can.
      if (is_array($item) || $item instanceof \ArrayAccess)
      {
        if ($matchAll)
        { // We need to match all queries.
          $matched = true;
          foreach ($query as $key => $val)
          {
            if (!isset($item[$key]) || $item[$key] != $val)
            {
              $matched = false;
              break;
            }
          }
          if ($matched)
          {
            if     ($single)  return $item;
            elseif ($spawn)   $found->append($item);
            else              $found[] = $item;
          }
        } // End if ($matchAll)
        else
        { // We're only matching a single query.
          if ($item[$key] == $val)
          {
            if     ($single)  return $item;
            elseif ($spawn)   $found->append($item);
            else              $found[] = $item;
          }
        }
      } // End test to see if we are a valid array/object.
    } // End data loop.
    return $found;
  } // End of find().

  /**
   * Create a new child item, and return it.
   *
   * @param  mixed  $data   Data to build object from (optional.)
   * @param  array  $opts   Options to build object with (optional.)
   * @return mixed          Either a child Item, or null on failure.
   */
  public function newItem ($data=null, $opts=[])
  {
    $opts['parent'] = $this;
    $class = $this->get_itemclass();
    if (isset($class))
    {
      $child = new $class($data, $opts);
      return $child;
    }
  }

  /**
   * Create a new child item, and add it to our data.
   *
   * @param  integer  $pos     Position to insert at. -1 = End, 0 = Beginning.
   *                           Optional, defaults to -1 if not specified.
   * @param  mixed    $data    Optional -- passed to newItem().
   * @param  array    $opts    Optional -- passed to newItem().
   */
  public function addItem ($pos=null, $data=null, $opts=[])
  {
    $child = $this->newItem($data, $opts);
    if (isset($child))
    {
      if (isset($pos) && $pos != -1)
      {
        $this->insert($child, $pos);
      }
      else
      {
        $this->append($child);
      }
    }
    return $child;
  }

}


