<?php

namespace plainview\form2\inputs;

/**
	@brief		Singular radio input.
	@details	Developers will want to use the radios input, instead of this.
	@author		Edward Plainview <edward@plainview.se>
	@copyright	GPL v3
	@version	20130524
**/
class radio
	extends option
{
	use traits\checked;
	use traits\value;

	public $self_closing = true;
	public $tag = 'input';
	public $type = 'radio';

	public function check( $checked = true )
	{
		$this->checked( $checked );
	}

	public function is_checked()
	{
		return $this->get_attribute( 'checked' );
	}
}

