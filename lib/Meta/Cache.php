<?php

namespace Lum\Meta;

trait Cache
{
  protected $_lum_cache = [];

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

  protected function load_cache($key)
  {
    $core = \Lum\Core::getInstance();
    if (isset($core->sess, $core->sess[$key]))
    {
      $this->_lum_cache = $core->sess[$key];
    }
  }

  protected function save_cache($key)
  {
    $core = \Lum\Core::getInstance();
    if (isset($core->sess, $this->_lum_cache))
    {
      $core->sess[$key] = $this->_lum_cache;
    }
  }

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

}