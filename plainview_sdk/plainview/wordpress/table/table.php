<?php

namespace plainview\wordpress\table;

if ( ! class_exists( 'plainview::table::table' ) )
	require_once( dirname( __FILE__ ) . '/../table/table.php' );

/**
	@brief			Extends the table class by using the Wordpress base class to translate table object names and titles.

	@par			Changelog

	- 20130509		Complete rework moving all of the translation to the parent table class. Only _() is overridden.
	- 20130507		Code: td() and th() can return existing cells.
	- 20130506		New: trait has title_().
					Code: Renamed wordpress_table_object to wordpress_table_element.

	@author			Edward Plainview		edward@plainview.se
	@copyright		GPL v3
	@since			20130430
	@version		21030509
**/
class table
	extends \plainview\table\table
{
	/**
		@brief		The \\plainview\\table\\wordpress\\base object that created this class.
	**/
	public $base;

	public function __construct( $base )
	{
		parent::__construct();
		$this->base = $base;
	}

	/**
		@brief		Use the base's _() method to translate this string. sprintf aware.
		@param		string		$string		String to translate.
		@return		string					Translated string.
	**/
	public function _( $string )
	{
		return call_user_func_array( array( $this->base, '_' ), func_get_args() );
	}
}

