<?php

namespace plainview\sdk\form2\inputs;

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
}
