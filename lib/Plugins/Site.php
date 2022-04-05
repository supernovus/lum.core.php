<?php

namespace Lum\Plugins;
use Lum\Exception;
use Lum\Core;

/**
 * Site plugin.
 *
 * A way to build quick PHP websites using templates and other features from
 * the `lum-core` library without using a full-fledged application framework
 * like the `lum-framework` library provides.
 *
 * This should be called from a bootstrap file common to every page on
 * your website. A very simple bootstrap file might look like:
 *
 * ```php
 *  <?php
 *  // This is './inc/site.php', the bootstrap file for our simple website.
 *  require_once 'vendor/autoload.php';
 *  $core = \Lum\Core::getInstance();
 *  $core->site->start('./inc/site.json', './inc/layout.php');
 * ```
 *
 * A more complex bootstrap for a site using view loaders, and maybe even
 * some controllers for more advanced pages might look like:
 *
 * ```php
 *  <?php
 *  // This is '../lib/site.php' the bootstrap file for our complex website.
 *  require_once '../vendor/autoload.php';
 *  \Lum\Autoload::register();
 *  $core = \Lum\Core::getInstance();
 *  $core->controllers->addNS('\My\App\Controllers');
 *  $core->layouts = 'views';
 *  $core->layouts->addDir('../views');
 *  $core->conf->setDir('../conf');
 *  function done()
 *  {
 *    global $core;
 *    return $core->site->end(false);
 *  }
 *  $core->site->start();
 * ```
 *
 * There's multiple ways to use this to build super simple websites.
 *
 */

class Site
{
  const SITE_CONF = 'site.conf';
  const SITE_TMPL = 'site.template';

  /**
   * The template the render our site pages with.
   *
   * Can be either the path to a PHP file, or a string with the name
   * of a Core _loader_ and a view to load with that loader.
   *
   *  Example of filename:  `../inc/layout.php` 
   *  Example of loader:    `layouts:site_template`
   *
   * This can be set in a few ways, see the `start()` method for details.
   */
  protected $template;

  /**
   * Load a site configuration and start recording output into a buffer.
   *
   * @param ?string $config  The path to a config file or directory.
   *
   * If no config file is passed, we'll look for a `$core['site.conf']` 
   * Core Attribute pointing to a config file.
   *
   * If no config file is found at all, we assume the `$core->conf` has been
   * populated by the _bootstrap_ file, or isn't needed for this site.
   *
   * If the config file points to a directory, it'll be set as the config
   * directory and every file and subdirectory in it will be autoloaded on 
   * demand as a nested config property.
   *
   * @param ?string $template    The template to set for this page.
   *
   * If no template is passed, we'll look in the `$core->conf` for one of:
   *
   *  - A `template` top-level property a string.
   *  - A `site` nested config property with a `template` property in it.
   * 
   * If no template is found in the `$core->conf`, then we'll look for a
   * `$core['site.template']` Core Attribute for the template.
   *
   * If no template is found is _any_ of the above locations, an exception
   * will be thrown as we need a template to render the page content into.
   *
   * @return static  A copy of the Site instance for this page.
   */
  public function start (string $config=null, string $template=null): static
  {
    $core = Core::getInstance();

    if (!isset($config))
    { // Look for a default.
      $config = $core[self::SITE_CONF];
    }
    
    if (isset($config) && file_exists($config))
    {
      if (is_dir($config))
      { // Set the config directory.
        $core->conf->setDir($config);
      }
      else
      { // Load an individual config file.
        $core->conf->loadFile($config);
      }
    }

    if (isset($template))
    { // Specified the template directly.
      $this->template = $template;
    }
    elseif (isset($core->conf->template))
    { // Found in top-level 'template' config property.
      $this->template = $core->conf->template;
    }
    elseif (isset($core->conf->site, $core->conf->site['template']))
    { // Found in the `template` property of the `site` config section.
      $this->template = $core->conf->site['template'];
    }
    elseif (isset($core[self::SITE_TMPL]))
    { // Found in the `site.template` Core Attribute.
      $this->template = $core[self::SITE_TMPL];
    }
    else
    {
      throw new Exception('No site template was defined');
    }

    // Okay, we have our template, now let's start capturing the page content.
    $core->capture->start();

    return $this;
  }

  /**
   * End capturing the input, and render our template.
   *
   * @param bool $echoOutput  Changes how we handle the script buffer output. 
   *
   * If `true` echo the output to `STDOUT` and the method returns `null`.
   * 
   * If `false` we simply return the output as a string and let the calling
   * script handle it. 
   *
   * This defaults to `true` for backwards compatibility
   * with the older versions.
   *
   * @return ?string  Read the `$echoOutput` description for details.
   *
   */
  public function end (bool $echoOutput=true): ?string
  {
    $core = Core::getInstance();
    $content = $core->capture->end();
    $template = $this->template;
    $loader = null;
    if (strpos($template, ':') !== False)
    {
      $tparts = explode(':', $template);
      $loader = $tparts[0];
      if (isset($core->$loader))
      {
        $template = $tparts[1];
      }
      else
      {
        $loader = null;
      }
    }
    $pagedata = array(
      'content' => $content, // The page content to insert.
      'core'    => $core,    // Provide Lum Core to the template.
      'nano'    => $core,    // An alias for old templates.
    );
    if (isset($loader))
    { // We're using a loader.
      $output = $core->$loader->load($template, $pagedata);
    }
    else
    { // We're using an include file.
      $output = Core::get_php_content($template, $pagedata);
    }

    if ($echoOutput)
    {
      echo $output;
      return null;
    }
    else
    {
      return $output;
    }
  }

}
