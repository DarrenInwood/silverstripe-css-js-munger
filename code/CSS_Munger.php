<?php

/**
 * Simplified CSS Munger, using modular components.
 *
 * Usage:
 * <code>
 * $m = new CSS_Munger();
 * // $m = new CMM_Munger($options); // You can pass in options to change the defaults
 * $m->addFile('/absolute/url/to/file1.scss', 'screen');
 * $m->addFile('relative/path/to/file2.scss', 'screen');
 * $m->addFiles(array(
 *     '/absolute/url/to/file3.css' => 'screen',
 *     'relative/path/to/file4.scss' => 'print',
 *     '/path/to/another/file5.scss' => 'aural,braille,embossed'
 * ));
 * // $m->setDev(true); // 'Dev mode' turns off caching, minifying and file combining
 *                      // independant of the options.
 * $files = $m->getMungedFiles(); // Returns an array of munged files so you can output however
 * $html = $m->toHTML(); // Returns a string containing HTML tags for the munged files
 * </code>
 *
 * Common options:  (for more, see the source code)
 *   - client - name of the client in file headers
 *   - project - name of the project in file headers
 *   - author - code author in file headers
 *   - blacklist - true|false, whether to use the blacklist
 *   - cache - true|false, turn cache on/off
 *   - cache_dir - which directory to use for caching? defaults to the directory this file is in.
 *   - sass - true|false, whether to use SASS parsing on .scss files (ignores other file extensions)
 *   - sniffer - true|false, use the sniffer
 *   - minify - true|false, minify the CSS
 *   - combine - true|false, combine files with matching media types
 *
 * @package munger-silverstripe-module
 * @author Darren Inwood <darren.inwood@chrometoaster.com>
 */

class CSS_Munger {

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
        // SASS
        'sass' => true,
        'sass_opts' => array(
            'vendor_properties' => false,
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
    );

    /**
     * Instance options
     */
    public $options;

    /**
     * The filenames and their associated media.  Keys are filenames, values are 
     * media strings.
     */
    private $files = array();
    
    /**
     * Constructor allows overriding the default options.
     */
    public function __construct($options=array()) {
        $this->options = array_merge(self::$default_options, $options);
        if ( ! $this->options['cache_dir'] ) {
            $this->options['cache_dir'] = dirname(__FILE__);
        }
        if ( ! $this->options['sass_opts']['load_paths'] ) {
            $this->options['sass_opts']['load_paths'] = array($_SERVER['DOCUMENT_ROOT']);
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
        $this->options['sass_opts']['cache'] = false;
        $this->options['sniffer_opts']['cache'] = false;
    }

    /**
     * Add a file of a specified media type.
     *
     * Usage:
     * <code>
     * $s = new CSS_Munger();
     * $s->addFile('/path/to/file', 'screen');
     * </code>
     */    
    public function addFile($filename, $media='all') {
        $this->files[$filename] = $media;
    }
    
    /**
     * Add multiple files, specifying the media type for each.
     *
     * Usage:
     * <code>
     * $s = new CSS_Munger();
     * $s->addFiles(array(
     *     '/path/to/file1.css' => 'all',
     *     '/path/to/file2.css' => 'screen',
     *     '/path/to/file3.css' => 'screen',
     *     '/path/to/file4.css' => 'print',
     *     '/path/to/file5.css' => 'aural,braille,embossed'
     * ));
     * </code>
     */
    public function addFiles($files) {
        foreach( $files as $file => $media ) {
            $this->addFile($file, $media);
        }
    }

    /**
     * Get an associative array of all output files and their associated media 
     * types.
     */
    public function getMungedFiles() {
        if ( $this->options['blacklist'] && $this->isBlacklisted() ) {
            // Blacklisted browsers do not get any CSS.
            return array();
        }
        $output_files = array();
        foreach( $this->files as $filename => $media ) {
            $ua = Useragent::current();
            $real_filename = $this->getRealFilename($filename);
            if ( ! $real_filename || ! file_exists($real_filename) ) continue; // File doesn't exist!
            $cache_filename = $this->options['cache_dir']
                . '/'
                . md5(serialize($this->options))
                . str_replace('/','_',$filename)
                . ( $this->options['sniffer'] ? '.' . $ua->browser_short . round($ua->version*10)/10 . $ua->platform_short : '' )
                . '.css';
            $output_files[$cache_filename] = $media;
            if ( $this->options['cache'] && file_exists($cache_filename) 
            && filemtime($cache_filename) > filemtime($real_filename) ) {
                // File is cached, no need to regenerate
                continue; 
            }
            $css = file_get_contents($real_filename);

            // Rewrite all URIs as absolute - also enforces double quotes for url()
            require_once( dirname(__FILE__).'/cssmin/UriRewriter.php' );
            $rewriter = new Minify_CSS_UriRewriter();
            $css = $rewriter->rewrite( $css, dirname($real_filename) );

            // SASS
            if ( $this->options['sass'] ) {
                // Only parse .scss files
                if ( substr(strrchr($filename, '.'), 1) == 'scss' ) {
                    require_once( dirname(__FILE__).'/phpsass/sass/SassParser.php' );
                    $sass = new SassParser($this->getSassOpts($filename));
                    // Run CSS through the SASS parser
                    $css = $sass->toCss($css, false); 
                }
            }
            // Sniffer
            if ( $this->options['sniffer'] ) {
                require_once( dirname(__FILE__).'/sniffer/Sniffer.php' );
                $sniffer = new Sniffer($this->getSnifferOpts());
                $sniffer->loadString($css, $real_filename);
                $css = $sniffer->toString();
            }
            // Minify
            if ( $this->options['minify'] ) {
                require_once( dirname(__FILE__).'/cssmin/CSS.php' );
                $m = new Minify_CSS();
                $css = $m->minify( $css, $this->getMinifyOpts() );
            }
            // Write file
            if ( ! $this->options['combine'] ) {
                $css = $this->file_header($media, $filename) . $css;
            }
            file_put_contents($cache_filename, $css);
        }
        if ( ! $this->options['combine'] ) {
            return $output_files;
        }
        // Combine files - retain media types
        $combined = array();
        foreach( $output_files as $filename => $media ) {
            if ( ! isset($combined[$media]) ) $combined[$media] = array();
            $combined[$media][] = $filename;
        }
        $output_files = array();
        foreach( $combined as $media => $files ) {
            $latest_modification_time = false;
            foreach( $files as $file ) {
                $latest_modification_time = max(filemtime($file), $latest_modification_time);
            }
            if ( $this->options['combined_filename'] ) {
                $cache_filename = $this->options['cache_dir']
                    . '/'
                    . ( $this->options['sniffer'] ? $ua->browser_short . round($ua->version*10)/10 . $ua->platform_short : '' ).'_'
                    . basename($this->options['combined_filename']);
            } else {
                $cache_filename = $this->options['cache_dir']
                    . '/'
                    . 'combined_' 
                    . ( $this->options['sniffer'] ? $ua->browser_short . round($ua->version*10)/10 . $ua->platform_short : '' ).'_'
                    . md5(implode('-',$files)).'.css';            
            }
            $output_files[$cache_filename] = $media;
            if ( $this->options['cache'] 
            && file_exists($cache_filename) && filemtime($cache_filename) > $latest_modification_time ) {
               continue; // This file is cached
            }
            // Cache combined file
            $fh = fopen($cache_filename, 'w');
            fwrite($fh, $this->file_header($media, $files));
            foreach( $files as $file ) {
                fwrite($fh, file_get_contents($file)."\n");
            }
            fclose($fh);
        }
        return $output_files;
    }

    /**
     * Returns HTML tags for inclusion into your page, outputting the files we
     * added using addFile() and addFiles().
     */
    public function toHTML() {
        $html = '';
        foreach( $this->getMungedFiles() as $file => $media ) {
            if ( strpos($file, $_SERVER['DOCUMENT_ROOT']) !== false ) {
                $file = str_replace($_SERVER['DOCUMENT_ROOT'], '', $file);
            }
            $html .= '<link type="text/css" rel="stylesheet" href="'.$file.'" media="'.$media.'" />'."\n";
        }
        return $html;
    }

    /** 
     * File header
     * Generate a short file header to supplement the non human-readable filename
     * Requires preserve_comments to be enabled, else file header will be added but stripped by compressor
     *
     * @access  private
     * @param $media: css media type
     * @return String   A header describing the output
     */  
    private function file_header($media, $files) {
        if ( ! is_array($files) ) $files = array($files);
        $client = $this->options['client']; 
        $project = $this->options['project'];
        $author = $this->options['author'];
      
        if ( $media == 'all' ) {
            $desc = 'Shared styles';
        } else {
            $desc = ucfirst($media) . ' styles';
        }
        $header = "";
        $header .= "/*!\n";
        $header .= " * Description: $desc\n";
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
        $header .= ' * SASS: '.($this->options['sass']?'On':'Off')."\n";
        $header .= ' * Sniffer: '.($this->options['sniffer']?'On':'Off')."\n";
        if ( $this->options['sniffer'] ) {
            $ua = Useragent::current();
            $header .= ' * - Browser: '.$ua->browser_short .' '. round($ua->version*10)/10 .' '. $ua->platform_short."\n";
        }
        $header .= ' * Minification: '.($this->options['minify']?'On':'Off')."\n";
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
     * Get the options to pass to the SassParser constructor, setting the cache 
     * settings if they haven't been overriden.
     * @param $filename String  Allows Sass to determine the location of the 
     * file being parsed, for @import statements
     */
    private function getSassOpts($filename) {
        if ( $this->options['sass_opts']['cache'] === null ) {
            $this->options['sass_opts']['cache'] = $this->options['cache'];
        }
        if ( $this->options['sass_opts']['cache_location'] === null ) {
            $this->options['sass_opts']['cache_location'] = $this->options['cache_dir'];
        }
        $sass_opts = $this->options['sass_opts'];
        $sass_opts['load_path'] = $_SERVER['DOCUMENT_ROOT'];
        return $sass_opts;
    }

    /**
     * Get the options to pass to the Sniffer constructor, setting the cache 
     * settings if they haven't been overriden.
     */
    private function getSnifferOpts() {
        if ( $this->options['sniffer_opts']['cache'] === null ) {
            $this->options['sniffer_opts']['cache'] = $this->options['cache'];
        }
        if ( $this->options['sniffer_opts']['cache_dir'] === null ) {
            $this->options['sniffer_opts']['cache_dir'] = $this->options['cache_dir'];
        }
        return $this->options['sniffer_opts'];
    }

    /**
     * Get the options to pass to the Minify_CSS minifier.
     */
    private function getMinifyOpts() {
        return $this->options['minify_opts'];
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
            // Relative path
            return $_SERVER['DOCUMENT_ROOT'].'/'.dirname($_SERVER['REQUEST_URI']).'/'.$filename;
        }
        return $_SERVER['DOCUMENT_ROOT'] . '/' . $filename;
    }

}

