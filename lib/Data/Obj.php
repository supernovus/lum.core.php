<?php

namespace Lum\Data;

use \Lum\Exception;

/** 
 * Data Object -- Base class for all Lum\Data classes.
 *
 * These are "magic" objects which are meant for converting data between
 * different formats easily, with PHP arrays, JSON and XML as the default
 * targets.
 *
 * The load() method, which can be used in the constructor, will determine
 * the data type either by a 'type' parameter passed to it, or by calling
 * the detect_data_type() method (a default version is supplied, feel free
 * to override it, or create chains using parent:: calls.)
 * when the type has been determined, the data will be passed to a method
 * called load_$type() which will be used to load the data.
 *
 * It is expected that custom methods to perform operations on the data
 * will be added, as well as operations to return the data in specific
 * formats (typically the same ones that you accept in the load() statement.)
 *
 * The default version can load PHP arrays, plus JSON and YAML strings.
 * It also has to_array(), to_json() and to_yaml() methods to return in 
 * those formats. The JSON and YAML methods wrap around the array ones,
 * so overriding the array methods is all you really need to do.
 * The default versions perform no transformations, but simply set our
 * data to the PHP array result.
 *
 * This will also detect SimpleXML and DOM objects, and XML strings.
 * In order to load any of the above objects, you need to implement
 * the load_simple_xml() method (XML strings and DOM objects will be
 * converted to SimpleXMLElement objects and passed through.)
 *
 * In order to use the to_dom_document(), to_dom_element() or to_xml() methods
 * you must implement a to_simple_xml() method first (again for simplicity
 * we call to_simple_xml() then convert the object it return to the desired
 * format.)
 *
 * Add extra formats as desired, chaining the detect_data_type() and 
 * detect_string_type() methods is easy, so go crazy!
 *
 */
abstract class Obj implements \JsonSerializable
{
  use \Lum\Meta\ClassInfo, JSON, OutputXML, InputXML, BuildXML, DetectType;

  protected $parent;             // Will be set if we have a parent object.
  protected $data       = [];    // The actual data we represent.
  protected $newconst   = False; // If true, we enable the new constructor.
  protected $constprops = [];    // Constructor property items.
  protected $save_opts  = False; // Do we want to save our constructor opts?
  protected $data_opts;          // The saved opts, if the above is true.

  /**
   * The public constructor. The default version simply forwards to the
   * internal method, see below.
   */
  public function __construct ($mixed=Null, $opts=Null)
  {
    $this->__construct_data($mixed, $opts);
  }

  /**
   * Internal constructor method.
   *
   * Supports two forms, the original form has the data as the first
   * parameter, and a set of options as the second.
   *
   * The second form sends the options as the first parameter,
   * with a named 'data' option to specify the data. In this case,
   * the second parameter should be left off.
   */
  protected function __construct_data ($mixed=Null, $opts=Null)
  {
    if (is_null($opts))
    {
      if (is_array($mixed) && $this->newconst)
      {
        $opts  = $mixed;
        $mixed = isset($opts['data']) ? $opts['data'] : Null;
      }
      else
      {
        $opts = [];
      }
    }

    // Set the parent if it's defined.
    if (isset($opts['parent']))
    {
      $this->parent = $opts['parent'];
    }

    // Find any properties that we need to initialize.
    $props   = $this->constprops;
    $props[] = '__classid'; // Explicitly add __classid.
    foreach ($props as $popt => $pname)
    {
      // Positional entries have the same property name and option name.
      if (is_numeric($popt))
      {
        $popt = $pname;
      }

      if (property_exists($this, $pname) && isset($opts[$popt]))
      {
        $this->$pname = $opts[$popt];
      }
    }

    if (method_exists($this, 'data_init'))
    { // The data_init can set up pre-requisites to loading our data.
      // It CANNOT reference our data, as that has not been loaded yet.
      $this->data_init($opts);
    }

    // If we want the options saved for later, do it now.
    if ($this->save_opts)
    {
      $this->data_opts = $opts;
    }

    // How we proceed depends on if we have initial data.
    if (isset($mixed))
    { // Load the passed data.
      $loadopts = ['clear'=>False, 'prep'=>True, 'post'=>True];
      if (isset($opts['type']))
      {
        $loadopts['type'] = $opts['type'];
      }
      $this->load($mixed, $loadopts);
    }
    elseif (is_callable([$this, 'data_defaults']))
    { 
      if (!isset($opts['nodefaults']) || !$opts['nodefaults'])
      {
        // Set our default values.
        $this->data_defaults($opts);
      }
    }
  }

  // Return the parent object.
  public function parent ()
  {
    return $this->parent;
  }

  // Returns the converted data structure.
  public function load_data ($data, $opts=[])
  {
    $return = Null;
    // If we set the 'prep' option, send the data to data_prep()
    // for initial preparations which will return the prepared data.
    if (isset($opts['prep']) && $opts['prep'] 
      && method_exists($this, 'data_prep'))
    {
      $data = $this->data_prep($data, $opts);
    }
    // Figure out the data type.
    $type = Null;
    if (isset($opts['type']))
    {
      $type = $opts['type'];
    }
    else 
    {
      $type = $this->detect_data_type($data);
    }
    // Handle the data type.
    if (isset($type))
    {
      $method = "load_$type";
      if (method_exists($this, $method))
      {
#        error_log("Sending '$data' to '$method'");
        // If this method returns False, something went wrong.
        // If it returns an array or object, that becomes our data.
        // If it returns Null or True, we assume the method set the data.
        $return = $this->$method($data, $opts);
#        error_log("Retreived: ".json_encode($return));
        if ($return === False)
        {
          throw new Exception("Could not load data.");
        }
      }
      else
      {
        throw new Exception("Could not handle data type.");
      }
    }
    else
    {
      throw new Exception("Unsupported data type.");
    }
    return $return;
  }

  // Set our data to the desired structure.
  public function load ($data, $opts=[])
  { // If we set the 'clear' option, clear our any existing data.
    if (isset($opts['clear']) && $opts['clear'])
    {
      $this->clear();
    }
    $return = $this->load_data($data, $opts);
    if (isset($return) && $return !== True)
    {
      $this->data = $return;
    }
    // If we have set the 'post' option, call data_post().
    if (isset($opts['post']) && $opts['post'] 
      && method_exists($this, 'data_post'))
    {
      $this->data_post($opts);
    }
  }

  // Clear our data.
  public function clear ($opts=[])
  {
    $this->data = [];
  }

  // Spawn a new empty data object.
  public function spawn ($opts=[])
  {
    $copy = clone $this;
    $copy->clear();
    return $copy;
  }

  // This is very (VERY) cheap. Override as needed.
  public function load_array ($array, $opts=Null)
  {
    return $array;
  }

  // Again, pretty cheap, but works well.
  public function load_json ($json, $opts=Null)
  {
    $array = json_decode($json, True);
    return $this->load_array($array, $opts);
  }

  // Just as cheap, different format.
  public function load_yaml($yaml, $opts=Null)
  {
    $array = yaml_parse($yaml);
    return $this->load_array($array, $opts);
  }

  // Output as an array. Just as cheap as load_array().
  public function to_array ($opts=Null)
  {
    return $this->data;
  }


  // And again, the same as above, but with YAML.
  public function to_yaml ($opts=Null)
  {
    return yaml_emit($this->to_array($opts));
  }

  /** 
   * The XML-related methods from the traits require that you implement the
   * load_simple_xml() and to_simple_xml() methods. We provide default
   * versions that don't do anything.
   */

  // Load a SimpleXML object.
  public function load_simple_xml ($simplexml, $opts=Null)
  {
    throw new Exception("No load_simple_xml() method defined.");
  }

  // Output as a SimpleXML object.
  public function to_simple_xml ($opts=Null)
  {
    throw new Exception("No to_simple_xml() method defined.");
  }

}

