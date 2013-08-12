<?php

namespace plainview\form2\inputs;

/**
	@brief		Text input with e-mail formatting.
	@author		Edward Plainview <edward@plainview.se>
	@copyright	GPL v3
	@version	20130524
**/
class email
	extends text
{
	public $type = 'email';

	public function _construct()
	{
		$this->add_validation_method( 'email' );
	}

	public function validate_email()
	{
		$value = $this->get_value();
		if ( ! \plainview\base::is_email( $value ) )
			$this->validation_error()->set_unfiltered_label_( 'The e-mail address in %s is not valid!', '<em>' . $this->get_label()->content . '</em>' );
	}
}

