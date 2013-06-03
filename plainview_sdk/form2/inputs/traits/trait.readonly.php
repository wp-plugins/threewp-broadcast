<?php

namespace plainview\form2\inputs\traits;

/**
	@brief		Readonly attribute manipulation.
	@author		Edward Plainview <edward@plainview.se>
	@copyright	GPL v3
	@version	20130524
**/
trait readonly
{
	/**
		@brief		Set the readonly attribute of this object.
		@param		bool		$readonly		The new state of the readonly attribute.
		@return		this		Object chaining.
		@since		20130524
	**/
	public function readonly( $readonly = true )
	{
		$this->set_boolean_attribute( 'readonly', $readonly );
		return $this->set_boolean_attribute( 'readonly', $readonly );
	}

	/**
		@brief		Return if the object is readonly.
		@return		bool		True if the readonly attribute is set.
		@since		20130524
	**/
	public function is_readonly()
	{
		return $this->get_boolean_attribute( 'readonly' );
	}
}

