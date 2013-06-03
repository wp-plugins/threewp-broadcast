<?php

namespace plainview\form2\inputs;

/**
	@brief		A fieldset / input container.
	@author		Edward Plainview <edward@plainview.se>
	@copyright	GPL v3
	@version	20130524
**/
class fieldset
	extends input
{
	use traits\container;

	public $legend;

	public $self_closing = false;

	public $tag = 'fieldset';

	public function __toString_before_inputs()
	{
		return $this->legend;
	}

	public function _construct()
	{
		$this->legend = new legend( $this );
	}

	/**
		@brief		Returns the legend attribute.
		@return		legend		The fieldset's legend.
		@since		20130524
	**/
	public function legend()
	{
		return $this->legend;
	}

	public function indentation()
	{
		return $this->form()->indentation() + 1;
	}
}

