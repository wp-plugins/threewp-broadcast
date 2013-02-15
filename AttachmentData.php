<?php
/**
	@brief	Data of an attached file.
	
	@par	Changelog
	
	- @b 2013-02-14 Added extra attachment data from werk@haha.nl: attachment_title and attachment_menu_order
 */
class AttachmentData
{
	private $filename_base;			// img.jpg
	private $filename_path;			// /var/www/wordpress/image.jpg
	private $file_metadata;			// Wordpress' metadata for the attached image.
	private $filename_upload_dir;	// Wordpress' upload directory for this blog / file
	private $_wp_attachment_image_alt;		// The alt value for this image, if any.
	private $attachment_title; 		// plaatje echte naam toegevoegd
	private $attachment_menu_order;	// plaatjes volgorde toegevoegd
	public	$post_custom;					// Array of post meta keys and values.
	
	public function __construct( $options = array() )
	{
		foreach($options as $key => $value)
			$this->getset($key, $value);
	}
	
	public static function from_attachment_id( $attachment_id, $upload_dir )
	{
		$returnValue = new AttachmentData();
		$metadata = wp_get_attachment_metadata($attachment_id);
		$returnValue->filename_base( basename($metadata['file']) );
		$returnValue->filename_path( $upload_dir['basedir'] . '/' . $metadata['file'] );
		$returnValue->file_metadata( $metadata );
		
		$attachment_data = get_post( $attachment_id );
		$returnValue->attachment_title( $attachment_data->post_title );
		$returnValue->attachment_menu_order( $attachment_data->menu_order );

		// Copy all of the custom data for this post.		
		$returnValue->post_custom = get_post_custom( $attachment_id );
		
		return $returnValue;
	}
	
	private function getset( $variable, $variable_new = '' )
	{
		if ($variable_new == '')
			return $this->$variable;
		else
			$this->$variable = $variable_new;
	}
	
	public function filename_base($filename_base = '')						{	return $this->getset('filename_base', $filename_base);					}
	public function filename_path($filename_path = '')						{	return $this->getset('filename_path', $filename_path);					}
	public function file_metadata($file_metadata = '')						{	return $this->getset('file_metadata', $file_metadata);					}
	public function filename_upload_dir($filename_upload_dir = '')			{	return $this->getset('file_metadata', $filename_upload_dir);			}
	public function attachment_menu_order($attachment_menu_order = '')		{	return $this->getset('attachment_menu_order', $attachment_menu_order);	}
	public function attachment_title($attachment_title = '')				{	return $this->getset('attachment_title', $attachment_title);			}
}
