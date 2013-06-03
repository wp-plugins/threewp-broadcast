<?php

namespace plainview\form2\inputs;

/**
	@brief		Input consisting of several radio inputs.
	@author		Edward Plainview <edward@plainview.se>
	@copyright	GPL v3
	@version	20130524
**/
class radios
	extends input
{
	use traits\options;
	use traits\value
	{
		traits\options::use_post_value insteadof traits\value;
		traits\options::value insteadof traits\value;
	}

	public $self_closing = false;
	public $tag = 'div';

	/**
		@brief		No global label.
		@return		string		The radios label as a normal string.
	**/
	public function display_label()
	{
		return $this->label;
	}

	public function display_value()
	{
		return $this->options_to_inputs();
	}

	public function new_option( $o )
	{
		$input = new radio( $o->container, $o->container->get_attribute( 'name' ) );
		$input->set_attribute( 'name', $o->name );
		$input->set_attribute( 'value', $o->value );
		return $input;
	}
}

