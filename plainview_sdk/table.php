<?php

namespace plainview\table;

/**
	@brief		Plainview XHTML table class.
	
	@details	Allows tables to be made created, modified and displayed efficiently.
	
	@par		Example 1
	
	`$table = new table();
	$table->caption()->text( 'This is a caption' );
	$tr = $table->head()->row();
	$tr->th()->text( 'Name' );
	$tr->th()->text( 'Surname' );
	foreach( $names as $name )
	{
		$tr = $table->body()->row();
		$tr->td()->text( $name->first );
		$tr->td()->text( $name->last );
		
		// Or...
		$table->body()->row()				// Create a new row.
			->td()->text( $name->first )
			->row()							// Still the same row, used for chaining.
			->td()->text( $name->last )
	}
	`
	
	@par		Example 2 - How about some styling?
	
	`$tr->td()->text( $name->first )->css_style( 'font-weight: bold' );`
	
	@par		Example 3 - How about some CSS classing??
	
	`$tr->td()->text( $name->first )->css_class( 'align_center' )->css_style( 'font-size: 200%;' );`
	
	@par		Source code sorting
	
	The functions are sorted in order of importance: table > sections > rows > cells
		
	@par		Changelog
	
	- @b 2013-04-24		Cells are not padded anymore.
	- @b 2013-04-10		Part of Plainview SDK.
	- @b 2013-04-08		First release.
	
	@author			Edward Plainview <edward.plainview@sverigedemokraterna.se>
	@license		GPL v3
	@since			20130430
	@version		20130430
**/
class table
{
	use table_object;
	
	/**
		@brief		The body object.
		@var		$body
	**/
	public $body;
	
	/**
		@brief		The foot object.
		@var		$foot
	**/
	public $foot;
	
	/**
		@brief		The head object.
		@var		$head
	**/
	public $head;
	
	/**
		@brief		How many tabs to apply before each output line.
		@var		$tabs
	**/
	public $tabs = 0;
	
	/**
		@brief		Object / element HTML tag.
		@var		$tag
	**/
	public $tag = 'table';
	
	public function __construct()
	{
		$this->caption = new caption( $this );
		$this->body = new body( $this );
		$this->foot = new foot( $this );
		$this->head = new head( $this );
	}
	
	/**
		@brief		Returns the table as an HTML string.
		@since		20130430
	**/
	public function __tostring()
	{
		$rv = '';
		$rv .= $this->open_tag();
		$rv .= $this->caption . $this->head . $this->foot . $this->body;
		$rv .= $this->close_tag();
		return $rv;
	}
	
	/**
		@brief		Return the body section.
		@return		body		The table section of the table.
		@since		20130430
	**/
	public function body()
	{
		return $this->body;
	}
	
	/**
		@brief		Return the caption object of the table.
		@return		table_caption		The table's caption.
		@since		20130430
	**/
	public function caption()
	{
		return $this->caption;
	}
	
	/**
		@brief		Return the foot section.
		@return		foot		The table section of the table.
		@since		20130430
	**/
	public function foot()
	{
		return $this->foot;
	}
	
	/**
		@brief		Return the head section.
		@return		head		The head section of the table.
		@since		20130430
	**/
	public function head()
	{
		return $this->head;
	}
}

/**
	@brief		A table section: the thead or tbody.
	@since		20130430
	@version	20130430
**/
class section
{
	use table_object;

	/**
		@brief		Array of rows.
		@var		$rows
	**/
	public $rows;
	
	/**
		@brief		Parent table.
		@var		$table
	**/
	public $table;
	
	/**
		@brief		Object / element HTML tag.
		@var		$tag
	**/
	public $tag = 'section';
	
	public function __construct( $table )
	{
		$this->table = $table;
		$this->rows = array();
		$this->tabs = $this->table->tabs + 1;
	}
	
	/**
		@brief		Returns the section as an HTML string.
		@since		20130430
	**/		
	public function __tostring()
	{
		$rv = '';
		$rv .= $this->open_tag();
		
		if ( $this->text != '' )
			$rv .= $this->text;
		
		foreach( $this->rows as $row )
			$rv .= $row;
		$rv .= $this->close_tag();
		return $rv;
	}
	
	/**
		@brief		Create a new row, with an optional id.
		@param		string		$id		The HTML ID of this row.
		@return		row		The newly created row.
		@since		20130430
	**/
	public function row( $id = null )
	{
		$row = new row( $this, $id );
		$this->rows[ $row->id ] = $row;
		return $row;
	}
}
/**
	@brief		Body section of table.
	@since		20130430
	@version	20130430
**/
class body
	extends section
{
	public $tag = 'tbody';
}

/**
	@brief		Caption section of table.
	@since		20130430
	@version	20130430
**/
class caption
	extends section
{
	/**
		@brief		Object / element HTML tag.
		@var		$tag
	**/
	public $tag = 'caption';
}

/**
	@brief		Foot section of table.
	@since		20130430
	@version	20130430
**/
class foot
	extends section
{
	public $tag = 'tfoot';
}

/**
	@brief		Head section of table.
	@since		20130430
	@version	20130430
**/
class head
	extends section
{
	public $tag = 'thead';
}

/**
	@brief		A table row.
	@since		20130430
	@version	20130430
**/
class row
{
	use table_object;
	
	/**
		@brief		Array of cells.
		@var		$cells
	**/
	public $cells;
	
	/**
		@brief		Unique ID of this row.
		@var		$id
	**/
	public $id;
	
	/**
		@brief		Parent section.
		@var		$section
	**/
	public $section;
	
	/**
		@brief		Object / element tag.
		@var		$tag
	**/
	public $tag = 'tr';

	public function __construct( $section, $id = null )
	{
		if ( $id === null )
			$id = 'r' . $this->random_id();
		$this->id = $id;
		$this->cells = array();
		$this->section = $section;
		$this->tabs = $this->section->tabs + 1;
	}
	
	/**
		@brief		Return the row as an HTML string.
		@since		20130430
	**/
	public function __tostring()
	{
		if ( count( $this->cells ) < 1 )
			return '';
		
		$rv = '';
		$this->set_attribute( 'id', $this->id );
		$rv .= $this->open_tag();
		foreach( $this->cells as $cell )
			$rv .= $cell;
		$rv .= $this->close_tag();
		
		return $rv;
	}
	
	/**
		@brief		Add a cell to the cell array.
		@param		table_cell		The table cell to add.
		@return		table_cell		The table cell just added.
		@since		20130430
	**/
	public function cell( $table_cell )
	{
		$this->cells[ $table_cell->id ] = $table_cell;
		return $table_cell;
	}
		
	/**
		@brief		Create a new td cell, with an optional id.
		@param		string		$id			The HTML ID of the td.
		@return		td		The newly created td.
		@since		20130430
	**/
	public function td( $id = null )
	{
		$td = new td( $this, $id );
		return $this->cell( $td );
	}
	
	/**
		@brief		Create a new th cell, with an optional id.
		@param		string		$id			The HTML ID of the th.
		@return		th		The newly created th.
		@since		20130430
	**/
	public function th( $id = null )
	{
		$th = new th( $this, $id );
		return $this->cell( $th );
	}
}

/**
	@brief		A table cell.
	@details	This is a superclass for the td and th subclasses.
	@since		20130430
	@version	20130430
**/
class cell
{
	use table_object;
	
	/**
		@brief		Unique ID of this cell.
		@var		$id
	**/
	public $id;
	
	/**
		@brief		row object with which this cell was created.
		@var		$row
	**/
	public $row;
	
	public $tag = 'cell';
	
	public function __construct( $row, $id = null )
	{
		if ( $id === null )
			$id = 'c' . $this->random_id();
		$this->id = $id;
		$this->row = $row;
		$this->tabs = $this->row->tabs + 1;
	}
	
	public function __tostring()
	{
		$this->set_attribute( 'id', $this->id );
		return rtrim( $this->open_tag() ) . $this->text . ltrim( $this->close_tag() );
	}
	
	/**
		@brief		Return the row of this cell.
		@details	Is used to continue the ->td()->row()->td() chain.
		@return		row		The table row this cell was created in.
		@since		20130430
	**/
	public function row()
	{
		return $this->row;
	}	
}	

/**
	@brief		Cell of type TD.
	@since		20130430
**/
class td
	extends cell
{
	public $tag = 'td';
}

/**
	@brief		Cell of type TH.
	@since		20130430
**/
class th
	extends cell
{
	public $tag = 'th';
}

/**
	@brief		Used for setting attributes and handling open / close tags.
	@since		20130430
**/
trait table_object
{
	/**
		@brief		Parent section.
		@var		$section
	**/
	public $attributes = array();
	/**
		@brief		Parent section.
		@var		$section
	**/
	public $css_class = array();
	
	/**
		@brief		Text / contents of this object.
		@var		$text
	**/
	public $text = '';
	
	/**
		@brief		Append a text to an attribute.
		@details	Text is appended with a space between.
		@param		string		$type		Type of attribute.
		@param		string		$text		Attribute text to append.
		@return		$this
		@since		20130430
	**/
	public function append_attribute( $type, $text )
	{
		$text = $this->get_attribute( $type ) . ' ' . $text;
		$text = trim( $text );
		return $this->set_attribute( $type, $text );
	}
	
	/**
		@brief		Convenience function to append an attribute.
		@param		string		$type		Type of attribute.
		@param		string		$text		Attribute text.
		@return		$this
		@see		append_attribute
		@since		20130430
	**/
	public function attribute( $type, $text )
	{
		return $this->append_attribute( $type, $text );
	}
	
	/**
		@brief		Output a string that closes the tag of this object.
		@return		string		The closed tag.
		@since		20130430
	**/
	public function close_tag()
	{
		return sprintf( '%s</%s>%s', $this->tabs(), $this->tag, "\n" );
	}
	
	/**
		@brief		Convenience function to set colspan property.
		
		Should only be used on cells.
		
		@param		string		$colspan		How much the object should colspan.
		@return		$this
		@since		20130430
	**/
	public function colspan( $colspan )
	{
		return $this->set_attribute( 'colspan', $colspan );
	}
	
	/**
		@brief		Convenience function to add another CSS class to this object.
		@param		string		$css_class		A CSS class or classes to append to the object.
		@return		$this
		@since		20130430
	**/
	public function css_class( $css_class )
	{
		return $this->append_attribute( 'class', $css_class );
	}
	
	/**
		@brief		Convenience function to add another CSS style to this object.
		@param		string		$css_style		A CSS style string to append to this object.
		@return		$this
		@since		20130430
	**/
	public function css_style( $css_style )
	{
		$style = $this->get_attribute( 'style' );
		$style .= '; ' . $css_style;
		$style = trim( $style, '; ' );
		$style = preg_replace( '/;;/m', ';', $style );
		return $this->append_attribute( 'style', $style );
	}
	
	/**
		@brief		string		$type		Type of attribute (key).
		@return		false|string			False if there is no attribute of that type set, or whatever is set.
		@since		20130430
	**/
	public function get_attribute( $type )
	{
		if ( ! isset( $this->attributes[ $type ] ) )
			return false;
		else
			return $this->attributes[ $type ];
	}
	
	/**
		@brief		Convenience function to set header property.
		
		The header property of a td cell is an accessability feature that tells screen readers which th headers this cell is associated with.
		
		@param		string		$header		The ID or IDs (spaced) with which this cell is associated.
		@return		$this					The class itself.
		@since		20130430
	**/
	public function header( $header )
	{
		return $this->set_attribute( 'header', $header );
	}
	
	/**
		@brief		Opens the tag of this object.		
		@details	Will take care to include any attributes that have been set.
		@since		20130430
	**/
	public function open_tag()
	{
		$attributes = array();
		
		foreach( $this->attributes as $key => $value )
			if ( $value != '' )
				$attributes[] = sprintf( '%s="%s"', $key, trim( $value ) );
			
		if ( count( $attributes ) > 0 )
			$attributes = ' ' . implode( ' ', $attributes );
		else
			$attributes = '';
		
		return sprintf( '%s<%s%s>%s', $this->tabs(), $this->tag, $attributes, "\n" );
	}
	
	/**
		@brief		Produce a random ID if 8 md5 characters.
		@return		string		An 8-character random ID.
		@since		20130430
	**/
	public function random_id()
	{
		$id = md5( microtime() . rand( 1000, 9999 ) );
		$id = substr( $id, 0, 8 );
		return $id;
	}
	
	/**
		@brief		Clears and resets an attribute with new text.		
		@param		string		$type		Type of attribute.
		@param		string		$text		Attribute text to set.
		@return		$this					The class itself.
		@since		20130430
	**/
	public function set_attribute( $type, $text )
	{
		$this->attributes[ $type ] = $text;
		return $this;
	}
	
	/**
		@brief		Returns a string of tabs.
		@param		int		$tabs		How many tabs to return. If null, uses the $tabs property of the object.
		@return		string				A number of tab characters.
		@since		20130430
	**/
	public function tabs( $tabs = null )
	{
		if ( $tabs === null )
			$tabs = $this->tabs;
		return str_pad( '', $tabs, "\t" );
	}
	
	/**
		@brief		Sets the text of this object.
		@details	The text is the contents of this object, most often an HTML string.
		@param		string		$text		Text to set.
		@return		$this					The class itself.
		@since		20130430
	**/
	public function text( $text )
	{
		$this->text = $text;
		return $this;
	}
	
	/**
		@brief		Sets the text of this object using sprintf.
		@details	The $text and all extra parameters is run through sprintf as convenience.
		@param		string		$text		Text to set via sprintf.
		@return		$this
		@see		text()
	**/
	public function textf( $text )
	{
		return $this->text( call_user_func_array( 'sprintf', func_get_args() ) );
	}
	
	/**
		@brief		Convenience function to set the hoverover title property.
		@param		string		$title		Title to set.
		@return		$this
	**/
	public function title( $title )
	{
		return $this->attribute( 'title', $title );
	}
}

