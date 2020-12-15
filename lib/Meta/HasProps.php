<?php

namespace Lum\Meta;

trait HasProps
{
  /**
   * If a property exists, return it's value, otherwise return a default.
   *
   * @param string $property  The property we are looking for.
   * @param mixed $default  (Optional, default null) The default value.
   * @return mixed
   */
  public function get_prop ($property, $default=Null)
  {
    if (property_exists($this, $property))
      return $this->$property;
    else
      return $default;
  }

  /**
   * Set a property if it exists.
   *
   * @param string $property  The property we want to set if it exists.
   * @param mixed $value  The value we want to set the property to.
   * @return bool  Did the property exist?
   */
  public function set_prop ($property, $value, $exception=null)
  {
    if (property_exists($this, $property))
    {
      $this->$property = $value;
      return true;
    }
    return false;
  }

}
