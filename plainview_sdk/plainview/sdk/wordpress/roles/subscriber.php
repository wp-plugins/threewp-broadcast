<?php

namespace plainview\sdk\wordpress\roles;

class subscriber
extends role
{
	public static $can = 'read';
	public static $id = 1;
	public static $name = 'subscriber';
}
