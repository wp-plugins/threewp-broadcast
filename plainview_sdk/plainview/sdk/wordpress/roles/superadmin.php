<?php

namespace plainview\sdk\wordpress\roles;

class superadmin
extends role
{
	public static $can = 'manage_options';
	public static $id = 6;
	public static $name = 'super admin';

	public static function current_user_can()
	{
		return is_super_admin();
	}
}
