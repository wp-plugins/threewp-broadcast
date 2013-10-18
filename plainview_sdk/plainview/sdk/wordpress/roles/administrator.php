<?php

namespace plainview\sdk\wordpress\roles;

class administrator
extends role
{
	public static $can = 'manage_options';
	public static $id = 5;
	public static $name = 'administrator';
}
