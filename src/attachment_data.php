<?php

namespace threewp_broadcast;

use \Exception;

/**
	@brief	Data of an attached file.

	@par	Changelog

	- 20130530		from_attachment_id uses the whole WP_Post, not just the ID.
	- @b 2013-02-21 Added post excerpt, guid, id
	- @b 2013-02-14 Added extra attachment data from werk@haha.nl: post_title and menu_order
 */

class attachment_data
{
	use \plainview\sdk_broadcast\traits\method_chaining;

	public $filename_base;					// img.jpg
	public $filename_path;					// /var/www/wordpress/image.jpg
	public $file_metadata;					// Wordpress' metadata for the attached image.
	public $filename_upload_dir;			// Wordpress' upload directory for this blog / file
	public $id;								// ID of attachment.
	public $guid;							// Old guid of image.
	public $post_custom;					// Array of post meta keys and values.
	public $_wp_attachment_image_alt;		// The alt value for this image, if any.

	public function __construct( $options = array() )
	{
		foreach($options as $key => $value)
			$this->getset($key, $value);
	}

	public static function from_attachment_id( $attachment, $upload_dir )
	{
		$r = new attachment_data;

		if ( is_object( $attachment ) )
			$r->id = $attachment->ID;
		else
			$r->id = $attachment;

		$r->post = get_post( $r->id );

		if ( ! $r->post )
			throw new Exception( sprintf( 'The attachment ID %s does not have an associated post.', $r->id ) );

		$metadata = wp_get_attachment_metadata( $r->id );
		// Does the file have metadata?
		if ( $metadata )
			$r->file_metadata = $metadata;

		$r->filename_path = get_attached_file( $r->id );
		$r->filename_base = basename( $r->filename_path );

		// Copy all of the custom data for this post.
		$r->post_custom = get_post_custom( $r->id );

		return $r;
	}

	/**
		Remove this in about a years time, when nobody uses it anymore.

		2013-02-21.
	**/
	private function getset( $variable, $variable_new = null )
	{
		if ($variable_new == null )
			return $this->$variable;
		else
			$this->$variable = $variable_new;
	}

	// Sometime in the future: remove all of these functions.

	public function filename_base( $filename_base = null )							{	return $this->getset( 'filename_base', $filename_base );					}
	public function filename_path( $filename_path = null )							{	return $this->getset( 'filename_path', $filename_path );					}
	public function file_metadata( $file_metadata = null )							{	return $this->getset( 'file_metadata', $file_metadata );					}
	public function filename_upload_dir( $filename_upload_dir = null )				{	return $this->getset( 'file_metadata', $filename_upload_dir );				}
	public function guid( $guid = null )											{	return $this->getset( 'guid', $guid );										}
	public function menu_order( $menu_order = null )								{	return $this->getset( 'menu_order', $menu_order );							}
	public function post_excerpt( $post_excerpt = null )							{	return $this->getset( 'post_excerpt', $post_excerpt );						}
	public function post_title( $post_title = null )								{	return $this->getset( 'post_title', $post_title );							}

	/**
		@brief		Is this attachment attached to a parent post?
		@since		2014-08-01 13:11:04
	**/
	public function is_attached_to_parent()
	{
		if ( ! isset( $this->attached_to_parent ) )
			return false;
		return $this->attached_to_parent;
	}

	/**
		@brief		Set the "attached to parent" status.
		@since		2014-08-01 13:09:09
	**/
	public function set_attached_to_parent( $post, $attached = null )
	{
		if ( $attached === null )
			$attached = ( $this->post->post_parent == $post->ID );

		$this->attached_to_parent = $attached;
	}
}

