<?php

namespace threewp_broadcast;

/**
	@brief		Container used when broadcasting data.
	@details

	This is general purpose container for storing and working with all the data necessary to broadcast posts.

	@since		20130530
	@version	20131015
**/
class broadcasting_data
{
	/**
		@brief		IN: The _POST array.
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
		@brief		IN: Array of child blog objects to which to broadcast.
		@var		$blogs
		@since		20130927
	**/
	public $blogs = [];

	/**
		@brief		OPTIONAL IN: Broadcast data object.
		@details	If this is left to null, and linking is enabled, broadcast will retrieve the broadcast data automatically during broadcast_post().
		@since		2014-08-31 18:50:10
	**/
	public $broadcast_data;

	/**
		@brief		The ID of the child blog we are currently working on.
		@var		$current_child_blog_id
		@since		20130927
	**/
	public $current_child_blog_id;

	/**
		@brief		IN: True if custom fields are to be broadcasted.
		@details	If true then the broadcasting trait will convert it to an object that contains info about the various custom field options.
		@var		$custom_fields
		@since		20130603
	**/
	public $custom_fields = false;

	/**
		@brief		Delete the attachments of all linked child posts.
		@details	This is normally only a problem when several posts share the exact same attachments between them.
		@since		2014-06-20 11:58:18
	**/
	public $delete_attachments = true;

	/**
		@brief		Storage for equivalent post IDs on various blogs.
		@details	Used as a "seen" lookup table to prevent looping.
		@see		equivalent_posts
		@since		2014-09-21 12:55:22
	**/
	public $equivalent_posts;

	/**
		@brief		IN: True if the broadcaster wants to link this post to the child blog posts,
		@var		$link
		@since		20130603
	**/
	public $link = false;

	/**
		@brief		IN: The meta box data presented to the user.
		@var		$meta_box_data
		@since		20131015
	**/
	public $meta_box_data = false;

	/**
		@brief		Was a new child created on this blog?
		@details

		This variable is reset to false upon switching child blogs.

		If Broadcast created a new child on the blog, it will set this property to true.

		@since		2015-01-20 14:10:09
	**/
	public $new_child_created = false;

	/**
		@brief		The child post object during each blog loop.
		@since		2015-05-06 17:28:29
	**/
	public $new_post;

	/**
		@brief		An array of the custom fields of each child before deletion.
		@since		2015-05-06 17:29:25
	**/
	public $new_post_old_custom_fields;

	/**
		@brief		IN: The ID of the parent blog.
		@var		$parent_blog_id
		@since		20130927
	**/
	public $parent_blog_id;

	/**
		@brief		IN: The ID of the parent post.
		@var		$parent_post_id
		@since		20130603
	**/
	public $parent_post_id;

	/**
		@brief		IN: The parent post WP_Post object.
		@var		$post
		@since		20130603
	**/
	public $post;

	/**
		@brief		IN: True if the post type supports a hierarchy.
		@var		$post_type_is_hierarchical
		@since		20130603
	**/
	public $post_type_is_hierarchical = false;

	/**
		@brief		True if the parent post is marked as sticky.
		@var		$post_is_sticky
		@since		20130603
	**/
	public $post_is_sticky = false;

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
	public $post_type_supports_custom_fields = false;

	/**
		@brief		True if the post type supports thumbnails.
		@var		$post_type_supports_thumbnails
		@since		20130603
	**/
	public $post_type_supports_thumbnails = false;

	/**
		@brief		IN: True if taxonomies are to be broadcasted to the child blogs.
		@var		$taxonomies
		@since		20130603
	**/
	public $taxonomies = false;

	/**
		@brief		IN: The wp_upload_dir() of the parent blog.
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

	public function __construct( $options = [] )
	{
		$this->equivalent_posts = new equivalent_posts();

		// Import any known values from the options object.
		foreach( (array)$options as $key => $value )
			if ( property_exists( $this, $key ) )
				$this->$key = $value;

		// Clear the blogs.
		$this->blogs = new blog_collection;
	}

	/**
		@brief		Add a blog or blogs to which to broadcast.
		@param		mixed		$blog_id		A broadcast_data\blog object or an array of such objects.
		@return		this						Method chaining.
		@since		20130928
	**/
	public function broadcast_to( $blog )
	{
		// Convert into an array.
		$blogs = blog_collection::make( $blog );

		foreach( $blogs as $blog )
			$this->blogs->put( $blog->id, $blog );

		return $this;
	}

	/**
		@brief		Return the custom fields helper.
		@since		2015-06-06 09:03:08
	**/
	public function custom_fields()
	{
		return new \threewp_broadcast\broadcasting_data\Custom_Fields( $this );
	}

	/**
		@brief		Return the equivalent post IDs collection.
		@since		2014-09-21 11:36:12
	**/
	public function equivalent_posts()
	{
		return $this->equivalent_posts;
	}

	/**
		@brief		Convenience method to query whether there are child blogs to be broadcasted to.
		@return		bool		True if there are child blogs to be broadcasted to.
		@since		20130928
	**/
	public function has_blogs()
	{
		return count( $this->blogs ) > 0;
	}

	/**
		@brief		Return the new post, or a key thereof, as an object.
		@since		2014-05-20 19:00:16
	**/
	public function new_post( $key = null )
	{
		if ( $key === null )
			return $this->new_post;
		else
			return $this->new_post->$key;
	}
}
