<?php

namespace plainview\form2\inputs;

/**
	@brief		Input superclass.
	@author		Edward Plainview <edward@plainview.se>
	@copyright	GPL v3
	@version	20130524
**/
class input
{
	use \plainview\html\element;
	use \plainview\html\indentation;
	use traits\disabled;		// Most elements can be disabled, so instead of including it 500 times later, just include it once here.
	use traits\label;			// Same reason as _disabled.
	use traits\datalist;		// Same reason as _disabled.
	use traits\readonly;		// Same reason as _disabled.
	use traits\validation;		// All subinputs will inherit the validation methods.

	public $container;

	public $description = '';

	public $label = '';

	public $prefix = array();

	public $self_closing = true;

	public $tag = 'input';

	public function __construct( $container, $name )
	{
		$this->container = $container;
		$this->description = new description( $this );
		$this->set_attribute( 'name', $name );
		if ( isset( $this->type ) )
			$this->set_attribute( 'type', $this->type );
		$this->_construct();
	}

	/**
		@brief		Displays the input as a string.
		@details	Converting a combination of label + input + description, wrapped in a div, is a several step process.

		First, we must use placeholders to hold the above elements during the wrapping and tabbing process.
		Otherwise any tabs in the input / description / label will be modified, which breaks especially breaks textareas.

		Then, after wrapping and retabbing is done, replace the unique placeholders with the actual elements.

		@return		string		The input + label + description as a string.
		@since		20130524
	**/
	public function __toString()
	{
		$random = \plainview\base::uuid();

		$placeholders = new \stdClass();
		$placeholders->label = $random . 'label';
		$placeholders->input = $random . 'input';
		$placeholders->description = $random . 'description';

		$div = new \plainview\html\div();
		$div->css_class( 'form_item' )
			->css_class( 'form_item_' . ( isset( $this->type ) ? $this->type : $this->tag ) )
			->css_class( 'form_item_' . $this->make_id() );

		// It would be a good idea if the container could include information about the status of the input.
		if ( ! $this->validates() )
			$div->css_class( 'does_not_validate' );
		if ( $this->is_required() )
			$div->css_class( 'required' );

		$description = $this->display_description();
		if ( $description != '' )
			$description = "\n" . $this->indent() . $placeholders->description;
		$r = sprintf( '%s%s%s',
			$div->open_tag(),
			"\n",
			$this->indent() . $placeholders->label . "\n" .
			$this->indent() . $placeholders->input .
			$description
		);
		// Increase one tab
		$r = preg_replace( '/^\\t/m', "\t\t", $r );
		// Close the tag
		$r = $this->indent() . $r . "\n" . $this->indent() . $div->close_tag() . "\n";

		// Replace the placeholders with their corresponding functions.
		foreach( $placeholders as $type => $placeholder )
		{
			$function = 'display_' . $type;
			$r = str_replace( $placeholder, $this->$function(), $r );
		}

		return $r;
	}

	/**
		@brief		Overridable method for subclasses to use instead of having to override the parent constructor and remembering to parent::construct.
		@since		20130524
	**/
	public function _construct()
	{
	}

	/**
		@brief		Append a / several prefixes.
		@details	All arguments to this method are discovered and appended.
		@return		this		Object chaining.
		@see		prefix()
		@see		prepend_prefix()
		@since		20130524
	**/
	public function append_prefix( $prefix )
	{
		foreach( func_get_args() as $arg )
			$this->prefix[] = $arg;
		return $this;
	}

	/**
		@brief		Return the input's container.
		@return		object		The container in which this input is placed. Form or fieldset.
		@since		20130524
	**/
	public function container()
	{
		return $this->container;
	}

	/**
		@brief		Set the description for this input.
		@param		string		$text		The text to set as the description.
		@return		this		Object chaining.
		@since		20130524
	**/
	public function description( $text )
	{
		call_user_func_array( array( $this->description, 'label' ), func_get_args() );
		return $this;
	}

	/**
		@brief		Translate and set the description for this input.
		@param		string		$text		The text to translate and set as the description.
		@return		this		Object chaining.
		@since		20130524
	**/
	public function description_( $text )
	{
		call_user_func_array( array( $this->description, 'label_' ), func_get_args() );
		return $this;
	}

	/**
		@brief		Request that the description convert itself to a string.
		@return		string		The description as a string.
		@since		20130524
	**/
	public function display_description()
	{
		return $this->description;
	}

	/**
		@brief		Display the input itself.
		@return		string		The input as HTML.
		@since		20130524
	**/
	public function display_input()
	{
		$input = clone $this;

		$input->set_attribute( 'id', $input->make_id() );
		$input->set_attribute( 'name', $input->make_name() );

		$input->css_class( isset( $this->type ) ? $this->type : $this->tag );

		if ( $input->is_required() )
			$input->css_class( 'required' );

		if ( ! $this->validates() )
			$input->css_class( 'does_not_validate' );
		else
			if ( $this->requires_validation() )
				$input->css_class( 'validates' );

		// Is the POST variable set?
		if ( $input->form()->post_is_set() )
		{
			// Retrieve the post value.
			$value = $input->get_value();
			if ( $value !== null )
			{
				$value = \plainview\form2\form::unfilter_text( $value );
				$input->value( $value );
			}
			else
				$this->clear_attribute( 'value' );
		}

		// Allow subclasses the chance to modify themselves in case displaying isn't straightforward.
		$input->prepare_to_display();

		return $input->open_tag() . $input->display_value() . $input->close_tag();
	}

	/**
		@brief		Display the input's label.
		@return		string		The label as HTML.
		@since		20130524
	**/
	public function display_label()
	{
		$input = clone $this;
		$input->set_attribute( 'id', $input->make_id() );
		$input->set_attribute( 'name', $input->make_name() );

		return sprintf(
			'<label for="%s">%s</label>',
			$input->make_id(),
			$input->get_label()
		);
	}

	/**
		@brief		Return the form object.
		@return		form		The form object.
		@since		20130524
	**/
	public function form()
	{
		return $this->container->form();
	}

	public function indentation()
	{
		return $this->container->indentation() + 1;
	}

	/**
		@brief		Make a unique ID for this input.
		@return		string		A unique string fit for use as the HTML ID attribute.
		@since		20130524
	**/
	public function make_id()
	{
		$id = $this->get_attribute( 'id' );
		if ( $id !== false )
			return $id;
		$id = $this->make_name();
		$id = \plainview\base::strtolower( $id );
		$id = preg_replace( '/[\[|\]]/', '_', $id );
		return $id;
	}

	/**
		@brief		Make the form name for this input.
		@details	Takes the prefixes into account when making the name.
		@return		string		A form name for this input.
		@since		20130524
	**/
	public function make_name()
	{
		$name = $this->get_attribute( 'name' );
		$names = array_merge( $this->prefix, array( $name ) );
		$r = '';

		// No prefix? Just return the name.
		if ( count( $names ) == 1 )
			$r = $name;
		else
		{
			// The first prefix does NOT have brackets. The rest do. *sigh*
			$r = array_shift( $names );
			while ( count( $names ) > 0 )
				$r .= '[' . array_shift( $names ) . ']';
		}

		return $r;
	}

	/**
		@brief		Set the prefix(es) for this input.
		@details	Clears the prefixes before setting them.
		@return		this		Object chaining.
		@see		append_prefix()
		@see		prepend_prefix()
		@since		20130524
	**/
	public function prefix( $prefix )
	{
		$this->prefix = func_get_args();
		return $this;
	}

	public function prepare_to_display()
	{
	}

	/**
		@brief		Prepend a / several prefixes.
		@details	All arguments to this method are discovered and prepended to the beginning of the current prefixes.
		@return		this		Object chaining.
		@see		append_prefix()
		@see		prefix()
		@since		20130524
	**/
	public function prepend_prefix( $prefix )
	{
		foreach( func_get_args() as $arg )
			array_unshift( $this->prefix, $arg );
		return $this;
	}

	/**
		@brief		Convenience method to translate the title before setting it.
		@return		this		Object chaining.
		@since		20130524
	**/
	public function title_( $title )
	{
		$title = call_user_func_array( array( $this->container, '_' ), func_get_args() );
		return $this->title( $title );
	}
}

