<?php

namespace plainview\sdk\wordpress\roles;

class role
{
	public static $can = 'read';
	public static $id = 0;
	public static $name = '';

	public static function get_name()
	{
		return self::$name;
	}

	public static function current_user_can()
	{
		return current_user_can( self::$can );
	}
}
