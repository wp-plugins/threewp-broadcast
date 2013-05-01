<?php

namespace plainview\wordpress\table;

if ( ! class_exists( 'plainview::table::table' ) )
	require_once( dirname( __FILE__ ) . '/../table.php' );

/**
	@brief			Provides Wordpress functions for expanding the \plainview\table class.
	@details		The text_() function allows for translations directly, instead of having to go through `->text( $this->_( 'String to translate' ) )`.
	@author			Edward Plainview		edward@plainview.se
	@license		GPL v3
	@since			20130430
	@version		20130430
**/
class table
	extends \plainview\table\table
{
	/**
		@brief		The \plainview\table\wordpress\base object that created this class.
	**/
	public $base;
	
	public function __construct( $base )
	{
		$this->base = $base;
		$this->caption = new caption( $this );
		$this->body = new body( $this );
		$this->foot = new foot( $this );
		$this->head = new head( $this );
	}	
}

class section
	extends \plainview\table\section
{
	use wordpress_table_object;

	public function row( $id = null )
	{
		$row = new row( $this, $id );
		$this->rows[ $row->id ] = $row;
		return $row;
	}
}

class body
	extends \plainview\wordpress\table\section
{
	public $tag = 'tbody';
}

class caption
	extends \plainview\wordpress\table\section
{
	public $tag = 'caption';
}

class foot
	extends \plainview\wordpress\table\section
{
	public $tag = 'tfoot';
}

class head
	extends \plainview\wordpress\table\section
{
	public $tag = 'thead';
}

class row
	extends \plainview\table\row
{
	use wordpress_table_object;

	public function td( $id = null )
	{
		$td = new td( $this, $id );
		return $this->cell( $td );
	}
	
	public function th( $id = null )
	{
		$th = new th( $this, $id );
		return $this->cell( $th );
	}
}

class cell
	extends \plainview\table\cell
{
	use wordpress_table_object;
}

class td
	extends \plainview\table\td
{
	use wordpress_table_object;
}

class th
	extends \plainview\table\th
{
	use wordpress_table_object;
}

trait wordpress_table_object
{
	/**
		@brief		Translates and sets the text of this object. Sprintf friendly.
		@details	The text is the contents of this object, most often an HTML string.
		@param		string		$string		Text to set. Is translated using the base's _() function.
		@return		$this
		@see		\plainview\wordpress\base::_
		@since		20130430
	**/
	public function text_( $string )
	{
		// Find where the base is, no matter in which class we're called.
		if ( isset( $this->base ) )
			$base = $this->base;
		if ( isset( $this->table ) )
			$base = $this->table->base;
		if ( isset( $this->row ) )
			$base = $this->row->section->table->base;
		
		// Ask the base class to translate the string for us.
		$this->text = call_user_func_array( array( $base, '_' ), func_get_args() );
		return $this;
	}
}

