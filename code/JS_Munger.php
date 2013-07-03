<?php

/**
 * Simplified JS munger.
 *
 * @package munger-silverstripe-module
 * @author Darren Inwood <darren.inwood@chrometoaster.com>
 */

class JS_Munger {

    /**
     * Default options
     */
    public static $default_options = array(
        // Header
        'client' => false,
        'project' => false,
        'author' => false,
        // Blacklist
        'blacklist' => false,
        'blacklist_min_versions' => array(
            'ie' => 6,
            'safari' => 3,
            'ff' => 3,
            'opera' => 9.5,
            'netscape' => 8
        ),
        // Cache
        'cache' => true,
        'cache_dir' => false,
        // JSmin compression
        'jsmin' => true,
        'jsmin_opts' => array(),
        // Combine files
        'combine' => false,
        'combined_filename' => false    // output filename - will invent one if nothing is supplied
                                        // note - ignores any directory info, just uses the filename
    );

    /**
     * Instance options
     */
    public $options;

    /**
     * An array of the included files.
     */
    private $files = array(
    );

    public function __construct($options=array()) {
        $this->options = array_merge(self::$default_options, $options);
        if ( ! $this->options['cache_dir'] ) {
            $this->options['cache_dir'] = dirname(__FILE__);
        }
    }

    /**
     * Enables setting 'Dev mode', where caching, file combining and 
     * minification are disabled regardless of options.
     */
    public function setDev($isDev) {
        if ( $isDev !== true ) return;
        $this->options['cache'] = false;
        $this->options['minify'] = false;
        $this->options['combine'] = false;
    }

    public function addFile($file) {
        $this->files[] = $file;
    }

    public function addFiles($files) {
        foreach( $files as $file ) {
            $this->addFile($file);
        }
    }

    public function getMungedFiles() {
        if ( $this->options['blacklist'] && $this->isBlacklisted() ) {
            // Blacklisted browsers do not get any javascript.
            return array();
        }
        $output_files = array();
        foreach( $this->files as $file ) {
            $real_filename = $this->getRealFilename($file);
            if ( ! $real_filename || ! file_exists($real_filename) ) continue; // File doesn't exist!
            $cache_filename = $this->options['cache_dir']
                . '/' 
                . md5(serialize($this->options))
                . str_replace('/', '_', $file);
            $output_files[] = $cache_filename;

            if ( $this->options['cache'] && file_exists($cache_filename) 
            && filemtime($cache_filename) > filemtime($real_filename) ) {
                // File is cached, no need to regenerate
                continue; 
            }

            $js = file_get_contents($real_filename);
            // JSMin
            if ( $this->options['jsmin'] ) {
/*
                // seems to spit the dummy...
                require_once( dirname(__FILE__).'/jsmin/JSMinPlus.php' );
                $js = JSMinPlus::minify($js);
*/
                require_once( dirname(__FILE__).'/jsmin/jsmin.php' );
                $js = JSMin::minify($js);

            }

            if ( ! $this->options['combine'] ) {
                $js = $this->file_header($file) . $js;
            }

            // Write cache file
            file_put_contents($cache_filename, $js);
        }

        // If we're not combining, just return the bare files
        if ( ! $this->options['combine'] ) {
            return $output_files;
        }

        // Combine files
        $latest_modification_time = false;
        foreach( $output_files as $file ) {
            $latest_modification_time = max(filemtime($file), $latest_modification_time);
        }
        if ( $this->options['combined_filename'] ) {
            $cache_filename = $this->options['cache_dir']
                . '/'
                . md5(serialize($this->options)) . '_'
                . basename($this->options['combined_filename']);
        } else {
            $cache_filename = $this->options['cache_dir']
                . '/'
                . 'combined_' 
                . md5(implode('-',$output_files).serialize($this->options)).'.js';
        }
        if ( $this->options['cache'] 
        && file_exists($cache_filename) && filemtime($cache_filename) > $latest_modification_time ) {
           return array($cache_filename); // This file is cached
        }
        // Cache combined file
        $fh = fopen($cache_filename, 'w');

        fwrite($fh, $this->file_header($output_files));
        foreach( $output_files as $file ) {
            fwrite($fh, file_get_contents($file).";\n");
        }
        fclose($fh);
        return array($cache_filename);
    }
    
    /**
     * Returns HTML tags for inclusion into your page, outputting the files we
     * added using addFile() and addFiles().
     */
    public function toHTML() {
        $files = $this->getMungedFiles();
        $out = '';
        foreach( $files as $file ) {
            if ( strpos($file, $_SERVER['DOCUMENT_ROOT']) !== false ) {
                $file = str_replace($_SERVER['DOCUMENT_ROOT'], '', $file);
            }
            $out .= '<script type="text/javascript" src="'.$file.'"></script>'."\n";
        }
        return $out;
    }
    
    /** 
     * File header
     * Generate a short file header to supplement the non human-readable filename
     * Requires preserve_comments to be enabled, else file header will be added but stripped by compressor
     *
     * @access  private
     * @return String   A header describing the output
     */  
    private function file_header($files) {
        if ( ! is_array($files) ) $files = array($files);
        $client = $this->options['client']; 
        $project = $this->options['project'];
        $author = $this->options['author'];
        $header = "";
        $header .= "/*!\n";
        $header .= " * Description: JavaScript functions\n";
        if ( $client && $project ) {
            $header .= " * For: $client / $project\n";
        } else if ( $client && !$project ) {
            $header .= " * For: $client\n";
        } else if ( !$client && $project ) {
            $header .= " * For: $project\n";
        }
        if ( $author ) {
            $header .= " * Author: $author\n";
        }
        $header .= ' * Cache: '.($this->options['cache']?'On':'Off')."\n";
        $header .= ' * Minification: '.($this->options['jsmin']?'On':'Off')."\n";
        $header .= ' * File Combining: '.($this->options['combine']?'On':'Off')."\n";
        if ( count($files) == 0 ) {
            $header .= ' * No source files specified.'."\n";
        } else if ( count($files) == 1 ) {
            $header .= ' * Source file: '.basename($files[0])."\n";
        } else {
            $header .= ' * Source files:'."\n";
            foreach( $files as $file ) {
                $header .= ' * - '.basename($file)."\n";
            }
        }
        $header .= " * Generated: ".date('Y-m-d g:i:sa')."\n";
        $header .= " */\n\n";
        return $header;
    }

    /**
     * DS, 14.03.2011    
     * Withhold munged assets from C-grade browsers as per Yahoo! graded best practice
     * This is separate from the sniffing functions, as we need to be able to withhold assets when the optional munger CSS conditionals are not in use.
     * Reference: http://developer.yahoo.com/yui/articles/gbs/, http://monospaced.co.uk/labs/gbs/
     * Note that the C-grade browser list is independent of the versioning system used by Yahoo! for the A-grade browser list.
     */    
    private function isBlacklisted() {
        require_once( dirname(__FILE__).'/useragent/Useragent.php' );
        $ua = Useragent::current();
        // if the current browser matches any array entries, then it's on the blacklist
        foreach( $this->options['blacklist_min_versions'] as $browser => $version ) {
          if ( $ua->browser_short == $browser && (float)$ua->version < (float)$version ) {          
                return true;
            }   
        }
        return false;
    }

    /**
     * Converts a relative or absolute URL to a file into a full filesystem path
     *
     * @param $filename String  The URL to the file to find.  Can be relative to 
     * the current request, or absolute to the document root. 
     * @return String   A full filesystem path to the file.
     */
    private function getRealFilename($filename) {
        if ( substr($filename, 0, 1) == '/' ) {
            // Absolute path
            return $_SERVER['DOCUMENT_ROOT'] . $filename;
        }
        if ( file_exists($_SERVER['DOCUMENT_ROOT'].'/'.dirname($_SERVER['REQUEST_URI']).'/'.$filename) ) {
            // Relative to current request
            return $_SERVER['DOCUMENT_ROOT'].'/'.dirname($_SERVER['REQUEST_URI']).'/'.$filename;
        }
        return $_SERVER['DOCUMENT_ROOT'] . '/' . $filename;
    }

}

