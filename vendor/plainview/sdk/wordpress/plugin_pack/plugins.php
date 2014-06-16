<?php

namespace plainview\sdk\wordpress\plugin_pack;

use \plainview\sdk\collections\collection;

/**
	@brief		A collection of plugin objects.
	@since		2014-05-07 22:54:56
**/
class plugins
	extends \plainview\sdk\collections\collection
{
	/**
		@brief		The plugin pack container class.
		@since		2014-05-08 00:12:04
	**/
	public $__plugin_pack;

	/**
		@brief		Construct.
		@since		2014-05-08 00:10:17
	**/
	public function __construct( $pp )
	{
		$this->__plugin_pack = $pp;
	}

	/**
		@brief		Return a collection of plugins with the specified ID numbers.
		@since		2014-05-08 16:32:16
	**/
	public function from_ids( $ids )
	{
		$r = new collection;

		foreach( $this->items as $plugin )
			foreach( $ids as $id )
				if ( $plugin->get_id() == $id )
					$r->append( $plugin );

		return $r;
	}

	/**
		@brief		Load the populated plugins.
		@since		2014-05-08 00:05:38
	**/
	public function load()
	{
		foreach( $this->items as $classname => $plugin )
		{
			if ( $plugin->is_loaded() )
				continue;
			try
			{
				$plugin->load();
			}
			catch( Exception $e )
			{
				// This class no long exists or could not be loaded. Delete it.
				$this->need_to_save();
			}
		}
	}

	/**
		@brief		Resave our data if necessary.
		@since		2014-05-09 10:36:08
	**/
	public function maybe_save()
	{
		if ( ! isset( $this->__need_to_save ) )
			return;
		ddd( $this->items );
		ddd( 'we need to resave!' );
		return;
		$this->save();
	}

	/**
		@brief		Set the flag stating that we need to resave our activated plugins at the first best chance.
		@since		2014-05-09 10:35:00
	**/
	public function need_to_save( $need_to_save = true )
	{
		if ( $need_to_save )
			$this->__need_to_save = true;
		else
			unset( $this->__need_to_save );
		return $this;
	}

	/**
		@brief		Add one or more plugins to our list of plugins.
		@since		2014-05-08 00:04:13
	**/
	public function populate( $classnames )
	{
		if ( ! is_array( $classnames ) )
			$classnames = [ $classnames ];

		foreach( $classnames as $classname )
		{
			if ( $this->has( $classname ) )
				continue;
			$plugin = new plugin( $this );
			$plugin->set_classname( $classname );
			$this->set( $classname, $plugin );
		}
	}

	/**
		@brief		Return the plugin pack class that created us.
		@since		2014-05-08 16:20:04
	**/
	public function pp()
	{
		return $this->__plugin_pack;
	}

	/**
		@brief		Asks the plugin pack to save our data.
		@since		2014-05-09 10:36:33
	**/
	public function save()
	{
		$classnames = [];
		foreach( $this as $plugin )
			$classnames[] = $plugin->get_classname();
		$this->pp()->set_enabled_plugins( $classnames );
	}
}