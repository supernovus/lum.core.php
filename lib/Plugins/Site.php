<?php

/**
 * Build websites quickly using site-wide templates and having
 * full access to Lum features from each site page.
 *
 */

namespace Lum\Plugins;
use Lum\Exception;

/**
 * Site class.
 *
 * Load in a standalone PHP page that you want to wrap in a template
 * and provide Lum features to. The template can be a path to a
 * PHP file, or can be a loader:file name, such as "views:template"
 * where "views" is the loader and "template" is the view name.
 *
 */

class Site
{
  protected $template; // Either a view, or a filename.
  public function start ($configfile=Null)
  {
    $core = \Lum\Core::getInstance();
    if (isset($configfile))
    { // Load our provided config file.
      $core->conf->loadFile($configfile);
    }
    else
    {
      $siteconf = $core['site.conf'];
      if (isset($siteconf))
      {
        $core->conf->loadFile($siteconf);
      }
      else
      {
        throw new Exception('Could not find configuration.');
      }
    }
    if (!isset($core->conf->template))
    {
      throw new Exception('No template defined in configuration.');
    }
    $this->template = $core->conf->template;
    // Okay, we have our template, now let's start capturing the page content.
    $core->capture->start();
  }

  public function end ()
  {
    $core = \Lum\Core::getInstance();
    $content = $core->capture->end();
    $template = $this->template;
    $loader = Null;
    if (strpos($template, ':') !== False)
    {
      $tparts = explode(':', $template);
      if (isset($core->lib[$tparts[0]]))
      {
        $loader   = $tparts[0];
        $template = $tparts[1];
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
      $output = \Lum\Core::get_php_content($template, $pagedata);
    }
    echo $output;
  }

}
