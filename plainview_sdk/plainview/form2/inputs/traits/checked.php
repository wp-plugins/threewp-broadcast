<?php

namespace plainview\form2\inputs\traits;

/**
	@brief		Manipulate the checked attribute [of an input option].
	@author		Edward Plainview <edward@plainview.se>
	@copyright	GPL v3
	@version	20130524
**/
trait checked
{
	/**
		@brief		Mark this option as checked.
		@param		bool		$checked		The checked status of the option.
		@return		this		Object chaining.
		@since		20130524
	**/
	public function checked( $checked = true )
	{
		if ( $checked )
			$this->set_attribute( 'checked', 'checked' );
		else
			$this->clear_attribute( 'checked' );
		return $this;
	}
}

