<?php

namespace plainview\sdk\drupal;

/**
	@brief			Drupal-specific db_aware_object.
	@details		Uses \plainview\sdk\db_aware_object.
	@version		20130430
**/
trait db_aware_object
{
	use \plainview\sdk\traits\db_aware_object;

	public function __db_delete()
	{
		$id_key = $this->id_key();
		$this->switch_to_db();
		db_delete( $this->db_table() )->condition( $id_key, $this->$id_key )->execute();
		$this->switch_from_db();
		return $this;
	}

	public function __db_update()
	{
		$o = clone $this;

		// Create a clone of this object so that the serializing doesn't disturb anything.
		$o = clone $this;
		self::serialize_keys( $o );

		$fields = $o->fields();
		$id_key = $this->id_key();

		$this->switch_to_db();
		if ( $this->$id_key === null )
		{
			$this->$id_key = db_insert( $this->db_table() )->fields( $fields )->execute();
		}
		else
			db_update( $this->db_table() )->fields( $fields )->condition( $id_key, $this->$id_key )->execute();
		$this->switch_from_db();

		return $this;
	}

	/**
		@brief		Switch back from the object's database if necessary.
	**/
	public function switch_from_db()
	{
		// There is no extra database specified, which means we never change db.
		if ( self::db() == '' )
			return;
		db_set_active( 'default' );
	}

	/**
		@brief		Switch to the object's database if necessary.
	**/
	public function switch_to_db()
	{
		// There is no extra database specified, which means we never change db.
		if ( self::db() == '' )
			return;
		db_set_active( self::db() );
	}
}

