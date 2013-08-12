<?php

namespace plainview\form2\inputs;

/**
	@brief		HTML markup providing text/HTML display.
	@details

	The markup is saved safe (filtered) and then unfiltered before display.

	@author		Edward Plainview <edward@plainview.se>
	@copyright	GPL v3
	@version	20130524
**/
class markup
	extends input
{
	use traits\value;

	/**
		@brief		If no name was specified, and markup doesn't really need one, then make something up.
	**/
	public function _construct()
	{
		if ( $this->get_attribute( 'name' ) == '' )
			$this->set_attribute( 'name', \plainview\base::uuid( 4 ) );
	}

	public function __toString()
	{
		return \plainview\form2\form::unfilter_text( $this->get_value() );
	}

	/**
		@brief		Markup has no input, just markup.
	**/
	public function display_input()
	{
		return $this;
	}

	/**
		@brief		Set the HTML markup.
		@param		string		$markup
		@return		this		Object chaining.
		@since		20130524
	**/
	public function markup( $markup )
	{
		return $this->value( $markup );
	}

	/**
		@brief		Convenience method to wpautop the markup before setting it.
		@param		string		$markup
		@return		this		Object chaining.
		@since		20130524
	**/
	public function p( $markup )
	{
		return $this->markup( wpautop( $markup ) );
	}

	/**
		@brief		Convenience method to first translate and then wpautop the markup before setting it.
		@param		string		$markup
		@return		this		Object chaining.
		@since		20130524
	**/
	public function p_( $markup )
	{
		$markup = call_user_func_array( array( $this->container, '_' ), func_get_args() );
		return $this->p( $markup );
	}
}

