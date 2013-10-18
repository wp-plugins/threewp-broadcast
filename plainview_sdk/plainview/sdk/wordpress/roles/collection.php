<?php

namespace plainview\sdk\wordpress\roles;

/**
	@brief		Convience class for handling roles.
	@since		20131015
**/
class collection
extends \plainview\sdk\collections\collection
{
	/**
		@brief		Return the roles as an array suitable for a form2 select.
		@param		string		$value		Which is the role properties to use as the select option value.
		@since		201311015
	**/
	public function as_form_options( $value = 'id' )
	{
		$r = [];
		foreach( $this->items as $role )
		{
			$name = $role::$name;
			$name = _( $name );
			$name = ucfirst( $name );
			$r[ $name ] = $role::$$value;
		}
		return $r;
	}

	/**
		@brief		Find the user's role.
		@since		201311015
	**/
	public function find_role()
	{
		$r = 1;
		foreach( $this->items as $item )
			if ( ( $item::$id > $r ) && $item::current_user_can() )
				$r = $item::$id;
		return $this->get( $r );
	}
}
