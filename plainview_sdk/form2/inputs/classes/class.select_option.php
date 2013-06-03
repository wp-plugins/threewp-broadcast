<?php

namespace plainview\form2\inputs;

/**
	@brief		A select option.
	@author		Edward Plainview <edward@plainview.se>
	@copyright	GPL v3
	@version	20130524
**/
class select_option
	extends option
{
	use traits\value;
	use traits\selected;

	public function check( $checked = true )
	{
		$this->selected( $checked );
	}

	public function is_checked()
	{
		return $this->get_attribute( 'selected' );
	}
}

