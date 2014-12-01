<?php

namespace threewp_broadcast\actions;

class action
	extends \plainview\sdk_broadcast\wordpress\actions\action
{
	// TODO: Remove this @ v11
	public function execute()
	{
		$action_name = $this->get_name();
		do_action( $action_name, $this );
		return $this;		// Because of this.
	}

	public function get_prefix()
	{
		return 'threewp_broadcast_';
	}

	// TODO: Remove this @ v11
	public function finish( $finished = true )
	{
		// Because of set_boolean instead of set_bool as in the SDK.
		return $this->set_boolean( 'finished', $finished );
	}
}
