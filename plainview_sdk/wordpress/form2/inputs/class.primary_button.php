<?php

namespace plainview\wordpress\form2\inputs;

class primary_button
	extends \plainview\form2\inputs\submit
{
	public function _construct()
	{
		$this->css_class( 'button-primary' );
	}
}

