<?php

namespace threewp_broadcast\meta_box;

/**
	@brief		The data class that is passed around when creating the broadcast meta box.
	@since		20130928
**/
class data
{
	/**
		@brief		ID of the blog.
		@since		20131005
		@var		$blog_id
	**/
	public $blog_id;

	/**
		@brief		The Form2 object from the Plainview SDK.
		@since		20131005
		@var		$form
	**/
	public $form;

	/**
		@brief		HTML object containing data to be displayed.
		@since		20130928
		@var		$html
	**/
	public $html;

	/**
		@brief		The Wordpress Post object for this meta box.
		@since		20130928
		@var		$post
	**/
	public $post;

	/**
		@brief		ID of the post. Convenience property.
		@since		20131005
		@var		$post_id
	**/
	public $post_id;

	public function __construct()
	{
		$this->html = new html;
	}
}
