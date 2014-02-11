<?php

namespace threewp_broadcast\meta_box;

/**
	@brief		The data class that is passed around when creating the broadcast meta box.
	@since		20130928
**/
class data
{
	/**
		@brief		INPUT: ID of the blog.
		@since		20131005
		@var		$blog_id
	**/
	public $blog_id;

	/**
		@brief		OUTPUT: Collection of CSS files that should be loaded.
		@since		20131010
		@var		$css
	**/
	public $css;

	/**
		@brief		INPUT: The Form2 object from the Plainview SDK.
		@since		20131005
		@var		$form
	**/
	public $form;

	/**
		@brief		OUTPUT: HTML object containing data to be displayed.
		@since		20130928
		@var		$html
	**/
	public $html;

	/**
		@brief		OUTPUT: Collection of JS files that should be loaded.
		@since		20131010
		@var		$js
	**/
	public $js;

	/**
		@brief		INPUT: The Wordpress Post object for this meta box.
		@since		20130928
		@var		$post
	**/
	public $post;

	/**
		@brief		INPUT: ID of the post. Convenience property.
		@since		20131005
		@var		$post_id
	**/
	public $post_id;

	public function __construct()
	{
		$this->css = new \plainview\sdk\collections\collection;
		$this->html = new html;
		$this->html->data = $this;
		$this->js = new \plainview\sdk\collections\collection;
	}
}
