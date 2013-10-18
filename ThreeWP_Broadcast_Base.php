<?php

namespace threewp_broadcast;

if ( ! class_exists( '\\plainview\\sdk\\wordpress\\base' ) )	require_once( __DIR__ . '/plainview_sdk/plainview/sdk/autoload.php' );

class ThreeWP_Broadcast_Base
	extends \plainview\sdk\wordpress\base
{
}