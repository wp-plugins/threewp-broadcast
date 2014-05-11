<?php

namespace plainview\sdk\wordpress\traits;

/**
	@brief			Wordpress-specific db_aware_object.
	@details		Uses \plainview\sdk\db_aware_object.
	@version		20130430
**/
trait db_aware_object
{
	use \plainview\sdk\traits\db_aware_object;

	public function __db_delete()
	{
		$id_key = self::id_key();
		$sql = sprintf( "DELETE FROM `%s` WHERE `%s` = '%s'", self::db_table(), $id_key, $this->$id_key );
		global $wpdb;
		$wpdb->query( $sql );
		return $this;
	}

	public function __db_update( $fields = null )
	{
		$id_key = self::id_key();

		if ( $fields === null )
			$fields = $this->get_field_data();

		if ( $this->$id_key === null )
			$this->db_insert( $fields );
		else
		{
			global $wpdb;
			$wpdb->update( self::db_table(), $fields, array( $id_key => $this->$id_key ) );
		}

		return $this;
	}

	public function __db_insert( $fields = null )
	{
		$id_key = self::id_key();

		if ( $fields === null )
			$fields = $this->get_field_data();

		// For some reason a clone of wpdb is necessary. Else get_post sometimes doesn't work after x amount of calls. Weird.
		global $wpdb;
		$db = clone( $wpdb );
		$db->insert( self::db_table(), $fields );
		$this->$id_key = $db->insert_id;
	}

	public static function __db_load( $id )
	{
		global $wpdb;
		$sql = sprintf( "SELECT * FROM `%s` WHERE `%s` = '%s'", self::db_table(), self::id_key(), $id );
		$result = $wpdb->get_results( $sql );
		if ( count( $result ) != 1 )
			return false;
		$result = reset( $result );
		return self::sql( $result );
	}
}
