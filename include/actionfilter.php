<?php

namespace threewp_broadcast;

abstract class actionfilter
{
	use \plainview\sdk\traits\method_chaining;

	/**
		@brief		Has the actionfilter been applied enough to be considered done?
		@see		is_applied()
		@var		$applied
	**/
	public $applied = false;

	public function __construct()
	{
		call_user_func_array( [ $this, '_construct' ], func_get_args() );
	}

	public function _construct()
	{
	}

	/**
		@brief		Applies the actionfilter.
		@details	The method prepends the text 'threewp_broadcast_' in front of the actionfilter class name.
		@return		this		Method chaining.
		@see		apply_method()
		@since		20131003
	**/
	public function apply()
	{
		global $threewp_broadcast;
		$name = get_class( $this );
		$name = preg_replace( '/.*\\\\/', '', $name );
		$actionfilter_name = 'threewp_broadcast_' . $name;
		return $this->apply_method( $actionfilter_name );
	}

	public abstract function apply_method( $actionfilter_name );

	/**
		@brief		Mark this actionfilter as applied.
		@return		this		Method chaining.
		@since		20131003
	**/
	public function applied( $applied = true )
	{
		return $this->set_boolean( 'applied', $applied );
	}

	/**
		@brief		Has the filter been applied / handled by a method somewhere?
		@details	This method means nothing to broadcast, only to the methods that handle this filter.
		@return		bool		True if the filter has been handled / applied.
		@see		$applied
		@since		20131003
	**/
	public function is_applied()
	{
		return $this->applied;
	}
}
