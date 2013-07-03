<?php

/**
 * This class is for HTML developers who want to use the SASS/Sniffing/minification 
 * etc features while developing plain HTML pages without actually having them
 * integrated into SilverStripe.
 * 
 * This class does NOT require a functioning SilverStripe installation, and can 
 * be used as-is.  However, it does require a directory writable by the webserver
 * for the static Munger to store the munged files.
 * 
 * Usage:
 * // This will cache assets in the specified directory, and will return the
 * // <link> and <script> elements as requested. 
 * // You need to put the output of this function into your php template. 
 * // You can either output CSS and JS resources together, or seperately.
 * // The example function below will need to be tailored to your CMS/application.
 *
 * function return_assets() {
 *
 *     require_once 'path/to/StaticMunger.php';
 *     $m = new StaticMunger();
 *     $m->set_cache_dir( $_SERVER['DOCUMENT_ROOT'].'/assets/_combinedfiles' );
 *     $assets = '';
 *     // Configuration
 *     $m->set_css_config(array(
 *     ));
 *    // Examples of CSS munging (make sure paths are relative to the site root)
 *     // Screen CSS group
 *     $css_files_all = array(
 *        '/resources/ui/styles/defaults.css',
 *        '/resources/ui/styles/homepage.css',
 *        '/resources/ui/styles/screen.css'
 *     );
 *     $assets .= $m->display_css($css_files_all, 'all');
 *     // Print CSS (make sure paths are relative to the site root)
 *     $assets .= $m->display_css('/resources/ui/styles/print.css', 'print');
 *     // Access CSS (make sure paths are relative to the site root)
 *     $assets .= $m->display_css('/resources/ui/styles/access.css', 'aural,braille,embossed');
 *
 *     // Examples of JS munging (make sure paths are relative to the site root)
 *     $m->set_js_config(array(
 *     ));
 *     $assets .= $m->display_js('/resources/ui/scripts/jquery.css');
 *
 *     return $assets;
 *
 * }
 */

class StaticMunger {

    public $css_config;
    public $js_config; 
    public $isDev;

    function __construct() {
        $this->js_config = array(
        );
        $this->css_config = array(
        );
    }

    function set_cache_dir($cache_dir) {
        $this->js_config['cache_dir']  = $cache_dir;
        $this->css_config['cache_dir'] = $cache_dir;
    }

    function setDev($dev) {
        $this->isDev = (bool)$dev;
    }

    function set_css_config($config) {
        $this->css_config = array_merge($this->css_config, $config);
    }
    
    function set_js_config($config) {
        $this->js_config = array_merge($this->js_config, $config);
    }

    function display_js($files) {
        if ( ! is_writable( $this->js_config['cache_dir'] ) ) {
            die('Cache dir '.$this->js_config['cache_dir'].' is not writable. Please check your config and permissions.');
        }
        require_once( dirname(__FILE__).'/JS_Munger.php' );
        $js = new JS_Munger($this->js_config);
        $js->setDev( $this->isDev );
        if ( is_array($files) ) {
            $js->addFiles($files);
        } else {
            $js->addFile($file);
        }
        $out = '';
        foreach( $js->getMungedFiles() as $file ) {
            // Files are returned as complete filesystem paths
            $file = str_replace($_SERVER['DOCUMENT_ROOT'], '', $file);
            $out .= $this->tag('js', $file);
        }
        return $out;
    }

    function display_css($files, $media) {
        if ( ! is_writable( $this->css_config['cache_dir'] ) ) {
            die('Cache dir '.$this->css_config['cache_dir'].' is not writable. Please check your config and permissions.');
        }
        require_once( dirname(__FILE__).'/useragent/Useragent.php' );
        require_once( dirname(__FILE__).'/CSS_Munger.php' );
        $css = new CSS_Munger($this->css_config);
        $css->setDev( $this->isDev );
        if ( is_array($files) ) {
            foreach( $files as $file ) {
                $css->addFile($file, $media);
            }
        } else {
            $css->addFile($files, $media);
        }
        $out = '';
        foreach( $css->getMungedFiles() as $file => $media ) {
            // Files are returned as complete filesystem paths
            $file = str_replace($_SERVER['DOCUMENT_ROOT'], '', $file);
            $out .= $this->tag('css', $file, $media);
        }
        return $out;
    }

    /** 
    * Internal function for making tag strings
    * @param  String flag for type: css|js
    * @param  String of reference of file. 
    * @param  String Media type for the tag.  Only applies to CSS links. defaults to 'screen'
    */
    private function tag($flag, $file, $media = 'screen') {
        switch($flag){
            case 'css':
                return '<link type="text/css" rel="stylesheet" href="'.$file.'" media="'.$media.'" />'."\r\n";
            break;
            case 'js':
                return '<script type="text/javascript" src="'.$file.'"></script>'."\r\n";
            break;
        }
    } 

}

