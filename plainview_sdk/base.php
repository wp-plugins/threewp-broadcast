<?php

namespace plainview;

/**
	@brief			Collection of useful functions.
	@par			Changelog
	
	- 20130501		array_rekey: force conversion of arrays to arrays (from objects).
	- 20130430		Added 3rdparty/phpmailer.
	- 20130426		implode_html has switch the parameter order. $array is now first.
	
	@author			Edward Plainview		edward@plainview.se
	@license		GPL v3
	@version		20130430
**/
class base
{
	/**
		@brief		The instance of the base.
		@since		20130425
		@var		$instance
	**/
	protected static $instance;
		
	/**
		@brief		Constructor.
		@since		20130425
	**/
	public function __construct()
	{
		self::$instance = $this;
	}
	
	/**
		@brief		Insert an array into another.
		@details	Like array_splice but better, because it even inserts the new key.
		@param		array		$array		Array into which to insert the new array.
		@param		int			$position	Position into which to insert the new array.
		@param		array		$new_array	The new array which is to be inserted.
		@return		array					The complete array.
		@since		20130416
	**/
	public static function array_insert( $array, $position, $new_array )
	{
		$part1 = array_slice( $array, 0, $position, true ); 
		$part2 = array_slice( $array, $position, null, true ); 
		return $part1 + $new_array + $part2;
	}
	
	/**
		@brief		Sort an array of arrays using a specific key in the subarray as the sort key.
		@param		array		$array		An array of arrays.
		@param		string		$key		Key in subarray to use as sort key.
		@return		array					The array of arrays. 
		@since		20130416
	**/
	public static function array_sort_subarrays( $array, $key )
	{
		// In order to be able to sort a bunch of objects, we have to extract the key and use it as a key in another array.
		// But we can't just use the key, since there could be duplicates, therefore we attach a random value.
		$sorted = array();
		
		$is_array = is_array( reset( $array ) );
		
		foreach( $array as $index => $item )
		{
			$item = (object) $item;
			do
			{
				$rand = rand(0, PHP_INT_MAX / 2);
				if ( is_int( $item->$key ) )
					$random_key = $rand + $item->$key;
				else
					$random_key = $item->$key . '-' . $rand;
			}
			while ( isset( $sorted[ $random_key ] ) );
			
			$sorted[ $random_key ] = array( 'key' => $index, 'value' => $item );
		}
		ksort( $sorted );
		
		// The array has been sorted, we want the original array again.
		$r = array();
		foreach( $sorted as $item )
		{
			$value = ( $is_array ? (array)$item[ 'value' ] : $item[ 'value' ] );
			$r[ $item['key'] ] = $item['value'];
		}
			
		return $r;
	}
	
	/**
		@brief		Make a value a key.
		@details	Given an array of arrays, take the key from the subarray and makes it the key of the main array.
		@param		$array		Array to rearrange.
		@param		$key		Which if the subarray keys to make the key in the main array.
		@return		array		Rearranged array.
		@since		20130416
	**/
	public static function array_rekey( $array, $key )
	{
		$r = array();
		foreach( $array as $value )
		{
			$value = (array) $value;
			$r[ $value[ $key ] ] = $value;
		}
		return $r;
	}
	
	/**
		@brief		Check that the supplied text is plaintext.
		@details	Strips out HTML. Inspired by Drupal's check_plain.
		@param		string		$text		String to check for plaintext.
		@return		string					Cleaned up string.
		@since		20130416
	**/
	public static function check_plain( $text )
	{
		$text = strip_tags( $text );
		$text = stripslashes( $text );
		return $text;
	}
	
	/**
		@brief		Convenience function to wrap a string in h1 tags.
		@param		string		$string		The string to wrap.
		@return		string					The wrapped string.
		@see		open_close_tag()
		@since		20130416
	**/
	public static function h1( $string )
	{
		return self::open_close_tag( $string, 'h1' );
	}
	
	/**
		@brief		Convenience function to wrap a string in h2 tags.
		@param		string		$string		The string to wrap.
		@return		string					The wrapped string.
		@see		open_close_tag()
		@since		20130416
	**/
	public static function h2( $string )
	{
		return self::open_close_tag( $string, 'h2' );
	}
	
	/**
		@brief		Convenience function to wrap a string in h3 tags.
		@param		string		$string		The string to wrap.
		@return		string					The wrapped string.
		@see		open_close_tag()
		@since		20130416
	**/
	public static function h3( $string )
	{
		return self::open_close_tag( $string, 'h3' );
	}
	
	/**
		@brief		Returns a hash value of a string. The standard hash type is sha512 (64 chars).
		
		@param		string		$string			String to hash.
		@param		string		$type			Hash to use. Default is sha512.
		@return									Hashed string.
		@since		20130416
	**/
	public static function hash( $string, $type = 'sha512' )
	{
		return hash($type, $string);
	}
	
	/**
		@brief		Implode an array in an HTML-friendly way.
		@details	Used to implode arrays using HTML tags before, between and after the array. Good for lists.
		@param		string		$prefix		li
		@param		string		$suffix		/li
		@param		array		$array		The array of strings to implode.
		@return		string					The imploded string.
		@since		20130416
	**/
	public static function implode_html( $array, $prefix = '<li>', $suffix = '</li>' )
	{
		return $prefix . implode( $suffix . $prefix, $array ) . $suffix;
	}
	
	/**
		@brief		Return the instance of this object class.
		@return		base		The instance of this object class.
		@since		20130425
	**/
	public static function instance()
	{
		return self::$instance;
	}
	
	/**
		@brief		Check en e-mail address for validity.
		@param		string		$address		Address to check.
		@param		boolean		$check_mx		Check for a valid MX?
		@return		boolean		True, if the e-mail address is valid.
		@since		20130416
	**/
	public static function is_email( $address, $check_mx = true )
	{
		if ( filter_var( $address, FILTER_VALIDATE_EMAIL ) != $address )
			return false;
 
		// If no need to check the MX, and we've gotten this far, then it's ok.
		if ( $check_mx == false )
			return true;
		
		// Check the DNS record.
		$host = preg_replace( '/.*@/', '', $address );
		if ( ! checkdnsrr( $host, 'MX' ) )
			return false;
		
		return true;
	}
	
	/**
		@brief		Creates a mail object.
		@return		\\plainview\\mail\\mail		A new PHPmailer object.
		@since		20130430
	**/ 
	public static function mail()
	{
		if ( ! class_exists( '\\plainview\\mail' ) )
			require_once( dirname( __FILE__ ) . '/mail.php' );
		
		$mail = new \plainview\mail\mail();
		$mail->CharSet = 'UTF-8';
		return $mail;
	}
	
	/**
		@brief		Merge two objects.
		@details	The objects can even be arrays, since they're automatically converted into objects.
		@param		mixed		$base		An array or object into which to append the new properties.
		@param		mixed		$new		New properties to append to $base.
		@return		object					The expanded $base object.
		@since		20130416
	**/
	public static function merge_objects( $base, $new )
	{
		$base = clone (object)$base;
		foreach( (array)$new as $key => $value )
			$base->$key = $value;
		return $base;
	}
	
	/**
		@brief		Returns the number corrected into the min and max values.
		@param		int		$number		Number to adjust.
		@param		int		$min		Minimum value.
		@param		int		$max		Maximum value.
		@return		int					The corrected $number.
		@since		20130416
	**/
	public static function minmax( $number, $min, $max )
	{
		$number = min( $max, $number );
		$number = max( $min, $number );
		return $number;
	}
	
	/**
		@brief		Tries to figure out the mime type of this filename.		
		@param		string		$filename		The complete file path.
		@return		string		
		@since		20130416
	**/
	public static function mime_type( $filename )
	{
		// Try to use file.
		if ( is_executable( '/usr/bin/file' ) )
		{
			exec( "file -bi '$filename'", $r );
			$r = reset( $r );
			$r = preg_replace( '/;.*/', '', $r );
			return $r;
		}
		
		// Try to use the finfo class.
		if ( class_exists( 'finfo' ) )
		{
			$fi = new finfo( FILEINFO_MIME, '/usr/share/file/magic' );
			$r = $fi->buffer(file_get_contents( $filename ));
			return $r;
		}
		
		// Last resort: mime_content_type which rarely works properly.
		if ( function_exists( 'mime_content_type' ) )
		{
			$r = mime_content_type ( $filename );
			return $r;
		}
		
		// Nope. Return a general value.
		return 'application/octet-stream';
	}
	
	/**
		@brief		Enclose in string in an HTML element tag.
		@details	Parameter order: enclose THIS in THIS
		@param		string		$string		String to wrap.
		@param		string		$tag		HTML element tag: h1, h2, h3, p, etc...
		@return		string					The wrapped string.
		@since		20130416
	**/
	public static function open_close_tag( $string, $tag )
	{
		return sprintf( '<%s>%s</%s>', $tag, $string, $tag );
	}
	
	/**
		@brief		Recursively removes a directory.
		@details	Assumes that all files in the directory, and the dir itself, are writeable.
		@param		string		$directory		Directory to remove.
		@since		20130416
	**/
	public static function rmdir( $directory )
	{
		$directory = rtrim( $directory, '/' );
		if ( $directory == '.' || $directory == '..' )
			return;
		if ( is_file( $directory ) )
			unlink ( $directory );
		else
		{
			$files = glob( $directory . '/*' );
			foreach( $files as $file )
				self::rmdir( $file );
			rmdir( $directory );
		}
	}
	
	/**
		@brief		Returns a stack trace as a string.
		@return		string			A stacktrace.
		@since		20130416
	**/
	public static function stacktrace()
	{
		$e = new \Exception;
		return var_export( $e->getTraceAsString(), true );
	}
	
	/**
		@brief		Converts a string to an array of e-mail addresses.
		@param		string		$string		A multiline text-area.
		@return		array					An array of valid e-mail addresses. If no valid e-mail addresses are found, then the returned array is empty.
		@since		20130425
	**/
	public static function string_to_emails( $string )
	{
		$string = str_replace( array( "\r", "\n", "\t", ';', ',', ' ' ), "\n", $string );
		$lines = array_filter( explode( "\n", $string ) );
		$r = array();
		foreach( $lines as $line )
			if ( self::is_email( $line ) )
				$r[ $line ] = $line;
		ksort( $r );
		return $r;
	}
	
	/**
		@brief		Strips the array of slashes. Used for $_POSTS.
		@param		array		$post		The array to strip. If null then $_POST is used.
		@return		array					Posts with slashes stripped.
		@since		20130416
	**/
	public static function strip_post_slashes( $post = null )
	{
		if ( $post === null )
			$post = $_POST;
		foreach( $post as $key => $value )
			if ( ! is_array( $value ) && strlen( $value ) > 1 )
				$post[ $key ] = stripslashes( $value );
		
		return $post;
	}
	
	/**
		@brief		Multibyte strtolower.
		@param		string		$string			String to lowercase.
		@return									Lowercased string.
		@since		20130416
	**/
	public static function strtolower( $string )
	{
		return mb_strtolower( $string ); 
	}
	
	/**
		@brief		Multibyte strtoupper.
		@param		string		$string			String to uppercase.
		@return									Uppercased string.
		@since		20130416
	**/
	public static function strtoupper( $string )
	{
		return mb_strtoupper( $string ); 
	}
}

