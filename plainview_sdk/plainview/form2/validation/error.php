<?php

namespace plainview\form2\validation;

/**
	@brief		A validation error.
	@details

	Uses labels to store the error message.

	Changelog
	---------

	- 20130604	__toString() added.

	@version	20130604
**/
class error
{
	use \plainview\form2\inputs\traits\label;

	/**
		@brief		Which input this error belongs to.
		@var		$container
	**/
	public $container;

	public function __construct( $container )
	{
		// Hack because a label requires an input->container.
		$i = new \plainview\form2\inputs\input( $container, md5( microtime() ) );
		$i->container = $container;

		$this->container = $container;
		$this->label = new \plainview\form2\inputs\label( $i );
	}

	public function __toString()
	{
		return $this->get_label()->content;
	}
}

