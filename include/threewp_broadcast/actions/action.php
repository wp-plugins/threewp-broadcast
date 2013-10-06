<?php

namespace threewp_broadcast\actions;

class action
	extends \threewp_broadcast\actionfilter
{
	public function apply_method( $filter_name )
	{
		do_action( $filter_name, $this );
	}
}
