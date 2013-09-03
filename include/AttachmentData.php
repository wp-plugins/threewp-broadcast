<?php

namespace threewp_broadcast;

/**
	@brief	Data of an attached file.

	@par	Changelog

	- 20130530		from_attachment_id uses the whole WP_Post, not just the ID.
	- @b 2013-02-21 Added post excerpt, guid, id
	- @b 2013-02-14 Added extra attachment data from werk@haha.nl: post_title and menu_order
 */

class AttachmentData
{
	public $filename_base;					// img.jpg
	public $filename_path;					// /var/www/wordpress/image.jpg
	public $file_metadata;					// Wordpress' metadata for the attached image.
	public $filename_upload_dir;			// Wordpress' upload directory for this blog / file
	public $id;								// ID of attachment.
	public $guid;							// Old guid of image.
	public $menu_order;						// Menu order
	public $post_custom;					// Array of post meta keys and values.
	public $post_excerpt;					// Post excerpt is the small caption.
	public $post_title;						// Title
	public $_wp_attachment_image_alt;		// The alt value for this image, if any.

	public function __construct( $options = array() )
	{
		foreach($options as $key => $value)
			$this->getset($key, $value);
	}

	public static function from_attachment_id( $attachment, $upload_dir )
	{
		$r = new AttachmentData();
		$r->id = $attachment->ID;
		$metadata = wp_get_attachment_metadata( $attachment->ID );
		// Does the file have metadata?
		if ( $metadata )
		{
			$r->filename_base = basename( $metadata['file'] );
			$r->filename_path = $upload_dir[ 'basedir' ] . '/' . $metadata[ 'file' ];
			$r->file_metadata = $metadata;
		}
		else
		{
			// No metadata = not an image. Guess the upload directory and what not.
			$r->filename_path = get_attached_file( $attachment->ID );
			$r->filename_base = basename( $r->filename_path );
		}

		$data = get_post( $attachment->ID );
		$r->guid = $data->guid;
		$r->post_title = $data->post_title;
		$r->menu_order = $data->menu_order;
		$r->post_excerpt = $data->post_excerpt;

		// Copy all of the custom data for this post.
		$r->post_custom = get_post_custom( $attachment->ID );

		return $r;
	}

	/**
		Remove this in about a years time, when nobody uses it anymore.

		2013-02-21.
	**/
	private function getset( $variable, $variable_new = '' )
	{
		if ($variable_new == '')
			return $this->$variable;
		else
			$this->$variable = $variable_new;
	}

	// Sometime in the future: remove all of these functions.

	public function filename_base( $filename_base = '' )						{	return $this->getset( 'filename_base', $filename_base );					}
	public function filename_path( $filename_path = '' )						{	return $this->getset( 'filename_path', $filename_path );					}
	public function file_metadata( $file_metadata = '' )						{	return $this->getset( 'file_metadata', $file_metadata );					}
	public function filename_upload_dir( $filename_upload_dir = '' )			{	return $this->getset( 'file_metadata', $filename_upload_dir );				}
	public function guid( $guid = '' )											{	return $this->getset( 'guid', $guid );										}
	public function menu_order( $menu_order = '' )								{	return $this->getset( 'menu_order', $menu_order );							}
	public function post_excerpt( $post_excerpt = '' )							{	return $this->getset( 'post_excerpt', $post_excerpt );						}
	public function post_title( $post_title = '' )								{	return $this->getset( 'post_title', $post_title );							}
}

