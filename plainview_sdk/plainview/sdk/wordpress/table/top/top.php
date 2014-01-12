<?php

namespace plainview\sdk\wordpress\table\top;

/**
	@brief			The

	@par			Changelog

	- 20131019		Initial.

	@author			Edward Plainview		edward@plainview.se
	@copyright		GPL v3
	@since			20131019
**/
class top
{
	public $left;

	public function __construct()
	{
		$this->left = new \plainview\sdk\collections\collection;
	}

	public function __toString()
	{
		$div = new \plainview\sdk\html\div;
		$div->css_class( 'tablenav' );
		$div->css_class( 'top' );

		foreach( $this->left as $left )
		{
			$l = new \plainview\sdk\html\div;
			$l->css_class( 'alignleft' );
			$l->content = $left . '&nbsp;';
			$div->content .= $l;
		}

		return $div . '';
	}
}
