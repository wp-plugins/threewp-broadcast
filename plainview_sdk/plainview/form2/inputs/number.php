<?php

namespace plainview\form2\inputs;

/**
	@brief		Text input with number specialization.
	@author		Edward Plainview <edward@plainview.se>
	@copyright	GPL v3
	@version	20130712
**/
class number
	extends text
{
	use traits\max;
	use traits\min;
	use traits\size;
	use traits\step;

	public $type = 'number';

	public function _construct()
	{
		// Remove all non-numbers from the value.
		$this->add_value_filter( 'number.numbers', function( $value )
		{
			$value = preg_replace( "/[^0-9\.]/", "", $value );
			return floatval( $value );
		});
	}
}
