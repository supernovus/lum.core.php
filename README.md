# lum.core.php

## Summary

The Lum\Core library, and it's primary plugins and helpers.

## Classes

| Class                   | Description                                       |
| ----------------------- | ------------------------------------------------- |
| Lum\Core                | The Core class itself.                            |
| Lum\Plugins\Capture     | A helper for capturing PHP output.                |
| Lum\Plugins\Client      | A plugin to get information on client.            |
| Lum\Plugins\Conf        | A configuration plugin.                           |
| Lum\Plugins\Controllers | A plugin for loading controllers.                 |
| Lum\Plugins\Debug       | A plugin for debugging.                           |
| Lum\Plugins\Fakeserver  | A plugin for using 'cli' or 'cli-server' SAPI.    |
| Lum\Plugins\Instance    | A base class for loading class instances.         |
| Lum\Plugins\Models      | A plugin for loading models.                      |
| Lum\Plugins\Output      | A plugin for setting output options.              |
| Lum\Plugins\Plugins     | A meta-plugin for loading plugins.                |
| Lum\Plugins\Router      | A plugin for routing URL paths to controllers.    |
| Lum\Plugins\Sess        | A plugin for managing PHP Sessions.               |
| Lum\Plugins\Site        | A plugin for building simple PHP sites.           |
| Lum\Plugins\Url         | A plugin for working with URLs.                   |
| Lum\Plugins\Views       | A plugin for loading views.                       |
| Lum\Data\JSON           | A trait for objects with JSON representations.    |
| Lum\Data\Arraylike      | A trait for array-like data objects.              |
| Lum\Data\BuildXML       | A trait with helpers for building XML data.       |
| Lum\Data\InputXML       | A trait for data objects that can import XML.     |
| Lum\Data\OutputXML      | A trait for data objects that can output XML.     |
| Lum\Data\DetectType     | A trait with helpers for detecting input type.    |
| Lum\Data\O              | Minimal base class for Data objects.              |
| Lum\Data\Obj            | Default base class for Data objects.              |
| Lum\Data\Arrayish       | An extension of Obj using Arraylike trait.        |
| Lum\Data\Container      | An extension of Arrayish with indexed children.   |
| Lum\Loader\Content      | A loader trait for parsing PHP content.           |
| Lum\Loader\Files        | A loader trait for finding files in a folder.     |
| Lum\Loader\Instance     | A loader trait for creating an object instance.   |
| Lum\Loader\Namespaces   | A loader trait for finding classes in namespaces. |
| Lum\Meta\Cache          | A trait for simple caching.                       |
| Lum\Meta\ClassID        | A trait for returning a class id from a loader.   |
| Lum\Meta\ClassInfo      | A trait for extended class information.           |
| Lum\Meta\HasDeps        | A trait for managing dependencies.                |
| Lum\Meta\HasProps       | A trait for working with property defaults.       |
| Lum\Meta\SetProps       | A trait for setting properties via an array.      |
| Lum\Router\FromRIML     | A class for converting RIML into Router config.   |

## Creating a Core instance

You only need to do this once in your application, generally right at the
beginning.

```php
require_once 'vendor/autoload.php';  // Register Composer autoloaders.
\Lum\Autoload::register();           // If using spl_autoload, call this.
$core = \Lum\Core::getInstance();    // Create your Core object.
```

## Getting the current Core instance

Any time you need the Core instance, you simply call the getInstance method.

```php
$core = \Lum\Core::getInstance();  // Return the current core instance.
```

## Using plugins

The plugins are loaded automatically when first called. For instance:

```php
$lum = \Lum\Core::getInstance(); 
$lum->conf->setDir('./conf'); // Load Conf plugin and call setDir() on it.   
$lum->router = ['extend'=>true, 'auto_prefix'=>true]; // Load Router plugin.
$lum->router->loadRoutes($lum->conf->routes); // Load routes into Router.
$lum->controllers->addNS("\\MyApp\\Controllers"); // Load Controllers plugin.
$lum->models->addNS("\\MyApp\\Models"); // Load Models plugin.
$lum->controllers->use_screens(); // Use default view loaders.
$lum->dispatch(); // Call an extension method added by Router plugin.
```

## Official URLs

This library can be found in two places:

 * [Github](https://github.com/supernovus/lum.core.php)
 * [Packageist](https://packagist.org/packages/lum/lum-core)

## Author

Timothy Totten

## License

[MIT](https://spdx.org/licenses/MIT.html)
