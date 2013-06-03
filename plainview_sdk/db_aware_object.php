<?php
/**
	@brief		Trait for objects that can update / delete themselves from the database.
	
	@details	Used as a convenience to make normal objects easier to interface into the database.
	
	A subtrait (Wordpress / Drupal) should be used, instead of this trait, because of the need to overload __db_delete and __db_update.
	
	@par		Usage
	
	- require_once() this file.
	- use \plainview\wordpress\db_aware_object or \plainview\drupal\db_aware_object.
	- Override the db_table() function.
	- Override the keys() function.
	
	In this example, assume that the email_user class uses this trait.
	
	After having loaded a row (in the form of an array) from the database:
	
	$email_user = email_user:sql( $row );
	
	If you have several rows from the db:
	
	$email_users = email_user:sqls( $rows );
	
	@author			Edward Plainview		edward@plainview.se
	@copyright		GPL v3
	@version		20130430
	
	@par		Changelog
	
	- @b 2013-04-22		First release.
**/

namespace plainview;

trait db_aware_object
{	
	/**
		@brief		Which database should the object use?
		@return		string		If the object is stored in another database, return the name here. Else return nothing.
		@since		20130430
	**/
	public static function db()
	{
		return '';
	}
	
	/**
		@brief		Returns in which database table is this object type stored?
		@return		string		The name of the table in which the object is to be stored.
		@since		20130430
	**/
	public static function db_table()
	{
		die( sprintf( 'Override the db_table() function in %s from the db_aware_object trait.', get_class() ) );
	}
	
	/**
		@brief		Deletes this object from the database.
		@details	Overload this function if the object that uses this trait needs to hook into db_delete.
		@since		20130430
	**/
	public function db_delete()
	{
		return $this->__db_delete();
	}

	/**
		@brief		Actual delete function.
		@details	Delete the object from the databse.
		@since		20130430
	**/
	public function __db_delete()
	{
	}
	
	/**
		@brief		Updates the database.
		@details	Overload this function if the object that uses this trait needs to hook into db_update.
		@since		20130430
	**/
	public function db_update()
	{
		return $this->__db_update();
	}
	
	/**
		@brief		Actual update function.
		@details	Update the object stored in the DB.
		@since		20130430
	**/
	public function __db_update()
	{
	}
	
	/**
		@brief		Return an array containing database data.		
		@return		array		An array containing only database fields. 
		@since		20130430
	**/
	public function fields()
	{
		$rv = array();
		foreach( $this->keys() as $key )
		{
			if ( $this->$key === '' )
				$this->$key = null;
			$rv[ $key ] = $this->$key;
		}
		unset( $rv[ $this->id_key() ] );
		return $rv;
	}
	
	/**
		@brief		Returns the name of the column in which the primary key is stored. Usually 'id'.
		@return		string		Name of primary DB column.
		@since		20130430
	**/
	public static function id_key()
	{
		return 'id';
	}
	
	/**
		@brief		Returns a an array of column name strings (from the database).
		@return		array		An array of strings (keys).
		@since		20130430
	**/
	public static function keys()
	{
		die( sprintf( 'Override the keys() function in %s from the db_aware_object trait.', get_class() ) );
	}
	
	/**
		@brief		Which of the keys in the DB must be serialized / unserialized (after base64 coding)?
		@details	Used for those instances where just storing the data corrupts the db_update function.
		@return		array		An array of field names to un/serialize.
		@since		20130430
	**/
	public static function keys_to_serialize()
	{
		return array();
	}

	/**
		@brief		Serializes the keys this class requires unserializing.
		@param		db_aware_object		$object		Object to help unserialize.
		@since		20130430
	**/
	public static function serialize_keys( $object )
	{
		foreach( $object->keys_to_serialize() as $key )
			$object->$key = base64_encode( serialize( $object->$key ) );
	}
	
	/**
		@brief		Creates a class object from the database row.
		@param		array		$row		Database row as an object / array.
		@return		db_aware_object			A class of the subclass' type.
		@since		20130430					
	**/
	public static function sql( $row )
	{
		if ( $row === false )
			return false;
		$row = (object)$row;
		$class_name = get_called_class();
		$o = new $class_name();
		foreach( $o->keys() as $key )
			$o->$key = $row->$key;
		
		self::unserialize_keys( $o );
		
		return $o;
	}	

	/**
		@brief		Creates class objects from an array of database rows.
		@param		array		$array		Array of database rows.
		@return		array					An array of db_aware_object classes.
		@since		20130430					
	**/
	public static function sqls( $array )
	{
		if ( $array === false )
			return false;
		$rv = array();
		$id_key = self::id_key();
		foreach( $array as $row )
		{
			$row = (object)$row;
			$rv[ $row->$id_key ] = self::sql( $row );
		}
		return $rv;
	}
	
	/**
		@brief		Unserializes the keys this class requires unserializing.
		@param		db_aware_object		$object		Object to help unserialize.
		@since		20130430
	**/
	public static function unserialize_keys( $object )
	{
		foreach( $object->keys_to_serialize() as $key )
			$object->$key = unserialize( base64_decode( $object->$key ) );
	}
}

