<?php

namespace threewp_broadcast\broadcast_data;

class blog
{
	use \plainview\sdk_broadcast\traits\method_chaining;

	public $id;

	public $disabled = false;
	public $linked = false;
	public $required = false;
	public $selected = false;

	/**
		@brief		Allow construction of blog with ID.
		@since		2015-06-15 10:43:57
	**/
	public function __construct( $id = null )
	{
		if ( absint( $id ) > 0 )
			$this->id = absint( $id );
	}

	public function __toString()
	{
		$info = get_blog_details( $this->id );
		$r = $info->blogname ? $info->blogname : $info->domain . $info->path;
		return $r . '';
	}

	public function disabled( $disabled = true )
	{
		return $this->set_boolean( 'disabled', $disabled );
	}

	/**
		@brief		Return a unique ID for this blog.
		@details	This is the preferred way of getting the ID of the blog.
	**/
	public function get_id()
	{
		return $this->id;
	}

	/**
		@brief		Return the blog's name.
		@details	This is the preferred way of getting the ID of the blog name.
	**/
	public function get_name()
	{
		return $this->__toString();
	}

	public function is_disabled()
	{
		return $this->disabled;
	}

	public function is_linked()
	{
		return $this->linked;
	}

	public function is_required()
	{
		return $this->required;
	}

	public function is_selected()
	{
		return $this->selected;
	}

	public function linked( $linked = true )
	{
		return $this->set_boolean( 'linked', $linked );
	}

	public static function make( $data )
	{
		$r = new blog;
		foreach( (array)$data as $key=>$value )
			$r->$key = $value;
		if ( property_exists( $r, 'blog_id' ) )
			$r->id = intval( $r->blog_id );
		return $r;
	}

	public function required( $required = true )
	{
		return $this->set_boolean( 'required', $required );
	}

	public function selected( $selected = true )
	{
		return $this->set_boolean( 'selected', $selected );
	}

	public function switch_to()
	{
		switch_to_blog( $this->id );
	}

	public function switch_from()
	{
		restore_current_blog();
	}

}
