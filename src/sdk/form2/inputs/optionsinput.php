<?php

namespace plainview\sdk_broadcast\form2\inputs;

class optionsinput
	extends input
{
	use traits\options;

	public $options = [];

	public function add_option( $option )
	{
		$name = $option->get_name();
		$this->options[ $name ] = $option;
		return $this;
	}

	public function get_option( $name )
	{
		return ( isset( $this->options[ $name ] ) ? $this->options[ $name ] : false );
	}

	public function get_options()
	{
		return $this->options;
	}

}