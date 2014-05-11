<?php

namespace threewp_broadcast\filters;

class filter
	extends \threewp_broadcast\actionfilter
{
	public function apply_method( $filter_name )
	{
		global $threewp_broadcast;
		$threewp_broadcast->filters( $filter_name, $this );
		return $this;
	}
}
