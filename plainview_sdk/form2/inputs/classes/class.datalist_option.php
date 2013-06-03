<?php

namespace plainview\form2\inputs;

/**
	@brief		An option belonging to a datalist.
	@author		Edward Plainview <edward@plainview.se>
	@copyright	GPL v3
	@version	20130524
**/
class datalist_option
	extends option
{
	public $self_closing = true;

	public function __toString()
	{
		$option = clone( $this );
		$option->clear_attribute( 'name' );
		$option->set_attribute( 'value', $option->label );
		return $option->open_tag();
	}

	public function check()
	{
	}

	public function is_checked()
	{
	}
}

