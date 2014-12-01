<?php

namespace threewp_broadcast\posts\actions;

/**
	@brief		A base, generic post action.
	@since		2014-11-02 21:33:57
**/
class generic
{
	/**
		@brief		A short name / verb that describes the action.
		@since		2014-10-31 14:14:15
	**/
	public $name;

	/**
		@brief		Get the action name.
		@see		$name
		@since		2014-10-31 14:14:31
	**/
	public function get_name()
	{
		return $this->name;
	}

	/**
		@brief		Set the action name.
		@see		$name
		@since		2014-10-31 14:14:34
	**/
	public function set_name( $name )
	{
		$this->name = $name;
	}
}
