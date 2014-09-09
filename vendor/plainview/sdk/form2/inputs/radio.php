<?php

namespace plainview\sdk\form2\inputs;

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

	public $self_closing = true;
	public $tag = 'input';
	public $type = 'radio';

	public function assemble_input_string( $o )
	{
		$r = '';
		$r .= $o->indent . $o->input . "\n";
		$r .= $o->indent . $o->label . "\n";
		if ( isset( $o->description ) )
			$r .= $o->indent . $o->description . "\n";
		return $r;
	}

	public function check( $checked = true )
	{
		$this->checked( $checked );
	}

	public function is_checked()
	{
		return $this->get_attribute( 'checked' );
	}
}
