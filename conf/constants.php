<?php if (!defined('APPLICATION')) exit();
/**
 * Garden Constants.
 */

// If you want to change where the garden libraries are located on your server, edit these paths:
define('PATH_APPLICATIONS', PATH_ROOT . DS . 'applications');
define('PATH_CACHE', PATH_ROOT . DS . 'cache');
define('PATH_LIBRARY', PATH_ROOT . DS . 'library');
define('PATH_PLUGINS', PATH_ROOT . DS . 'plugins');
define('PATH_THEMES', PATH_ROOT . DS . 'themes');
define('PATH_DATABASE_CONF', PATH_CONF . DS . 'database.php');

// Delivery type enumerators:
define('DELIVERY_TYPE_ALL', 1); // Deliver an entire page
define('DELIVERY_TYPE_ASSET', 2); // Deliver all content for the requested asset
define('DELIVERY_TYPE_VIEW', 3); // Deliver only the view
define('DELIVERY_TYPE_BOOL', 4); // Deliver only the success status (or error) of the request
define('DELIVERY_TYPE_NONE', 5); // Deliver nothing

// Delivery method enumerators
define('DELIVERY_METHOD_XHTML', 1);
define('DELIVERY_METHOD_JSON', 2);

// Handler enumerators:
define('HANDLER_TYPE_NORMAL', 1); // Standard call to a method on the object.
define('HANDLER_TYPE_EVENT', 2); // Call to an event handler.
define('HANDLER_TYPE_OVERRIDE', 3); // Call to a method override.
define('HANDLER_TYPE_NEW', 4); // Call to a new object method.

// Addon type enumerators:
define('ADDON_TYPE_APPLICATION', 'Application');
define('ADDON_TYPE_PLUGIN', 'Plugin');
define('ADDON_TYPE_THEME', 'Theme');
define('ADDON_TYPE_LOCALE', 'Locale');

// Dataset type enumerators:
define('DATASET_TYPE_ARRAY', 'array');
define('DATASET_TYPE_OBJECT', 'object');

// Syndication enumerators:
define('SYNDICATION_NONE', 1);
define('SYNDICATION_RSS', 2);
define('SYNDICATION_ATOM', 3);