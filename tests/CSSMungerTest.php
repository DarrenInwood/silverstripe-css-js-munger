<?php

class CSSMungerTest extends SapphireTest {

    // Tests that the header is output under various circumstances
    public function testHeader() {
        $m = new CSS_Munger(array(
            'cache' => false
        ));
        $m->addFile('/munger/examples/empty.css');
        $m->setDev(false);
        $files = $m->getMungedFiles();
        var_dump( $files );
        
    }
    
    // Tests that the blacklist detects old browsers properly
    public function testBlacklist() {
    
    }    

    // Tests that dev mode treats caching, minification and file combining 
    // correctly
    public function testDevMode() {
    
    }

    // Tests the cache
    public function testCache() {
    
    }

    // Tests that SASS is run on scss files
    public function testSassScss() {
    
    }

    // Tests that the sniffer is run correctly
    public function testSniffer() {
    
    }

    // Tests that minify is run correctly
    public function testMinify() {
    
    }

    // Tests that file combining is run correctly
    public function testFileCombining() {
    
    }
    
}

