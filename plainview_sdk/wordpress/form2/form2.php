<?php

namespace plainview\wordpress\form2;

require_once( 'inputs/class.primary_button.php' );
require_once( 'inputs/class.secondary_button.php' );

class form
	extends \plainview\form2\form
{
	public $base;

	public function __construct( $base )
	{
		parent::__construct();
		$this->base = $base;
		$this->set_attribute( 'action', '' );
		$this->set_attribute( 'method', 'post' );

		foreach( array(
			'primary_button',
			'secondary_button'
		) as $input )
		{
			$o = new \stdClass();
			$o->name = $input;
			$o->class = sprintf( '\\plainview\\wordpress\form2\\inputs\\%s', $input );
			$this->register_input_type( $o );
		}
	}

	/**
		@brief		Displays an array of inputs using Wordpress table formatting.
		@param		array		$o	Array of options.
		@since		20130416
	**/
	public function display_form_table( $o = array() )
	{
		$o = \plainview\base::merge_objects( array(
			'base' => $this->base,
			'header' => '',
			'header_level' => 'h3',
			'r' => '',					// Return value.
		), $o );

		$r = '';

		$o->inputs = $this->inputs;

		$this->display_form_table_inputs( $o );

		return $o->r;
	}

	public function display_form_table_inputs( $o )
	{
		if ( $o->header != '' )
			$o->r .= sprintf( '<%s class="title">%s</%s>%s',
				$o->header_level,
				$o->header,
				$o->header_level,
				"\n"
			);

		$o->table = $this->base->table()->set_attribute( 'class', 'form-table' );

		foreach( $o->inputs as $input )
		{
			// Input containers (fieldsets) must be recursed.
			$uses = class_uses( $input );
			if ( isset( $uses[ 'plainview\\form2\\inputs\\traits\\container' ] ) )
			{
				// Should the table be displayed?
				if ( count( $o->table->body->rows ) > 0 )
					$o->r .= $o->table;

				// Clone the options object to allow the input container to create its own table
				$o2 = clone $o;
				$o2->header = $input->label;
				$o2->inputs = $input->inputs;
				$o2->r = '';
				$o2->table = $this->base->table()->set_attribute( 'class', 'form-table' );
				$this->display_form_table_inputs( $o2 );

				$o->table = $this->base->table()->set_attribute( 'class', 'form-table' );
				$o->r .= $o2->r;
				continue;
			}

			// Hidden inputs cannot be displayed.
			if ( $input->get_attribute( 'hidden' ) )
			{
				$o->r .= $input->display_input();
				continue;
			}

			if ( is_a( $input, 'plainview\\form2\\inputs\\markup' ) )
			{
				$o->table->body()->row()->td()->set_attribute( 'colspan', 2 )->text( $input->display_input() );
				continue;
			}

			$description = $input->display_description( $input );
			if ( $description != '' )
				$description = sprintf( '<div class="input_description">%s</div>', $description );
			$row = $o->table->body()->row();

			if ( ! $input->validates() )
				$row->css_class( 'does_not_validate' );

			$row->th()->text( $input->display_label() )->row()
				->td()->textf( '<div class="input_itself">%s</div>%s',
					$input->display_input( $input ),
					$description
				);
		}
		if ( count( $o->table->body->rows ) > 0 )
			$o->r .= $o->table;
	}

	/**
		@brief		Ask base to translate this string. Is sprintf aware.
		@param		string		$string		The string to translate.
		@see		\\plainview\\form2\\form::_
		@return		string					The translated string.
	**/

	public function _( $string )
	{
		return call_user_func_array( array( $this->base, '_' ), func_get_args() );
	}

	public function start()
	{
		return $this->open_tag();
	}

	public function stop()
	{
		return $this->close_tag();
	}

}

