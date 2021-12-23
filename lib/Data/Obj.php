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
abstract class Obj extends O implements \JsonSerializable
{
  use \Lum\Meta\ClassInfo, JSON, OutputXML, InputXML, BuildXML, DetectType;

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

