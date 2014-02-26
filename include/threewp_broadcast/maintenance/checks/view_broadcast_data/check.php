<?php

namespace threewp_broadcast\maintenance\checks\view_broadcast_data;

use \threewp_broadcast\BroadcastData;

/**
	@brief		View individual broadcast data objects
	@since		20131107
**/
class check
extends \threewp_broadcast\maintenance\checks\check
{
	public function get_description()
	{
		return 'View individual broadcast data objects. This tool does not modify the database.';
	}

	public function get_name()
	{
		return 'View broadcast data';
	}

	public function step_start()
	{
		$o = new \stdClass;
		$o->inputs = new \stdClass;
		$o->form = $this->broadcast()->form2();
		$o->r = '';

		$fs = $o->form->fieldset( 'fs_by_id' )
			->label( 'Find broadcast data by ID' );

		$o->inputs->row_id = $fs->number( 'id' )
			->description( 'The ID of the row in the database table.' )
			->label( 'ID' );

		$button = $o->form->primary_button( 'dump' )
			->value( 'Find and display the broadcast data' );

		if ( $o->form->is_posting() )
		{
			$o->form->post()->use_post_value();

			$this->handle_row_id( $o );

		}

		$o->r .= $o->form->open_tag();
		$o->r .= $o->form->display_form_table();
		$o->r .= $o->form->close_tag();
		return $o->r;
	}

	public function handle_row_id( $o )
	{
		$row_id = $o->inputs->row_id->get_value();
		if ( $row_id < 1 )
			return;

		$table = $this->broadcast()->broadcast_data_table();
		$query = sprintf( "SELECT * FROM `%s` WHERE `id` = '%s'", $table, $row_id );
		$o->results = $this->broadcast()->query( $query );
		if ( count( $o->results ) !== 1 )
		{
			$o->r .= $this->broadcast()->error_( 'Row %s in the broadcast data table was not found!', $row_id );
			return;
		}

		// Try to unserialize the object.
		$result = reset( $o->results );
		$bcd = BroadcastData::sql( $result );

		$text = sprintf( '<pre>%s</pre>', var_export( $bcd, true ) );
		$o->r .= $this->broadcast()->message( $text );
	}
}
