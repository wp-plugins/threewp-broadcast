<?php

namespace plainview\sdk\html\tests;

/**
	@brief		TestCase for HTML testing.

	@details

	@par		Changelog

	- 20130718	Initial version.

	@since		20130718
	@version	20130718
**/
class TestCase extends \plainview\sdk\tests\TestCase
{
	/**
		@brief		Create a div.
		@return		\plainview\sdk\html\div		Newly-created div.
		@since		20130718
	**/
	public function div()
	{
		return new \plainview\sdk\html\div;
	}
}
