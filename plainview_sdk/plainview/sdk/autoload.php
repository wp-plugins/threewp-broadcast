<?php

// Check for PHP v5.4
$version = '5.4.0';
if ( version_compare(PHP_VERSION, $version) < 0 )
	die( "The Plainview SDK requires PHP version $version." );

// And now require the autoloader
require_once( __DIR__ . '/autoload/vendor/autoload.php' );
