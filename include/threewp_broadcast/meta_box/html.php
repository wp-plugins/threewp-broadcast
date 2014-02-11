<?php

namespace threewp_broadcast\meta_box;

class html
	extends \plainview\sdk\collections\collection
{
	/**
		@brief		The [meta box] data container.
		@since		20131027
	**/
	public $data;

	public function __toString()
	{
		$r = '';
		foreach( $this->items as $key => $value )
			$r .= sprintf( '<div class="%s html_section">%s</div>', $key, $value );
		return $r;
	}

	/**
		@brief		Converts any item objects to strings.
		@since		20131027
	**/
	public function render()
	{
		foreach( $this->items as $item )
		{
			if ( ! item::is_a( $item ) )
				continue;
			$item->meta_box_data_prepared();
		}
		return $this->__toString();
	}
}