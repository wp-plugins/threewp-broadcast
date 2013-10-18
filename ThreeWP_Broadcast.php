<?php
/*
Author:			edward_plainview
Author Email:	edward@plainview.se
Author URI:		http://www.plainview.se
Description:	Broadcast / multipost a post, with attachments, custom fields, tags and other taxonomies to other blogs in the network.
Plugin Name:	ThreeWP Broadcast
Plugin URI:		http://plainview.se/wordpress/threewp-broadcast/
Version:		2.4
*/

namespace threewp_broadcast;

if ( ! class_exists( '\\threewp_broadcast\\base' ) )	require_once( __DIR__ . '/ThreeWP_Broadcast_Base.php' );

require_once( 'include/vendor/autoload.php' );

use \plainview\sdk\collections\collection;
use \threewp_broadcast\broadcast_data\blog;

class ThreeWP_Broadcast
	extends \threewp_broadcast\ThreeWP_Broadcast_Base
{
	private $broadcasting = false;

	/**
		@brief	Public property used during the broadcast process.
		@see	include/Broadcasting_Data.php
		@since	20130530
		@var	$broadcasting_data
	**/
	public $broadcasting_data = null;

	/**
		@brief		Display Broadcast completely, including menus and post overview columns.
		@since		20131015
		@var		$display_broadcast
	**/
	public $display_broadcast = true;

	/**
		@brief		Display the Broadcast columns in the post overview.
		@details	Disabling this will prevent the user from unlinking posts.
		@since		20131015
		@var		$display_broadcast_columns
	**/
	public $display_broadcast_columns = true;

	/**
		@brief		Display the Broadcast menu
		@since		20131015
		@var		$display_broadcast_menu
	**/
	public $display_broadcast_menu = true;

	/**
		@brief		Add the meta box in the post editor?
		@details	Standard is null, which means the plugin(s) should work it out first.
		@since		20131015
		@var		$display_broadcast_meta_box
	**/
	public	$display_broadcast_meta_box = true;

	/**
		@brief	Display information in the menu about the premium pack?
		@see	threewp_broadcast_premium_pack_info()
		@since	20131004
		@var	$display_premium_pack_info
	**/
	public $display_premium_pack_info = true;

	/**
		@brief		Caches permalinks looked up during this page view.
		@see		post_link()
		@since		20130923
	**/
	public $permalink_cache;

	public $plugin_version = 2.4;

	protected $sdk_version_required = 20130505;		// add_action / add_filter

	protected $site_options = array(
		'broadcast_internal_custom_fields' => false,		// Broadcast internal custom fields?
		'canonical_url' => true,							// Override the canonical URLs with the parent post's.
		'custom_field_whitelist' => '_wp_page_template _wplp_ _aioseop_',				// Internal custom fields that should be broadcasted.
		'custom_field_blacklist' => '',						// Internal custom fields that should not be broadcasted.
		'database_version' => 0,							// Version of database and settings
		'save_post_priority' => 640,						// Priority of save_post action. Higher = lets other plugins do their stuff first
		'override_child_permalinks' => false,				// Make the child's permalinks link back to the parent item?
		'post_types' => 'post page',						// Custom post types which use broadcasting
		'role_broadcast' => 'super_admin',					// Role required to use broadcast function
		'role_link' => 'super_admin',						// Role required to use the link function
		'role_broadcast_as_draft' => 'super_admin',			// Role required to broadcast posts as templates
		'role_broadcast_scheduled_posts' => 'super_admin',	// Role required to broadcast scheduled, future posts
		'role_taxonomies' => 'super_admin',					// Role required to broadcast the taxonomies
		'role_custom_fields' => 'super_admin',				// Role required to broadcast the custom fields
	);

	public function _construct()
	{
		if ( ! $this->is_network )
			wp_die( $this->_( 'Broadcast requires a Wordpress network to function.' ) );

		$this->add_action( 'add_meta_boxes' );
		$this->add_action( 'admin_menu' );
		$this->add_action( 'admin_print_styles' );

		$this->add_filter( 'threewp_activity_monitor_list_activities' );

		if ( $this->get_site_option( 'override_child_permalinks' ) )
		{
			$this->add_filter( 'post_link', 10, 3 );
			$this->add_filter( 'post_type_link', 'post_link', 10, 3 );
		}

		$this->add_filter( 'threewp_broadcast_add_meta_box' );
		$this->add_filter( 'threewp_broadcast_admin_menu', 100 );
		$this->add_filter( 'threewp_broadcast_prepare_meta_box', 9 );
		$this->add_filter( 'threewp_broadcast_prepare_meta_box', 'threewp_broadcast_prepared_meta_box', 100 );
		$this->add_filter( 'threewp_broadcast_broadcast_post' );
		$this->add_filter( 'threewp_broadcast_get_user_writable_blogs' );
		$this->add_action( 'threewp_broadcast_manage_posts_custom_column', 9 );		// Just before the standard 10.
		$this->add_action( 'threewp_broadcast_menu', 9 );
		$this->add_action( 'threewp_broadcast_menu', 'threewp_broadcast_menu_final', 100 );
		$this->add_filter( 'threewp_broadcast_prepare_broadcasting_data' );

		if ( $this->get_site_option( 'canonical_url' ) )
			$this->add_action( 'wp_head', 1 );

		$this->permalink_cache = new \stdClass;
	}

	public function admin_menu()
	{
		$this->load_language();

		$action = new actions\admin_menu;
		$action->apply();

		$action = new actions\menu;
		$action->broadcast = $this;
		$action->apply();

		// Hook into save_post, no matter is the meta box is displayed or not.
		$this->add_action( 'save_post', intval( $this->get_site_option( 'save_post_priority' ) ) );
	}

	public function admin_print_styles()
	{
		$load = false;

		$pages = array(get_class(), 'ThreeWP_Activity_Monitor' );

		if ( isset( $_GET[ 'page' ] ) )
			$load |= in_array( $_GET[ 'page' ], $pages);

		foreach(array( 'post-new.php', 'post.php' ) as $string)
			$load |= strpos( $_SERVER[ 'SCRIPT_FILENAME' ], $string) !== false;

		if ( !$load )
			return;

		$this->enqueue_js();
		wp_enqueue_style( 'threewp_broadcast', '/' . $this->paths[ 'path_from_base_directory' ] . '/css/css.scss.min.css'  );
	}

	// --------------------------------------------------------------------------------------------
	// ----------------------------------------- Activate / Deactivate
	// --------------------------------------------------------------------------------------------

	public function activate()
	{
		if ( !$this->is_network )
			wp_die("This plugin requires a Wordpress Network installation.");

		$db_ver = $this->get_site_option( 'database_version', 0 );

		if ( $db_ver < 1 )
		{
			// Remove old options
			$this->delete_site_option( 'requirewhenbroadcasting' );

			// Removed 1.5
			$this->delete_site_option( 'activity_monitor_broadcasts' );
			$this->delete_site_option( 'activity_monitor_group_changes' );
			$this->delete_site_option( 'activity_monitor_unlinks' );

			$this->query("CREATE TABLE IF NOT EXISTS `".$this->wpdb->base_prefix."_3wp_broadcast` (
			  `user_id` int(11) NOT NULL COMMENT 'User ID',
			  `data` text NOT NULL COMMENT 'User''s data',
			  PRIMARY KEY (`user_id`)
			) ENGINE=MyISAM DEFAULT CHARSET=latin1 COMMENT='Contains the group settings for all the users';
			");

			$this->query("CREATE TABLE IF NOT EXISTS `".$this->wpdb->base_prefix."_3wp_broadcast_broadcastdata` (
			  `blog_id` int(11) NOT NULL COMMENT 'Blog ID',
			  `post_id` int(11) NOT NULL COMMENT 'Post ID',
			  `data` text NOT NULL COMMENT 'Serialized BroadcastData',
			  KEY `blog_id` (`blog_id`,`post_id`)
			) ENGINE=MyISAM DEFAULT CHARSET=latin1;
			");

			// Cats and tags replaced by taxonomy support. Version 1.5
			$this->delete_site_option( 'role_categories' );
			$this->delete_site_option( 'role_categories_create' );
			$this->delete_site_option( 'role_tags' );
			$this->delete_site_option( 'role_tags_create' );
			$db_ver = 1;
		}

		if ( $db_ver < 2 )
		{
			// Convert the array site options to strings.
			foreach( [ 'custom_field_exceptions', 'post_types' ] as $key )
			{
				$value = $this->get_site_option( $key, '' );
				if ( is_array( $value ) )
				{
					$value = array_filter( $value );
					$value = implode( ' ', $value );
				}
				$this->update_site_option( $key, $value );
			}
			$db_ver = 2;
		}

		if ( $db_ver < 3 )
		{
			$this->delete_site_option( 'always_use_required_list' );
			$this->delete_site_option( 'blacklist' );
			$this->delete_site_option( 'requiredlist' );
			$this->delete_site_option( 'role_taxonomies_create' );
			$this->delete_site_option( 'role_groups' );
			$db_ver = 3;
		}

		if ( $db_ver < 4 )
		{
			$exceptions = $this->get_site_option( 'custom_field_exceptions', '' );
			$this->delete_site_option( 'custom_field_exceptions' );
			$whitelist = $this->get_site_option( 'custom_field_whitelist', $exceptions );
			$db_ver = 4;
		}

		$this->update_site_option( 'database_version', $db_ver );
	}

	public function uninstall()
	{
		$this->query("DROP TABLE `".$this->wpdb->base_prefix."_3wp_broadcast`");
		$this->query("DROP TABLE `".$this->wpdb->base_prefix."_3wp_broadcast_broadcastdata`");
	}

	// --------------------------------------------------------------------------------------------
	// ----------------------------------------- Admin
	// --------------------------------------------------------------------------------------------

	public function admin_menu_post_types()
	{
		$form = $this->form2();

		$post_types = $this->get_site_option( 'post_types' );
		$post_types = str_replace( ' ', "\n", $post_types );

		$post_types_input = $form->textarea( 'post_types' )
			->cols( 20, 10 )
			->label( 'Custom post types to broadcast' )
			->value( $post_types );
		$label = $this->_( 'A list of custom post types that have broadcasting enabled. The default value is %s.', '<code>post<br/>page</code>' );
		$post_types_input->description->set_unfiltered_label( $label );

		$form->primary_button( 'save_post_types' )
			->value( $this->_( 'Save the broadcastable custom post types' ) );

		if ( $form->is_posting() )
		{
			$form->post()->use_post_values();
			$post_types = $form->input( 'post_types' )->get_value();
			$post_types = $this->lines_to_string( $post_types );
			$this->update_site_option( 'post_types', $post_types);
			$this->message( 'Custom post types saved!' );
		}

		echo '

		<p>' . $this->_( 'Custom post types must be specified using their internal Wordpress names with a space between each. It is not possible to automatically make a list of available post types on the whole network because of a limitation within Wordpress (the current blog knows only of its own custom post types).' ) . '</p>

		'.$form->open_tag().'

		' . $form->display_form_table() .'

		'.$form->close_tag().'
		';
	}

	public function admin_menu_premium_pack_info()
	{
		$contents = file_get_contents( __DIR__ . '/html/premium_pack_info.html' );
		$contents = wpautop( $contents );
		$contents = $this->wrap( $contents, $this->_( 'ThreeWP Broadcast Premium Pack info' ) );
		echo $contents;
	}

	public function admin_menu_settings()
	{
		$this->enqueue_js();
		$form = $this->form2();
		$form->id( 'broadcast_settings' );
		$roles = $this->roles_as_options();
		$roles = array_flip( $roles );

		$fs = $form->fieldset( 'roles' )
			->label_( 'Roles' );

		$role_broadcast = $fs->select( 'role_broadcast' )
			->value( $this->get_site_option( 'role_broadcast' ) )
			->description_( 'The broadcast access role is the user role required to use the broadcast function at all.' )
			->label_( 'Broadcast' )
			->options( $roles );

		$role_link = $fs->select( 'role_link' )
			->value( $this->get_site_option( 'role_link' ) )
			->description_( 'When a post is linked with broadcasted posts, the child posts are updated / deleted when the parent is updated.' )
			->label_( 'Link to child posts' )
			->options( $roles );

		$role_custom_fields = $fs->select( 'role_custom_fields' )
			->value( $this->get_site_option( 'role_custom_fields' ) )
			->description_( 'Which role is needed to allow custom field broadcasting?' )
			->label_( 'Broadcast custom fields' )
			->options( $roles );

		$role_taxonomies = $fs->select( 'role_taxonomies' )
			->value( $this->get_site_option( 'role_taxonomies' ) )
			->description_( 'Which role is needed to allow taxonomy broadcasting? The taxonomies must have the same slug on all blogs.' )
			->label_( 'Broadcast taxonomies' )
			->options( $roles );

		$role_broadcast_as_draft = $fs->select( 'role_broadcast_as_draft' )
			->value( $this->get_site_option( 'role_broadcast_as_draft' ) )
			->description_( 'Which role is needed to broadcast drafts?' )
			->label_( 'Broadcast as draft' )
			->options( $roles );

		$role_broadcast_scheduled_posts = $fs->select( 'role_broadcast_scheduled_posts' )
			->value( $this->get_site_option( 'role_broadcast_scheduled_posts' ) )
			->description_( 'Which role is needed to broadcast scheduled (future) posts?' )
			->label_( 'Broadcast scheduled posts' )
			->options( $roles );

		$fs = $form->fieldset( 'seo' )
			->label_( 'SEO' );

		$override_child_permalinks = $fs->checkbox( 'override_child_permalinks' )
			->checked( $this->get_site_option( 'override_child_permalinks' ) )
			->description_( "Use the parent post's permalink for the children. If checked, child posts will link back to the parent post." )
			->label_( "Use parent permalink" );

		$canonical_url = $fs->checkbox( 'canonical_url' )
			->checked( $this->get_site_option( 'canonical_url' ) )
			->description_( 'Child posts have their canonical URLs pointed to the URL of the parent post.' )
			->label_( 'Canonical URL' );

		$fs = $form->fieldset( 'custom_field_handling' )
			->label_( 'Custom field handling' );

		$fs->markup( 'internal_field_info' )
			->p_( 'Some custom fields start with underscores. They are generally Wordpress internal fields and therefore not broadcasted. Some plugins store their information as underscored custom fields. If you wish them, or some of them, to be broadcasted, use either of the options below.' );

		$broadcast_internal_custom_fields = $fs->checkbox( 'broadcast_internal_custom_fields' )
			->checked( $this->get_site_option( 'broadcast_internal_custom_fields' ) )
			->description_( 'Broadcast all fields, including those beginning with underscores.' )
			->label_( 'Broadcast internal custom fields' );

		$blacklist = $this->get_site_option( 'custom_field_blacklist' );
		$blacklist = str_replace( ' ', "\n", $blacklist );
		$custom_field_blacklist = $fs->textarea( 'custom_field_blacklist' )
			->cols( 40, 10 )
			->description_( 'When broadcasting internal custom fields, override and do not broadcast these fields.' )
			->label_( 'Internal field blacklist' )
			->trim()
			->value( $blacklist );

		$whitelist = $this->get_site_option( 'custom_field_whitelist' );
		$whitelist = str_replace( ' ', "\n", $whitelist );
		$custom_field_whitelist = $fs->textarea( 'custom_field_whitelist' )
			->cols( 40, 10 )
			->description_( 'When not broadcasting internal custom fields, override and broadcast these fields.' )
			->label_( 'Internal field whitelist' )
			->trim()
			->value( $whitelist );

		$fs->markup( 'whitelist_defaults' )
			->p_( 'The default whitelist is: %s', "<code>\n_wp_page_template\n_wplp_\n_aioseop_</code>" );

		$fs = $form->fieldset( 'misc' )
			->label_( 'Miscellaneous' );

		$save_post_priority = $fs->number( 'save_post_priority' )
			->description_( 'The priority for the save_post hook. Should be after all other plugins have finished modifying the post. Default is 640.' )
			->label_( 'save_post priority' )
			->min( 1 )
			->required()
			->size( 5, 5 )
			->value( $this->get_site_option( 'save_post_priority' ) );

		$save = $form->primary_button( 'save' )
			->value_( 'Save settings' );

		if ( $form->is_posting() )
		{
			$form->post();
			$form->use_post_values();

			$this->update_site_option( 'role_broadcast', $role_broadcast->get_post_value() );
			$this->update_site_option( 'role_link', $role_link->get_post_value() );
			$this->update_site_option( 'role_taxonomies', $role_taxonomies->get_post_value() );
			$this->update_site_option( 'role_custom_fields', $role_custom_fields->get_post_value() );
			$this->update_site_option( 'role_broadcast_as_draft', $role_broadcast_as_draft->get_post_value() );
			$this->update_site_option( 'role_broadcast_scheduled_posts', $role_broadcast_scheduled_posts->get_post_value() );

			$this->update_site_option( 'override_child_permalinks', $override_child_permalinks->is_checked() );
			$this->update_site_option( 'canonical_url', $canonical_url->is_checked() );

			$this->update_site_option( 'broadcast_internal_custom_fields', $broadcast_internal_custom_fields->is_checked() );

			$blacklist = $custom_field_blacklist->get_post_value();
			$blacklist = $this->lines_to_string( $blacklist );
			$this->update_site_option( 'custom_field_blacklist', $blacklist );

			$whitelist = $custom_field_whitelist->get_post_value();
			$whitelist = $this->lines_to_string( $whitelist );
			$this->update_site_option( 'custom_field_whitelist', $whitelist );

			$this->update_site_option( 'save_post_priority', $save_post_priority->get_post_value() );
			$this->message( 'Options saved!' );
		}

		$r = $form->open_tag();
		$r .= $form->display_form_table();
		$r .= $form->close_tag();

		echo $r;
	}

	public function admin_menu_tabs()
	{
		$this->load_language();

		$tabs = $this->tabs();
		$tabs->tab( 'settings' )		->callback_this( 'admin_menu_settings' )		->name_( 'Settings' );
		$tabs->tab( 'post_types' )		->callback_this( 'admin_menu_post_types' )		->name_( 'Custom post types' );
		$tabs->tab( 'uninstall' )		->callback_this( 'admin_uninstall' )			->name_( 'Uninstall' );

		echo $tabs;
	}

	public function unlink()
	{
		// Check that we're actually supposed to be removing the link for real.
		$nonce = $_GET[ '_wpnonce' ];
		$post_id = $_GET[ 'post' ];
		if ( isset( $_GET[ 'child' ] ) )
			$child_blog_id = $_GET[ 'child' ];

		// Generate the nonce key to check against.
		$nonce_key = 'broadcast_unlink';
		if ( isset( $child_blog_id) )
			$nonce_key .= '_' . $child_blog_id;
		$nonce_key .= '_' . $post_id;

		if ( !wp_verify_nonce( $nonce, $nonce_key) )
			die( 'Security check: not supposed to be unlinking broadcasted post!' );

		global $blog_id;

		$broadcast_data = $this->get_post_broadcast_data( $blog_id, $post_id );
		$linked_children = $broadcast_data->get_linked_children();

		// Remove just one child?
		if ( isset( $child_blog_id ) )
		{
			// Inform Activity Monitor that a post has been unlinked.
			// Get the info about this post.
			$post_data = get_post( $post_id );
			$post_url = get_permalink( $post_id );
			$post_url = '<a href="'.$post_url.'">'.$post_data->post_title.'</a>';

			// And about the child blog
			switch_to_blog( $child_blog_id );
			$blog_url = '<a href="'.get_bloginfo( 'url' ).'">'.get_bloginfo( 'name' ).'</a>';
			restore_current_blog();

			do_action( 'threewp_activity_monitor_new_activity', array(
				'activity_id' => '3broadcast_unlinked',
				'activity_strings' => array(
					'' => '%user_display_name_with_link% unlinked ' . $post_url . ' with the child post on ' . $blog_url,
				),
			) );

			$this->delete_post_broadcast_data( $child_blog_id, $linked_children[ $child_blog_id ] );
			$broadcast_data->remove_linked_child( $child_blog_id );
			$this->set_post_broadcast_data( $blog_id, $post_id, $broadcast_data );
			$message = $this->_( 'Link to child post has been removed.' );
		}
		else
		{
			$blogs_url = [];
			foreach( $linked_children as $linked_child_blog_id => $linked_child_post_id)
			{
				// And about the child blog
				switch_to_blog( $linked_child_blog_id );
				$blogs_url[] = '<a href="'.get_bloginfo( 'url' ).'">'.get_bloginfo( 'name' ).'</a>';
				restore_current_blog();
				$this->delete_post_broadcast_data( $linked_child_blog_id, $linked_child_post_id );
			}

			// Inform Activity Monitor
			// Get the info about this post.
			$post_data = get_post( $post_id );
			$post_url = get_permalink( $post_id );
			$post_url = '<a href="'.$post_url.'">'.$post_data->post_title.'</a>';

			$blogs_url = implode( ', ', $blogs_url);

			do_action( 'threewp_activity_monitor_new_activity', array(
				'activity_id' => '3broadcast_unlinked',
				'activity_strings' => array(
					'' => '%user_display_name_with_link% unlinked ' . $post_url . ' with the child posts on ' . $blogs_url,
				),
			) );

			$broadcast_data = $this->get_post_broadcast_data( $blog_id, $post_id );
			$broadcast_data->remove_linked_children();
			$message = $this->_( 'All links to child posts have been removed!' );
		}

		$this->set_post_broadcast_data( $blog_id, $post_id, $broadcast_data );

		echo '
			'.$this->message( $message).'
			<p>
				<a href="'.wp_get_referer().'">Back to post overview</a>
			</p>
		';
	}

	/**
		Deletes a broadcasted post.
	**/
	public function user_delete()
	{
		// Check that we're actually supposed to be removing the link for real.
		global $blog_id;
		$nonce = $_GET[ '_wpnonce' ];
		$post_id = $_GET[ 'post' ];
		$child_blog_id = $_GET[ 'child' ];

		// Generate the nonce key to check against.
		$nonce_key = 'broadcast_delete';
		$nonce_key .= '_' . $child_blog_id;
		$nonce_key .= '_' . $post_id;

		if ( ! wp_verify_nonce( $nonce, $nonce_key) )
			die("Security check: not supposed to be deleting broadcasted post!");

		$broadcast_data = $this->get_post_broadcast_data( $blog_id, $post_id );
		switch_to_blog( $child_blog_id );
		$broadcasted_post_id = $broadcast_data->get_linked_child_on_this_blog();
		if ( $broadcasted_post_id === null )
			wp_die( 'No broadcasted child post found on this blog!' );
		wp_delete_post( $broadcasted_post_id, true );
		restore_current_blog();

		$message = $this->_( 'The broadcasted child post has been deleted.' );

		echo $this->message( $message);
		echo sprintf( '<p><a href="%s">%s</a></p>',
			wp_get_referer(),
			$this->_( 'Back to post overview' )
		);
	}

	/**
		Finds orphans for a specific post.
	**/
	public function user_find_orphans()
	{
		$current_blog_id = get_current_blog_id();
		$nonce = $_GET[ '_wpnonce' ];
		$post_id = $_GET[ 'post' ];

		// Generate the nonce key to check against.
		$nonce_key = 'broadcast_find_orphans';
		$nonce_key .= '_' . $post_id;

		if ( ! wp_verify_nonce( $nonce, $nonce_key) )
			die("Security check: not finding orphans for you!");

		$post = get_post( $post_id );
		$post_type = get_post_type( $post_id );
		$r = '';
		$form = $this->form();

		$broadcast_data = $this->get_post_broadcast_data( $current_blog_id, $post_id );

		// Get a list of blogs that this user can link to.
		$filter = new filters\get_user_writable_blogs( $this->user_id() );
		$blogs = $filter->apply()->blogs;

		$orphans = [];

		foreach( $blogs as $blog )
		{
			if ( $blog->id == $current_blog_id )
				continue;

			if ( $broadcast_data->has_linked_child_on_this_blog( $blog->id ) )
				continue;

			switch_to_blog( $blog->id );

			$args = array(
				'name' => $post->post_name,
				'numberposts' => 1,
				'post_type'=> $post_type,
				'post_status' => $post->post_status,
			);
			$posts = get_posts( $args );

			if ( count( $posts ) > 0 )
			{
				$orphan = reset( $posts );
				$orphan->permalink = get_permalink( $orphan->ID );
				$orphans[ $blog->id ] = $orphan;
			}

			restore_current_blog();
		}

		if ( isset( $_POST[ 'action_submit' ] ) && isset( $_POST[ 'blogs' ] ) )
		{
			if ( $_POST[ 'action' ] == 'link' )
			{
				foreach( $orphans as $blog_id => $orphan )
				{
					if ( isset( $_POST[ 'blogs' ][ $blog_id ] [ $orphan->ID ] ) )
					{
						$broadcast_data->add_linked_child( $blog_id, $orphan->ID );
						unset( $orphans[ $blog_id ] );		// There can only be one orphan per blog, so we're not interested in the blog anymore.

						$child_broadcast_data = $this->get_post_broadcast_data( $blog_id, $orphan->ID );
						$child_broadcast_data->set_linked_parent( $current_blog_id, $post_id );
						$this->set_post_broadcast_data( $blog_id, $orphan->ID, $child_broadcast_data );
					}
				}
				// Save the broadcast data.
				$this->set_post_broadcast_data( $current_blog_id, $post_id, $broadcast_data );
				echo $this->message( 'The selected children were linked!' );
			}	// link
		}

		if ( count( $orphans ) < 1 )
		{
			$message = $this->_( 'No possible child posts were found on the other blogs you have write access to. Either there are no posts with the same title as this one, or all possible orphans have already been linked.' );
		}
		else
		{
			$t_body = '';
			foreach( $orphans as $blog_id => $orphan )
			{
				$select = array(
					'type' => 'checkbox',
					'checked' => false,
					'label' => $orphan->ID,
					'name' => $orphan->ID,
					'nameprefix' => '[blogs][' . $blog_id . ']',
				);

				$t_body .= '
					<tr>
						<th scope="row" class="check-column">' . $form->make_input( $select ) . ' <span class="screen-reader-text">' . $form->make_label( $select ) . '</span></th>
						<td><a href="' . $orphan->permalink . '">' . $blogs[ $blog_id ]->blogname . '</a></td>
					</tr>
				';
			}

			$input_actions = array(
				'type' => 'select',
				'name' => 'action',
				'label' => $this->_( 'With the selected rows' ),
				'options' => array(
					array( 'value' => '', 'text' => $this->_( 'Do nothing' ) ),
					array( 'value' => 'link', 'text' => $this->_( 'Create link' ) ),
				),
			);

			$input_action_submit = array(
				'type' => 'submit',
				'name' => 'action_submit',
				'value' => $this->_( 'Apply' ),
				'css_class' => 'button-secondary',
			);

			$selected = array(
				'type' => 'checkbox',
				'name' => 'check',
			);

			$r .= '
				' . $form->start() . '
				<p>
					' . $form->make_label( $input_actions ) . '
					' . $form->make_input( $input_actions ) . '
					' . $form->make_input( $input_action_submit ) . '
				</p>
				<table class="widefat">
					<thead>
						<th class="check-column">' . $form->make_input( $select ) . '<span class="screen-reader-text">' . $this->_( 'Selected' ) . '</span></th>
						<th>' . $this->_( 'Domain' ) . '</th>
					</thead>
					<tbody>
						' . $t_body . '
					</tbody>
				</table>
				' . $form->stop() . '
			';
		}

		if ( isset( $message ) )
			echo $this->message( $message );

		echo $r;

		echo '<p><a href="edit.php?post_type='.$post_type.'">Back to post overview</a></p>';
	}

	public function user_broadcast_info()
	{
		$r = $this->p_( '%sThreeWP Broadcast%s version %s is installed.',
			sprintf( '<a href="%s">', 'http://wordpress.org/plugins/threewp-broadcast/' ),
			'</a>',
			$this->plugin_version
		);
		echo $r;
	}

	public function user_menu_tabs()
	{
		$this->load_language();

		$tabs = $this->tabs()->default_tab( 'user_broadcast_info' )->get_key( 'action' );

		if ( isset( $_GET[ 'action' ] ) )
		{
			switch( $_GET[ 'action' ] )
			{
				case 'unlink':
					$tabs->tab( 'unlink' )
						->name_( 'Unlink' );
					break;
				case 'user_delete':
					$tabs->tab( 'user_delete' )
						->name_( 'Delete' );
					break;
				case 'user_find_orphans':
					$tabs->tab( 'user_find_orphans' )
						->name_( 'Find orphans' );
					break;
				case 'user_trash':
					$tabs->tab( 'user_trash' )
						->name_( 'Trash' );
					break;
			}
		}

		$tabs->tab( 'user_broadcast_info' )->name_( 'Broadcast information' );

		echo $tabs;
	}

	/**
		Trashes a broadcasted post.
	**/
	public function user_trash()
	{
		// Check that we're actually supposed to be removing the link for real.
		global $blog_id;
		$nonce = $_GET[ '_wpnonce' ];
		$post_id = $_GET[ 'post' ];
		$child_blog_id = $_GET[ 'child' ];

		// Generate the nonce key to check against.
		$nonce_key = 'broadcast_trash';
		$nonce_key .= '_' . $child_blog_id;
		$nonce_key .= '_' . $post_id;

		if (!wp_verify_nonce( $nonce, $nonce_key) )
			die("Security check: not supposed to be unlinking broadcasted post!");

		$broadcast_data = $this->get_post_broadcast_data( $blog_id, $post_id );
		switch_to_blog( $child_blog_id );
		$broadcasted_post_id = $broadcast_data->get_linked_child_on_this_blog();
		wp_trash_post( $broadcasted_post_id );
		restore_current_blog();
		$broadcast_data->remove_linked_child( $blog_id );
		$this->set_post_broadcast_data( $blog_id, $post_id, $broadcast_data );

		$message = $this->_( 'The broadcasted child post has been put in the trash.' );

		echo $this->message( $message);
		echo sprintf( '<p><a href="%s">%s</a></p>',
			wp_get_referer(),
			$this->_( 'Back to post overview' )
		);
	}

	// --------------------------------------------------------------------------------------------
	// ----------------------------------------- Callbacks
	// --------------------------------------------------------------------------------------------

	public function add_meta_boxes()
	{
		// Display broadcast at all?
		if ( ! $this->display_broadcast )
			return false;
		// Display the meta box?
		if ( $this->display_broadcast_meta_box === false )
			return;

		// If it's true, then show it to all post types!
		if ( $this->display_broadcast_meta_box === true )
		{
			$post_types = $this->get_site_option( 'post_types' );
			foreach( explode( ' ', $post_types ) as $post_type )
				add_meta_box( 'threewp_broadcast', $this->_( 'Broadcast' ), array( &$this, 'threewp_broadcast_add_meta_box' ), $post_type, 'side', 'low' );
			return;
		}

		// No decision yet. Decide.
		$this->display_broadcast_meta_box |= is_super_admin();
		$this->display_broadcast_meta_box |= $this->role_at_least( $this->get_site_option( 'role_broadcast' ) );

		// No access to any other blogs = no point in displaying it.
		$filter = new filters\get_user_writable_blogs( $this->user_id() );
		$blogs = $filter->apply()->blogs;
		if ( count( $blogs ) <= 1 )
			$this->display_broadcast_meta_box = false;

		// Convert to a bool value
		$this->display_broadcast_meta_box = ( $this->display_broadcast_meta_box == true );

		if ( $this->display_broadcast_meta_box == true )
			return $this->add_meta_boxes();
	}

	public function delete_post( $post_id)
	{
		$this->trash_untrash_delete_post( 'wp_delete_post', $post_id );
	}

	public function manage_posts_custom_column( $column_name, $parent_post_id )
	{
		if ( $column_name != '3wp_broadcast' )
			return;

		$blog_id = get_current_blog_id();

		// Prep the bcd cache.
		$broadcast_data = $this->broadcast_data_cache()
			->expect_from_wp_query()
			->get_for( $blog_id, $parent_post_id );

		global $post;
		$action = new actions\manage_posts_custom_column();
		$action->post = $post;
		$action->parent_blog_id = $blog_id;
		$action->parent_post_id = $parent_post_id;
		$action->broadcast_data = $broadcast_data;
		$action->apply();

		echo $action->render();
	}

	public function manage_posts_columns( $defaults)
	{
		$defaults[ '3wp_broadcast' ] = '<span title="'.$this->_( 'Shows which blogs have posts linked to this one' ).'">'.$this->_( 'Broadcasted' ).'</span>';
		return $defaults;
	}

	public function post_link( $link, $post )
	{
		// Don't overwrite the permalink if we're in the editing window.
		// This allows the user to change the permalink.
		if ( $_SERVER[ 'SCRIPT_NAME' ] == '/wp-admin/post.php' )
			return $link;

		$blog_id = get_current_blog_id();

		// Have we already checked this post ID for a link?
		$key = 'b' . $blog_id . '_p' . $post->ID;
		if ( property_exists( $this->permalink_cache, $key ) )
			return $this->permalink_cache->$key;

		$broadcast_data = $this->get_post_broadcast_data( $blog_id, $post->ID );

		$linked_parent = $broadcast_data->get_linked_parent();

		if ( $linked_parent === false)
		{
			$this->permalink_cache->$key = $link;
			return $link;
		}

		switch_to_blog( $linked_parent[ 'blog_id' ] );
		$post = get_post( $linked_parent[ 'post_id' ] );
		$permalink = get_permalink( $post );
		restore_current_blog();

		$this->permalink_cache->$key = $permalink;

		return $permalink;
	}

	public function post_row_actions( $actions, $post )
	{
		$this->broadcast_data_cache()->expect_from_wp_query();

		global $blog_id;
		$broadcast_data = $this->get_post_broadcast_data( $blog_id, $post->ID );
		if ( $broadcast_data->has_linked_children() )
			$actions = array_merge( $actions, array(
				'broadcast_unlink' => '<a href="'.wp_nonce_url("admin.php?page=threewp_broadcast&amp;action=unlink&amp;post=".$post->ID."", 'broadcast_unlink_' . $post->ID).'" title="'.$this->_( 'Remove links to all the broadcasted children' ).'">'.$this->_( 'Unlink' ).'</a>',
			) );
		$actions[ 'broadcast_find_orphans' ] = '<a href="'.wp_nonce_url("admin.php?page=threewp_broadcast&amp;action=user_find_orphans&amp;post=".$post->ID."", 'broadcast_find_orphans_' . $post->ID).'" title="'.$this->_( 'Find posts on other blogs that are identical to this post' ).'">'.$this->_( 'Find orphans' ).'</a>';
		return $actions;
	}

	public function save_post( $post_id )
	{
		// Loop check.
		if ( $this->is_broadcasting() )
			return;

		if ( count( $_POST ) < 1 )
			return;

		// No permission.
		if ( ! $this->role_at_least( $this->get_site_option( 'role_broadcast' ) ) )
			return;

		// Save the user's last settings.
		if ( isset( $_POST[ 'broadcast' ] ) )
			$this->save_last_used_settings( $this->user_id(), $_POST[ 'broadcast' ] );

		$post = get_post( $post_id );

		$meta_box_data = $this->create_meta_box( $post );

		// Allow plugins to modify the meta box with their own info.
		$action = new actions\prepare_meta_box;
		$action->meta_box_data = $meta_box_data;
		$action->apply();

		$broadcasting_data = new broadcasting_data( [
			'_POST' => $_POST,
			'meta_box_data' => $meta_box_data,
			'parent_blog_id' => get_current_blog_id(),
			'parent_post_id' => $post_id,
			'post' => $post,
			'upload_dir' => wp_upload_dir(),
		] );

		$this->filters( 'threewp_broadcast_prepare_broadcasting_data', $broadcasting_data );

		if ( $broadcasting_data->has_blogs() )
			$this->filters( 'threewp_broadcast_broadcast_post', $broadcasting_data );
	}

	public function threewp_activity_monitor_list_activities( $activities )
	{
		// First, fill in our own activities.
		$this->activities = array(
			'3broadcast_broadcasted' => array(
				'name' => $this->_( 'A post was broadcasted.' ),
			),
			'3broadcast_group_added' => array(
				'name' => $this->_( 'A Broadcast blog group was added.' ),
			),
			'3broadcast_group_deleted' => array(
				'name' => $this->_( 'A Broadcast blog group was deleted.' ),
			),
			'3broadcast_group_modified' => array(
				'name' => $this->_( 'A Broadcast blog group was modified.' ),
			),
			'3broadcast_unlinked' => array(
				'name' => $this->_( 'A post was unlinked.' ),
			),
		);

		// Insert our module name in all the values.
		foreach( $this->activities as $index => $activity )
		{
			$activity[ 'plugin' ] = 'ThreeWP Broadcast';
			$activities[ $index ] = $activity;
		}

		return $activities;
	}

	/**
		@brief		Prepare and display the meta box data.
		@since		20131003
	**/
	public function threewp_broadcast_add_meta_box( $post )
	{
		$meta_box_data = $this->create_meta_box( $post );

		// Allow plugins to modify the meta box with their own info.
		$action = new actions\prepare_meta_box;
		$action->meta_box_data = $meta_box_data;
		$action->apply();

		foreach( $meta_box_data->css as $key => $value )
			wp_enqueue_style( $key, $value );
		foreach( $meta_box_data->js as $key => $value )
			wp_enqueue_script( $key, $value );

		echo $meta_box_data->html;
	}

	/**
		@brief		Begin adding admin hooks.
		@since		20131015
	**/
	public function threewp_broadcast_admin_menu()
	{
		if ( is_super_admin() || $this->role_at_least( $this->get_site_option( 'role_link' ) ) )
		{
			if (  $this->display_broadcast_columns )
			{
				$this->add_action( 'post_row_actions', 10, 2 );
				$this->add_action( 'page_row_actions', 'post_row_actions', 10, 2 );

				$this->add_filter( 'manage_posts_columns' );
				$this->add_action( 'manage_posts_custom_column', 10, 2 );

				$this->add_filter( 'manage_pages_columns', 'manage_posts_columns' );
				$this->add_action( 'manage_pages_custom_column', 'manage_posts_custom_column', 10, 2 );
			}

			// Hook into the actions that keep track of the broadcast data.
			$this->add_action( 'wp_trash_post', 'trash_post' );
			$this->add_action( 'trash_post' );
			$this->add_action( 'trash_page', 'trash_post' );

			$this->add_action( 'untrash_post' );
			$this->add_action( 'untrash_page', 'untrash_post' );

			$this->add_action( 'delete_post' );
			$this->add_action( 'delete_page', 'delete_post' );
		}
	}

	/**
		@brief		Prepare and display the meta box data.
		@since		20131010
	**/
	public function threewp_broadcast_prepare_meta_box( $action )
	{
		if ( $action->is_applied() )
			return;

		$meta_box_data = $action->meta_box_data;	// Convenience.

		if ( $meta_box_data->broadcast_data->get_linked_parent() !== false)
		{
			$meta_box_data->html->put( 'already_broadcasted',  sprintf( '<p>%s</p>',
				$this->_( 'This post is broadcasted child post. It cannot be broadcasted further.' )
			) );
			$action->applied();
			return;
		}

		$form = $meta_box_data->form;		// Convenience
		$form->prefix( 'broadcast' );		// Create all inputs with this prefix.

		$published = $meta_box_data->post->post_status == 'publish';

		$has_linked_children = $meta_box_data->broadcast_data->has_linked_children();

		$last_used_settings = $this->load_last_used_settings( $this->user_id() );

		$post_type = $meta_box_data->post->post_type;
		$post_type_object = get_post_type_object( $post_type );
		$post_type_supports_thumbnails = post_type_supports( $post_type, 'thumbnail' );
		$post_type_supports_custom_fields = post_type_supports( $post_type, 'custom-fields' );
		$post_type_is_hierarchical = $post_type_object->hierarchical;

		if ( is_super_admin() || $this->role_at_least( $this->get_site_option( 'role_link' ) ) )
		{
			// Check the link box is the post has been published and has children OR it isn't published yet.
			$linked = (
				( $published && $meta_box_data->broadcast_data->has_linked_children() )
				||
				! $published
			);
			$link_input = $form->checkbox( 'link' )
				->checked( $linked )
				->label_( 'Link this post to its children' )
				->title( $this->_( 'Create a link to the children, which will be updated when this post is updated, trashed when this post is trashed, etc.' ) );
			$meta_box_data->html->put( 'link', '' );
		}

		if (
			( $post_type_supports_custom_fields || $post_type_supports_thumbnails )
			&&
			( is_super_admin() || $this->role_at_least( $this->get_site_option( 'role_custom_fields' ) ) )
		)
		{
			$custom_fields_input = $form->checkbox( 'custom_fields' )
				->checked( isset( $last_used_settings[ 'custom_fields' ] ) )
				->label_( 'Custom fields' )
				->title( 'Broadcast all the custom fields and the featured image?' );
			$meta_box_data->html->put( 'custom_fields', '' );
		}

		if ( is_super_admin() || $this->role_at_least( $this->get_site_option( 'role_taxonomies' ) ) )
		{
			$taxonomies_input = $form->checkbox( 'taxonomies' )
				->checked( isset( $last_used_settings[ 'taxonomies' ] ) )
				->label_( 'Taxonomies' )
				->title( 'The taxonomies must have the same name (slug) on the selected blogs.' );
			$meta_box_data->html->put( 'taxonomies', '' );
		}

		$meta_box_data->html->put( 'broadcast_strings', '
			<script type="text/javascript">
				var broadcast_strings = {
					hide_all : "' . $this->_( 'hide all' ) . '",
					invert_selection : "' . $this->_( 'Invert selection' ) . '",
					select_deselect_all : "' . $this->_( 'Select / deselect all' ) . '",
					show_all : "' . $this->_( 'show all' ) . '"
				};
			</script>
		' );

		$filter = new filters\get_user_writable_blogs( $this->user_id() );
		$blogs = $filter->apply()->blogs;

		$blogs_input = $form->checkboxes( 'blogs' )
			->label( 'Broadcast to' )
			->prefix( 'blogs' );

		// Preselect those children that this post has.
		$linked_children = $meta_box_data->broadcast_data->get_linked_children();
		foreach( $linked_children as $blog_id => $ignore )
		{
			$blog = $blogs->get( $blog_id );
			if ( ! $blog )
				continue;
			$blog->linked()->selected();
		}

		foreach( $blogs as $blog )
		{
			$blogs_input->option( $blog->blogname, $blog->id );
			$option = $blogs_input->input( 'blogs_' . $blog->id );
			if ( $blog->is_disabled() )
				$option->disabled()->css_class( 'disabled' );
			if ( $blog->is_linked() )
				$option->css_class( 'linked' );
			if ( $blog->is_required() )
				$option->css_class( 'required' )->title_( 'This blog is required' );
			if ( $blog->is_selected() )
				$option->checked( true );
			// The current blog should be "selectable", for the sake of other plugins that modify the meta box. But hidden from users.
			if ( $blog->id == $meta_box_data->blog_id )
				$option->hidden();
		}

		$meta_box_data->html->put( 'blogs', '' );

		// Advertize the premium plugins.
		$queue_url = add_query_arg( 'page', 'threewp_broadcast_premium_pack_info', 'admin.php' );
		$meta_box_data->html->put( 'broadcast_queue', $this->_( '%sQueue%s not available.',
			sprintf( '<a href="%s" title="%s">',
				$queue_url,
				$this->_( 'Information about the Broadcast Queue Plugin' )
			),
			'</a>'
		) );

		// We require some js.
		$meta_box_data->js->put( 'threewp_broadcast', $this->paths[ 'url' ] . '/js/user.min.js' );
		// And some CSS
		$meta_box_data->css->put( 'threewp_broadcast', $this->paths[ 'url' ] . '/css/css.scss.min.css'  );

		$action->applied();
	}

	/**
		@brief		Fix up the inputs.
		@since		20131010
	**/
	public function threewp_broadcast_prepared_meta_box( $action )
	{
		$meta_box_data = $action->meta_box_data;

		// If our places in the html are still left, insert the inputs.
		foreach( [
			'link',
			'custom_fields',
			'taxonomies',
			'groups',
			'blogs'
		] as $type )
			if ( $meta_box_data->html->has( $type ) )
			{
				$input = $meta_box_data->form->input( $type );
				$meta_box_data->html->put( $type, $input );
			}
	}

	/**
		@brief		Return a collection of blogs that the user is allowed to write to.
		@since		20131003
	**/
	public function threewp_broadcast_get_user_writable_blogs( $filter )
	{
		if ( $filter->is_applied() )
			return;

		$blogs = get_blogs_of_user( $filter->user_id, true );
		foreach( $blogs as $blog)
		{
			$blog = blog::make( $blog );
			$blog->id = $blog->userblog_id;
			if ( ! $this->is_blog_user_writable( $filter->user_id, $blog ) )
				continue;
			$filter->blogs->set( $blog->id, $blog );
		}

		$filter->blogs->sort_logically();
		$filter->applied();
	}

	/**
		@brief		Fill the broadcasting_data object with information.

		@details

		The difference between the calculations in this filter and the actual broadcast_post method is that this filter

		1) does access checks
		2) tells broadcast_post() WHAT to broadcast, not how.

		@since		20131004
	**/
	public function threewp_broadcast_manage_posts_custom_column( $filter )
	{
		if ( $filter->broadcast_data->get_linked_parent() !== false)
		{
			$parent = $filter->broadcast_data->get_linked_parent();
			$parent_blog_id = $parent[ 'blog_id' ];
			switch_to_blog( $parent_blog_id );
			$filter->html->put(
				'linked_from',
				$this->_(sprintf( 'Linked from %s', '<a href="' . get_bloginfo( 'url' ) . '/wp-admin/post.php?post=' .$parent[ 'post_id' ] . '&action=edit">' . get_bloginfo( 'name' ) . '</a>' ) )
			);
			restore_current_blog();
		}
		elseif ( $filter->broadcast_data->has_linked_children() )
		{
			$children = $filter->broadcast_data->get_linked_children();

			if ( count( $children ) > 0 )
			{
				$blogs = new \plainview\sdk\collections\collection;
				$output = '';

				foreach( $children as $child_blog_id => $child_post_id )
				{
					$url_child = get_blog_permalink( $child_blog_id, $child_post_id );
					// The post id is for the current blog, not the target blog.

					$url_delete = sprintf( "admin.php?page=threewp_broadcast&amp;action=user_delete&amp;post=%s&amp;child=%s", $filter->parent_post_id, $child_blog_id );
					$url_delete = wp_nonce_url( $url_delete, 'broadcast_delete_' . $child_blog_id . '_' . $filter->parent_post_id );

					$url_trash = sprintf( "admin.php?page=threewp_broadcast&amp;action=user_trash&amp;post=%s&amp;child=%s", $filter->parent_post_id, $child_blog_id );
					$url_trash = wp_nonce_url( $url_trash, 'broadcast_trash_' . $child_blog_id . '_' . $filter->parent_post_id );

					$url_unlink = sprintf( "admin.php?page=threewp_broadcast&amp;action=unlink&amp;post=%s&amp;child=%s", $filter->parent_post_id, $child_blog_id );
					$url_unlink = wp_nonce_url( $url_unlink, 'broadcast_unlink_' . $child_blog_id . '_' . $filter->parent_post_id );

					// For get_bloginfo.
					switch_to_blog( $child_blog_id );

					$string = sprintf( '
						<div class="child_blog_name blog_%s">
							<a class="broadcasted_child" href="%s">
								%s
							</a>
						</div>
						<div class="row-actions broadcasted_blog_actions">
							<small>
							<a href="%s" title="%s">%s</a>
							| <span><a href="%s" title="%s">%s</a></span>
							| <span class="trash"><a href="%s" title="%s">%s</a></span>
							</small>
						</div>
					',
						$child_blog_id,
						$url_child,
						get_bloginfo( 'blogname' ),
						$url_unlink,
						$this->_( 'Remove link to this broadcasted child post' ),
						$this->_( 'Unlink' ),
						$url_trash,
						$this->_( 'Put this broadcasted child post in the trash' ),
						$this->_( 'Trash' ),
						$url_delete,
						$this->_( 'Unlink and delete this broadcasted child post' ),
						$this->_( 'Delete' )
					);

					restore_current_blog();
					$blogs->put( $child_blog_id, $string );
					$output .= $string;
				}
				$filter->html->put( 'broadcasted_to', $output );
				$filter->blogs = $blogs;
			}
		}
		$filter->applied();
	}

	/**
		@brief		Fill the broadcasting_data object with information.

		@details

		The difference between the calculations in this filter and the actual broadcast_post method is that this filter

		1) does access checks
		2) tells broadcast_post() WHAT to broadcast, not how.

		@since		20131004
	**/
	public function threewp_broadcast_prepare_broadcasting_data( $bcd )
	{
		$allowed_post_status = [ 'pending', 'private', 'publish' ];

		if ( $bcd->post->post_status == 'draft' && $this->role_at_least( $this->get_site_option( 'role_broadcast_as_draft' ) ) )
			$allowed_post_status[] = 'draft';

		if ( $bcd->post->post_status == 'future' && $this->role_at_least( $this->get_site_option( 'role_broadcast_scheduled_posts' ) ) )
			$allowed_post_status[] = 'future';

		if ( ! in_array( $bcd->post->post_status, $allowed_post_status ) )
			return;

		$form = $bcd->meta_box_data->form;
		$form->post();

		// Collect the list of blogs from the meta box.
		$blogs_input = $form->input( 'blogs' );
		foreach( $blogs_input->inputs() as $blog_input )
			if ( $blog_input->is_checked() )
			{
				$blog_id = $blog_input->get_name();
				$blog_id = str_replace( 'blogs_', '', $blog_id );
				$blog = new broadcast_data\blog;
				$blog->id = $blog_id;
				$bcd->broadcast_to( $blog );
			}

		// Remove the current blog
		$bcd->blogs->forget( $bcd->parent_blog_id );

		$bcd->post_type_object = get_post_type_object( $bcd->post->post_type );
		$bcd->post_type_supports_thumbnails = post_type_supports( $bcd->post->post_type, 'thumbnail' );
		$bcd->post_type_supports_custom_fields = post_type_supports( $bcd->post->post_type, 'custom-fields' );
		$bcd->post_type_is_hierarchical = $bcd->post_type_object->hierarchical;

		$bcd->custom_fields = $form->checkbox( 'custom_fields' )->get_post_value()
			&& ( is_super_admin() || $this->role_at_least( $this->get_site_option( 'role_custom_fields' ) ) );

		$bcd->link = $form->checkbox( 'link' )->get_post_value()
			&& ( is_super_admin() || $this->role_at_least( $this->get_site_option( 'role_link' ) ) );

		$bcd->taxonomies = $form->checkbox( 'taxonomies' )->get_post_value()
			&& ( is_super_admin() || $this->role_at_least( $this->get_site_option( 'role_taxonomies' ) ) );

		// Is this post sticky? This info is hidden in a blog option.
		$stickies = get_option( 'sticky_posts' );
		$bcd->post_is_sticky = in_array( $bcd->post->ID, $stickies );

		return;

		ddd( $bcd->blogs );
		ddd( $bcd->link );
		ddd( $bcd->custom_fields );
		ddd( $bcd->taxonomies );
		ddd( 'noooooooooooo mooooooooooooooooooreeeeeeeeee' );
		exit;
	}

	public function trash_post( $post_id)
	{
		$this->trash_untrash_delete_post( 'wp_trash_post', $post_id );
	}

	/**
	 * Issues a specific command on all the blogs that this post_id has linked children on.
	 * @param string $command Command to run.
	 * @param int $post_id Post with linked children
	 */
	private function trash_untrash_delete_post( $command, $post_id)
	{
		global $blog_id;
		$broadcast_data = $this->get_post_broadcast_data( $blog_id, $post_id );

		if ( $broadcast_data->has_linked_children() )
		{
			foreach( $broadcast_data->get_linked_children() as $childBlog=>$childPost)
			{
				if ( $command == 'wp_delete_post' )
				{
					// Delete the broadcast data of this child
					$this->delete_post_broadcast_data( $childBlog, $childPost );
				}
				switch_to_blog( $childBlog);
				$command( $childPost);
				restore_current_blog();
			}
		}

		if ( $command == 'wp_delete_post' )
		{
			global $blog_id;
			// Find out if this post has a parent.
			$linked_parent_broadcast_data = $this->get_post_broadcast_data( $blog_id, $post_id );
			$linked_parent_broadcast_data = $linked_parent_broadcast_data->get_linked_parent();
			if ( $linked_parent_broadcast_data !== false)
			{
				// Remove ourselves as a child.
				$parent_broadcast_data = $this->get_post_broadcast_data( $linked_parent_broadcast_data[ 'blog_id' ], $linked_parent_broadcast_data[ 'post_id' ] );
				$parent_broadcast_data->remove_linked_child( $blog_id );
				$this->set_post_broadcast_data( $linked_parent_broadcast_data[ 'blog_id' ], $linked_parent_broadcast_data[ 'post_id' ], $parent_broadcast_data );
			}

			$this->delete_post_broadcast_data( $blog_id, $post_id );
		}
	}

	/**
		@brief		Adds to the broadcast menu.
		@param		threewp_broadcast		$threewp_broadcast		The broadcast object.
		@since		20130927
	**/
	public function threewp_broadcast_menu( $action )
	{
		if ( $this->display_premium_pack_info && is_super_admin() )
			$this->add_submenu_page(
				'threewp_broadcast',
				$this->_( 'Premium Pack info' ),
				$this->_( 'Premium Pack' ),
				'edit_posts',
				'threewp_broadcast_premium_pack_info',
				[ &$this, 'admin_menu_premium_pack_info' ]
			);

		if ( is_super_admin() )
			$action->broadcast->add_submenu_page(
				'threewp_broadcast',
				'Admin settings',
				'Admin settings',
				'activate_plugins',
				'threewp_broadcast_admin_menu',
				[ &$this, 'admin_menu_tabs' ]
			);
	}

	/**
		@brief		Adds to the broadcast menu.
		@param		threewp_broadcast		$threewp_broadcast		The broadcast object.
		@since		20130927
	**/
	public function threewp_broadcast_menu_final( $action )
	{
		if ( ! $this->display_broadcast_menu )
			return;

		add_menu_page(
			$this->_( 'ThreeWP Broadcast' ),
			$this->_( 'Broadcast' ),
			'edit_posts',
			'threewp_broadcast',
			[ &$this, 'user_menu_tabs' ]
		);

		$this->add_submenu_pages();
	}

	/**
		@brief		Broadcasts a post.
		@param		broadcasting_data		$broadcasting_data		Object containing broadcasting instructions.
		@since		20130927
	**/
	public function threewp_broadcast_broadcast_post( $broadcasting_data )
	{
		if ( ! is_a( $broadcasting_data, get_class( new broadcasting_data ) ) )
			return $broadcasting_data;
		return $this->broadcast_post( $broadcasting_data );
	}

	public function untrash_post( $post_id)
	{
		$this->trash_untrash_delete_post( 'wp_untrash_post', $post_id );
	}

	/**
		@brief		Use the correct canonical link.
	**/
	public function wp_head()
	{
		// Only override the canonical if we're looking at a single post.
		if ( ! is_single() )
			return;

		global $post;
		global $blog_id;

		// Find the parent, if any.
		$broadcast_data = $this->get_post_broadcast_data( $blog_id, $post->ID );
		$linked_parent = $broadcast_data->get_linked_parent();
		if ( $linked_parent === false)
			return;

		// Post has a parent. Get the parent's permalink.
		switch_to_blog( $linked_parent[ 'blog_id' ] );
		$url = get_permalink( $linked_parent[ 'post_id' ] );
		restore_current_blog();

		echo sprintf( '<link rel="canonical" href="%s" />', $url );
		echo "\n";

		// Prevent Wordpress from outputting its own canonical.
		remove_action( 'wp_head', 'rel_canonical' );
	}

	// --------------------------------------------------------------------------------------------
	// ----------------------------------------- Misc functions
	// --------------------------------------------------------------------------------------------

	/**
		@brief		Returns the current broadcast_data cache object.
		@return		broadcast_data\\cache		A newly-created or old cache object.
		@since		201301009
	**/
	public function broadcast_data_cache()
	{
		$property = 'broadcast_data_cache';
		if ( ! property_exists( $this, 'broadcast_data_cache' ) )
			$this->$property = new \threewp_broadcast\broadcast_data\cache;
		return $this->$property;
	}

	/**
		@brief		Broadcast a post.
		@details	The BC data parameter contains all necessary information about what is being broadcasted, to which blogs, options, etc.
		@param		broadcasting_data		$broadcasting_data		The broadcasting data object.
		@since		20130603
	**/
	public function broadcast_post( $broadcasting_data )
	{
		$this->broadcasting_data = $broadcasting_data;					// Global copy.
		$bcd = $this->broadcasting_data;								// Convenience.

		// Create new post data from the original stuff.
		$bcd->new_post = (array) $bcd->post;
		foreach( array( 'comment_count', 'guid', 'ID', 'menu_order', 'post_parent' ) as $key )
			unset( $bcd->new_post[ $key ] );

		if ( $bcd->link )
		{
			// Prepare the broadcast data for linked children.
			$broadcast_data = $this->get_post_broadcast_data( $bcd->parent_blog_id, $bcd->post->ID );

			// Does this post type have parent support, so that we can link to a parent?
			if ( $bcd->post_type_is_hierarchical && $bcd->post->post_parent > 0)
			{
				$parent_broadcast_data = $this->get_post_broadcast_data( $bcd->parent_blog_id, $bcd->post->post_parent );
			}
		}

		if ( $bcd->taxonomies )
		{
			$bcd->parent_blog_taxonomies = get_object_taxonomies( [ 'object_type' => $bcd->post->post_type ], 'array' );
			$bcd->parent_post_taxonomies = [];
			foreach( $bcd->parent_blog_taxonomies as $parent_blog_taxonomy => $taxonomy )
			{
				// Parent blog taxonomy terms are used for creating missing target term ancestors
				$bcd->parent_blog_taxonomies[ $parent_blog_taxonomy ] = [
					'taxonomy' => $taxonomy,
					'terms'    => $this->get_current_blog_taxonomy_terms( $parent_blog_taxonomy ),
				];
				$bcd->parent_post_taxonomies[ $parent_blog_taxonomy ] = get_the_terms( $bcd->post->ID, $parent_blog_taxonomy );
			}
		}

		$bcd->attachment_data = [];
		$attached_files = get_children( 'post_parent='.$bcd->post->ID.'&post_type=attachment' );
		$has_attached_files = count( $attached_files) > 0;
		if ( $has_attached_files )
			foreach( $attached_files as $attached_file )
				$bcd->attachment_data[ $attached_file->ID ] = attachment_data::from_attachment_id( $attached_file, $bcd->upload_dir );

		if ( $bcd->custom_fields )
		{
			$bcd->post_custom_fields = get_post_custom( $bcd->post->ID );

			$bcd->has_thumbnail = isset( $bcd->post_custom_fields[ '_thumbnail_id' ] );
			if ( $bcd->has_thumbnail )
			{
				$bcd->thumbnail_id = $bcd->post_custom_fields[ '_thumbnail_id' ][0];
				$bcd->thumbnail = get_post( $bcd->thumbnail_id );
				unset( $bcd->post_custom_fields[ '_thumbnail_id' ] ); // There is a new thumbnail id for each blog.
				$bcd->attachment_data[ 'thumbnail' ] = attachment_data::from_attachment_id( $bcd->thumbnail, $bcd->upload_dir);
				// Now that we know what the attachment id the thumbnail has, we must remove it from the attached files to avoid duplicates.
				unset( $bcd->attachment_data[ $bcd->thumbnail_id ] );
			}

			// Remove all the _internal custom fields.
			$bcd->post_custom_fields = $this->keep_valid_custom_fields( $bcd->post_custom_fields );
		}

		// Handle any galleries.
		$bcd->galleries = new collection;
		$rx = get_shortcode_regex();
		$matches = '';
		preg_match_all( '/' . $rx . '/', $bcd->post->post_content, $matches );

		// [2] contains only the shortcode command / key. No options.
		foreach( $matches[ 2 ] as $index => $key )
		{
			// Look for only the gallery shortcode.
			if ( $key !== 'gallery' )
				continue;

			// We've found a gallery!
			$bcd->has_galleries = true;
			$gallery = new \stdClass;
			$bcd->galleries->push( $gallery );

			// Complete matches are in 0.
			$gallery->old_shortcode = $matches[ 0 ][ $index ];

			// Extract the IDs
			$gallery->ids_string = preg_replace( '/.*ids=\"([0-9,]*)".*/', '\1', $gallery->old_shortcode );
			$gallery->ids_array = explode( ',', $gallery->ids_string );
			foreach( $gallery->ids_array as $id )
			{
				$ad = attachment_data::from_attachment_id( $id, $bcd->upload_dir );
				$bcd->attachment_data[ $id ] = $ad;
			}
		}

		$to_broadcasted_blogs = [];				// Array of blog names that we're broadcasting to. To be used for the activity monitor action.
		$to_broadcasted_blog_details = []; 		// Array of blog and post IDs that we're broadcasting to. To be used for the activity monitor action.

		// To prevent recursion
		$this->broadcasting = true;
		unset( $_POST[ 'broadcast' ] );

		$action = new actions\broadcasting_started;
		$action->broadcasting_data = $bcd;
		$action->apply();

		foreach( $bcd->blogs as $child_blog )
		{
			$child_blog->switch_to();
			$bcd->current_child_blog_id = $child_blog->get_id();

			$action = new actions\broadcasting_after_switch_to_blog;
			$action->broadcasting_data = $bcd;
			$action->apply();

			// Post parent
			if ( $bcd->link && isset( $parent_broadcast_data) )
				if ( $parent_broadcast_data->has_linked_child_on_this_blog() )
				{
					$linked_parent = $parent_broadcast_data->get_linked_child_on_this_blog();
					$bcd->new_post[ 'post_parent' ] = $linked_parent;
				}

			// Insert new? Or update? Depends on whether the parent post was linked before or is newly linked?
			$need_to_insert_post = true;
			if ( $bcd->link )
				if ( $broadcast_data->has_linked_child_on_this_blog() )
				{
					$child_post_id = $broadcast_data->get_linked_child_on_this_blog();

					// Does this child post still exist?
					$child_post = get_post( $child_post_id );
					if ( $child_post !== null )
					{
						$temp_post_data = $bcd->new_post;
						$temp_post_data[ 'ID' ] = $child_post_id;
						$bcd->new_post[ 'ID' ] = wp_update_post( $temp_post_data );
						$need_to_insert_post = false;
					}
				}

			if ( $need_to_insert_post )
			{
				$temp_post_data = $bcd->new_post;
				unset( $temp_post_data[ 'ID' ] );
				$result = wp_insert_post( $temp_post_data, true );
				// Did we manage to insert the post properly?
				if ( intval( $result ) < 1 )
					continue;
				// Yes we did.
				$bcd->new_post[ 'ID' ] = $result;

				if ( $bcd->link )
					$broadcast_data->add_linked_child( $bcd->current_child_blog_id, $bcd->new_post[ 'ID' ] );
			}

			if ( $bcd->taxonomies )
			{
				foreach( $bcd->parent_post_taxonomies as $parent_post_taxonomy => $parent_post_terms )
				{
					// If we're updating a linked post, remove all the taxonomies and start from the top.
					if ( $bcd->link )
						if ( $broadcast_data->has_linked_child_on_this_blog() )
							wp_set_object_terms( $bcd->new_post[ 'ID' ], [], $parent_post_taxonomy );

					// Skip this iteration if there are no terms
					if ( ! is_array( $parent_post_terms ) )
						continue;

					// Get a list of terms that the target blog has.
					$target_blog_terms = $this->get_current_blog_taxonomy_terms( $parent_post_taxonomy );

					// Go through the original post's terms and compare each slug with the slug of the target terms.
					$taxonomies_to_add_to = [];
					foreach( $parent_post_terms as $parent_post_term )
					{
						$found = false;
						$parent_slug = $parent_post_term->slug;
						foreach( $target_blog_terms as $target_blog_term )
						{
							if ( $target_blog_term[ 'slug' ] == $parent_slug )
							{
								$found = true;
								$taxonomies_to_add_to[] = intval( $target_blog_term[ 'term_id' ] );
								break;
							}
						}

						// Should we create the taxonomy if it doesn't exist?
						if ( ! $found )
						{
							// Does the term have a parent?
							$target_parent_id = 0;
							if ( $parent_post_term->parent != 0 )
							{
								// Recursively insert ancestors if needed, and get the target term's parent's ID
								$target_parent_id = $this->insert_term_ancestors(
									(array) $parent_post_term,
									$parent_post_taxonomy,
									$target_blog_terms,
									$bcd->parent_blog_taxonomies[ $parent_post_taxonomy ][ 'terms' ]
								);
							}

							$new_taxonomy = wp_insert_term(
								$parent_post_term->name,
								$parent_post_taxonomy,
								array(
									'slug' => $parent_post_term->slug,
									'description' => $parent_post_term->description,
									'parent' => $target_parent_id,
								)
							);

							// Sometimes the search didn't find the term because it's SIMILAR and not exact.
							// WP will complain and give us the term tax id.
							if ( is_wp_error( $new_taxonomy ) )
							{
								$wp_error = $new_taxonomy;
								if ( isset( $wp_error->error_data[ 'term_exists' ] ) )
									$term_taxonomy_id = $wp_error->error_data[ 'term_exists' ];
							}
							else
							{
								$term_taxonomy_id = $new_taxonomy[ 'term_taxonomy_id' ];
							}

							$taxonomies_to_add_to []= intval( $term_taxonomy_id );
						}
					}

					$this->sync_terms( $bcd, $parent_post_taxonomy );

					if ( count( $taxonomies_to_add_to) > 0 )
					{
						// This relates to the bug mentioned in the method $this->set_term_parent()
						delete_option( $parent_post_taxonomy . '_children' );
						clean_term_cache( '', $parent_post_taxonomy );
						wp_set_object_terms( $bcd->new_post[ 'ID' ], $taxonomies_to_add_to, $parent_post_taxonomy );
					}
				}
			}

			// Remove the current attachments.
			$attachments_to_remove = get_children( 'post_parent='.$bcd->new_post[ 'ID' ].'&post_type=attachment' );
			foreach ( $attachments_to_remove as $attachment_to_remove )
				wp_delete_attachment( $attachment_to_remove->ID );

			// Copy the attachments
			$bcd->copied_attachments = [];
			foreach( $bcd->attachment_data as $key => $attachment )
			{
				if ( $key != 'thumbnail' )
				{
					$o = clone( $bcd );
					$o->attachment_data = $attachment;
					if ( $o->attachment_data->post->post_parent > 0 )
						$o->attachment_data->post->post_parent = $bcd->new_post[ 'ID' ];
					$this->maybe_copy_attachment( $o );
					$a = new \stdClass();
					$a->old = $attachment;
					$a->new = get_post( $o->attachment_id );
					$bcd->copied_attachments[] = $a;
				}
			}

			// Maybe modify the post content with new URLs to attachments and what not.
			$unmodified_post = (object)$bcd->new_post;
			$modified_post = clone( $unmodified_post );

			// If there were any image attachments copied...
			if ( count( $bcd->copied_attachments ) > 0 )
			{
				// Update the URLs in the post to point to the new images.
				$new_upload_dir = wp_upload_dir();
				foreach( $bcd->copied_attachments as $a )
				{
					// Replace the GUID with the new one.
					$modified_post->post_content = str_replace( $a->old->guid, $a->new->guid, $modified_post->post_content );
					// And replace the IDs present in any image captions.
					$modified_post->post_content = str_replace( 'id="attachment_' . $a->old->id . '"', 'id="attachment_' . $a->new->id . '"', $modified_post->post_content );
				}
			}

			// If there are galleries...
			foreach( $bcd->galleries as $gallery )
			{
				// Work on a copy.
				$gallery = clone( $gallery );
				$new_ids = [];

				// Go through all the attachment IDs
				foreach( $gallery->ids_array as $id )
				{
					// Find the new ID.
					foreach( $bcd->copied_attachments as $ca )
					{
						if ( $ca->old->id != $id )
							continue;
						$new_ids[] = $ca->new->ID;
					}
				}
				$new_ids_string = implode( ',', $new_ids );
				$new_shortcode = $gallery->old_shortcode;
				$new_shortcode = str_replace( $gallery->ids_string, $new_ids_string, $gallery->old_shortcode );
				$modified_post->post_content = str_replace( $gallery->old_shortcode, $new_shortcode, $modified_post->post_content );
			}

			// Maybe updating the post is not necessary.
			if ( $unmodified_post->post_content != $modified_post->post_content )
				wp_update_post( $modified_post );	// Or maybe it is.

			if ( $bcd->custom_fields )
			{
				// Remove all old custom fields.
				$old_custom_fields = get_post_custom( $bcd->new_post[ 'ID' ] );

				foreach( $old_custom_fields as $key => $value )
				{
					// This post has a featured image! Remove it from disk!
					if ( $key == '_thumbnail_id' )
					{
						$thumbnail_post = $value[0];
						wp_delete_post( $thumbnail_post );
					}

					delete_post_meta( $bcd->new_post[ 'ID' ], $key );
				}

				foreach( $bcd->post_custom_fields as $meta_key => $meta_value )
				{
					if ( is_array( $meta_value ) )
					{
						foreach( $meta_value as $single_meta_value )
						{
							$single_meta_value = maybe_unserialize( $single_meta_value );
							add_post_meta( $bcd->new_post[ 'ID' ], $meta_key, $single_meta_value );
						}
					}
					else
					{
						$meta_value = maybe_unserialize( $meta_value );
						add_post_meta( $bcd->new_post[ 'ID' ], $meta_key, $meta_value );
					}
				}

				// Attached files are custom fields... but special custom fields.
				if ( $bcd->has_thumbnail )
				{
					$o = clone( $bcd );
					$o->attachment_data = $bcd->attachment_data[ 'thumbnail' ];

					// Clear the attachment cache for this blog because the featured image could have been copied by the file copy.
					if ( isset( $this->attachment_cache ) )
						$this->attachment_cache->forget( get_current_blog_id() );

					$this->maybe_copy_attachment( $o );
					if ( $o->attachment_id !== false )
						update_post_meta( $bcd->new_post[ 'ID' ], '_thumbnail_id', $o->attachment_id );
				}
			}

			// Sticky behaviour
			$child_post_is_sticky = is_sticky( $bcd->new_post[ 'ID' ] );
			if ( $bcd->post_is_sticky && ! $child_post_is_sticky )
				stick_post( $bcd->new_post[ 'ID' ] );
			if ( ! $bcd->post_is_sticky && $child_post_is_sticky )
				unstick_post( $bcd->new_post[ 'ID' ] );

			if ( $bcd->link )
			{
				$new_post_broadcast_data = $this->get_post_broadcast_data( $bcd->parent_blog_id, $bcd->new_post[ 'ID' ] );
				$new_post_broadcast_data->set_linked_parent( $bcd->parent_blog_id, $bcd->post->ID );
				$this->set_post_broadcast_data( $bcd->current_child_blog_id, $bcd->new_post[ 'ID' ], $new_post_broadcast_data );
			}

			$to_broadcasted_blogs[] = '<a href="' . get_permalink( $bcd->new_post[ 'ID' ] ) . '">' . get_bloginfo( 'name' ) . '</a>';
			$to_broadcasted_blog_details[] = array( 'blog_id' => $bcd->current_child_blog_id, 'post_id' => $bcd->new_post[ 'ID' ], 'inserted' => $need_to_insert_post );

			$action = new actions\broadcasting_before_restore_current_blog;
			$action->broadcasting_data = $bcd;
			$action->apply();

			$child_blog->switch_from();
		}

		// Save the post broadcast data.
		if ( $bcd->link )
			$this->set_post_broadcast_data( $bcd->parent_blog_id, $bcd->post->ID, $broadcast_data );

		$action = new actions\broadcasting_finished;
		$action->broadcasting_data = $bcd;
		$action->apply();

		// Finished broadcasting.
		$this->broadcasting = false;
		$this->broadcasting_data = null;

		$this->load_language();

		$post_url_and_name = '<a href="' . get_permalink( $bcd->post->ID ) . '">' . $bcd->post->post_title. '</a>';
		do_action( 'threewp_activity_monitor_new_activity', [
			'activity_id' => '3broadcast_broadcasted',
			'activity_strings' => array(
				'' => '%user_display_name_with_link% has broadcasted '.$post_url_and_name.' to: ' . implode( ', ', $to_broadcasted_blogs ),
			),
			'activity_details' => $to_broadcasted_blog_details,
		] );

		return $bcd;
	}

	/**
		@brief		Creates a new attachment.
		@details

		The $o object is an extension of Broadcasting_Data and must contain:
		- @i attachment_data An attachment_data object containing the attachmend info.

		@param		object		$o		Options.
		@return		@i int The attachment's new post ID.
		@since		20130530
		@version	20131003
	*/
	private function copy_attachment( $o )
	{
		if ( ! file_exists( $o->attachment_data->filename_path ) )
			return false;

		// Copy the file to the blog's upload directory
		$upload_dir = wp_upload_dir();

		copy( $o->attachment_data->filename_path, $upload_dir[ 'path' ] . '/' . $o->attachment_data->filename_base );

		// And now create the attachment stuff.
		// This is taken almost directly from http://codex.wordpress.org/Function_Reference/wp_insert_attachment
		$wp_filetype = wp_check_filetype( $o->attachment_data->filename_base, null );
		$attachment = [
			'guid' => $upload_dir[ 'url' ] . '/' . $o->attachment_data->filename_base,
			'menu_order' => $o->attachment_data->post->menu_order,
			'post_excerpt' => $o->attachment_data->post->post_excerpt,
			'post_mime_type' => $wp_filetype[ 'type' ],
			'post_title' => $o->attachment_data->post->post_title,
			'post_content' => '',
			'post_status' => 'inherit',
		];
		$o->attachment_id = wp_insert_attachment( $attachment, $upload_dir[ 'path' ] . '/' . $o->attachment_data->filename_base, $o->attachment_data->post->post_parent );

		// Now to maybe handle the metadata.
		if ( $o->attachment_data->file_metadata )
		{
			// 1. Create new metadata for this attachment.
			require_once( ABSPATH . "wp-admin" . '/includes/image.php' );
			$attach_data = wp_generate_attachment_metadata( $o->attachment_id, $upload_dir[ 'path' ] . '/' . $o->attachment_data->filename_base );

			// 2. Write the old metadata first.
			foreach( $o->attachment_data->post_custom as $key => $value )
			{
				$value = reset( $value );
				$value = maybe_unserialize( $value );
				switch( $key )
				{
					// Some values need to handle completely different upload paths (from different months, for example).
					case '_wp_attached_file':
						$value = $attach_data[ 'file' ];
						break;
				}
				update_post_meta( $o->attachment_id, $key, $value );
			}

			// 3. Overwrite the metadata that needs to be overwritten with fresh data.
			wp_update_attachment_metadata( $o->attachment_id,  $attach_data );
		}
	}

	/**
		@brief		Create a meta box for this post.
		@since		20131015
	**/
	public function create_meta_box( $post )
	{
		$meta_box_data = new meta_box\data;
		$meta_box_data->blog_id = get_current_blog_id();
		$meta_box_data->broadcast_data = $this->get_post_broadcast_data( $meta_box_data->blog_id, $post->ID );
		$meta_box_data->form = $this->form2();
		$meta_box_data->post = $post;
		$meta_box_data->post_id = $post->ID;
		return $meta_box_data;
	}

	/**
		Deletes the broadcast data completely of a post in a blog.
	*/
	public function delete_post_broadcast_data( $blog_id, $post_id)
	{
		$this->broadcast_data_cache()->set_for( $blog_id, $post_id, new BroadcastData );
		$this->sql_delete_broadcast_data( $blog_id, $post_id );
	}

	/**
		@brief		Enqueue the JS file.
		@since		20131007
	**/
	public function enqueue_js()
	{
		return;
		if ( isset( $this->_js_enqueued ) )
			return;
		wp_enqueue_script( 'threewp_broadcast', '/' . $this->paths[ 'path_from_base_directory' ] . '/js/user.min.js' );
		$this->_js_enqueued = true;
	}

	private function get_current_blog_taxonomy_terms( $taxonomy )
	{
		$terms = get_terms( $taxonomy, array(
			'hide_empty' => false,
		) );
		$terms = (array) $terms;
		$terms = $this->array_rekey( $terms, 'term_id' );
		return $terms;
	}

	/**
	 * Retrieves the BroadcastData for this post_id.
	 *
	 * Will return a fully functional BroadcastData class even if the post doesn't have BroadcastData.
	 *
	 * Use BroadcastData->is_empty() to check for that.
	 * @param int $post_id Post ID to retrieve data for.
	 */
	public function get_post_broadcast_data( $blog_id, $post_id )
	{
		return $this->broadcast_data_cache()->get_for( $blog_id, $post_id );
	}

	public function is_blog_user_writable( $user_id, $blog )
	{
		// Check that the user has write access.
		$blog->switch_to();

		global $current_user;
		wp_get_current_user();
		$r = current_user_can( 'edit_posts' );

		$blog->switch_from();

		return $r;
	}

	/**
	 * Recursively adds the missing ancestors of the given source term at the
	 * target blog.
	 *
	 * @param array $source_post_term           The term to add ancestors for
	 * @param array $source_post_taxonomy       The taxonomy we're working with
	 * @param array $target_blog_terms          The existing terms at the target
	 * @param array $parent_blog_taxonomy_terms The existing terms at the source
	 * @return int The ID of the target parent term
	 */
	public function insert_term_ancestors( $source_post_term, $source_post_taxonomy, $target_blog_terms, $parent_blog_taxonomy_terms )
	{
		// Fetch the parent of the current term among the source terms
		foreach ( $parent_blog_taxonomy_terms as $term )
		{
			if ( $term[ 'term_id' ] == $source_post_term[ 'parent' ] )
			{
				$source_parent = $term;
			}
		}

		if ( ! isset( $source_parent ) )
		{
			return 0; // Sanity check, the source term's parent doesn't exist! Orphan!
		}

		// Check if the parent already exists at the target
		foreach ( $target_blog_terms as $term )
		{
			if ( $term[ 'slug' ] === $source_parent[ 'slug' ] )
			{
				// The parent already exists, return its ID
				return $term[ 'term_id' ];
			}
		}

		// Does the parent also have a parent, and if so, should we create the parent?
		$target_grandparent_id = 0;
		if ( 0 != $source_parent[ 'parent' ] )
		{
			// Recursively insert ancestors, and get the newly inserted parent's ID
			$target_grandparent_id = $this->insert_term_ancestors( $source_parent, $source_post_taxonomy, $target_blog_terms, $parent_blog_taxonomy_terms );
		}

		// Check if the parent exists at the target grandparent
		$term_id = term_exists( $source_parent[ 'name' ], $source_post_taxonomy, $target_grandparent_id );

		if ( is_null( $term_id ) || 0 == $term_id )
		{
			// The target parent does not exist, we need to create it
			$new_term = wp_insert_term(
				$source_parent[ 'name' ],
				$source_post_taxonomy,
				array(
					'slug'        => $source_parent[ 'slug' ],
					'description' => $source_parent[ 'description' ],
					'parent'      => $target_grandparent_id,
				)
			);

			$term_id = $new_term[ 'term_id' ];
		}
		elseif ( is_array( $term_id ) )
		{
			// The target parent exists and we got an array as response, extract parent id
			$term_id = $term_id[ 'term_id' ];
		}

		return $term_id;
	}

	/**
		@brief		Are we in the middle of a broadcast?
		@return		bool		True if we're broadcasting.
		@since		20130926
	*/
	public function is_broadcasting()
	{
		return $this->broadcasting !== false;
	}

	/**
		@brief		Is this custom field (1) external or (2) underscored, but excepted?
		@details

		Internal fields start with underscore and are generally not interesting to broadcast.

		Some plugins store important information as internal fields and should have their fields broadcasted.

		Documented 20130926.

		@param		string		$custom_field		The name of the custom field to check.
		@return		bool		True if the field is OK to broadcast.
		@since		20130926
	**/
	private function is_custom_field_valid( $custom_field )
	{
		// If the field does not start with an underscore, it is automatically valid.
		if ( strpos( $custom_field, '_' ) !== 0 )
			return true;

		// Has the user requested that all internal fields be broadcasted?
		$broadcast_internal_custom_fields = $this->get_site_option( 'broadcast_internal_custom_fields' );
		if ( $broadcast_internal_custom_fields )
		{
			// Prep the cache.
			if ( ! isset( $this->custom_field_blacklist_cache ) )
				$this->custom_field_blacklist_cache = explode( ' ', $this->get_site_option( 'custom_field_blacklist' ) );

			foreach( $this->custom_field_blacklist_cache as $exception)
				if ( strpos( $custom_field, $exception) !== false )
					return false;

			// Not found in the blacklist. Broadcast the field!
			return true;
		}
		else
		{
			// Prep the cache.
			if ( !isset( $this->custom_field_whitelist_cache ) )
				$this->custom_field_whitelist_cache = explode( ' ', $this->get_site_option( 'custom_field_whitelist' ) );

			foreach( $this->custom_field_whitelist_cache as $exception)
				if ( strpos( $custom_field, $exception) !== false )
					return true;

			// Not found in the whitelist. Do not broadcast.
			return false;
		}
	}

	private function keep_valid_custom_fields( $custom_fields )
	{
		foreach( $custom_fields as $key => $array)
			if ( ! $this->is_custom_field_valid( $key ) )
				unset( $custom_fields[$key] );

		return $custom_fields;
	}

	/**
		@brief		Converts a textarea of lines to a single line of space separated words.
		@param		string		$lines		Multiline string.
		@return		string					All of the lines on one line, minus the empty lines.
		@since		20131004
	**/
	public function lines_to_string( $lines )
	{
		$lines = explode( "\n", $lines );
		$r = [];
		foreach( $lines as $line )
			if ( trim( $line ) != '' )
				$r[] = trim( $line );
		return implode( ' ', $r );
	}

	private function load_last_used_settings( $user_id)
	{
		$data = $this->sql_user_get( $user_id );
		if (!isset( $data[ 'last_used_settings' ] ) )
			$data[ 'last_used_settings' ] = [];
		return $data[ 'last_used_settings' ];
	}

	/**
		@brief		Will only copy the attachment if it doesn't already exist on the target blog.
		@details	The return value is an object, with the most important property being ->attachment_id.

		@param		object		$options		See the parameter for copy_attachment.
	**/
	public function maybe_copy_attachment( $options )
	{
		if ( !isset( $this->attachment_cache ) )
			$this->attachment_cache = new collection;

		$attachment_data = $options->attachment_data;		// Convenience.

		$key = get_current_blog_id();

		$attachment_posts = $this->attachment_cache->get( $key, null );
		if ( $attachment_posts === null )
		{
			$attachment_posts = get_posts( [
				'post_name' => $attachment_data->post->post_name,			// Isn't used, though it should be. Maybe a patch is in order...
				'post_type' => 'attachment',
			] );
			$this->attachment_cache->put( $key, $attachment_posts );
		}

		// Is there an existing media file?
		// Try to find the filename in the GUID.
		foreach( $attachment_posts as $post )
		{
			if ( $post->post_name !== $attachment_data->post->post_name )
				continue;
			// The ID is the important part.
			$options->attachment_id = $post->ID;
			return $options;
		}

		// Since it doesn't exist, copy it.
		$this->copy_attachment( $options );
		return $options;
	}

	private function save_last_used_settings( $user_id, $settings )
	{
		$data = $this->sql_user_get( $user_id );
		$data[ 'last_used_settings' ] = $settings;
		$this->sql_user_set( $user_id, $data );
	}

	/**
	 * Updates / removes the BroadcastData for a post.
	 *
	 * If the BroadcastData->is_empty() then the BroadcastData is removed completely.
	 *
	 * @param int $blog_id Blog ID to update
	 * @param int $post_id Post ID to update
	 * @param BroadcastData $broadcast_data BroadcastData file.
	 */
	public function set_post_broadcast_data( $blog_id, $post_id, $broadcast_data )
	{
		// Update the cache.
		$this->broadcast_data_cache()->set_for( $blog_id, $post_id, $broadcast_data );

		if ( $broadcast_data->is_modified() )
			if ( $broadcast_data->is_empty() )
				$this->sql_delete_broadcast_data( $blog_id, $post_id );
			else
				$this->sql_update_broadcast_data( $blog_id, $post_id, $broadcast_data->getData() );
	}

	/**
		@brief		Syncs the terms of a taxonomy from the parent blog in the BCD to the current blog.
		@details

		Checks the parentage of the terms.

		@param		broadcasting_data		$bcd			The broadcasting data.
		@param		string					$taxonomy		The taxonomy to sync.
		@since		20131004
	**/
	private function sync_terms( $bcd, $taxonomy )
	{
		$source_terms = $bcd->parent_blog_taxonomies[ $taxonomy ][ 'terms' ];
		$target_terms = $this->get_current_blog_taxonomy_terms( $taxonomy );

		// Keep track of which terms we've found.
		$found_targets = [];
		$found_sources = [];

		// First step: find out which of the target terms exist on the source blog
		foreach( $target_terms as $target_term_id => $target_term )
			foreach( $source_terms as $source_term_id => $source_term )
			{
				if ( isset( $found_sources[ $source_term_id ] ) )
					continue;
				if ( $source_term[ 'slug' ] == $target_term[ 'slug' ] )
				{
					$found_targets[ $target_term_id ] = $source_term_id;
					$found_sources[ $source_term_id ] = $target_term_id;
				}
			}

		// Now we know which of the terms on our target blog exist on the source blog.
		// Next step: see if the parents are the same on the target as they are on the source.
		// "Same" meaning pointing to the same slug.
		foreach( $found_targets as $target_term_id => $source_term_id)
		{
			$parent_of_target_term = $target_terms[ $target_term_id ][ 'parent' ];
			$parent_of_equivalent_source_term = $source_terms[ $source_term_id ][ 'parent' ];

			if ( $parent_of_target_term != $parent_of_equivalent_source_term &&
				(isset( $found_sources[ $parent_of_equivalent_source_term ] ) || $parent_of_equivalent_source_term == 0 )
			)
			{
				if ( $parent_of_equivalent_source_term != 0)
					$new_term_parent = $found_sources[ $parent_of_equivalent_source_term ];
				else
					$new_term_parent = 0;
				wp_update_term( $target_term_id, $taxonomy, array(
					'parent' => $new_term_parent,
				) );

				// wp_update_category alone won't work. The "cache" needs to be cleared.
				// see: http://wordpress.org/support/topic/category_children-how-to-recalculate?replies=4
				delete_option( 'category_children' );
			}
		}
	}

	// --------------------------------------------------------------------------------------------
	// ----------------------------------------- SQL
	// --------------------------------------------------------------------------------------------

	/**
	 * Gets the user data.
	 *
	 * Returns an array of user data.
	 */
	public function sql_user_get( $user_id)
	{
		$r = $this->query("SELECT * FROM `".$this->wpdb->base_prefix."_3wp_broadcast` WHERE user_id = '$user_id'");
		$r = @unserialize( base64_decode( $r[0][ 'data' ] ) );		// Unserialize the data column of the first row.
		if ( $r === false)
			$r = [];

		// Merge/append any default values to the user's data.
		return array_merge(array(
			'groups' => [],
		), $r);
	}

	/**
	 * Saves the user data.
	 */
	public function sql_user_set( $user_id, $data)
	{
		$data = serialize( $data);
		$data = base64_encode( $data);
		$this->query("DELETE FROM `".$this->wpdb->base_prefix."_3wp_broadcast` WHERE user_id = '$user_id'");
		$this->query("INSERT INTO `".$this->wpdb->base_prefix."_3wp_broadcast` (user_id, data) VALUES ( '$user_id', '$data' )");
	}

	/**
		@brief		Returns an array of SQL rows for these post_ids.
		@param		int		$blog_id		ID of blog for which to fetch the datas
		@param		mixed	$post_ids		An array of ints or a string signifying which datas to retrieve.
		@return		array					An array of database rows. Each row has a BroadcastData object in the data column.
		@since		20131009
	**/
	public function sql_get_broadcast_datas( $blog_id, $post_ids )
	{
		if ( ! is_array( $post_ids ) )
			$post_ids = [ $post_ids ];

		$query = sprintf( "SELECT * FROM `%s` WHERE `blog_id` = '%s' AND `post_id` IN ('%s')",
			$this->wpdb->base_prefix . '_3wp_broadcast_broadcastdata',
			$blog_id,
			implode( "', '", $post_ids )
		);
		$results = $this->query( $query );
		foreach( $results as $index => $result )
		{
			$data = @ unserialize( base64_decode( $result[ 'data' ] ) );
			if ( ! $data )
				$data = new BroadcastData;
			else
				$data = new BroadcastData( $data );
			$results[ $index ][ 'data' ] = $data;
		}
		return $results;
	}

	public function sql_delete_broadcast_data( $blog_id, $post_id )
	{
		$this->query("DELETE FROM `".$this->wpdb->base_prefix."_3wp_broadcast_broadcastdata` WHERE blog_id = '$blog_id' AND post_id = '$post_id'");
	}

	public function sql_update_broadcast_data( $blog_id, $post_id, $data )
	{
		$data = serialize( $data);
		$data = base64_encode( $data);
		$this->sql_delete_broadcast_data( $blog_id, $post_id );
		$this->query("INSERT INTO `".$this->wpdb->base_prefix."_3wp_broadcast_broadcastdata` (blog_id, post_id, data) VALUES ( '$blog_id', '$post_id', '$data' )");
	}

}

$threewp_broadcast = new ThreeWP_Broadcast();
