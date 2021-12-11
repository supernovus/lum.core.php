<?php

namespace Lum\Meta;

trait Cache
{
  protected $_lum_cache = [];

  /**
   * Get or set a value from the current cache (if one is loaded).
   */
  public function cache ($key, $value=null)
  {
    if (isset($this->_lum_cache))
    {
      if (isset($value))
      { // Caching a value.
        $cache = &$this->_lum_cache;
        if (is_scalar($key))
        {
          $cache[$key] = $value;
        }
        elseif (is_array($key))
        {
          $lastkey = array_pop($key);
          foreach ($key as $k)
          {
            if (!isset($cache[$k]))
            {
              $cache[$k] = [];
            }
            $cache = &$cache[$k];
          }
          $cache[$lastkey] = $value;
        }
      }
      else
      { // Retreive a cached value.
        $cache = $this->_lum_cache;
        if (is_scalar($key) && isset($cache[$key]))
        {
          return $cache[$key];
        }
        elseif (is_array($key))
        {
          foreach ($key as $k)
          {
            if (isset($cache[$k]))
            {
              $cache = $cache[$k];
            }
            else
            { // Nothing to return.
              return;
            }
          }
          return $cache;
        }
      }
    }
  }

  /**
   * Get the store we are using to load/save caches from.
   */
  protected function get_cache_store()
  {
    if (property_exists($this, '_lum_cache_store')
      && isset($this->_lum_cache_store) 
      && is_object($this->_lum_cache_store) 
      && ($this->_lum_cache_store instanceof \ArrayAccess) )
    { // A cache store object has been defined.
      return $this->_lum_cache_store;
    }

    $core = \Lum\Core::getInstance();
    if (isset($core->sess))
    { // We're going to use a session cache.
      return $core->sess;
    }
  }

  /**
   * Load a cache.
   */
  protected function load_cache($key)
  {
    $store = $this->get_cache_store();
    if (isset($store, $store[$key]))
    { // The key exists, lets make sure it's a valid cache.
      $cache = $store[$key];
      if (is_array($cache) 
        || (is_object($cache) && $cache instanceof \ArrayAccess))
      {
        $this->_lum_cache = $store[$key];
      }
      else
      {
        throw new \Exception("load_cache: '$key' was not a valid cache: "
          . json_encode($cache));
      }
    }
  }

  /**
   * Save a cache.
   */
  protected function save_cache($key)
  {
    $store = $this->get_cache_store();
    if (isset($store, $this->_lum_cache))
    {
      $store[$key] = $this->_lum_cache;

      if (is_callable([$store, 'save'])
      {
        $store->save();
      }
    }
  }

  /**
   * Clear the current cache (does not affect the saved cache.)
   */
  protected function clear_cache($disable=false)
  {
    if ($disable)
    { // Unset the property entirely.
      unset($this->_lum_cache);
    }
    else
    { // Just set it to an empty array.
      $this->_lum_cache = [];
    }
  }

  /**
   * Delete a saved cache (does not affect the current cache.)
   */
  protected function delete_cache($key, $force=false)
  {
    $store = $this->get_cache_store();
    if (isset($store) && ($force || isset($store[$key])))
    { // Delete the cache from the store.
      unset($store[$key]);

      if (is_callable([$store, 'save'])
      { 
        $store->save();
      }
    }
  }


}
