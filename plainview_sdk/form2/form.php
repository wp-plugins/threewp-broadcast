<?php

namespace plainview\form2;

require_once( 'inputs/traits/trait.autocomplete.php' );
require_once( 'inputs/traits/trait.checked.php' );
require_once( 'inputs/traits/trait.container.php' );
require_once( 'inputs/traits/trait.disabled.php' );
require_once( 'inputs/traits/trait.high.php' );
require_once( 'inputs/traits/trait.label.php' );
require_once( 'inputs/traits/trait.list.php' );
require_once( 'inputs/traits/trait.low.php' );
require_once( 'inputs/traits/trait.max.php' );
require_once( 'inputs/traits/trait.maxlength.php' );
require_once( 'inputs/traits/trait.min.php' );
require_once( 'inputs/traits/trait.minlength.php' );
require_once( 'inputs/traits/trait.optimum.php' );
require_once( 'inputs/traits/trait.options.php' );
require_once( 'inputs/traits/trait.placeholder.php' );
require_once( 'inputs/traits/trait.readonly.php' );
require_once( 'inputs/traits/trait.selected.php' );
require_once( 'inputs/traits/trait.size.php' );
require_once( 'inputs/traits/trait.step.php' );
require_once( 'inputs/traits/trait.validation.php' );
require_once( 'inputs/traits/trait.value.php' );

require_once( 'inputs/classes/class.input.php' );
require_once( 'inputs/classes/class.option.php' );
require_once( 'inputs/classes/class.text.php' );
require_once( 'inputs/classes/class.number.php' );
require_once( 'inputs/classes/class.select_optgroup.php' );

require_once( 'validation/class.error.php' );

require_once( 'inputs/classes/class.button.php' );
require_once( 'inputs/classes/class.checkbox.php' );
require_once( 'inputs/classes/class.checkboxes.php' );
require_once( 'inputs/classes/class.datalist.php' );
require_once( 'inputs/classes/class.datalist_option.php' );
require_once( 'inputs/classes/class.date.php' );
require_once( 'inputs/classes/class.datetime.php' );
require_once( 'inputs/classes/class.datetime_local.php' );
require_once( 'inputs/classes/class.description.php' );
require_once( 'inputs/classes/class.email.php' );
require_once( 'inputs/classes/class.fieldset.php' );
require_once( 'inputs/classes/class.hidden.php' );
require_once( 'inputs/classes/class.legend.php' );
require_once( 'inputs/classes/class.markup.php' );
require_once( 'inputs/classes/class.meter.php' );
require_once( 'inputs/classes/class.month.php' );
require_once( 'inputs/classes/class.password.php' );
require_once( 'inputs/classes/class.radio.php' );
require_once( 'inputs/classes/class.radios.php' );
require_once( 'inputs/classes/class.range.php' );
require_once( 'inputs/classes/class.search.php' );
require_once( 'inputs/classes/class.select.php' );
require_once( 'inputs/classes/class.select_option.php' );
require_once( 'inputs/classes/class.submit.php' );
require_once( 'inputs/classes/class.tel.php' );
require_once( 'inputs/classes/class.time.php' );
require_once( 'inputs/classes/class.textarea.php' );
require_once( 'inputs/classes/class.url.php' );
require_once( 'inputs/classes/class.week.php' );

/**
	@brief		HTML5/XHTML form manipulation class.
	@details

	Provides form generation, manipulation, _POST handling and validation.

	All values and labels are stored filtered and can be displayed directly.

	Examples
	--------

	@par		Generate a form with a text input

	@code
		$form = new \\plainview\\form2\\form();
		// Add a text input
		$form->text( 'username' )
			->label( 'Your username' );
		// And display the form. Start() opens the form tag and stop()...
		echo $form->start() . $form . $form->stop();
	@endcode

	@par		Add more attributes to the above text field

	@code
		// Using the same name will find the existing input
		$form->text( 'username' )
			->description( 'You should have received your username by now.' )
			->maxlength( 64 )
			->size( 40 )
			->title( "Hovering won't help you." );
	@endcode

	@par		How about a select input?

	@code
		$form->select( 'age_group' )
			->description( 'Which group do you identify with most?' )
			->label( 'Age group' )
			->option( '0 to 10 year olds', '0_10' )
			->option( '11 to 37 year olds', '11-37' )
			->option( 'Other', 'other' )
			->value( 'other' );		// Default value is other.
	@endcode

	@par		Add a submit button

	@code
		$form->submit( 'login' )
			->value( 'Log me in' )
			->title( 'Will log you into the system, using only your username!' );
	@endcode

	@par		Handle the submit button

	@code
		// Is there anything in the _POST array?
		if ( $form->is_posting() )
		{
			// Ask the form to retrieve the form values.
			$form->post();
			if ( $form->input( 'login' )->pressed() )
				echo "The login button was pressed!";
		}
	@endcode

	@par		Add validation

	@code
		$form->text( 'username' )
			->required();
	@endcode

	And when the form is posted:

	@code
		if ( $form->validates() )
		{
			echo "Form validates!";
		}
		else
		{
			$errors = $form->get_validation_errors();
			foreach ( $errors as $error )
				echo $error->get_label();
		}
	@endcode


	Changelog
	---------

	- 20130524	Initial version

	@author		Edward Plainview <edward@plainview.se>
	@copyright	GPL v3
	@version	20130524
**/
class form
{
	use \plainview\html\element;
	use inputs\traits\container
	{
		// There is an input of type hidden, instead of the HTML element assigning itself the hidden attribute.
		inputs\traits\container::hidden insteadof \plainview\html\element;
	}

	/**
		@brief		Array of objects containing information about the available input types.
		@var		$input_types
		@since		20130524
	**/
	public $input_types = array();

	/**
		@brief		The _POST array with which to work.
		@see		post()
		@var		$post
		@since		20130524
	**/
	public $post = null;

	public $tag = 'form';

	public function __construct()
	{
		// Add the standard input types.
		$input_types = array(
			'button',
			'checkbox',
			'checkboxes',
			'datalist',
			'date',
			'datetime',
			'datetime_local',
			'email',
			'fieldset',
			'hidden',
			'markup',
			'meter',
			'month',
			'number',
			'password',
			'radio',
			'radios',
			'range',
			'search',
			'select',
			'submit',
			'tel',
			'time',
			'text',
			'textarea',
			'url',
			'week',
		);
		foreach( $input_types as $input_type )
		{
			$o = new \stdClass();
			$o->name = $input_type;
			$o->class = '\\plainview\\form2\\inputs\\' . $input_type;
			$this->register_input_type( $o );
		}
	}

	/**
		@brief		Provide subclasses a chance to translate strings.
		@param		string		$string		String to translate.
		@return		string		The translated, or unstranslated, string.
		@since		20130524
	**/
	public function _(  $string )
	{
		return $string;
	}

	/**
		@brief		Clear the stored POST array.
		@return		this		Object chaining.
		@since		20130524
	**/
	public function clear_post()
	{
		$this->post = null;
		return $this;
	}

	/**
		@brief		Set the encoding type of this form.
		@details	The default is 'application/x-www-form-urlencoded'.
		@param		string		$enctype		The encoding type to use.
		@return		this		Object chaining.
		@see		action()
		@see		method()
		@since		20130524
	**/
	public function enctype( $enctype )
	{
		$enctypes = array( 'application/x-www-form-urlencoded', 'multipart/form-data', 'text/plain' );
		if ( ! in_array( $enctype, $enctypes ) )
			$enctype = reset( $enctypes );
		return $this->set_attribute( 'enctype', $enctypes );
	}

	/**
		@brief		Filter a string.
		@details	Makes the string safe to be displayed / saved.

		Currently just runs htmlspecialchars on it.

		@param		string		$text		Text to filter.
		@return		string		The filtered text string.
		@since		20130524
		@see		unfilter_text()
	**/
	public static function filter_text( $text )
	{
		$text = htmlspecialchars( $text );
		return $text;
	}

	/**
		@brief		Return the form object.
		@details	Exists as an override to the container trait.
		@return		this		This form object.
		@since		20130524
	**/
	public function form()
	{
		return $this;
	}

	/**
		@brief		Return an input type.
		@param		string		$name		Name of the input type. hidden or textarea or whatever.
		@return		mixed		The input type object specified, or false if it isn't registered.
		@since		20130524
	**/
	public function get_input_type( $name )
	{
		if ( ! $this->is_input_type_registered( $name ) )
			return false;
		return $this->input_types[ $name ];
	}

	/**
		@brief		Return the POST value for an input name.
		@details	The $name variable should be the complete name used in the form, with [] prefixes and all.

		This method will then search the form's POST variable for the input value.

		Will return null if the value is not set.

		@param		string		$name		The name of the input to fetch.
		@see		post()
		@since		20130524
	**/
	public function get_post_value( $name )
	{
		// No prefix?
		if ( strpos( $name, '['  ) === false )
		{
			if ( ! isset( $this->post[ $name ] ) )
				return null;
			else
				return $this->post[ $name ];
		}
		else
		{
			// Prepare to split the name up into arrays.
			$name = preg_replace( '/\[/', '][', $name, 1 );
			$name = rtrim( $name, ']' );
			$names = explode( '][', $name );

			// Delve into the POST array.
			$post = $this->post;
			do
			{
				$name = array_shift( $names );
				if ( ! isset( $post[ $name ] ) )
					return null;
				$post = $post[ $name ];
			} while ( count( $names ) > 0 );
			return $post;
		}
	}

	/**
		@brief		Returns if this input type is registered.
		@param		string		$name		Name of input type.
		@return		bool		True if the input type is registered.
		@see		get_input_type()
		@see		register_input_type()
		@since		20130524
	**/
	public function is_input_type_registered( $name )
	{
		return isset( $this->input_types[ $name ] );
	}

	/**
		@brief		Is there data in the POST array?
		@param		array		$post		Optional POST array to check. If not specified will use _POST.
		@return		this		Object chaining.
		@see		action()
		@see		enctype()
		@since		20130524
	**/
	public function is_posting( array $post = null )
	{
		$post = ( $post === null ? $_POST : $post );
		return count( $post ) > 0;
	}

	/**
		@brief		Set the method of this form.
		@param		string		$method		Method to set: either post (default) or get.
		@return		this		Object chaining.
		@see		action()
		@see		enctype()
		@since		20130524
	**/
	public function method( $method )
	{
		$methods = array( 'post', 'get' );
		if ( ! in_array( $method, $methods ) )
			$method = reset( $methods );
		return $this->set_attribute( 'method', $method );
	}

	/**
		@brief		Set the novalidate attribute of the form.
		@param		bool		$novalidate			True to not validate the form.
		@return		this		Object chaining.
		@since		20130524
	**/
	public function novalidate( $novalidate = true )
	{
		return $this->set_boolean_attribute( $novalidate );
	}

	/**
		@brief		Give the form a POST array with which to work.
		@details	Either leave empty to automatically use the $_POST, or give the method an array.
		@param		array		$post		POST array with which to work.
		@return		this		This.
		@since		2013
	**/
	function post( array $post = null )
	{
		$this->post = ( $post === null ? $_POST : $post );
		$this->use_post_value();
		return $this;
	}

	/**
		@brief		Return whether the internal POST property is set.
		@return		bool		True if the property is set.
		@see		clear_post()
		@see		post()
		@since		20130524
	**/
	function post_is_set()
	{
		return $this->post !== null;
	}

	/**
		@brief		Register an input type.
		@details	The $o object must contain:

		- @b name The name of the input type: hidden, text, textarea, number, etc.
		- @b class The string identifier of the class, including namespace. See the constructor for examples.

		@param		object		$o		Input type object.
		@return		this		Object chaining.
		@see		is_input_type_registered()
		@since		20130524
	**/
	public function register_input_type( $o )
	{
		$this->input_types[ $o->name ] = $o;
		return $this;
	}

	/**
		@brief		Remove filtering from text.
		@param		string		$text		String to unfilter.
		@return		string		Unfiltered string.
		@see		filter_text()
		@since		20130524
	**/
	public static function unfilter_text( $text )
	{
		$text = htmlspecialchars_decode( $text );
		return $text;
	}
}

