<?php

namespace threewp_broadcast\premium_pack;

class base
	extends \plainview\sdk_broadcast\wordpress\base
{
	/**
		@brief		Send debug info to Broadcast.
		@since		2014-02-24 00:47:19
	**/
	public function debug( $string )
	{
		$bc = ThreeWP_Broadcast();
		$args = func_get_args();
		// Get the name of the class
		$class_name = get_called_class();
		// But without the namespace
		$class_name = preg_replace( '/.*\\\/', '', $class_name );
		// And append it at the beginning of the string.
		$args[ 0 ] =  $class_name . ': ' . $args[ 0 ];
		return call_user_func_array( [ $bc, 'debug' ] , $args );
	}

	/**
		@brief		This overrides the SDK's load language in order to load all of the pack plugin translations from a single file.
		@details	A note about translations.

					Note that if you're trying to call the _ functions of, say, the meta box, the _() function that will be called will be that of Broadcast, not of this plugin.

					So instead of writing:

					$mbd->lock_post = $form->checkbox( 'lock_post' )
						->label_( 'Lock the post' )

					You have to ask the plugin itself to translate the string first, before it is given to the form:

					$mbd->lock_post = $form->checkbox( 'lock_post' )
						->label( $this->_( 'Lock the post' ) )


		@since		2015-10-03 15:32:24
	**/
	public function load_language( $domain = '' )
	{
		$this->language_domain = 'Broadcast_Pack';
		$directory = ThreeWP_Broadcast()->paths( 'path_from_plugin_directory' ) . '/src/premium_pack/lang/';
		// Allow people to load their own pot files.
		$directory = apply_filters( 'Broadcast_Pack_language_directory', $directory );
		load_plugin_textdomain( $this->language_domain, false, $directory );
	}

	/**
		@brief		Loads and paragraphs a file.
		@since		20131207
	**/
	public function wpautop_file( $filepath )
	{
		$r = file_get_contents( $filepath );
		$r = wpautop( $r );
		return $r;
	}
}
