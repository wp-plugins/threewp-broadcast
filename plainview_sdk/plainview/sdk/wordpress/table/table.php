<?php

namespace plainview\sdk\wordpress\table;

/**
	@brief			Extends the table class by using the Wordpress base class to translate table object names and titles.

	@par			Changelog

	- 20131015		Added bulk_actions();
	- 20130509		Complete rework moving all of the translation to the parent table class. Only _() is overridden.
	- 20130507		Code: td() and th() can return existing cells.
	- 20130506		New: trait has title_().
					Code: Renamed wordpress_table_object to wordpress_table_element.

	@author			Edward Plainview		edward@plainview.se
	@copyright		GPL v3
	@since			20130430
	@version		20131015
**/
class table
	extends \plainview\sdk\table\table
{
	use \plainview\sdk\traits\method_chaining;

	/**
		@brief		The \\plainview\\sdk\\table\\wordpress\\base object that created this class.
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

	public function __toString()
	{
		$r = '';
		if ( isset( $this->bulk_actions ) )
			$r .= $this->bulk_actions;
		$r .= parent::__toString();

		return $r;
	}

	/**
		@brief
		@since		20131015
	**/
	public function bulk_actions()
	{
		if ( ! isset( $this->bulk_actions ) )
			$this->bulk_actions = new bulkactions\controller( $this );
		return $this->bulk_actions;
	}
}

