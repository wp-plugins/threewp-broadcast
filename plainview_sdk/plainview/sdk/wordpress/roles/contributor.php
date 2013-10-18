<?php

namespace plainview\sdk\wordpress\roles;

class contributor
extends role
{
	public static $can = 'edit_posts';
	public static $id = 2;
	public static $name = 'contributor';
}
