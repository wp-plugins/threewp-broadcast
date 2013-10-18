<?php

namespace threewp_broadcast\actions;

use \threewp_broadcast\blog_collection;

class manage_posts_custom_column
	extends action
{
	public $html;

	public function _construct()
	{
		$this->html = new \plainview\sdk\collections\collection;
	}

	public function render()
	{
		$r = '';
		foreach( $this->html as $key => $html )
			$r .= sprintf( '<div class="%s">%s</div>', $key, $html );
		return $r;
	}
}