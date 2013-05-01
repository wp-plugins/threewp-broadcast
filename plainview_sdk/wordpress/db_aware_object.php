<?php

namespace plainview\wordpress;

/**
	@brief			Wordpress-specific db_aware_object.
	@details		Uses \plainview\db_aware_object.
	@version		20130430
**/
trait db_aware_object
{
	use \plainview\db_aware_object;
	
	public function __db_delete()
	{	
		$id_key = self::id_key();
		$sql = sprintf( "DELETE FROM `%s` WHERE `%s` = '%s'", self::db_table(), $id_key, $this->$id_key );
		global $wpdb;
		$wpdb->query( $sql );
		return $this;
	}
	
	public function __db_update()
	{
		global $wpdb;
		
		// Create a clone of this object so that the serializing doesn't disturb anything.
		$o = clone $this;
		self::serialize_keys( $o );
		
		$fields = $o->fields();
		$id_key = self::id_key();
		unset( $fields[ $id_key ] );

		if ( $this->$id_key === null )
		{
			$wpdb->insert( self::db_table(), $fields );
			$this->$id_key = $wpdb->insert_id;
		}
		else
		{
			$wpdb->update( self::db_table(), $fields, array( $id_key => $this->$id_key ) );
		}
		
		return $this;
	}
}

