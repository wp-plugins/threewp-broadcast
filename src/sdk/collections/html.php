<?php

namespace plainview\sdk_broadcast\collections;

/**
	@brief		A collection of HTML strings.
	@details	The main method is append(). get() and set() aren't really used.
	@since		2014-05-04 13:10:54
**/
class html
	extends collection
{
	/**
		@brief		Converts all of the items to a string.
		@since		2014-05-04 13:09:23
	**/
	public function __toString()
	{
		$r = implode( '', $this->items );
		return $r;
	}

	/**
		@brief		Appends a new html string to the collection.
		@since		2014-05-04 13:08:18
	**/
	public function append( $item )
	{
		$args = func_get_args();
		$text = call_user_func_array( 'sprintf', $args );
		if ( $text == '' )
			$text = $args[ 0 ];
		$text = \plainview\sdk_broadcast\base::wpautop( $text );
		return parent::append( $text );
	}
}
