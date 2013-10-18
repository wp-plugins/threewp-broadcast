<?php

namespace threewp_broadcast\meta_box;

class html
	extends \plainview\sdk\collections\collection
{
	public function __toString()
	{
		$r = '';
		foreach( $this->items as $key => $value )
			$r .= sprintf( '<div class="%s html_section">%s</div>', $key, $value );
		return $r;
	}
}