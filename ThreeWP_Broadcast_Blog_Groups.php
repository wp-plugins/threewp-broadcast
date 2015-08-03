<?php
/*
Author:			edward_plainview
Author Email:	edward@plainviewplugins.com
Author URI:		https://plainviewplugins.com
Description:	Obsolete. Use Blog Groups 2 in the pack. Allows users to create blog groups to ease blog selection when broadcasting.
Plugin Name:	ThreeWP Broadcast Blog Groups
Plugin URI:		https://plainviewplugins.com/threewp-broadcast/
Version:		23
*/

require_once( __DIR__ . '/vendor/autoload.php' );

/**
	@brief		Return the current instance of the Blog Groups plugin.
	@since		2014-10-18 14:52:30
**/
function ThreeWP_Broadcast_Blog_Groups()
{
	return threewp_broadcast\blog_groups\ThreeWP_Broadcast_Blog_Groups::instance();
}

$threewp_broadcast_blog_groups = new threewp_broadcast\blog_groups\ThreeWP_Broadcast_Blog_Groups();
