<?php

/**
 * @class Useragent
 *
 * The Useragent class can be checked out out of svn at:
 * http://svn.chrometoaster.com/svn/src/libraries/useragent
 *
 * Provides methods for breaking apart and analysing browser User Agent strings.
 * Hopefully future-proofed.
 * This uses the method from http://www.texsoft.it/index.php?c=software&m=sw.php.useragent&l=it
 * to parse the string into component parts.
 * Browser family is the full string extracted from the User Agent string.  Might be:
 * 'Firefox', 'MSIE', 'Camino', 'Safari', 'Mosaic', 'Galeon', 'Opera', 'Chrome', 'Mozilla', 'Unknown'
 * Platform is the full string of the platform, eg. 'Linux i686' or 'Windows NT 5.1'
 * Short browser is the shortened form:
 * 'ff', 'ie', 'chrome', 'safari'
 * Short platform is the shortened form:
 * 'win', 'mac', 'unix', 'beos', 'amiga', 'os2', 'android', 'iphone'
 */

// Directory to keep cache files in
if ( ! defined('USERAGENT_CACHE_DIR') ) {
    define( 'USERAGENT_CACHE_DIR', dirname(__FILE__).'/cache' );
}

class Useragent {

    /** The user agent string. */
    var $useragent;

    /** Browser family. See class docs for details of what browsers are detected. */
    var $browser;
    /** Browser version as a string. */
    var $version;
    /** Platform. Might be 'unknown', 'mac', 'win', or 'unix' */
    var $platform;

    /** Short name for browser family, eg. 'ff' or 'ie' */
    var $browser_short;
    /** Short name for platform, eg. 'win', 'mac', 'unix' */
    var $platform_short;

    /**
     * The Useragent object for this request.
     * @static
     */
    private static $current;

    /**
     * Returns the current request's Useragent object.
     */
    public static function current() {
        if ( ! is_object(self::$current) ) {
            self::$current = new Useragent();
        }
        return self::$current;
    }

    /**
     * Analyses a user agent string, breaks it down into component parts. This 
     * You should use the static function Useragent::current() to get the 
     * current request's useragent object.
     * 
     * @access private
     * @param $useragent (String) The user agent string presented by the browser.
     *      Default is the $_SERVER['HTTP_USER_AGENT'] value.
     */
    public function __construct($useragent=null) {
        if ( $useragent === null && isset($_SERVER['HTTP_USER_AGENT']) ) {
            $useragent = $_SERVER['HTTP_USER_AGENT'];
        }  
        $this->useragent = $useragent;
    
        // Cached?
        if ( USERAGENT_CACHE_DIR && is_file(USERAGENT_CACHE_DIR.'/uacache_'.md5($useragent)) ) {
            $import = file_get_contents(USERAGENT_CACHE_DIR.'/uacache_'.md5($useragent));
            $import = unserialize($import);
            $this->browser        = $import->browser;
            $this->version        = $import->version;
            $this->platform       = $import->platform;
            $this->browser_short  = $import->browser_short;
            $this->platform_short = $import->platform_short;
            unset($import);
            return;
        }
        
        $this->browser = 'Unknown';
        $this->version = 'Unknown';
        $this->platform = 'Unknown';
        $this->browser_short = '';
        $this->platform_short = '';
        
        $this->parse_user_agent($useragent);

        $this->translate_short_vars();

        if ( USERAGENT_CACHE_DIR ) {
//            file_put_contents(USERAGENT_CACHE_DIR.'/uacache_'.md5($useragent), serialize($this));
        }
    }

    private function parse_user_agent($useragent) {

        $products = $this->extract_product_tokens($useragent);
        $this->extract_product($products);
        $this->extract_version($products);
        $this->extract_platform($products);

    }

    /**
     * Extracts product tokens - product/version (comment) - from a user agent string.
     * @param $useragent (String) The user agent string to extract from.
     * @return (Array) An array of product tokens, each token being of the format
     *          array( product, version, comment )
     */
    private function extract_product_tokens($useragent) {
        $products = array();

        // Regex to extract product, version, and comment from UA strings */
        $pattern  = 
            "([^/[:space:]]*)"      // product token, any chars expect / and whitespace. can be empty.
          . "(/([^[:space:]]*))?"   // optional version, follows / after product token
          . "([[:space:]]*\[[a-zA-Z][a-zA-Z]\])?" // some old browsers but language in brackets
          . "[[:space:]]*"          // eat spaces
          ."(\\((([^()]|(\\([^()]*\\)))*)\\))?" // optional comment inside parentheses, maybe nested parentheses
          . "[[:space:]]*";         // eat trailing spaces
        
        // Extracts all "product/version (comment)" blocks from the UA string 
        while( strlen($useragent) > 0 ) {
            if ($l = ereg($pattern, $useragent, $a)) {
                // product, version, comment
                $products[] = array($a[1], $a[3], $a[6]);  // Comment
                $useragent = substr($useragent, $l);
            } else {
                $useragent = "";
            }
        }    
        return $products;
    
    }
    /**
     * Extracts the browser from a set of product tokens, and sets
     * it as a property of the Useragent object.
     * @param $products (Array) The array of product tokens as output from 
     *          extract_product_tokens()
     */
    private function extract_product($products) {
        if ( empty($products) ) return;        
        
        // these strings are only found in UA strings for that browser - ie. if it says 
        // 'Opera', it ain't lying.
				
        $tmp_prod = null;
        foreach($products as $product) {
            switch($product[0]) {
                // Could turn out to be something else later, but probably right...
                case 'Firefox':
                    $tmp_prod = $product[0];
                break;
                case 'AppleWebKit':
                    $tmp_prod = 'Safari';
                break;
                // these are all definite hits.
                case 'Netscape':
                case 'Navigator':
                case 'Camino':
                case 'Mosaic':
                case 'Galeon':
                case 'Opera':
                case 'Chrome':
                case 'Safari':
                    $this->browser = $product[0];
                    return;
                break;
            }
        }
        if ( $tmp_prod != null ) {
            $this->browser = $tmp_prod;
            return;
        }

        // Mozilla compatible (MSIE, konqueror, etc)
        if ($products[0][0] == 'Mozilla' && !strncmp($products[0][2], 'compatible;', 11)) {
            if ($cl = ereg("compatible; ([^ ]*)[ /]([^;]*).*", $products[0][2], $ca)) {
                $this->browser = $ca[1];
            } else {
                $this->browser = $products[0][0];
            }
        } else {
            $this->browser = $products[0][0];
        }
        return;
    } 

    /**
     * Extracts the version from a set of product tokens, and sets
     * it as a property of the Useragent object.
     * @param $products (Array) The array of product tokens as output from 
     *          extract_product_tokens()
     */
    private function extract_version($products) {
        if ( empty($products) ) return;

        // these strings are only found in UA strings for that browser - ie. if it says 
        // 'Opera', it ain't lying.
        $tmp_ver = null;
        foreach($products as $product) {
            switch($product[0]) {
                // Probables, but later hits should overwrite...
                case 'Firefox':
                    $tmp_ver = $product[1];
                break;
                // Definite hits...
                case 'Netscape':
                case 'Navigator':
                case 'Camino':
                case 'Mosaic':
                case 'Galeon':
                case 'Chrome':
                case 'Version':
                    $this->version = $product[1];
                    return;
                // Special cases.
                case 'Safari';
                    $this->version = $this->get_safari_version($product[1]);
                    return;
                break;
                case 'Opera':
                    if ( $product[1] !== false ) {
                        $tmp_ver = $product[1];
                        break;
                    }
                    $this->version = $this->get_opera_version();
                    return;
                break;
            }
        }
        if ( $tmp_ver != null ) {
            $this->version = $tmp_ver;
            return;
        }

        // Mozilla compatible (MSIE, konqueror, etc)
        if ($products[0][0] == 'Mozilla' && !strncmp($products[0][2], 'compatible;', 11)) {
            if ($cl = ereg("compatible; ([^ ]*)[ /]([^;]*).*", $products[0][2], $ca)) {
                $this->version = $ca[2];
            } else {
                $this->version = $products[0][1];
            }
        } else {
            $this->version = $products[0][1];
        }
        return;
    }        

    /**
     * Extracts the version of a Safari browser.  Historically Safari has used 
     * really crazy version numbers.  Future versions look set to use actual 
     * versions in their User Agent strings.
     * There is pretty much no way to guarantee this will give the right result,
     * short of collecting every user agent string from every release of Safari
     * ever and testing for each one.  :-(  Sometimes later releases had earlier 
     * version numbers etc...
     * @param $version (String) A safari version number, eg. "Safari/125.5.5"
     *          would be 125.5.5
     */
    private function get_safari_version($version) {
        // versions less than 20 should actually be telling the truth.
        if ( (float)$version < 20 ) {
            return $version;
        }
        
        // correct version string => lowest Safari/version number
        $versions = array(
            '1.0' => '85.5',
            '1.0.3' => '85.8',
            '1.2' => '125.0',
            '1.2.2' => '125.7',
            '1.2.3' => '125.9',
            '1.2.4' => '125.12',
            '1.3' => '312.0',
            '1.3.1' => '312.3',
            '1.3.2' => '312.5',
            '2.0' => '412.0',
            '2.0.1' => '412.5',
            '2.0.2' => '416.12',
            '2.0.3' => '417.8',
            '2.0.4' => '419.3'
        );
    
        // default to no change
        $out = $this->version;
        // which of these is the highest version it might be?
        $version = explode('.', $version);
        foreach( $versions as $good => $lowest ) {
            $lowest = explode('.',$lowest);
            if ( count($version) >= 2 && $version[0] >= $lowest[0] && $version[1] >= $lowest[1] ) {
                $out = $good;
            }
        }
        return $out;
    }
     
    /**
     * Opera also uses really daft versions at times...
     */
    private function get_opera_version() {
        preg_match('/Opera ([0-9.]+)/', $this->useragent, $matches);
        if ( count($matches) < 2 ) {
            return $this->version;
        }
        return $matches[1];
    }
    
     
    /**
     * Extracts the operating system from the product tokens, sets this as a property of
     * the Useragent object.
     */
    private function extract_platform($products) {
        if ( empty($products) ) return;
        $product = $products[0];
        $os_list = array();
        // Strings that indicate a particular OS...
        $oses = array(
            'Linux' => 'unix',
            'Macintosh' => 'mac',
            'Mac OS X' => 'mac',
            'PowerPC' => 'mac',
            'FreeBSD' => 'unix',
            'NetBSD' => 'unix',
            'OpenBSD' => 'unix',
            'SunOS' => 'unix',
            'Amiga' => 'amiga',
            'BeOS' => 'beos',
            'IRIX' => 'unix',
            'OS/2' => 'os2',
            'Warp' => 'os2'
        );
        // Strings that definitely indicate an OS...
        $def_oses = array(
            'iPhone' => 'iphone',
            'Android' => 'android',
            'iPad' => 'ipad',
            'iPod' => 'ipod'
        );
        // Look at each element in comment separated by semicolon
        // Test each one for certain strings, if it exists add to the 'possible' list
        foreach( explode(';', $product[2]) as $element ) {
            $element = trim($element);
            // Is it Windows ME?
            if ( $element == 'Win 9x 4.90' ) {
                $this->platform = 'Windows ME';
                return;
            }
            if ( strtolower(substr($element, 0, 3)) == 'win' ) {
                $os_list[] = $element;
                continue;
            }
            // Did we find anything certain?
            foreach( $def_oses as $test => $os ) {
                if ( strpos($element, $test) !== false ) {
                    $this->platform = $element;
                    return;
                }
            }
            // Did we find anything possible?
            foreach( $oses as $test => $os ) {
                if ( strpos($element, $test) !== false ) {
                    $os_list[] = $element;
                    continue;
                }
            }
        }
        if ( count($os_list) > 0 ) {
            // guess it's the first one.
            $this->platform = $os_list[0];
            return;
        }
    }

    /** Translates the extracted strings into short forms for standard detection
     * and use as a browser sniffer
     */
    private function translate_short_vars() {
        // Strings that indicate a particular browser...
				// TODO: fix for Netscape, which has an empty browser_short value
        $browsers = array(
            'MSIE' => 'ie',
            'Firefox' => 'ff',
            'Safari' => 'safari',
            'Chrome' => 'chrome',
            'Opera' => 'opera',
            'Camino' => 'camino',
						'Netscape' => 'netscape'
        );

        foreach ( $browsers as $test => $browser ) {
            if ( strpos($this->browser, $test) !== false ) {
                $this->browser_short = $browser;
            }
        }								
    
        // Strings that indicate a particular OS...
        $platforms = array(
            'Win' => 'win',
            'Linux' => 'unix',
            'Macintosh' => 'mac',
            'Mac OS X' => 'mac',
            'PowerPC' => 'mac',
            'FreeBSD' => 'unix',
            'NetBSD' => 'unix',
            'OpenBSD' => 'unix',
            'SunOS' => 'unix',
            'Amiga' => 'amiga',
            'BeOS' => 'beos',
            'IRIX' => 'unix',
            'OS/2' => 'os2',
            'Warp' => 'os2',
            'iPhone' => 'iphone',
            'Android' => 'android',
            'iPad' => 'ipad',
            'iPod' => 'ipod'
        );

        foreach ( $platforms as $test => $platform ) {
            if ( strpos($this->platform, $test) !== false ) {
                $this->platform_short = $platform;
            }
        }

    }

}

