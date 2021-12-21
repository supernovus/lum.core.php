<?php

namespace Lum\Meta;

trait SetProps
{
  /**
   * Based on a passed value, return a callable that can be used to test 
   * if other values are of the same type.
   *
   * @param mixed $val  The initial/default value to pass to tests.
   *
   * @param callable|null $default [null] The default value to return.
   *
   * @param array|string $tests [...] The list of tests to use.
   *
   *   A list of test functions allowed to be used, in the order they
   *   should be called in. 
   *
   *   May be an array (where each item is either the a callable test), 
   *   or a single string which will be split on any non-word characters.
   *
   *   Generally if you want to use tests other than PHP built-in test
   *   functions, then you will need to use the array format not the string.
   *
   *   A few examples of useful custom tests might include:
   *
   *     [$object, 'myMethod']         // An object method.
   *     ['\My\Class', 'staticMethod'] // A static class method.
   *     '\Some\Class::methodName'     // Alternative static method syntax.
   *     fn($o) => $o instanceof Foo;  // An instanceof test.
   *
   *   The default list of tests if left unspecified:
   *
   *     is_int, is_float, is_bool, is_numeric, is_callable,
   *     is_string, is_array, is_object;
   *
   * @param bool $allowNull [false] Allow null values to be tested.
   *
   *   If this is true we can use the 'is_null' test, or test for null
   *   values in custom callable tests. This is generally NOT recommended.
   *
   *   If this is false (the default value) then we'll immediately return
   *   the $default if the $val is null without calling any further tests.
   *
   * @return callable|null  The test that should be used.
   * 
   *   If one of the tests passed, that test will be returned.
   *   If none of the tests passed, the default will be returned.
   *
   */
  public static function get_prop_test($val, 
    $default=null, 
    $tests=null,
    $allowNull=false)
  {
    if (is_string($tests))
    { // Split a string into an array of words, each assumed to be a test.
      $tests = preg_split('/\W+/', trim($tests));
    }
    elseif (!is_array($tests))
    { // Use our default set of built-in tests.
      $tests = 
      [
        'is_int', 'is_float', 'is_bool', 'is_numeric', 'is_callable',
        'is_string', 'is_array', 'is_object',
      ];
    }

    if (!$allowNull && is_null($val))
    { // Value was null, and allowNull was not true.
      return $default;
    }

    foreach ($tests as $test)
    {
      if (is_callable($test))
      { // It's callable, let's test the value.
        if (call_user_func($test, $val))
        { // The test returned true, pass it along.
          return $test;
        }
      }
      else
      { // A non-callable passed? That's not valid.
        error_log("invalid test passed to get_prop_test");
      }
    }

    // If we reached here, no tests passed.
    return $default;
  }

  protected function set_props(array $opts, $testing=true, $props=null)
  {
    $test = $testdef = $tests = null;
    $oneTest = false;

    if (is_callable($testing))
    { // One specific test for all properties.
      $test = $testing;
      $oneTest = true;
    }
    elseif (is_array($testing))
    { // Get test from get_prop_test() with specific options.
      if (isset($testing['default']))
      {
        $testdef = $testing['default'];
      }
      elseif (isset($testing[0]))
      {
        $testdef = $testing[0];
      }

      if (isset($testing['tests']))
      {
        $tests = $testing['tests'];
      }
      elseif (isset($testing[1]))
      {
        $tests = $testing[1];
      }
    }

    if (is_array($props))
    { // A list of properties was specified. We need to double check it.
      $ensureProp = true;
    }
    else
    { // Get a list of properties in the object itself.
      $ensureProp = false;
      if (is_string($props))
      { // Assume it's a regular expression we'll match properties with.
        $props = preg_grep($props, get_object_vars($this));
      }
      else
      { // Just get all our currently defined properties.
        $props = get_object_vars($this);
      }
    }

    error_log("set_props: props = ".json_encode($props));

    foreach ($props as $name => $defval)
    {
      if ($ensureProp)
      { 
        if (is_numeric($name) && is_string($defval))
        { // Flat array item.
          if (!property_exists($this, $defval))
          { // Nope, doesn't exist.
            continue;
          }
          $name = $defval;
          $defval = $this->$name;
        }
        elseif (!property_exists($this, $name))
        { // No such property.
          continue;
        }
      }

      if ($testing && !$oneTest)
      { // Per-property testing is enabled. Get the test.
        $test = static::get_prop_test($defval, $testdef, $tests);
      }

      if (isset($opts[$name]) && 
        (!is_callable($test) || call_user_func($test, $opts[$name]) ))
      { // An option was set, and passed the type test.
        $this->$name = $opts[$name];
      }
    }

  } // set_props()

}