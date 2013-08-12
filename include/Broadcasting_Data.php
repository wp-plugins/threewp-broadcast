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
		@brief		The _POST array.
		@var		$_POST
		@since		20130603
	**/
	public $_POST;

	/**
		@brief		Array of AttachmentData objects for this post.
		@var		$attachment_data
		@since		20130603
	**/
	public $attachment_data;

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

	/**
		@brief		True if new taxonomies are to be created on the child blogs.
		@var		$create_taxonomies
		@since		20130603
	**/
	public $create_taxonomies;

	/**
		@brief		True if custom fields are to be broadcasted.
		@var		$custom_fields
		@since		20130603
	**/
	public $custom_fields;

	/**
		@brief		True if the broadcaster wants to link this post to the child blog posts,
		@var		$link
		@since		20130603
	**/
	public $link;

	/**
		@brief		The ID of the parent post.
		@var		$parent_post_id
		@since		20130603
	**/
	public $parent_post_id;

	/**
		@brief		The post WP_Post object.
		@var		$post
		@since		20130603
	**/
	public $post;

	/**
		@brief		Convenience: the type of post (post, page, etc).
		@var		$post_type
		@since		20130603
	**/
	public $post_type;

	/**
		@brief		True if the post type supports a hierarchy.
		@var		$post_type_is_hierarchical
		@since		20130603
	**/
	public $post_type_is_hierarchical;

	/**
		@brief		True if the parent post is marked as sticky.
		@var		$post_is_sticky
		@since		20130603
	**/
	public $post_is_sticky;

	/**
		@brief		The post type object retrieved from get_post_type_object().
		@var		$post_type_object
		@since		20130603
	**/
	public $post_type_object;

	/**
		@brief		True if the post type supports custom fields.
		@var		$post_type_supports_custom_fields
		@since		20130603
	**/
	public $post_type_supports_custom_fields;

	/**
		@brief		True if the post type supports thumbnails.
		@var		$post_type_supports_thumbnails
		@since		20130603
	**/
	public $post_type_supports_thumbnails;

	/**
		@brief		True if taxonomies are to be broadcasted to the child blogs.
		@var		$taxonomies
		@since		20130603
	**/
	public $taxonomies;

	/**
		@brief		The wp_upload_dir() of the parent blog.
		@var		$upload_dir
		@since		20130603
	**/
	public $upload_dir;

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

