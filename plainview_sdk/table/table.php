<?php

namespace plainview\table;

require_once( 'table_element.php' );

/**
	@brief		Plainview XHTML table class.

	@details	Allows tables to be made created, modified and displayed efficiently.

	@par		Example 1

	@code
	$table = new table();
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
			->td()->text( $name->first )	// Create a td
			->row()							// Get the row back from the td
			->td()->text( $name->last )		// Create another td in the same row
	}
	@endcode

	@par		Example 2 - How about some styling?

	@code
	$tr->td()->text( $name->first )->css_style( 'font-weight: bold' );
	@endcode

	@par		Example 3 - How about some CSS classing??

	@code
	$tr->td()->text( $name->first )->css_class( 'align_center' )->css_style( 'font-size: 200%;' );
	@endcode

	@par		Example 4 - Reworking a cell

	@code
	$tr->td( 'first_name_1' )->text( $name->first );
	$tr->td( 'first_name_1' )->css_class( 'align_center' )->css_style( 'font-size: 200%;' );
	@endcode

	@par		Source code sorting

	The functions are sorted in order of importance: table > sections > rows > cells

	@par		Changelog

	- 20130527		Element UUID length extended from 4 to 8 to help prevent conflicts.
	- 20130513		Table self indents, instead of relying on html\\element.
	- 20130510		Sections do not display if they are empty.
	- 20130509		_() name_() and title_() added to aid in translation.
	- 20130507		Code: td() and th() can return existing cells.
	- 20130424		Cells are not padded anymore.
	- 20130410		Part of Plainview SDK.
	- 20130408		First release.

	@author			Edward Plainview <edward.plainview@sverigedemokraterna.se>
	@copyright		GPL v3
	@since			20130430
	@version		20130510
**/
class table
{
	use table_element;

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
		$r = $this->indent();
		$r .= $this->open_tag() . "\n";
		$r .= $this->caption . $this->head . $this->foot . $this->body;
		$r .= $this->indent();
		$r .= $this->close_tag() . "\n";
		return $r;
	}

	/**
		@brief		Maybe translate a string. Sprintf aware.
		@details	Is overridden by subclasses to translate strings.

		In this parent class is only returns the sprintf'd arguments.

		@param		string		$string		String to translate.
		@return		string		Sprintf'd (yes) and translated (maybe) string.
		@since		20130509
	**/
	public function _( $string )
	{
		return call_user_func_array( 'sprintf', func_get_args() );
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

	public function indentation()
	{
		return 0;
	}
}

/**
	@brief		A table section: the thead or tbody.
	@since		20130430
	@version	20130430
**/
class section
{
	use table_element;

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
	}

	/**
		@brief		Returns the section as an HTML string.
		@since		20130430
	**/
	public function __tostring()
	{
		if ( $this->text == '' && count( $this->rows ) < 1 )
			return '';

		$r = $this->indent();
		$r .= $this->open_tag() . "\n";

		if ( $this->text != '' )
			$r .= $this->text;

		foreach( $this->rows as $row )
			$r .= $row;
		$r .= $this->indent();
		$r .= $this->close_tag() . "\n";
		return $r;
	}

	public function indentation()
	{
		return $this->table->indentation() + 1;
	}

	/**
		@brief		Retrieve an existing or create a new row, with an optional id.
		@details	Call with no ID to create a new row. Call with an ID that does not exist and a new row will be created

		Call with an ID that has previously been created and it will return the requested row.

		@param		string		$id		The ID (attribute) of this row.
		@return		row		The existing or newly created row.
		@since		20130430
	**/
	public function row( $id = null )
	{
		if ( $id === null || ! isset( $this->rows[ $id ] ) )
		{
			$row = new row( $this, $id );
			$id = $row->id;
			$this->rows[ $id ] = $row;
		}
		return $this->rows[ $id ];
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
	use table_element;

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
			$id = \plainview\base::uuid( 8 );
		$this->id = $id;
		$this->cells = array();
		$this->section = $section;
	}

	/**
		@brief		Return the row as an HTML string.
		@since		20130430
	**/
	public function __tostring()
	{
		if ( count( $this->cells ) < 1 )
			return '';

		$this->attribute( 'id' )->set( $this->id );

		$r = $this->indent();
		$r .= $this->open_tag() . "\n";
		foreach( $this->cells as $cell )
			$r .= $cell;
		$r .= $this->indent();
		$r .= $this->close_tag() . "\n";

		return $r;
	}

	/**
		@brief		Retrieve an existing cell or create a new one.
		@details	If the ID exists, the existing cell is returned.

		If not: if $cell is null, return false;

		If $cell is a cell, add is to the cell array and return it again.

		@param		string			$id			ID of cell to retrieve or create.
		@param		cell			$cell		Cell to add to the cell array.
		@return		cell						The table cell specified, or the newly-created cell.
		@since		20130430
	**/
	public function cell( $id = null , $cell = null )
	{
		if ( $id === null && $cell === null )
			return false;
		if ( ! isset( $this->cells[ $id ] ) )
		{
			$id = $cell->id;
			$this->cells[ $id ] = $cell;
		}
		return $this->cells[ $id ];
	}

	public function indentation()
	{
		return $this->section->indentation() + 1;
	}

	/**
		@brief		Either retrieve an existing td cell or create a new one.
		@param		string		$id			The HTML ID of the cell.
		@return		td						The requested cell.
		@since		20130430
	**/
	public function td( $id = null )
	{
		return $this->cell( $id, new td( $this, $id ) );
	}

	/**
		@brief		Either retrieve an existing th cell or create a new one.
		@param		string		$id			The HTML ID of the cell.
		@return		th						The requested cell.
		@since		20130430
	**/
	public function th( $id = null )
	{
		return $this->cell( $id, new th( $this, $id ) );
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
	use table_element;

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
			$id = \plainview\base::uuid( 8 );
		$this->id = $id;
		$this->row = $row;
	}

	public function __tostring()
	{
		$this->attribute( 'id' )->set( $this->id );
		return $this->indent() . $this->open_tag() . $this->text . $this->close_tag() . "\n";
	}

	public function indentation()
	{
		return $this->row->indentation() + 1;
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

