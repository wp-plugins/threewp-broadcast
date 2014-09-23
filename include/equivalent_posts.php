<?php

namespace threewp_broadcast;

/**
	@brief		Storage class for equivalent posts on various blogs.
	@since		2014-09-21 11:53:45
**/
class equivalent_posts
{
	/**
		@brief		An array of parent blogs IDs => parent post IDs => child blog IDs => child post IDs.
		@since		2014-09-21 11:55:15
	**/
	public $equivalents;

	/**
		@brief		Constructor.
		@since		2014-09-21 11:55:03
	**/
	public function __construct()
	{
		$this->equivalents = [];
	}

	/**
		@brief		Retrieve the equivalent post on a blog for a specific parent blog/post.
		@since		2014-09-21 11:54:04
	**/
	public function get( $parent_blog, $parent_post, $child_blog )
	{
		if ( ! isset( $this->equivalents[ $parent_blog ][ $parent_post ][ $child_blog ] ) )
			return false;
		return $this->equivalents[ $parent_blog ][ $parent_post ][ $child_blog ];
	}

	/**
		@brief		Set the equivalent post on a blog.
		@since		2014-09-21 11:54:04
	**/
	public function set( $parent_blog, $parent_post, $child_blog, $child_post )
	{
		if ( ! isset( $this->equivalents[ $parent_blog ] ) )
			$this->equivalents[ $parent_blog ] = [];
		if ( ! isset( $this->equivalents[ $parent_blog ][ $parent_post ] ) )
			$this->equivalents[ $parent_blog ][ $parent_post ] = [];
		if ( ! isset( $this->equivalents[ $parent_blog ][ $parent_post ][ $child_blog ] ) )
			$this->equivalents[ $parent_blog ][ $parent_post ][ $child_blog ] = $child_post;
	}
}

