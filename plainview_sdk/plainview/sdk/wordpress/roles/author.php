<?php

namespace plainview\sdk\wordpress\roles;

class author
extends role
{
	public static $can = 'publish_posts';
	public static $id = 3;
	public static $name = 'author';
}
