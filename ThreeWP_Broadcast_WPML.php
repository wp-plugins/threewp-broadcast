<?php
/*
Author:			edward_plainview
Author Email:	edward@plainview.se
Author URI:		http://www.plainview.se
Description:	Obsolete: Add simple WPML support to ThreeWP Broadcast.
Plugin Name:	ThreeWP Broadcast WPML support
Plugin URI:		http://plainview.se/wordpress/threewp-broadcast/
Version:		2.21
*/

namespace threewp_broadcast;

// Don't cause a big fuss if the base does not exist.
if ( ! class_exists( '\\threewp_broadcast\\ThreeWP_Broadcast_Base' ) )
	return;

/**
	@brief		Adds WPML support to ThreeWP Broadcast.
	@details

	@par		Changelog

	- 20140129	Plugin replaced by Premium WPML plugin.
	- 20140111	Check that the content actually is translatable.
	- 20130812	Intial version v1.21.
**/
class ThreeWP_Broadcast_WPML
	extends \threewp_broadcast\ThreeWP_Broadcast_Base
{
	public $plugin_version = 2.20;

	protected $sdk_version_required = 20130505;		// add_action / add_filter

	public function _construct()
	{
		$this->add_action( 'threewp_broadcast_prepare_meta_box' );
		if( $this->is_wpml() )
		{
			$this->add_action( 'threewp_broadcast_broadcasting_before_restore_current_blog' );
			$this->add_action( 'threewp_broadcast_broadcasting_started' );
		}
	}

	// --------------------------------------------------------------------------------------------
	// ----------------------------------------- Callbacks
	// --------------------------------------------------------------------------------------------

	/**
		@brief		Add information to the broadcast box about the status of Broadcast WPML.
		@since		20130717
	**/
	public function threewp_broadcast_prepare_meta_box( $action )
	{
		$action->meta_box_data->html->put( 'broadcast_wpml', sprintf( '<div class="broadcast_wpml">%s</div><!-- broadcast_wpml -->',
			$this->generate_meta_box_info()
		) );
	}

	/**
		@brief		Handle translation of this post.
		@details

		Handles:
		- Marking the post as a language
		- Marking the post as a translation of a trid (language)

		@param		Broadcast_Data		The BCD object.
		@since		20130717
	**/
	public function threewp_broadcast_broadcasting_before_restore_current_blog( $action )
	{
		$bcd = $action->broadcasting_data;

		// Conv
		$current_child_blog_id = $bcd->current_child_blog_id;

		// Some convenience variables.
		$new_post = (object)$bcd->new_post;
		$id = $new_post->ID;
		$type = 'post_' . $new_post->post_type;

		// Does this language already exist? Do nothing.
		$trid = wpml_get_content_trid( $type, $id );
		if ( $trid > 0 )
			return;

		// Do any of the current translations have a functional trid?
		$trid = false;
		// Loop through each child on this blog and query it for a language / trid.
		foreach( $bcd->wpml->broadcast_data as $lang => $broadcast_data )
		{
			$child = $broadcast_data->get_linked_child_on_this_blog();
			if ( ! $child )
				continue;
			$trid = wpml_get_content_trid( $type, $child );
			if ( $trid > 0 )
				break;
		}

		// If no trid was found then fine.
		wpml_add_translatable_content( $type, $id, $bcd->wpml->language, $trid );
		// We have a trid now. Could be useful for later.
		$bcd->wpml->trid->$current_child_blog_id = wpml_get_content_trid( $type, $id );

		icl_cache_clear();
	}

	/**
		@brief		Save info about the broadcast.
		@param		Broadcast_Data		The BCD object.
		@since		20130717
	**/
	public function threewp_broadcast_broadcasting_started( $action )
	{
		$bcd = $action->broadcasting_data;

		// Conv
		$parent_blog_id = $bcd->parent_blog_id;

		$bcd->wpml = new \stdClass;

		// Retrieve the broadcast instance
		$broadcast = \threewp_broadcast\ThreeWP_Broadcast::instance();

		// Collect info about the translations, in order to link this language with the other languages on the child posts.
		$id = $bcd->post->ID;
		$type = 'post_' . $bcd->post->post_type;
		$bcd->wpml->translations = wpml_get_content_translations( $type, $id );

		// Is this content translateable?
		if ( ! is_array( $bcd->wpml->translations ) )
		{
			// No, then we do nothing.
			unset( $bcd->wpml );
			return;
		}

		// Calculate the language of this post.
		foreach( $bcd->wpml->translations as $lang => $post_id )
			if( $post_id == $id )
			{
				$bcd->wpml->language = $lang;
				break;
			}

		$bcd->wpml->trid = new \stdClass;
		$bcd->wpml->trid->$parent_blog_id = wpml_get_content_trid( $type, $id );
		$bcd->wpml->broadcast_data = new \stdClass;
		foreach( $bcd->wpml->translations as $lang => $element_id )
			$bcd->wpml->broadcast_data->$lang = $broadcast->get_post_broadcast_data( $parent_blog_id, $element_id );
	}


	// --------------------------------------------------------------------------------------------
	// ----------------------------------------- Misc functions
	// --------------------------------------------------------------------------------------------

	/**
		@brief		Output some data about WPML support.
		@return		string		HTML string containing Broadcast WPML info.
		@since		20130717
	**/
	public function generate_meta_box_info( $o = [] )
	{
		$this->load_language();

		if ( ! $this->is_wpml() )
			return $this->_( 'WPML was not detected.' );

		$r = [];

		$r []= $this->_( 'WPML v%s detected. Language: %s',
			self::open_close_tag( ICL_SITEPRESS_VERSION, 'em' ),
			self::open_close_tag( wpml_get_current_language(), 'em' )
		);

		return \plainview\sdk\base::implode_html( $r, '<div>', '</div>' );
	}

	/**
		@brief		Check for the existence of WPML.
		@return		bool		True if WPML is alive and kicking. Else false.
		@since		20130717
	**/
	public function is_wpml()
	{
		return defined( 'ICL_SITEPRESS_VERSION' );
	}

}

// Tell good ol' WPML to load API support.
if ( ! defined( 'WPML_LOAD_API_SUPPORT' ) )
	define( 'WPML_LOAD_API_SUPPORT', true );

$threewp_broadcast_wpml = new ThreeWP_Broadcast_WPML();
