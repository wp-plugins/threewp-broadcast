<?php

namespace plainview\sdk\wordpress\roles;

class editor
extends role
{
	public static $can = 'manage_links';
	public static $id = 4;
	public static $name = 'editor';
}
