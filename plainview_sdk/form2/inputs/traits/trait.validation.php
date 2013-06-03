<?php

namespace plainview\form2\inputs\traits;

/**
	@brief		Validation handling.
	@author		Edward Plainview <edward@plainview.se>
	@copyright	GPL v3
	@version	20130524
**/
trait validation
{
	/**
		@brief		An array of validation errors this input has acquired during validation.
		@see		get_validation_errors()
		@see		validate()
		@var		$validation_methods
	**/
	public $validation_errors = array();

	/**
		@brief		An array of methods that will validate this input.
		@see		validate()
		@var		$validation_methods
	**/
	public $validation_methods = array();

	/**
		@brief		Append a validation error.
		@param		\\plainview\\form2\\validation\\error		$error		Error to append.
		@return		$this		Object chaining.
		@since		20130524
	**/
	public function add_validation_error( $error )
	{
		$this->validation_errors[] = $error;
		return $this;
	}

	/**
		@brief		Add a validation method.
		@details	Accepts either one parameter, $p1, for an internal method. $this->validate_required(), for example.
		If two parameters are used, then the first is the class and the second is the callable method in the class.

		For example:

		$input->add_validation_method( 'string_length' ) will call $input->validate_string_length( $input );
		$input->add_validation_method( $other_class, 'string_length' ) will call $other_class->validate_string_length( $input );

		@param		mixed		$p1				Either an internal input method, or the class which contains the callable.
		@param		string		$callable		The name of the callable method in the first parameter, $p1 (which is an object).
		@return		$this		Object chaining.
		@since		20130524
	**/
	public function add_validation_method( $p1, $callable = null )
	{
		if ( $callable !== null )
			$method = array( $p1, 'validate_' . $callable );
		else
			$method = array( $this, 'validate_' . $p1 );

		$this->validation_methods[] = $method;

		return $this;
	}

	/**
		@brief		Returns an array of validation errors.
		@return		array		An array of \\plainview\\form2\\validation\\error objects.
		@since		20130524
	**/
	public function get_validation_errors()
	{
		return $this->validation_errors;
	}

	/**
		@brief		Returns whether this input requires validation of any kind.
		@return		bool		True if the input requires any sort of validation.
		@since		20130524
	**/
	public function requires_validation()
	{
		return ( $this->is_required() || count( $this->validation_methods ) > 0 );
	}

	/**
		@brief		Creates and returns a brand new validation error.
		@details	Adds it to the list of validation errors and returns it.
		@return		\\plainview\\form2\\validation\\error		A new validation error.
		@since		20130524
	**/
	public function validation_error()
	{
		$error = new \plainview\form2\validation\error( $this->container );
		$this->add_validation_error( $error );
		return $error;
	}

	/**
		@brief		Validates the input.
		@details	Goes through all the validation methods and then returns this class.
		@return		this		Object chaining.
		@since		20130524
	**/
	public function validate()
	{
		$this->validation_value = $this->get_post_value();

		// The required attribute requires special treatment because the HTML element itself has a required() global attribute setter.
		if ( $this->is_required() )
			$this->add_validation_method( 'required' );

		foreach( $this->validation_methods as $method )
			call_user_func_array( $method, array( $this ) );

		return $this;
	}

	/**
		@brief		Validates the input according to the required attribute.
		@see		validate()
		@since		20130524
	**/
	public function validate_required()
	{
		$trimmed_value = trim( $this->validation_value );

		if ( strlen( $trimmed_value ) < 1 )
			$this->validation_error()->set_unfiltered_label_( 'Please fill in %s.', '<em>' . $this->label . '</em>' );
	}

	/**
		@brief		Does this input validate?
		@return		bool		True, if the input validates correctly.
		@since		20130524
	**/
	public function validates()
	{
		return count( $this->validation_errors ) < 1;
	}
}

