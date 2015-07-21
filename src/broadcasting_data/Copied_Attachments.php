<?php

namespace threewp_broadcast\broadcasting_data;

/**
	@brief		Convenience methods for handling copied attachments.
	@since		2015-07-01 21:22:34
**/
class Copied_Attachments
{
	/**
		@brief		The broadcasting data object.
		@since		2015-06-06 09:02:08
	**/
	public $broadcasting_data;

	/**
		@brief		Constructor.
		@since		2015-07-01 21:23:44
	**/
	public function __construct( $broadcasting_data )
	{
		$this->broadcasting_data = $broadcasting_data;
	}

	/**
		@brief		Return the equivalent new attachment ID of this old attachment ID.
		@since		2015-07-01 21:39:43
	**/
	public function get( $attachment_id )
	{
		foreach( $this->broadcasting_data->copied_attachments as $attachment )
			if ( $attachment->old->id == $attachment_id )
				return $attachment->new->id;
		return false;
	}

	/**
		@brief		Does the copied attachments array contain this old attachment ID?
		@since		2015-07-01 21:24:15
	**/
	public function has( $attachment_id )
	{
		foreach( $this->broadcasting_data->copied_attachments as $attachment )
			if ( $attachment->old->id == $attachment_id )
				return true;
		return false;
	}
}
