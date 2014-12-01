<?php

namespace threewp_broadcast\meta_box;

use \plainview\sdk_broadcast\collections\collection;

/**
	@brief		HTML item.
	@since		20131027
**/
class item
{
	/**
		@brief		Convenience pointer to the meta box data object.
		@since		20131027
	**/
	public $data;

	public function __construct( $meta_box_data )
	{
		$this->data = $meta_box_data;
		$this->inputs = new collection;
		$this->_construct();
	}

	/**
		@brief		Called after the item has been constructed.
		@details	At this point the data object has been intialized.
		@since		20131027
	**/
	public function _construct()
	{
	}

	public function __toString()
	{
		$r = '';
		foreach( $this->inputs as $input )
			$r .= $input;
		return $r;
	}

	/**
		@brief		Extend this method to be informed of when the meta box data has been prepared / populated by the various plugins.
		@since		20131027
	**/
	public function meta_box_data_prepared()
	{
	}

	public static function is_a( $object )
	{
		if ( ! is_object( $object ) )
			return false;
		return is_subclass_of( $object, get_called_class() );
	}
}