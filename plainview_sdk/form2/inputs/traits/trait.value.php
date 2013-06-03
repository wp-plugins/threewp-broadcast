<?php

namespace plainview\form2\inputs\traits;

/**
	@brief		Value manipulation.
	@details	Values are saved safe.

	If you wish to retrieve the value, run a \plainview\form2\form::unfilter_text() on it to return it to its original status.
	@author		Edward Plainview <edward@plainview.se>
	@copyright	GPL v3
	@version	20130524
**/
trait value
{
	public $value;

	/**
		@brief		Exists solely to be overriden by those inputs that have the value in between their tags.
		@return		string		Usually nothing, but some input types have their value between their tags and not as element attributes.
		@since		20130524
	**/
	public function display_value()
	{
		return '';
	}

	/**
		@brief		Returns the input's value from the _POST variable.
		@details	Will strip off slashes before returning the value.
		@return		string		The value of the _POST variable. If no value was in the post, null is returned.
		@see		use_post_value()
		@since		20130524
	**/
	public function get_post_value()
	{
		$value = $this->form()->get_post_value( $this->make_name() );
		if ( $value !== null )
			$value = stripslashes( $value );
		return $value;
	}

	/**
		@brief		Returns the current value.
		@details	Note that the value has been filtered.
		@return		string		The current value of the input.
		@see		set_value()
		@see		value()
		@since		20130524
	**/
	public function get_value()
	{
		return $this->get_attribute( 'value' );
	}

	/**
		@brief		Convenience function for setting the value.
		@param		string		The new value to filter and set.
		@see		get_value()
		@see		set_value()
		@since		20130524
	**/
	public function value( $value )
	{
		return $this->set_value( $value );
	}

	/**
		@brief		Filters and sets the new value.
		@param		string		$value		Value to filter and set.
		@return		this		Object chaining.
		@see		get_value()
		@see		value()
		@since		20130524
	**/
	public function set_value( $value )
	{
		$value = \plainview\form2\form::filter_text( $value );
		$this->set_attribute( 'value', $value );
		return $this;
	}

	/**
		@brief		Retrieve this input's value from the _POST.
		@details	Will set the input's value to whatever is in the POST.
		@return		this		Object chaining.
		@see		get_post_value()
		@since		20130524
	**/
	public function use_post_value()
	{
		$value = $this->get_post_value();
		$this->value( $value );
		return $this;
	}

}

