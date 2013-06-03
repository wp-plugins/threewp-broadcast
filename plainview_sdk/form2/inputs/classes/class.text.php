<?php

namespace plainview\form2\inputs;

/**
	@brief		Text / textfield input.
	@details	Is the parent class of many text-related inputs.
	@author		Edward Plainview <edward@plainview.se>
	@copyright	GPL v3
	@version	20130524
**/
class text
	extends input
{
	use traits\maxlength;
	use traits\placeholder;
	use traits\size;
	use traits\value;

	public $type = 'text';
}

