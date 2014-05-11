<?php

namespace threewp_broadcast\filters;

use \threewp_broadcast\blog_collection;

class get_user_writable_blogs
	extends filter
{
	/**
		@brief		OUTPUT: A collection of blogs the user has access to.
		@var		$blogs
		@since		20131003
	**/
	public $blogs;

	/**
		@brief		INPUT: ID of user to query.
		@var		$user_id
		@since		20131003
	**/
	public $user_id;

	public function _construct( $user_id = null )
	{
		$this->blogs = new blog_collection;
		$this->user_id = $user_id;
	}
}
