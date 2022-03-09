<?php

namespace Lum\Data;

trait JSON
{
  /**
   * A method that must be defined to return a serialization-safe array.
   */
  abstract public function to_array($opts=[]);

  /**
   * Convert to a JSON string, optionally with fancy formatting.
   */
  public function to_json ($opts=[])
  {
    if (is_bool($opts))
    { // A boolean opts is assumed to be the 'fancy' option.
      $opts = ['fancy'=>$opts];
    }

    $flags = isset($opts['jsonFlags']) ? $opts['jsonFlags'] : 0;

    $optmap =
    [ // Mapping option names to their corresponding flags.
      'hexTag'       => JSON_HEX_TAG,
      'hexAmp'       => JSON_HEX_AMP,
      'hexApos'      => JSON_HEX_APOS,
      'hexQuot'      => JSON_HEX_QUOT,
      'forceObject'  => JSON_FORCE_OBJECT,
      'numeric'      => JSON_NUMERIC_CHECK,
      'pretty'       => JSON_PRETTY_PRINT,
      'slashes'      => JSON_UNESCAPED_SLASHES,
      'unicode'      => JSON_UNESCAPED_UNICODE,
      'partial'      => JSON_PARTIAL_OUTPUT_ON_ERROR,
      'float'        => JSON_PRESERVE_ZERO_FRACTION,
      // Some common compound options.
      'fancy'   => JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES,
      'clean'   => JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
      'numbers' => JSON_NUMERIC_CHECK | JSON_PRESERVE_ZERO_FRACTION,
      'xml'     => JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT,
    ];

    foreach ($opts as $optname => $optvalue)
    {
      if (isset($optmap[$optname]))
      {
        $flag = $optmap[$optname];
        \Lum\Util::set_flag($flags, $flag, $optvalue);
      }
    }

    $array = $this->to_array($opts);

    return json_encode($array, $flags);
  }

  /**
   * An extremely cheap version of jsonSerialize() using to_array()
   *
   * You'll still need to use `implements JSONSerializable` in your class.
   *
   * This is the bare minimum, you may want to override it.
   */ 
  public function jsonSerialize (): mixed
  {
    return $this->to_array();
  }

}
