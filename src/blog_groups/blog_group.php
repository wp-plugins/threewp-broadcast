<?php

namespace threewp_broadcast\blog_groups;

use \threewp_broadcast\blog_collection;

class blog_group
	extends \threewp_broadcast\db_object
{
	use \plainview\sdk_broadcast\wordpress\traits\db_aware_object;

	public $id;
	public $data;
	public $user_id;

	public function __construct()
	{
		$this->data = new \stdClass;
		$this->data->blogs = new blog_collection;
	}

	public static function db_table()
	{
		global $wpdb;
		return $wpdb->base_prefix. '3wp_broadcast_blog_groups';
	}

	public static function keys()
	{
		return [
			'id',
			'data',
			'user_id',
		];
	}

	public static function keys_to_serialize()
	{
		return [
			'data',
		];
	}
}
