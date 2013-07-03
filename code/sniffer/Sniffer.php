<?php

require_once( dirname(dirname(__FILE__)).'/useragent/Useragent.php' );

/**
 * The Sniffer goes through a CSS (or any other) file line-by-line, and detects
 * special sniffing comments denoted by double asterisks at start and end.  The
 * developer can include various tokens in the comment that instruct the Sniffer 
 * as to whether to include this line dependant on various browser conditions.
 *
 * For example, you can specify to only output certain CSS rules for IE, or 
 * Safari 3.
 *
 * Usage:
 * <code>
 * $s = new Sniffer();
 * $s->loadFile('/name/of/file');
 * $s->toFile('/name/of/file');  // writes to the specified file
 * 
 * $css = file_get_contents('/name/of/file');
 * $s = new Sniffer();
 * $s->loadString($css);
 * echo $s->toString();
 *
 * $if = fopen('/name/of/file');
 * $s->loadFile($if);
 * $of = $s->toFile(tmpfile(), true);
 * 
 */

class Sniffer {

    public $options = array(
        // Cache
        'cache' => true,
        'cache_dir' => null // set in constructor to current directory
    );

    /**
     * A file handle resource to the input content.
     * @access private
     */
    private $input_resource;

    /**
     * Filename to allow modification time based caching to work.
     */
    public $input_filename;
    
    /**
     * A file handle resource to the output.
     * @access private
     */
    private $output_resource;

    /**
     * The Useragent instance for this request.
     * @static
     * @access private
     */
    private static $useragent = null;

    /**
     * Any 'categories' set up inside the file being parsed.
     * @access private
     */
    private $categories = array();

    public function __construct($options=array()) {
        $this->options['cache_dir'] = dirname(__FILE__);
        $this->options = array_merge($this->options, $options);
    }

    /**
     * Loads a given filename into the Sniffer.
     *
     * @param $file String     The full filesystem path to the input file.
     */
    public function loadFile($file) {
        if ( !is_string($file) || !file_exists($file) ) {
            throw new Exception('Filename expected - got '.gettype($file).'.');
        }
        $this->input_resource = @fopen($file, 'r');
        $this->input_filename = $file;
    }
    
    /**
     * Loads a string into the sniffer.
     * @param $string String    A string containing the file contents to sniff.
     * @param $file   String    The full filesystem path to the source file.
     *                          Needs to be used for caching to detect file 
     *                          modification time if you are using this method.
     */
    public function loadString($string, $file) {
        if ( ! is_string($string) ) {
            throw new Exception('String expected - got '.gettype($string).'.');
        }
        $this->input_resource = fopen('data://text/plain,'.$string, 'r');
        $this->input_filename = $file;
    }
    
    /**
     * Returns the sniffed content as a string.
     */
    public function toString() {
        $this->output_resource = fopen('data://text/plain,', 'w+');
        $this->sniff();
        rewind($this->output_resource);
        $out = stream_get_contents($this->output_resource);
        fclose($this->output_resource);
        return $out;
    }

    /**
     * Outputs the sniffed content to a file.
     *
     * @param $file String     The full filesystem path to the output file to 
     * write to.
     * @param $return Boolean   True indicates to return a file handle to the 
     * written file.  False will fclose the file.  Default is false.
     * @return Resource     The file handle to the output file if $return is
     * true.
     */
    public function toFile($file, $return=false) {
        if ( ! is_string($file) ) {
            throw new Exception('Filename expected - got '.gettype($file).'.');
        }
        $this->output_resource = @fopen($file, 'w+');
        $this->sniff();
        if ( $return ) {
            return $this->output_resource;
        }
        fclose($this->output_resource);
    }

    /* Sniffs the user's browser, and outputs css relevant to the browser
     * determined by comments in the css source.
     * Comments should start with /** - there are two types of comment, category
     * definitions and conditions.
     * You can list as many conditions on a line as you like.  If any single 
     * condition is met, the line is included. Conditions are separated with spaces.
     * Any conditions that are not recognised are ignored, allowing you to mix
     * conditions with regular comments easily.
     * Note that you can't have two comments on the same line.
     * The simplest condition is the name of a browser, eg. /** ie ** /. You can
     * also state a version eg. /** ie5 ** /, a range of versions eg. /** ie3-5 ** /
     * or even anything greater than a given version eg. /** ie5.5+ ** /
     * You can specify the platform for a given string using the ^ symbol, eg.
     * /** ie5.5^mac ** /
     * Category definitions allow you to define a set of conditions that you can
     * later refer to by the category name.  To define a category:
     * /** foo : ie5.01 ie5.5 ie6 ** /
     * You can now use foo as a condition.
     * Putting a ! symbol before any condition or category will negate it, so to
     * include a line for all browsers other than ie6 you would use /** !ie6 ** /
     */
    private function sniff() {
        // Cache?
        $cache_filename = $this->getCacheFilename();
        if ( $this->options['cache'] === true && file_exists($cache_filename) && filemtime($cache_filename) > filemtime($this->input_filename) ) {
            fwrite( $this->output_resource, file_get_contents($cache_filename) );
            return;
        }
        while( $line = fgets($this->input_resource) ) {
            $line = explode('/** ', $line);
            if ( count($line) == 1 ) {
                fwrite($this->output_resource, $line[0]);
                continue;
            }
            // Is this a category definition?
            if ( strpos($line[1], ':') !== false ) {
                $out .= $line[0]."\n";
                $line = explode(':', $line[1]);
                $categories = array();
                foreach( explode(' ',trim($line[1])) as $cell ) {
                    if ( strpos($cell,'*/') !== false ) continue;
                    $categories[] = $this->make_rule($cell);
                }
                $this->categories[trim($line[0])] = $categories;
                continue;
            }
            // Find rules to match
            $rules = array();
            foreach( explode(' ',trim($line[1])) as $cell ) {
                if ( strpos($cell, '*/') !== false ) continue;
                $rules[] = $this->make_rule($cell);
            }
            // Test rules
            $pass = false;
            foreach( $rules as $rule ) {
                $pass = $pass || $this->test_rule($rule);
            }
            if ( $pass == true ) {
                fwrite($this->output_resource, $line[0]."\n");
            }
        }
        // Cache?
        if ( $this->options['cache'] ) {
            rewind($this->output_resource);
            file_put_contents(
                $this->getCacheFilename(),
                $this->output_resource
            );
        }
    }

    /* Makes a css marker rule array out of a string. */
    private function make_rule($string) {
        preg_match('/(!?)([a-z]*)([0-9\.]*)([\+-]?)([0-9\.]*)((\^[a-z]*)?)/',$string,$match);
        return $match;
    }
    
    /* Tests a rule and negates it if needed. Saves a lot of hassle internally.
     */
    private function test_rule($rule) {
        $pass = $this->_test_rule($rule);
        if ( $rule[1] == '!' ) {
            $pass = !$pass;
        }
        return $pass;
    }
    
    /* Tests a css marker rule to see if the line should be included in the 
     * output css or not */
    private function _test_rule($rule) {
        $ua = $this->getUseragent();
        $browsers = array(
            'MSIE' => 'ie',
            'Firefox' => 'ff',
            'Safari' => 'safari',
            'Chrome' => 'chrome',
            'Opera' => 'opera',
            'Camino' => 'camino',
            'Netscape' => 'netscape'
        );
        // is it a category? you can make a circular rule here :-(
        if ( array_key_exists($rule[2], $this->categories) ) {
            $pass = false;
            foreach( $this->categories[$rule[2]] as $cat_rule ) {
                $pass = $this->test_rule($cat_rule) || $pass;
            }
            return $pass;
        }

        if ( in_array($rule[2], $browsers) ) {
            // browser rule
            if ( $rule[2] != $ua->browser_short ) {
                // wrong browser type
                return false;
            }
            // platform
            if ( $rule[6] != '' && ( '^'.$ua->platform_short != $rule[6] ) ) {
                return false;
            }
            if ( $rule[3] == '' ) {
                // no version to check
                return true;
            }
            if ( (float)$ua->version < (float)$rule[3] ) {
                // version too low
                return false;
            }
            if ( $rule[4] == '' && (float)$ua->version != (float)$rule[3] ) {
                // specific version stated, and it's not this version
                return false;
            }
            if ( $rule[4] == '-' && (float)$ua->version > (float)$rule[5] ) {
                // browser range specified and this one is too high (too low is caught above)
                return false;
            }
            return true;
        }
        // unknown - ignore
        return false;
    }

    /**
     * Returns the Useragent object for this Sniffer.  If one has not been set, 
     * gets a Useragent object for the browser used to make the current request. 
     */
    public function getUseragent() {
        if ( ! self::$useragent ) {
            self::$useragent = new Useragent();
        }
        return self::$useragent;
    }

    /**
     * Sets the Useragent for this sniffer.  Use before toString() or toFile()
     * to sniff using a useragent other than the current request.
     */
    public function setUseragent($useragent) {
        self::$useragent = $useragent;
    }

    /**
     * Returns the cache filename for the given useragent/file combination
     * @return String   The full filesystem path to the cache file to use for 
     * the loaded CSS and useragent.
     */
    private function getCacheFilename() {
        $hash = md5(
            $this->input_filename
            . $this->getUseragent()->browser
            . $this->getUseragent()->version
            . $this->getUseragent()->platform
            . serialize($this->options)
        );
        return realpath($this->options['cache_dir']) . DIRECTORY_SEPARATOR . 'sniffer_' . $hash;
    }

}

