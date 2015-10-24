<?php

namespace threewp_broadcast;

use \Exception;
use \threewp_broadcast\actions;
use \threewp_broadcast\broadcast_data\blog;

/**
	@brief		This is general purpose container for storing and working with all the data necessary to broadcast posts.
	@details

	This object is easiest built with make().

	Variables:
		* IN =  Required property.
		* [IN] = Optional property.
		* OUT = Output property.

	If you're not using make(), at the very least specify the $parent_post_id in the constructor. The $meta_box_data, if not specified,
	will be automatically created and prepared. After constructing, you are free to modify any other variables needed.

	If you're accepting user input in the form of a meta box, allow Broadcast itself to validate the $_POST data by issuing a prepare_broadcasting_data action.
	Otherwise you're free to skip prepare if you're validating the _POST (if any) and checking access roles yourself.

	@since		20130530
	@version	20131015
**/
class broadcasting_data
{
	/**
		@brief		[IN]: The _POST array.
		@details	Assumes the $_POST array upon construction.
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
		@brief		[IN]: Array of child blog objects to which to broadcast.
		@details	Set by prepare_broadcasting_data action, or set by broadcast_to() method.
		@var		$blogs
		@see		broadcast_to();
		@since		20130927
	**/
	public $blogs = [];

	/**
		@brief		[IN]: Broadcast data object.
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
		@brief		[IN]: True if custom fields are to be broadcasted.
		@details	If true then the broadcasting trait will convert it to an object that contains info about the various custom field options.

		custom_fields->blacklist contains the list from the settings.
		custom_fields->original contains the array of custom fields of the original post.
		custom_fields->protectlist contains the list from the settings.
		custom_fields->whitelist contains the list from the settings.

		@var		$custom_fields
		@since		20130603
	**/
	public $custom_fields = true;

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
		@brief		[IN]: True if the broadcaster wants to link this post to the child blog posts,
		@var		$link
		@since		20130603
	**/
	public $link = true;

	/**
		@brief		[IN]: The meta box data presented to the user.
		@var		$meta_box_data
		@since		20131015
	**/
	public $meta_box_data;

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
		@brief		[IN]: The ID of the parent blog.
		@details	Assumes the current blog.
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
		@brief		[IN]: The parent post WP_Post object.
		@var		$post
		@since		20130603
	**/
	public $post;

	/**
		@brief		True if the post type supports a hierarchy.
		@details	Set by prepare_broadcasting_data action.
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
		@details	Set by prepare_broadcasting_data action.
		@var		$post_type_object
		@since		20130603
	**/
	public $post_type_object;

	/**
		@brief		True if the post type supports custom fields.
		@details	Set by prepare_broadcasting_data action.
		@var		$post_type_supports_custom_fields
		@since		20130603
	**/
	public $post_type_supports_custom_fields = false;

	/**
		@brief		True if the post type supports thumbnails.
		@details	Set by prepare_broadcasting_data action.
		@var		$post_type_supports_thumbnails
		@since		20130603
	**/
	public $post_type_supports_thumbnails = false;

	/**
		@brief		[IN]: True if taxonomies are to be broadcasted to the child blogs.
		@var		$taxonomies
		@since		20130603
	**/
	public $taxonomies = true;

	/**
		@brief		[IN]: The wp_upload_dir() of the parent blog.
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

	/**
		@brief		Constructor.
		@details

		If you're broadcasting something related to a current broadcast (a post that refers to another post in an ACF relationship field, for example),
		give the original broadcasting_data object as the parameter in order to inherit all the settings.

		@since		2015-06-16 21:51:44
	**/
	public function __construct( $options = [] )
	{
		$options = (array)$options;
		// Import any known values from the options object.
		foreach( $options as $key => $value )
			if ( property_exists( $this, $key ) )
				$this->$key = $value;

		// The custom fields object should be cloned.
		if ( isset( $options[ 'custom_fields' ] ) )
			if ( is_object( $this->custom_fields ) )
				$this->custom_fields = clone( $options[ 'custom_fields' ] );

		if ( ! $this->parent_post_id )
			throw new Exception( 'Specify the parent post ID property when creating the broadcasting_data object.' );

		if ( $this->equivalent_posts === null )
			$this->equivalent_posts = new equivalent_posts();

		if ( $this->_POST === null )
			$this->_POST = $_POST;

		if ( $this->parent_blog_id === null )
			$this->parent_blog_id = get_current_blog_id();

		switch_to_blog( $this->parent_blog_id );

		if ( $this->post === null )
			$this->post = get_post( $this->parent_post_id );

		if ( $this->upload_dir === null )
			$this->upload_dir = wp_upload_dir();

		$this->post_type_object = get_post_type_object( $this->post->post_type );
		$this->post_type_supports_thumbnails = post_type_supports( $this->post->post_type, 'thumbnail' );
		//$this->post_type_supports_custom_fields = post_type_supports( $this->post->post_type, 'custom-fields' );
		$this->post_type_supports_custom_fields = true;
		$this->post_type_is_hierarchical = $this->post_type_object->hierarchical;

		if ( $this->meta_box_data === null )
		{
			$this->meta_box_data = ThreeWP_Broadcast()->create_meta_box( $this->post );

			// Allow plugins to modify the meta box with their own info.
			$action = new actions\prepare_meta_box;
			$action->meta_box_data = $this->meta_box_data;
			$action->execute();
		}

		// Post the form.
		if ( ! $this->meta_box_data->form->has_posted )
		{
			$this->meta_box_data->form
				->post()
				->use_post_values();
		}

		restore_current_blog();

		// Clear the blogs, in case we were given a broadcasting_data object as a parameter.
		$this->blogs = new blog_collection;
	}

	/**
		@brief		Convenience method to add an attachment from the current blog our array.
		@details	The attachments will be copied over to the child blog.
		@return		bool True if the attachment was added. False if the attachment already exists.
		@since		2015-07-11 09:00:14
	**/
	public function add_attachment( $id )
	{
		if ( $id < 1 )
			return false;

		if ( isset( $this->attachment_data[ $id ] ) )
			return false;

		$ad = attachment_data::from_attachment_id( $id, $this->upload_dir );
		$this->attachment_data[ $id ] = $ad;
		return true;
	}

	/**
		@brief		Add a blog or blogs to which to broadcast.
		@param		mixed		$blog			A broadcast_data\blog object, an array of such objects, or an int.
		@return		this						Method chaining.
		@since		20130928
	**/
	public function broadcast_to( $blog )
	{
		if ( ! is_object( $blog ) )
			$blog = new blog( $blog );

		// Convert into an array.
		$blogs = blog_collection::make( $blog );

		foreach( $blogs as $blog )
		{
			$blog_id = $blog->id;

			switch_to_blog( $blog_id );

			if ( get_current_blog_id() != $blog_id )
				continue;

			restore_current_blog();

			$this->blogs->put( $blog->id, $blog );
		}

		return $this;
	}

	/**
		@brief		Returns a copied attachments handler.
		@see		\\threewp_broadcast\\broadcasting_data\\Copied_Attachments
		@since		2015-07-01 21:19:11
	**/
	public function copied_attachments()
	{
		return new \threewp_broadcast\broadcasting_data\Copied_Attachments( $this );
	}

	/**
		@brief		Return the custom fields helper.
		@see		\\threewp_broadcast\\broadcasting_data\\Custom_Fields
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
		@brief		Find the equivalent taxonomy term ID on this blog.
		@details	We'll remove this in v25 or something.
		@since		2015-07-10 13:59:17
	**/
	public function equivalent_taxonomy_term_id( $source_term_id )
	{
		_deprecated_function( __FUNCTION__, '23', 'Use ->terms()->get( old_term_id ) instead.' );
		return $this->terms()->get( $source_term_id );
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
		@brief		Convenience method to simplify broadcasting.
		@details	Takes one post ID and an optional array of blogs.

		If no blogs are specified the broadcasting data prepared will either rebroadcast the post to the current blogs it is linked to,
		or nothing, if the post is not a parent.

		@since		2015-06-16 19:34:29
	**/
	public static function make( $post_id, $blogs = [] )
	{
		$bcd = new static( [
			'parent_post_id' => $post_id,
		] );

		if ( ! is_array( $blogs ) )
			$blogs = [ intval( $blogs ) ];

		foreach( $blogs as $blog_id )
			$bcd->broadcast_to( $blog_id );

		return $bcd;
	}

	/**
		@brief		Return the new post, or a key thereof, as an object.
		@since		2014-05-20 19:00:16
	**/
	public function new_post( $key = null )
	{
		if ( $key === null )
			return $this->new_post;
		return $this->new_post->$key;
	}

	/**
		@brief		Return the equivalent terms helper.
		@since		2015-07-29 15:20:01
	**/
	public function terms()
	{
		return new \threewp_broadcast\broadcasting_data\Terms( $this );
	}
}
