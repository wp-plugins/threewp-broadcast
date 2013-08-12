<?php

namespace plainview\form2\inputs;

class inputfieldset
	extends fieldset
{
	use traits\options;
	use traits\value
	{
		traits\options::use_post_value insteadof traits\value;
		traits\options::value insteadof traits\value;
	}

	public function add_option( $input )
	{
		return $this->add_input( $input );
	}

	/**
		@brief		Input fieldsets don't have labels. They have just a fieldset and contents.
		@since		20130805
	**/
	public function display_label()
	{
		return '';
	}

	public function get_option( $name )
	{
		return $this->input( $name );
	}

	public function get_options()
	{
		return $this->inputs;
	}

	/**
		@brief		Assign the prefix to each of the options.
		@param		string		$prefix		Prefix to append to the options.
		@return		this		Method chaining.
		@since		20130524
	**/
	public function prefix( $prefix )
	{
		$this->prefix = func_get_args();
		foreach( $this->get_options() as $option )
			call_user_func_array( array( $option, 'prefix' ), $this->prefix );
		return $this;
	}
}