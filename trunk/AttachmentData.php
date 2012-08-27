<?php
/**
 * Data of an attached file.
 
 */
class AttachmentData
{
	private $filename_base;			// img.jpg
	private $filename_path;			// /var/www/wordpress/image.jpg
	private $file_metadata;			// Wordpress' metadata for the attached image.
	private $filename_upload_dir;	// Wordpress' upload directory for this blog / file
	private $_wp_attachment_image_alt;		// The alt value for this image, if any.
	public	$post_custom;					// Array of post meta keys and values.
	
	public static function from_attachment_id( $attachment_id, $upload_dir )
	{
		$returnValue = new AttachmentData();
		$metadata = wp_get_attachment_metadata($attachment_id);
		$returnValue->filename_base( basename($metadata['file']) );
		$returnValue->filename_path( $upload_dir['basedir'] . '/' . $metadata['file'] );
		$returnValue->file_metadata( $metadata );
		
		// Copy all of the custom data for this post.		
		$returnValue->post_custom = get_post_custom( $attachment_id );
		
		return $returnValue;
	}
	
	public function AttachmentData($options = array())
	{
		foreach($options as $key => $value)
			$this->getset($key, $value);
	}
	
	private function getset( $variable, $variable_new = '' )
	{
		if ($variable_new == '')
			return $this->$variable;
		else
			$this->$variable = $variable_new;
	}
	
	public function filename_base($filename_base = '')				{	return $this->getset('filename_base', $filename_base);			}
	public function filename_path($filename_path = '')				{	return $this->getset('filename_path', $filename_path);			}
	public function file_metadata($file_metadata = '')				{	return $this->getset('file_metadata', $file_metadata);			}
	public function filename_upload_dir($filename_upload_dir = '')	{	return $this->getset('file_metadata', $filename_upload_dir);	}
}
?>