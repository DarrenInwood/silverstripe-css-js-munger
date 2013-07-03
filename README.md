silverstripe-css-js-munger
==========================

SCSS/CSS and JS combining, minification and User Agent-targeted delivery module for SilverStripe

Requirements
-----------------------------------------------
SilverStripe 2.4, not tested on 3.x

Installation Instructions
-----------------------------------------------

Unzip into your webroot, and run /dev/build?flush=1 to tell your SilverStripe install
that it has some new classes to use.

To enable the module, add to your _config.php:

    Munger_Requirements_Backend::enable();


Basic Configuration
-----------------------------------------------

In it's default state, the Munger module will combine Javascript and CSS files
more or less exactly like the stock Requirements backend.

While using a 'dev' type environment, as set by Director::set_dev_servers() or
Director::set_environment_type('dev'), file combining and minification will be
turned off.

Additional features can be enabled using the following lines in _config.php:

    Requirements::set_combined_files_enabled(false);
    Munger_Requirements_Backend::setCache(true);
    Munger_Requirements_Backend::setSniffer(true);
    Munger_Requirements_Backend::setSass(true);
    Munger_Requirements_Backend::setMinify(true);


Use for static templates
-----------------------------------------------

If you are a straight HTML developer, you can use this module on your templates
without having a running SilverStripe install.  This lets you code your CSS
using the Sniffer and SASS functions, which can really make life easier.

The StaticMunger class is written with you in mind.  To use it, take a look at
the example static-test.php file in the examples directory.

You can tweak your configuration to suit, and the PHP developer who eventually
integrates your templates into SilverStripe can use the exact same config.


Advanced configuration
-----------------------------------------------

You can set more advanced config options by changing the $default_options
variables on the JS_Munger and CSS_Munger classes.


