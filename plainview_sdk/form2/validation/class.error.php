<?php

namespace plainview\form2\validation;

class error
{
	use \plainview\form2\inputs\traits\label;

	public $container;

	public function __construct( $container )
	{
		$this->container = $container;
	}
}

