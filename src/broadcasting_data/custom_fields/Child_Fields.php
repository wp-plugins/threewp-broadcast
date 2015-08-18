<?php

namespace threewp_broadcast\broadcasting_data\custom_fields;

/**
	@brief		A helper for the child's custom fields.
	@details	Created mainly as a convenience class for get and has lookups.

	The child_fields property of bcd->cf is reset after each blog switch.

	@since		2015-08-02 14:57:24
**/
class Child_Fields
	extends \threewp_broadcast\collection
{
	/**
		@brief		The broadcasting data object.
		@since		2015-08-02 14:57:43
	**/
	public $broadcasting_data;

	/**
		@brief		Constructor.
		@since		2015-08-02 14:57:43
	**/
	public function __construct( $broadcasting_data )
	{
		$this->broadcasting_data = $broadcasting_data;

		if ( ! isset( $this->broadcasting_data->custom_fields->child_fields ) )
			$this->broadcasting_data->custom_fields->child_fields = [];

		$this->items = &$this->broadcasting_data->custom_fields->child_fields;
	}

	/**
		@brief		Add this meta key and value pair to the current child.
		@details	Note that is a convenience method that does not keep the collection in sync with the database.
		@since		2015-08-02 15:18:56
	**/
	public function add_meta( $key, $value )
	{
		add_post_meta( $this->broadcasting_data->new_post( 'ID' ), $key, $value );
	}

	/**
		@brief		Delete this custom field from the current child.
		@details	Note that is a convenience method that does not keep the collection in sync with the database.
		@since		2015-08-02 15:18:56
	**/
	public function delete_meta( $key )
	{
		delete_post_meta( $this->broadcasting_data->new_post( 'ID' ), $key );
	}

	/**
		@brief		Fill the array with the fields of the current child.
		@since		2015-08-02 15:00:45
	**/
	public function load()
	{
		$this->items = get_post_meta( $this->broadcasting_data->new_post( 'ID' ) );
		return $this;
	}

	/**
		@brief		Update the meta key and value pair for this current child.
		@details	Note that is a convenience method that does not keep the collection in sync with the database.
		@since		2015-08-05 14:42:20
	**/
	public function update_meta( $key, $value )
	{
		update_post_meta( $this->broadcasting_data->new_post( 'ID' ), $key, $value );
	}
}
