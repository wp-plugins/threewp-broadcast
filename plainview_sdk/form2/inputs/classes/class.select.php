<?php

namespace plainview\form2\inputs;

/**
	@brief		Select input.
	@author		Edward Plainview <edward@plainview.se>
	@copyright	GPL v3
	@version	20130524
**/
class select
	extends input
{
	use traits\options;
	use traits\size;
	use traits\value
	{
		traits\options::use_post_value insteadof traits\value;
		traits\options::value insteadof traits\value;
	}

	public $self_closing = false;
	public $tag = 'select';

	public function __toString()
	{
		return $this->indent() . $this->display_label() . $this->display_input();
	}

	public function display_input()
	{
		$input = clone( $this );

		$input->css_class( 'select' );

		if ( $input->is_required() )
			$input->css_class( 'required' );

		$r = $input->indent() . $input->open_tag() . "\n";
		foreach( $input->options as $option )
		{
			$option = clone( $option );
			if ( is_a( $option, 'plainview\\form2\\inputs\\select_optgroup' ) )
				$r .= $option;
			else
			{
				$option->clear_attribute( 'name' );
				if ( in_array( $option->get_attribute( 'value' ), $input->_value ) )
					$option->check( true );
				$r.= $option;
			}
		}
		$r .= $input->indent() . $input->close_tag() . "\n";
		return $r;
	}

	/**
		@brief
		@param
		@return
		@since		20130524
	**/
	public function multiple( $multiple = true )
	{
		return $this->set_boolean_attribute( 'multiple', $multiple );
	}

	public function new_option( $o )
	{
		$input = new select_option( $o->container, $o->container->get_attribute( 'name' ) );
		return $input;
	}

	/**
		@brief		Create / return an optgroup.
		@param		string		$name		Name of the optgroup to create / return.
		@return		optgroup		Created or returned optgroup.
		@since		20130524
	**/
	public function optgroup( $name )
	{
		if ( isset( $this->inputs[ $name ] ) )
			return $this->inputs[ $name ];
		$input = new select_optgroup( $this, $name );
		$this->options[ $name ] = $input;
		return $input;
	}

	/**
		@brief		Set the value of this select.
		@details	Several parameters can be given and they will be merged into an array.
		@param		mixed		$value		Value to set.
		@return		$this		This object.
	**/
	public function value( $value, $value2 = null )
	{
		$args = func_get_args();
		if ( count( $args ) > 1 )
			$value = $args;
		if ( ! is_array( $value ) )
			$value = array( $value );
		$this->_value = $value;
		return $this;
	}
}

