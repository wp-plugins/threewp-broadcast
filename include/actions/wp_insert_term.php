<?php

namespace threewp_broadcast\actions;

/**
	@brief
	@since		2014-04-08 15:09:59
**/
class wp_insert_term
	extends action
{
	/**
		@brief		OUT: The newly-created term array. Or a WP_Error.
		@since		2014-04-08 15:32:24
	**/
	public $new_term;

	/**
		@brief		IN: The name of the taxonomy in which to create the term.
		@since		2014-04-08 15:34:26
	**/
	public $taxonomy;

	/**
		@brief		IN: The term object to create, taken from the source blog.
		@since		2014-04-08 15:30:19
	**/
	public $term;
}
