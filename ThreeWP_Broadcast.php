<?php
/*
Author:			edward_plainview
Author Email:	edward@plainview.se
Author URI:		http://www.plainview.se
Description:	Broadcast / multipost a post, with attachments, custom fields, tags and other taxonomies to other blogs in the network.
Plugin Name:	ThreeWP Broadcast
Plugin URI:		http://plainview.se/wordpress/threewp-broadcast/
Version:		11
*/

DEFINE( 'THREEWP_BROADCAST_VERSION', 11 );

require_once( 'vendor/autoload.php' );

/**
	@brief		Return the instance of ThreeWP Broadcast.
	@since		2014-10-18 14:48:37
**/
function ThreeWP_Broadcast()
{
	return threewp_broadcast\ThreeWP_Broadcast::instance();
}

$threewp_broadcast = new threewp_broadcast\ThreeWP_Broadcast();
