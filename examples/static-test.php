<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
<title>Static Munger test page</title>
<?php
require_once( dirname(dirname(__FILE__)).'/code/StaticMunger.php' );

$m = new StaticMunger();
// You only need to set options that are different from the defaults.
$m->set_css_config(array(
    // Header
    'client' => 'Test Client',
    'project' => 'Test Project',
    'author' => 'Test Author',
    // Blacklist
    'blacklist' => true,
    'blacklist_min_versions' => array(
        'ie' => 6,
        'safari' => 3,
        'ff' => 3,
        'opera' => 9.5,
        'netscape' => 8
    ),
    // Cache
    'cache' => true, 
    'cache_dir' => dirname(__FILE__), // set to your cache directory - must be inside the site root
    // SASS
    'sass' => true,
    'sass_opts' => array(
        'vendor_properties' => true,
        'style' => 'expanded',
        'cache' => null,  // If left as null, these will inherit from this class
        'cache_location' => null,  // If left as null, these will inherit from this class
        'property_syntax' => 'new',
        'load_paths' => null, 
        'syntax' => 'scss' // this is critical for passing strings to the SASS parser        
    ),
    // Sniffer
    'sniffer' => true,
    'sniffer_opts' => array(
        'cache' => null,  // If left as null, these will inherit from this class
        'cache_dir' => null  // If left as null, these will inherit from this class
    ),
    // Minify
    'minify' => true,
    'minify_opts' => array(
        'preserveComments' => true
    ),
    // Combine files
    'combine' => true,
    'combined_filename' => false    // output filename - will invent one if nothing is supplied
                                    // note - ignores any directory info, just uses the filename
));
echo $m->display_css('/munger/examples/static-test.css', 'screen');
?>
</head>
<body>

<div class="test">
    <p>This should appear blue in IE, orange in Firefox, green in Safari, and red in Chrome.</p>
</div>

