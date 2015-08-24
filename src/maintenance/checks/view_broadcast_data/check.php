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
		$o->r = ThreeWP_Broadcast()->p( 'Use the form below to look up the broadcast data (linking) either by specifying the ID of the row in the database or the combination of blog ID and post ID. Leave the row input empty to look up using blog and post IDs.' );

		$fs = $o->form->fieldset( 'fs_by_id' );
		$fs->legend->label( 'Find broadcast data by ID' );

		$o->inputs->row_id = $fs->number( 'id' )
			->description( 'The ID of the row in the database table.' )
			->label( 'ID' );

		$fs = $o->form->fieldset( 'fs_by_blog_and_post' );
		$fs->legend->label( 'Find broadcast data by blog and post ID' );

		$o->inputs->blog_id = $fs->number( 'blog_id' )
			->description( 'The ID of the blog. The current blog is the default.' )
			->label( 'Blog ID' )
			->value( get_current_blog_id() );

		$o->inputs->post_id = $fs->number( 'post_id' )
			->description( 'The ID of the post.' )
			->label( 'Post ID' )
			->value( '' );

		$button = $o->form->primary_button( 'dump' )
			->value( 'Find and display the broadcast data' );

		if ( $o->form->is_posting() )
		{
			$o->form->post()->use_post_value();

			if ( $o->inputs->row_id->get_post_value() != '' )
				$this->handle_row_id( $o );
			else
				$this->handle_blog_and_post_id( $o );
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

	public function handle_blog_and_post_id( $o )
	{
		$blog_id = intval( $o->inputs->blog_id->get_value() );
		$post_id = intval( $o->inputs->post_id->get_value() );

		if ( $blog_id < 1 )
			$blog_id = get_current_blog_id();

		$bcd = ThreeWP_Broadcast()->get_post_broadcast_data( $blog_id, $post_id );
		$text = sprintf( '<pre>%s</pre>', var_export( $bcd, true ) );
		$o->r .= $this->broadcast()->message( $text );
	}
}
