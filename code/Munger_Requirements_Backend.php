<?php

/**
 * Replacement Requirements backend that also runs .scss files through SASS,
 * adds serverside User Agent string based browser detection, and sniffing CSS
 * files for special comments.  See docs for usage.
 *
 * @package munger-silverstripe-module
 * @author Darren Inwood <darren.inwood@chrometoaster.com>
 */

class Munger_Requirements_Backend extends Requirements_Backend {

    private static $cache = true;
    private static $sniffer = false;
    private static $sass = false;
    private static $minify = false;
    private static $isDev = false;
    private static $jsOpts = array();
    private static $cssOpts = array();

    public static function setCache($val) { self::$cache = (bool)$val; }
    public static function setSniffer($val) { self::$sniffer = (bool)$val; }
    public static function setSass($val) { self::$sass = (bool)$val; }
    public static function setMinify($val) { self::$minify = (bool)$val; }
    public static function setDev($val) { self::$isDev = (bool)$val; }

    public static function enable() {
        $backend = new Munger_Requirements_Backend();
        $current = Requirements::backend();
        $backend->set_combined_files_enabled(
            $current->get_combined_files_enabled()
        );
        $backend->set_suffix_requirements(
            $current->get_suffix_requirements()
        );
        $backend->write_js_to_body = $current->write_js_to_body;
        Requirements::set_backend( $backend );

        // Fix for TinyMCE trying to load everything from /assets/_combinedfiles 
        // instead of /sapphire/thirdparty/tinymce
        // If you know you don't need this, add Requirements::block('munger_tinymce_fix') to Page::init()
        Requirements::customScript(
            "if ( typeof(tinymce) != 'undefined' ) tinymce.baseURL = '".Director::absoluteBaseURL()."sapphire/thirdparty/tinymce';",
            'munger_tinymce_fix'
        );
    }

    /**
     * Uses CSS_Munger and JS_Munger to process combined files instead of 
     * the inbuilt process_combined_files function.
     */
    public function process_combined_files() {
        // Set up where to store munged files
		$cache_dir = str_replace(DIRECTORY_SEPARATOR, '/', Director::baseFolder()) 
		    . '/' . $this->getCombinedFilesFolder();
        // Do we need to flush the cache?  (?flush=1)
        if ( isset($_REQUEST['flush']) ) {
            Filesystem::removeFolder($cache_dir, true); // second arg means "contents only"
        }
		if ( !file_exists($cache_dir) ) {
		    Filesystem::makeFolder($cache_dir);
		}

        // Make a map of which JS files are combined and which aren't
        $js_combine_map = array();
        foreach( $this->javascript as $js_file => $dummy ) {
            $js_combine_map[$js_file] = false;
            if ( $this->get_combined_files_enabled() ) {
                foreach( $this->combine_files as $label => $files ) {
                    if ( in_array($js_file, $files) ) {
                        $js_combine_map[$js_file] = $label;
                    }
                }
            }
        }

        // Process javascript
        $new_js = array();
        $already_combined = array();
        foreach( $this->javascript as $js_file => $dummy ) {
						if ( strpos($js_file, 'sapphire/thirdparty/tinymce') !== false ) {
							$new_js[$js_file] = true;
							continue;
						}
            $munge_files = array($js_file);
            $output_filename = str_replace('/','_',$js_file);
            if ( $js_combine_map[$js_file] ) {
                if ( in_array($js_combine_map[$js_file], $already_combined) ) {
                    continue;
                }
                $munge_files_tmp = $this->combine_files[$js_combine_map[$js_file]];
                // $this->combine_files isn't in the correct order, so we have to reorder according to $this->javascript
                $munge_files = array();
                foreach( $this->javascript as $munge_file => $dummy ) {
                    if ( in_array($munge_file, $munge_files_tmp) ) $munge_files[] = $munge_file;
                }
                $output_filename = $js_combine_map[$js_file];
                $already_combined[] = $js_combine_map[$js_file];
            }
            require_once( dirname(__FILE__).'/JS_Munger.php' );
            $js = new JS_Munger(array(
                'cache' => self::$cache,
                'cache_dir' => $cache_dir,
                'combine' => $this->get_combined_files_enabled(),
                'combined_filename' => $output_filename,
                'jsmin' => self::$minify
            ));
            $js->setDev( self::$isDev );
            foreach(array_diff($munge_files, $this->blocked) as $file) {
                $js->addFile($file);
            }
            foreach( $js->getMungedFiles() as $file ) {
                $output_filename = $this->getAbsoluteURL($file);
                $new_js[$output_filename] = true;
            }
        }
        $this->javascript = $new_js;
        // Make a map of which CSS files are combined and which aren't
        $css_combine_map = array();
        foreach( $this->css as $css_file => $params ) {
            $css_combine_map[$css_file] = false;
            foreach( $this->combine_files as $label => $files ) {
                if ( in_array($css_file, $files) ) {
                    $css_combine_map[$css_file] = $label;
                }
            }
        }
        
        // Process CSS
        $new_css = array();
        $already_combined = array();
        foreach( $this->css as $css_file => $params ) {
						if ( strpos($js_file, 'sapphire/thirdparty/tinymce') !== false ) {
							$new_css[$js_file] = array('media' => 'all');
							continue;
						}
            $munge_files = array($css_file);
            $output_filename = basename($css_file);
            if ( substr($output_filename, -5) == '.scss' ) {
                $output_filename = str_replace('.scss', '.css', $output_filename);
            }
            if ( $css_combine_map[$css_file] ) {
                if ( in_array($css_combine_map[$css_file], $already_combined) ) {
                    continue;
                }
                $munge_files = $this->combine_files[$css_combine_map[$css_file]];
                $output_filename = $css_combine_map[$css_file];
                $already_combined[] = $css_combine_map[$css_file];
            }
            require_once( dirname(__FILE__).'/CSS_Munger.php' );
            $css = new CSS_Munger(array(
                'cache' => self::$cache,
                'cache_dir' => $cache_dir,
                'combine' => $this->get_combined_files_enabled(),
                'combined_filename' => $output_filename,
                'sniffer' => self::$sniffer,
                'sass' => self::$sass,
                'minify' => self::$minify
            ));
            $css->setDev( self::$isDev );
            foreach(array_diff($munge_files, $this->blocked) as $file) {
                $css->addFile($file, $this->css[$file]['media']);                    
            }
            foreach( $css->getMungedFiles() as $file => $media ) {
                $output_filename = $this->getAbsoluteURL($file) . '?c='.md5(serialize($css->options));
                $new_css[$output_filename] = array('media' => $media);
            }
        }
        $this->css = $new_css;
    }


    /**
     * Update Requirements::combine_files function to retain media association for
     * css files.
     * 
     * Use an associative array of type 'filename' => 'media' to keep this association.     
     *
	 * @param string $combinedFileName Filename of the combined file (will be stored in {@link Director::baseFolder()} by default)
	 * @param array $files Array of filenames relative to the webroot
	 */
	public function combine_files($combinedFileName, $files) {
        if ( !is_array($files) || count($files) == 0 ) return;

        // Prepare file list to send to parent combine_files
        $combineFiles = array();
        foreach( $files as $key => $value ) {
            if ( substr($value, -3) == 'css' && is_numeric($key) ) {
                $combinedFiles[$value] = 'all';
            } else if ( substr($key, -3) == 'css' ) {
                $combinedFiles[$key] = $value;
            } else {
                $combinedFiles[$value] = true;
            }
        }

		parent::combine_files($combinedFileName, array_keys($combinedFiles));

        // Reattach media type
		foreach( $combinedFiles as $file => $media ) {
		    if(substr($file, -2) == 'js') {
			    continue;
		    } elseif(substr($file, -3) == 'css') {
			    $this->css[$file]['media'] = $media;
		    }
        }
	}

    /**
     * Converts a full filesystem path into a URL relative to the site root.
     * We have to leave the initial slash off or SS gets grumpy.
     *
     * @param $filepath String  the full filesystem path to turn into a URL.
     * @return  String  The URL relative to the site root that the given file
     * can be accessed at.
     */
    public function getAbsoluteURL($filepath) {
        if ( strpos($filepath, $_SERVER['DOCUMENT_ROOT']) !== false ) {
            $filepath = str_replace($_SERVER['DOCUMENT_ROOT'], '', $filepath);
        }
        if ( substr($filepath, 0, 1) == '/' ) {
            $filepath = substr($filepath, 1);
        }
        return $filepath;
    }

	function debug() {
		Debug::show($this->javascript);
		Debug::show($this->css);
		Debug::show($this->customCSS);
		Debug::show($this->customScript);
		Debug::show($this->customHeadTags);
		Debug::show($this->combine_files);
	}

}

