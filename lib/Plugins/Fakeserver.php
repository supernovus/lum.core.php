<?php

namespace Lum\Plugins;
use Lum\Exception;

/**
 * A way to emulate a web server from tests or the command line.
 *
 * This is pretty minimalistic, but should work to help tests access
 * web pages. I'm planning on adding some additional features down the road.
 */
class Fakeserver
{
  /**
   * Are we running under the CLI SAPI?
   *
   * Will be auto-detected during construction.
   */
  public $is_cli = false;

  /**
   * Are we running under the standalone web server?
   *
   * Will be auto-detected during construction.
   */
  public $is_cli_server = false;

  /**
   * Build a Fakeserver plugin instance.
   *
   * @param array $opts (Optional) Named options for this plugin:
   *
   * 'method'  If specified, we call setMethod() with this value.
   * 'proto'   If specified, we call setProto() with this value.
   * 'parse'   If true, we call parseArgv().
   *           If not specified, the default is the same as $this->is_cli.
   * 
   */
  public function __construct ($opts=[])
  {
    $this->is_cli = (php_sapi_name() == 'cli');
    $this->is_cli_server = (php_sapi_name() == 'cli-server');

    if (isset($opts['method']))
    {
      $this->setMethod($opts['method']);
    }
    if (isset($opts['proto']))
    {
      $this->setProto($opts['proto']);
    }

    $parse = isset($opts['parse']) ? $opts['parse'] : $this->is_cli;
    if ($parse)
    {
      $this->parseArgv();
    }
  }

  /**
   * Set the request method.
   */
  public function setMethod ($method)
  {
    $_SERVER['REQUEST_METHOD'] = strtoupper($method);
  }

  /**
   * Set the server protocol.
   *
   * @param mixed $proto  If a string, it's the protocol spec.
   *                      If it's anything else, and 'SERVER_PROTOCOL' is
   *                      not yet set, it will be set to 'HTTP/1.0'.
   */
  public function setProto ($proto)
  {
    if (is_string($proto))
    {
      $_SERVER['SERVER_PROTOCOL'] = $proto;
    }
    elseif (!isset($_SERVER['SERVER_PROTOCOL']))
    {
      $_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.0';
    }
  }

  /**
   * Set the Path Info
   */
  public function setPathInfo ($pathinfo)
  {
    $_SERVER['PATH_INFO'] = $pathinfo;
  }

  /**
   * Add an HTTP header.
   *
   * @param string $name   An HTTP header name.
   *
   * This will be converted to uppercase, dashes replaced by underscores,
   * and a prefix of 'HTTP_' added. So for instance, a header name of
   * 'Accept-Language' will become 'HTTP_ACCEPT_LANGUAGE'.
   *
   * @param string $value  The value to set the header to.
   */
  public function addHeader ($name, $value)
  {
    $name = str_replace('-','_', strtoupper($name));
    if (!preg_match("/HTTP_/", $name))
    {
      $name = "HTTP_$name";
    }
    $_SERVER[$name] = $value;
  }

  /**
   * Add a bunch of headers.
   *
   * @param mixed $headers  The headers we want to set (see below).
   *
   * Header values: 
   *
   * May be a string, in which case it's a full set of headers separated 
   * by newlines. May be an array of strings, in which case each string is 
   * a header-string (see below). May be an array of arrays, in which case 
   * each child array must have two children, the first is the name of the 
   * header (without a colon) and the second is the value to set the header.
   *
   * Header strings must be in the format:
   *
   *   "Header-Name: header value here"
   *
   * The header name will be split from the header values by the first
   * colon character found in the string.
   *
   * Current limitations, no arrays or multiple value headers are currently
   * supported. If a later header has the same name as an earlier one, it will
   * overwrite the previous one.
   */
  public function addHeaders ($headers)
  {
    if (is_string($headers))
    {
      $headers = explode($headers, "\n");
    }
    elseif (!is_array($headers))
    {
      throw new Exception("addHeaders must be passed a string or array");
    }
    foreach ($headers as $header)
    {
      if (is_string($header))
      {
        $header = explode(':', $header, 2);
      }
      if (is_array($header) && count($header) == 2)
      {
        $name  = trim($header[0]);
        $value = trim($header[1]);
        $this->addHeader($name, $value);
      }
      else
      {
        throw new Exception("Invalid header value passed to addHeaders");
      }
    }
  }

  /**
   * Use GET if a request method has not been set yet.
   */
  public function useGet ()
  {
    if (!isset($_SERVER['REQUEST_METHOD']))
    {
      $this->setMethod('GET');
    }
    if (!isset($_GET))
    {
      $_GET = [];
    }
  }

  /**
   * Use POST if a request method has not been set yet.
   */
  public function usePost ()
  {
    if (!isset($_SERVER['REQUEST_METHOD']))
    {
      $this->setMethod('POST');
    }
    if (!isset($_POST))
    {
      $_POST = [];
    }
    if (!isset($_FILES))
    {
      $_FILES = [];
    }
  }

  /**
   * Update the $_REQUEST from $_GET and $_POST (in that order).
   */
  public function updateRequest()
  {
    if (!isset($_REQUEST))
    {
      $_REQUEST = [];
    }
    if (isset($_GET) && count($_GET) > 0)
    {
      foreach ($_GET as $key => $val)
      {
        $_REQUEST[$key] = $val;
      }
    }
    if (isset($_POST) && count($_POST) > 0)
    {
      foreach ($_POST as $key => $val)
      {
        $_REQUEST[$key] = $val;
      }
    }
  }

  /**
   * Load $_POST data from a JSON file.
   *
   * @param string $file  The JSON file to load into $_POST.
   * @param bool $overwrite (false) Overwrite existing $_POST keys?
   */
  public function loadPostJson ($file, $overwrite=false)
  {
    if (!file_exists($file))
    {
      throw new Exception("Invalid file: $file");
    }
    $this->usePost();
    \Lum\Core::load_opts_from($file, $_POST, $overwrite);
  }

  /**
   * Parse arguments into the $_GET superglobal.
   *
   * @param mixed $args  Either a string, or an array.
   *                     If it's an array, it will be joined with '&' symbol.
   *                     If it's a string, we will trim a leading '?' from it.
   *
   * We then pass the $args to the PHP parse_str() function.
   *
   * If $_SERVER['QUERY_STRING'] as not been set, it will be set as well.
   */
  public function parseGet ($args)
  {
    $this->useGet();
    if (is_string($args))
    {
      $args = ltrim($args, '?');
    }
    elseif (is_array($args))
    {
      $args = implode('&', $args);
    }
    parse_str($args, $_GET);
    if (!isset($_SERVER['QUERY_STRING']))
    {
      $_SERVER['QUERY_STRING'] = $args;
    }
  }

  /**
   * Add a file to the $_FILES superglobal.
   *
   * @param string $name  The name to add. Array style names are supported.
   *
   * Array style names come in two styles:
   *
   *  "myfile[]"      A flat array called "myfile".
   *  "myfile[foo]"   An associative array called "myfile" with a child
   *                  property of "foo" inside it.
   *
   * @param string $file  The file we are adding (must exist.)
   *
   * @param array $filespec  (Optional) May contain the following properties:
   *
   * 'name'  The name of the file (defaults to basename($file)).
   * 'type'  The MIME type of the file (defaults to mime_content_type($file)).
   * 'error' An upload error code (defaults to UPLOAD_ERR_OK).
   * 'size'  The size of the file (defaults to filesize($file)).
   * 
   */
  public function addUpload ($name, $file, $filespec=[])
  {
    if (!file_exists($file))
    {
      throw new Exception("Invalid file: $file");
    }
    $this->usePost();
    if (preg_match("/(\w+)\[\]$/", $name, $matches))
    {
      $name = $matches[1];
      $arrayVar = true;
    }
    elseif (preg_match("/(\w+)\[(\w+)\]$/", $name, $matches))
    {
      $name = $matches[1];
      $arrayVar = $matches[2];
    }
    else
    {
      $arrayVar = false;
    }
    $filespec['tmp_name'] = $file;
    if (!isset($filespec['name'])) $filespec['name'] = basename($file);
    if (!isset($filespec['type'])) $filespec['type'] = mime_content_type($file);
    if (!isset($filespec['error'])) $filespec['error'] = UPLOAD_ERR_OK;
    if (!isset($filespec['size'])) $filespec['size'] = filesize($file);
    if ($arrayVar !== false)
    { // Array files.
      if (!isset($_FILES[$name]))
      {
        $_FILES[$name] =
        [
          'name'     => [],
          'type'     => [],
          'tmp_name' => [],
          'error'    => [],
          'size'     => [],
        ];
      }
      if ($arrayVar === true)
      { // We're just appending the value to the array.
        foreach ($filespec as $key => $val)
        {
          $_FILES[$name][$key][] = $val;
        }
      }
      else
      { // We're adding to a named sub-array key.
        foreach ($filespec as $key => $val)
        {
          $_FILES[$name][$key][$arrayVar] = $val;
        }
      }
    }
    else
    { // Flat file.
      $_FILES[$name] = $filespec;
    }
  }

  /**
   * Load a JSON file that will add a bunch of uploads at once.
   *
   * @param mixed $config  Either an array representing the JSON, or
   *                       a string representing the path to the JSON file.
   * 
   * The format of the JSON should be:
   *
   * ```javascript
   * [
   *   {
   *     "name": "myupload1",               // The upload name.
   *     "path": "./path/to/file.xml",      // The path to the file.
   *     "spec": {"type":"application/xml"} // Any $filespec opts here.
   *    }
   *    // More files here.
   * ]
   * ```
   *
   * Or if unique names are in use (no "foo[]" type names), you could do:
   *
   * ```javascript
   * {
   *   "myimage":                          // The upload name.
   *   {
   *     "path": "./path/to/file.jpg",     // The path to the file.
   *     "spec": {"name":"image.jpg"},     // Any $filespec opts here.
   *   }
   * }
   * ```
   *
   * For each upload defined, this will call addUpload().
   *
   */
  public function loadUploadsJson ($config)
  {
    if (is_array($config))
    {
      $conf = $config;
      $config = json_encode($conf);
    }
    elseif (is_string($config))
    {
      if (!file_exists($config))
      {
        throw new Exception("Invalid file: $config");
      }
      $conf = [];
      \Lum\Core::load_opts_from($config, $conf);
    }
    foreach ($conf as $key => $file)
    {
      if (!is_array($file))
      {
        throw new Exception("Invalid file spec for '$key' in '$config'");
      }
      if (!isset($file['path']))
      {
        throw new Exception("Missing 'path' for '$key' in '$config'");
      }
      $path = $file['path'];
      $name = isset($file['name']) ? $file['name'] : $key;
      $spec = isset($file['spec']) ? $file['spec'] : [];
      $this->addUpload($name, $path, $spec);
    }
  }

  /**
   * Parse the contents of $_SERVER['argv'] (command line arguments).
   *
   * The first element (name of script) is ignored. Then we look for:
   *
   *  "-p" <file>         Calls: loadPostJson($file, false);
   *  "-P" <file>         Calls: loadPostJson($file, true);
   *  "-f" <name> <file>  Calls: addUpload($name, $file);
   *  "-F" <file>         Calls: loadUploadsJson($file);
   *
   * If an argument starts with a slash, and no PATH_INFO has been set,
   * we will call setPathInfo($arg);
   * 
   * Any other arguments will be passed to parseGet().
   */
  public function parseArgv ()
  {
    if (isset($_SERVER['argv']) && count($_SERVER['argv']) > 1)
    { // We have arguments.
      $args = array_slice($_SERVER['argv'], 1);

      $query = [];
      while (count($args) > 0)
      {
        $arg = array_shift($args);
        if ($arg == '-p' || $arg == '-P')
        { // A JSON file containing POST data.
          if (count($args) < 1) throw new Exception("Missing $arg argument");
          $overwrite = ($arg == '-P');
          $file = array_shift($args);
          $this->loadPostJson($file, $overwrite);
        }
        elseif ($arg == '-f')
        {
          if (count($args) < 2) throw new Exception("Missing $arg arguments");
          $name = array_shift($args);
          $file = array_shift($args);
          $this->addUpload($name, $file);
        }
        elseif ($arg == '-F')
        {
          if (count($args) < 1) throw new Exception("Missing $arg argument");
          $file = array_shift($args);
          $this->loadUploadsJson($file);
        }
        elseif (!isset($_SERVER['PATH_INFO']) && substr($arg, 0, 1) === '/')
        {
          $this->setPathInfo($arg);
        }
        else
        { // Anything else is a query string parameter.
          $query[] = $arg;
        }
      }
      // Convert query string arguments.
      if (count($query) > 0)
      {
        $this->parseGet($query);
      }
      $this->updateRequest();
    }
  } // parseArgv()

  /**
   * Check to see if the REQUEST_URI matches a regex.
   *
   * Useful for the 'cli-server' SAPI, as we can do:
   *
   * ```php
   * if ($core->fakeserver->uriMatches('/\.(?:png|jpg|css|js)$/'))
   * { // It's a static file, let the PHP web server handle it.
   *   return false;
   * }
   * else
   * { // Show our content.
   * }
   * ```
   *
   * @param string $regex  The regular expression we are testing against.
   * 
   * @return mixed  If the regular expression matches, this will be an array
   *                with the matched values. If the regex does not match this
   *                will be boolean false.
   *
   */
  public function uriMatches ($regex)
  {
    $matches = [];
    if (preg_match($regex, $_SERVER['REQUEST_URI'], $matches))
    {
      return $matches;
    }
    return false;
  }

  /**
   * Check to see if the URI ends in a certain extension.
   *
   * This is a quick wrapper for uriMatches() that simply checks for specific
   * file extensions. Useful if you are using the 'cli-server' SAPI.
   *
   * @param mixed $exts  A list of extensions to check for.
   *                     May be a pipe separated string, or an array of
   *                     file extensions. No leading dot.
   *                     Default: 'png|jpg|css|js|php'
   *
   * @return mixed See uriMatches() for return values.
   */
  public function uriHasExts ($exts=null)
  {
    if (!isset($exts))
    {
      $exts = 'png|jpg|css|js|php';
    }
    elseif (is_array($exts))
    {
      $exts = implode('|', $exts);
    }
    $regex = '/\.(?:'.$exts.')$/';
    return $this->uriMatches($regex);
  }

}