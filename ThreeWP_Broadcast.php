<?php
/*
Author:			edward_plainview
Author Email:	edward@plainviewplugins.com
Author URI:		https://plainviewplugins.com
Description:	Broadcast / multipost a post, with attachments, custom fields, tags and other taxonomies to other blogs in the network.
Plugin Name:	ThreeWP Broadcast
Plugin URI:		https://plainviewplugins.com/threewp-broadcast/
Version:		24
*/

DEFINE( 'THREEWP_BROADCAST_VERSION', 24 );

require_once( __DIR__ . '/vendor/autoload.php' );

/**
	@brief		Return the instance of ThreeWP Broadcast.
	@since		2014-10-18 14:48:37
**/
function ThreeWP_Broadcast()
{
	return threewp_broadcast\ThreeWP_Broadcast::instance();
}

// For compatability with old SDK
if ( ! class_exists( '\\plainview\\sdk\\collections\collection' ) )
	require_once( 'src/old_sdk/collection.php' );

$threewp_broadcast = new threewp_broadcast\ThreeWP_Broadcast();
