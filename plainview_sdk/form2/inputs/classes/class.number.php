<?php

namespace plainview\form2\inputs;

/**
	@brief		Text input with number specialization.
	@author		Edward Plainview <edward@plainview.se>
	@copyright	GPL v3
	@version	20130524
**/
class number
	extends text
{
	use traits\max;
	use traits\min;
	use traits\size;
	use traits\step;
	use traits\value;

	public $type = 'number';
}

