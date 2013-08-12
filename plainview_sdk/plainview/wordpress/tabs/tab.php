<?php

namespace plainview\wordpress\tabs;

/**
	@brief		Actual tab that tabs contains.

	@par		Changelog

	- 20130505	New: parameters()
	- 20130503	Initial release

	@since		20130503
	@version	20130505
**/
class tab
{
	/**
		@brief		Tab callback function.
		@details	An array of (class, function_name) or just a function name.
					The default callback is the ID of the tab.
		@see		tabs::tab
		@since		20130503
		@var		$callback
	**/
	public $callback;

	/**
		@brief		Optional count to be displayed after the tab name. Default is no count.
		@since		20130503
		@var		$count
	**/
	public $count = '';

	/**
		@brief		Optional heading to display as the page heading instead of the tab name.
		@since		20130503
		@var		$heading
	**/
	public $heading;

	/**
		@brief		The ID of the tab.
		@since		20130503
		@var		$id
	**/
	public $id;

	/**
		@brief		Displayed name of tab.
		@since		20130503
		@var		$name
	**/
	public $name;

	/**
		@brief		An optional array of parameters to send to the callback.
		@since		20130505
		@var		$parameters
	**/
	public $parameters = array();

	/**
		@brief		Prefix that is displayed before displaying the tab name.
		@since		20130503
		@var		$prefix
	**/
	public $prefix;

	/**
		@brief		Suffix that is displayed after displaying the tab name.
		@since		20130503
		@var		$suffix
	**/
	public $suffix;

	/**
		@brief		The \\plainview\\wordpress\\tabs\\tabs object this tab belongs to.
		@since		20130503
		@var		$tabs
	**/
	public $tabs;

	/**
		@brief		The HTML title associated with the tab name.
		@since		20130503
		@var		$title
	**/
	public $title;

	public function __construct( $tabs )
	{
		$this->tabs = $tabs;
		$this->prefix = $tabs->tab_prefix;
		$this->suffix = $tabs->tab_suffix;
		return $this;
	}

	/**
		@brief		Sets the callback for this tab.
		@details	Either a class + function combination or just the function.
		@param		mixed		$callback		A class or function name.
		@param		string		$function		If $callback is a class, this is the method within the class to be called.
		@return		object						This tab.
		@since		20130503
	**/
	public function callback( $callback, $function = '' )
	{
		if ( $function != '' )
			$callback = array( $callback, $function );
		$this->callback = $callback;
		return $this;
	}

	/**
		@brief		Convenience function to call a method of the base object.
		@param		string		$method		Name of method to call.
		@return		object					Object chaining.
		@since		20130503
	**/
	public function callback_this( $method )
	{
		return $this->callback( $this->tabs->base, $method );
	}

	/**
		@brief		Set the page heading for this tab.
		@details	Optionally display this heading instead of the tab name as the page heading.
		@param		string		$heading		The page heading to set.
		@return		object						This tab.
		@since		20130503
	**/
	public function heading( $heading )
	{
		$this->heading = $heading;
		return $this;
	}

	/**
		@brief		Translate and set the page heading for this tab.
		@details	Almost the same as heading(), except the string is translated first.
		@param		string		$heading		The page heading to translate and set.
		@return		object						Object chaining.
		@see		heading()
		@since		20130503
	**/
	public function heading_( $heading )
	{
		return $this->heading( call_user_func_array( array( $this->tabs->base, '_' ), func_get_args() ) );
	}

	/**
		@brief		Set the name of this tab.
		@details	The name is displays in the tab list and as the page heading, if no specific page heading is set.
		@param		string		$name		The new name of the tab.
		@return		object					Object chaining.
		@since		20130503
	**/
	public function name( $name )
	{
		$this->name = $name;
		return $this;
	}

	/**
		@brief		Translate and set the name of this tab.
		@param		string		$name		String to translate and set as the name.
		@return		object					Object chaining.
		@see		name()
		@since		20130503
	**/
	public function name_( $name )
	{
		return $this->name( call_user_func_array( array( $this->tabs->base, '_' ), func_get_args() ) );
	}

	/**
		@brief		Set the parameters for the tab's callback.
		@details	All parameters used in this method are also sent directly to the callback.
		@return		object					Object chaining.
		@since		20130505
	**/
	public function parameters()
	{
		$this->parameters = func_get_args();
		return $this;
	}

	/**
		@brief		Set the HTML title of the page name in the tab list.
		@param		string		$title		Title to set.
		@return		object					Object chaining.
		@since		20130503
	**/
	public function title( $title )
	{
		$this->title = $title;
		return $this;
	}

	/**
		@brief		Translate and set the HTML title of the page name.
		@param		string		$title		Title to translate and set.
		@return		object					Object chaining.
		@see		title()
		@since		20130503
	**/
	public function title_( $title )
	{
		return $this->title( call_user_func_array( array( $this->tabs->base, '_' ), func_get_args() ) );
	}
}
