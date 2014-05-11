<?php

namespace threewp_broadcast\actions;

/**
	@brief		Allow plugins to return an array of post types that Broadcast may broadcast.
	@since		2014-02-22 10:32:19
**/
class get_post_types
	extends action
{
	/**
		@brief		An array of post types that Broadcast may broadcast.
		@since		2014-02-22 10:31:55
	**/
	public $post_types = [];
}
