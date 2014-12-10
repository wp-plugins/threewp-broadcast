<?php
/*
Author:			edward_plainview
Author Email:	edward@plainview.se
Author URI:		http://www.plainview.se
Description:	Allows users to create blog groups to ease blog selection when broadcasting.
Plugin Name:	ThreeWP Broadcast Blog Groups
Plugin URI:		http://plainview.se/wordpress/threewp-broadcast/
Version:		14
*/

require_once( 'vendor/autoload.php' );

/**
	@brief		Return the current instance of the Blog Groups plugin.
	@since		2014-10-18 14:52:30
**/
function ThreeWP_Broadcast_Blog_Groups()
{
	return threewp_broadcast\blog_groups\ThreeWP_Broadcast_Blog_Groups::instance();
}

$threewp_broadcast_blog_groups = new threewp_broadcast\blog_groups\ThreeWP_Broadcast_Blog_Groups();
