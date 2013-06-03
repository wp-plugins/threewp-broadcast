<?php

/**
	@brief		Container used when broadcasting data.
	@details

	This is general purpose container for storing and working with all the data necessary to broadcast posts.

	@since		20130530
	@version	20130530
**/
class Broadcasting_Data
{
	/**
		@brief		ID of blogs being worked on.
		@details

		A stdClass consisting of:
		- ->parent The parent blog from which the post is being broadcasted.
		- ->child The current child.
		- ->children An array of blog_ids we will be broadcasting to.

		@var		$blog_id
		@since		20130530
	**/
	public $blog_id;

	public function __call( $name, $parameters )
	{
		if ( count( $parameters ) < 1 )
			return ( isset( $this->$name ) ? $this->$name : null );
		$this->$name = reset( $parameters );
		return $this;
	}

	public function __construct()
	{
		$this->blog_id = new \stdClass();
		$this->blog_id->children = array();
	}
}

