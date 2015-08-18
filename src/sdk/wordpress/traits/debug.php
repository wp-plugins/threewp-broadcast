<?php

namespace plainview\sdk_broadcast\wordpress\traits;

/**
	@brief		Add debug methods to the plugin.
	@details

	The main method is debug(), with is_debugging() being queried to ascertain whether the user is in debug mode.

	1. Add to your class:

		use \plainview\sdk_broadcast\wordpress\debug;

	2. Add these rows to your site options:

		'debug' => false,									// Display debug information?
		'debug_ips' => '',									// List of IP addresses that can see debug information, when debug is enabled.

	3.1	In the settings form, display the inputs:

		$this->add_debug_settings_to_form( $form );

	3.2 In the settings form, save your settings.

		$this->save_debug_settings_from_form( $form );

	@since		2014-05-01 08:56:20
**/
trait debug
{
	/**
		@brief		Adds the debug settings to a form.
		@since		2014-05-01 08:57:39
	**/
	public function add_debug_settings_to_form( $form )
	{
		// We need this so that the options use the correct namespace.
		$bc = self::instance();

		$fs = $form->fieldset( 'fs_debug' );
		$fs->legend->label_( 'Debugging' );

		// You are currently NOT in debug mode.
		$not = $this->_( 'not' );

		$fs->markup( 'debug_info' )
			->p_( "According to the settings below, you are currently%s in debug mode. Don't forget to reload this page after saving the settings.", $this->debugging() ? '' : " <strong>$not</strong>" );

		$debug = $fs->checkbox( 'debug' )
			->description_( 'Show debugging information in various places.' )
			->label_( 'Enable debugging' )
			->checked( $bc->get_site_option( 'debug', false ) );

		$debug_ips = $fs->textarea( 'debug_ips' )
			->description_( 'Only show debugging info to specific IP addresses. Use spaces between IPs. You can also specify part of an IP address. Your address is %s', $_SERVER[ 'REMOTE_ADDR' ] )
			->label_( 'Debug IPs' )
			->rows( 5, 16 )
			->trim()
			->value( $bc->get_site_option( 'debug_ips', '' ) );
	}

	/**
		@brief		Output a string if in debug mode.
		@since		20140220
	*/
	public function debug( $string )
	{
		if ( ! $this->debugging() )
			return;

		// Convert the non-string arguments into lovely code blocks.
		$args = func_get_args();
		foreach( $args as $index => $arg )
		{
			$export = false;
			$export |= is_array( $arg );
			$export |= is_object( $arg );
			if ( $export )
				$args[ $index ] = sprintf( '<pre><code>%s</code></pre>', htmlspecialchars( var_export( $arg, true ) ) );
		}

		// Put all of the arguments into one string.
		$text = call_user_func_array( 'sprintf', $args );
		if ( $text == '' )
			$text = $string;

		// We want the name of the class.
		$class_name = get_called_class();
		// But without the namespace
		$class_name = preg_replace( '/.*\\\/', '', $class_name );

		// Date class: string
		$text = sprintf( '%s <em>%s</em>: %s<br/>', $this->now(), $class_name, $text, "\n" );
		echo $text;
		if ( ob_get_contents() )
			ob_flush();
	}

	/**
		@brief		Is Broadcast in debug mode?
		@since		20140220
	*/
	public function debugging()
	{
		// We need this so that the options use the correct namespace.
		$plugin = self::instance();

		$debugging = $plugin->get_site_option( 'debug', false );
		if ( ! $debugging )
			return false;

		// Debugging is enabled. Now check if we should show it to this user.
		$ips = $plugin->get_site_option( 'debug_ips', '' );
		// Empty = no limits.
		if ( $ips == '' )
			return true;

		$lines = explode( "\n", $ips );
		foreach( $lines as $line )
			if ( strpos( $_SERVER[ 'REMOTE_ADDR' ], $line ) !== false )
				return true;

		// No match = not debugging for this user.
		return false;
	}

	/**
		@brief		Saves the debug settings from the form.
		@since		2014-05-01 08:58:22
	**/
	public function save_debug_settings_from_form( $form )
	{
		// We need this so that the options use the correct namespace.
		$bc = self::instance();

		$bc->update_site_option( 'debug', $form->input( 'debug' )->is_checked() );
		$bc->update_site_option( 'debug_ips', $form->input( 'debug_ips' )->get_filtered_post_value() );
	}
}
