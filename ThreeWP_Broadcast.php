<?php
/*
Author:			edward_plainview
Author Email:	edward@plainview.se
Author URI:		http://www.plainview.se
Description:	Broadcast / multipost a post, with attachments, custom fields, tags and other taxonomies to other blogs in the network.
Plugin Name:	ThreeWP Broadcast
Plugin URI:		http://plainview.se/wordpress/threewp-broadcast/
Version:		2.21
*/

namespace threewp_broadcast;

if ( ! class_exists( '\\threewp_broadcast\\base' ) )	require_once( __DIR__ . '/ThreeWP_Broadcast_Base.php' );

require_once( 'include/vendor/autoload.php' );

use \plainview\sdk\collections\collection;
use \threewp_broadcast\broadcast_data\blog;
use \plainview\sdk\html\div;

class ThreeWP_Broadcast
	extends \threewp_broadcast\ThreeWP_Broadcast_Base
{
	/**
		@brief		Broadcasting stack.
		@details

		An array of broadcasting_data objects, the latest being at the end.

		@since		20131120
	**/
	private $broadcasting = [];

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
	public $display_broadcast_meta_box = true;

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

	public $plugin_version = 2.21;

	protected $sdk_version_required = 20130505;		// add_action / add_filter

	protected $site_options = array(
		'blogs_to_hide' => 5,								// How many blogs to auto-hide
		'broadcast_internal_custom_fields' => false,		// Broadcast internal custom fields?
		'canonical_url' => true,							// Override the canonical URLs with the parent post's.
		'clear_post' => true,								// Clear the post before broadcasting.
		'custom_field_whitelist' => '_wp_page_template _wplp_ _aioseop_',				// Internal custom fields that should be broadcasted.
		'custom_field_blacklist' => '',						// Internal custom fields that should not be broadcasted.
		'database_version' => 0,							// Version of database and settings
		'debug' => false,									// Display debug information
		'debug_ips' => '',									// List of IP addresses that can see debug information, when enabled.
		'save_post_priority' => 640,						// Priority of save_post action. Higher = lets other plugins do their stuff first
		'override_child_permalinks' => false,				// Make the child's permalinks link back to the parent item?
		'post_types' => 'post page',						// Custom post types which use broadcasting
		'existing_attachments' => 'use',					// What to do with existing attachments: use, overwrite, randomize
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
		$this->add_filter( 'threewp_broadcast_broadcast_post' );
		$this->add_filter( 'threewp_broadcast_get_user_writable_blogs', 11 );		// Allow other plugins to do this first.
		$this->add_filter( 'threewp_broadcast_get_post_types', 9 );					// Add our custom post types to the array of broadcastable post types.
		$this->add_action( 'threewp_broadcast_manage_posts_custom_column', 9 );		// Just before the standard 10.
		$this->add_action( 'threewp_broadcast_maybe_clear_post', 11 );
		$this->add_action( 'threewp_broadcast_menu', 9 );
		$this->add_action( 'threewp_broadcast_menu', 'threewp_broadcast_menu_final', 100 );
		$this->add_action( 'threewp_broadcast_prepare_broadcasting_data' );
		$this->add_filter( 'threewp_broadcast_prepare_meta_box', 9 );
		$this->add_filter( 'threewp_broadcast_prepare_meta_box', 'threewp_broadcast_prepared_meta_box', 100 );
		$this->add_action( 'threewp_broadcast_wp_insert_term', 9 );
		$this->add_action( 'threewp_broadcast_wp_update_term', 9 );

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
		wp_enqueue_style( 'threewp_broadcast', $this->paths[ 'url' ] . '/css/css.scss.min.css', '', $this->plugin_version  );
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

			$this->query("CREATE TABLE IF NOT EXISTS `". $this->broadcast_data_table() . "` (
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

		if ( $db_ver < 5 )
		{
			$this->create_broadcast_data_id_column();
			$db_ver = 5;
		}

		$this->update_site_option( 'database_version', $db_ver );
	}

	public function uninstall()
	{
		$this->query("DROP TABLE `".$this->wpdb->base_prefix."_3wp_broadcast`");
		$query = sprintf( "DROP TABLE `%s`", $this->broadcast_data_table() );
		$this->query( $query );
	}

	// --------------------------------------------------------------------------------------------
	// ----------------------------------------- Admin
	// --------------------------------------------------------------------------------------------

	/**
		@brief		Show maintenance options.
		@since		20131107
	**/
	public function admin_menu_maintenance()
	{
		$maintenance = new maintenance\controller;
		echo $maintenance;
	}

	public function admin_menu_post_types()
	{
		$form = $this->form2();
		$r = '';

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

		$r .= $this->p_( 'Custom post types must be specified using their internal Wordpress names with a space between each. It is not possible to automatically make a list of available post types on the whole network because of a limitation within Wordpress (the current blog knows only of its own custom post types).' );

		$blog_post_types = get_post_types();
		$blog_post_types = array_keys( $blog_post_types );
		$r .= $this->p_( 'The custom post types registered on <em>this</em> blog are: <code>%s</code>', implode( ', ', $blog_post_types ) );

		$r .= $form->open_tag();
		$r .= $form->display_form_table();
		$r .= $form->close_tag();
		echo $r;
	}

	public function admin_menu_premium_pack_info()
	{
		$r = '';
		$r .= $this->html_css();
		$contents = file_get_contents( __DIR__ . '/html/premium_pack_info.html' );
		$r .= $this->wrap( $contents, $this->_( 'ThreeWP Broadcast Premium Pack info' ) );
		echo $r;
	}

	public function admin_menu_settings()
	{
		$this->enqueue_js();
		$form = $this->form2();
		$form->id( 'broadcast_settings' );
		$r = '';
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
			->description_( "Child posts have their canonical URLs pointed to the URL of the parent post. This automatically disables the canonical URL from Yoast's Wordpress SEO plugin." )
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

		$clear_post = $fs->checkbox( 'clear_post' )
			->description_( 'The POST PHP variable is data sent when updating posts. Most plugins are fine if the POST is cleared before broadcasting, while others require that the data remains intact. Uncheck this setting if you notice that child posts are not being treated the same on the child blogs as they are on the parent blog.' )
			->label_( 'Clear POST' )
			->checked( $this->get_site_option( 'debug', false ) );

		$save_post_priority = $fs->number( 'save_post_priority' )
			->description_( 'The priority for the save_post hook. Should be after all other plugins have finished modifying the post. Default is 640.' )
			->label_( 'save_post priority' )
			->min( 1 )
			->required()
			->size( 5, 5 )
			->value( $this->get_site_option( 'save_post_priority' ) );

		$blogs_to_hide = $fs->number( 'blogs_to_hide' )
			->description_( 'In the broadcast meta box, after how many blogs the list should be auto-hidden.' )
			->label_( 'Blogs to hide' )
			->min( 1 )
			->required()
			->size( 3, 3 )
			->value( $this->get_site_option( 'blogs_to_hide' ) );

		$existing_attachments = $fs->select( 'existing_attachments' )
			->description_( 'Action to take when attachments with the same filename already exist on the child blog.' )
			->label_( 'Existing attachments' )
			->option( 'Use the existing attachment on the child blog', 'use' )
			->option( 'Overwrite the attachment', 'overwrite' )
			->option( 'Create a new attachment with a randomized suffix', 'randomize' )
			->required()
			->value( $this->get_site_option( 'existing_attachments', 'use' ) );

		$fs = $form->fieldset( 'debug' )
			->label_( 'Debugging' );

		$fs->markup( 'debug_info' )
			->p_( "According to the settings below, you are currently%s in debug mode. Don't forget to reload this page after saving the settings.", $this->debugging() ? '' : ' <strong>not</strong>' );

		$debug = $fs->checkbox( 'debug' )
			->description_( 'Show debugging information in various places.' )
			->label_( 'Enable debugging' )
			->checked( $this->get_site_option( 'debug', false ) );

		$debug_ips = $fs->textarea( 'debug_ips' )
			->description_( 'Only show debugging info to specific IP addresses. Use spaces between IPs. You can also specify part of an IP address. Your address is %s', $_SERVER[ 'REMOTE_ADDR' ] )
			->label_( 'Debug IPs' )
			->rows( 5, 16 )
			->trim()
			->value( $this->get_site_option( 'debug_ips', '' ) );

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

			$this->update_site_option( 'clear_post', $clear_post->is_checked() );
			$this->update_site_option( 'save_post_priority', $save_post_priority->get_post_value() );
			$this->update_site_option( 'blogs_to_hide', $blogs_to_hide->get_post_value() );
			$this->update_site_option( 'existing_attachments', $existing_attachments->get_post_value() );

			$this->update_site_option( 'debug', $debug->is_checked() );
			$this->update_site_option( 'debug_ips', $debug_ips->get_filtered_post_value() );

			$this->message( 'Options saved!' );
		}

		$r .= $form->open_tag();
		$r .= $form->display_form_table();
		$r .= $form->close_tag();

		echo $r;
	}

	public function admin_menu_tabs()
	{
		$this->load_language();

		$tabs = $this->tabs();
		$tabs->tab( 'settings' )		->callback_this( 'admin_menu_settings' )		->name_( 'Settings' );
		$tabs->tab( 'maintenance' )		->callback_this( 'admin_menu_maintenance' )		->name_( 'Maintenance' );
		$tabs->tab( 'post_types' )		->callback_this( 'admin_menu_post_types' )		->name_( 'Custom post types' );
		$tabs->tab( 'uninstall' )		->callback_this( 'admin_uninstall' )			->name_( 'Uninstall' );

		echo $tabs;
	}

	public function broadcast_menu_tabs()
	{
		$this->load_language();

		$tabs = $this->tabs()
			->default_tab( 'user_broadcast_info' )
			->get_key( 'action' );

		if ( isset( $_GET[ 'action' ] ) )
		{
			switch( $_GET[ 'action' ] )
			{
				case 'user_delete':
					$tabs->tab( 'user_delete' )
						->heading_( 'Delete the child post' )
						->name_( 'Delete child' );
					break;
				case 'user_delete_all':
					$tabs->tab( 'user_delete_all' )
						->heading_( 'Delete all child posts' )
						->name_( 'Delete all children' );
					break;
				case 'user_find_orphans':
					$tabs->tab( 'user_find_orphans' )
						->heading_( 'Find orphans' )
						->name_( 'Find orphans' );
					break;
				case 'user_restore':
					$tabs->tab( 'user_restore' )
						->heading_( 'Restore the child post from the trash' )
						->name_( 'Restore child' );
					break;
				case 'user_restore_all':
					$tabs->tab( 'user_restore_all' )
						->heading_( 'Restore all of the child posts from the trash' )
						->name_( 'Restore all' );
					break;
				case 'user_trash':
					$tabs->tab( 'user_trash' )
						->heading_( 'Trash the child post' )
						->name_( 'Trash child' );
					break;
				case 'user_trash_all':
					$tabs->tab( 'user_trash_all' )
						->heading_( 'Trash all child posts' )
						->name_( 'Trash all children' );
					break;
				case 'user_unlink':
					$tabs->tab( 'user_unlink' )
						->heading_( 'Unlink the child post' )
						->name_( 'Unlink child' );
					break;
				case 'user_unlink_all':
					$tab = $tabs->tab( 'user_unlink_all' )
						->callback_this( 'user_unlink' )
						->heading_( 'Unlink all child posts' )
						->name_( 'Unlink all children' );
					break;
			}
		}

		$tabs->tab( 'user_broadcast_info' )->name_( 'Broadcast information' );

		$action = new actions\broadcast_menu_tabs();
		$action->tabs = $tabs;
		$action->apply();

		echo $tabs;
	}

	/**
		Deletes a broadcasted post.
	**/
	public function user_delete()
	{
		// Nonce check
		global $blog_id;
		$nonce = $_GET[ '_wpnonce' ];
		$post_id = $_GET[ 'post' ];
		$child_blog_id = $_GET[ 'child' ];

		// Generate the nonce key to check against.
		$nonce_key = 'broadcast_delete';
		$nonce_key .= '_' . $child_blog_id;
		$nonce_key .= '_' . $post_id;

		if ( ! wp_verify_nonce( $nonce, $nonce_key) )
			die( __method__ . " security check failed." );

		$broadcast_data = $this->get_post_broadcast_data( $blog_id, $post_id );

		switch_to_blog( $child_blog_id );
		$broadcasted_post_id = $broadcast_data->get_linked_child_on_this_blog();

		if ( $broadcasted_post_id === null )
			wp_die( 'No broadcasted child post found on this blog!' );
		wp_delete_post( $broadcasted_post_id, true );
		$broadcast_data->remove_linked_child( $child_blog_id );

		restore_current_blog();

		$broadcast_data = $this->set_post_broadcast_data( $blog_id, $post_id, $broadcast_data );

		$message = $this->_( 'The child post has been deleted.' );

		echo $this->message( $message);
		echo sprintf( '<p><a href="%s">%s</a></p>',
			wp_get_referer(),
			$this->_( 'Back to post overview' )
		);
	}

	/**
		@brief		Deletes all of a post's children.
		@since		20131031
	**/
	public function user_delete_all()
	{
		// Nonce check
		global $blog_id;
		$nonce = $_GET[ '_wpnonce' ];
		$post_id = $_GET[ 'post' ];

		// Generate the nonce key to check against.
		$nonce_key = 'broadcast_delete_all';
		$nonce_key .= '_' . $post_id;

		if ( ! wp_verify_nonce( $nonce, $nonce_key) )
			die( __method__ . " security check failed." );

		$broadcast_data = $this->get_post_broadcast_data( $blog_id, $post_id );
		foreach( $broadcast_data->get_linked_children() as $child_blog_id => $child_post_id )
		{
			switch_to_blog( $child_blog_id );
			wp_delete_post( $child_post_id, true );
			$broadcast_data->remove_linked_child( $child_blog_id );
			restore_current_blog();
		}

		$broadcast_data = $this->set_post_broadcast_data( $blog_id, $post_id, $broadcast_data );

		$message = $this->_( "All of the child posts have been deleted." );

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
		$nonce_key = 'broadcast_find_orphans_' . $post_id;

		if ( ! wp_verify_nonce( $nonce, $nonce_key) )
			die( __method__ . " security check failed." );

		$form = $this->form2();
		$post = get_post( $post_id );
		$r = '';
		$table = $this->table();

		$row = $table->head()->row();
		$table->bulk_actions()
			->form( $form )
			->add( $this->_( 'Create link' ), 'create_link' )
			->cb( $row );
		$row->th()->text_( 'Domain' );

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

			$blog->switch_to();

			$args = array(
				'cache_results' => false,
				'name' => $post->post_name,
				'numberposts' => 2,
				'post_type'=> $post->post_type,
				'post_status' => $post->post_status,
			);
			$posts = get_posts( $args );

			if ( count( $posts ) == 1 )
			{
				$orphan = reset( $posts );
				$orphan->permalink = get_permalink( $orphan->ID );
				$orphans[ $blog->id ] = $orphan;
			}

			$blog->switch_from();
		}

		if ( $form->is_posting() )
		{
			$form->post();
			if ( $table->bulk_actions()->pressed() )
			{
				switch ( $table->bulk_actions()->get_action() )
				{
					case 'create_link':
						$ids = $table->bulk_actions()->get_rows();

						foreach( $orphans as $blog_id => $orphan )
						{
							$bulk_id = sprintf( '%s_%s', $blog_id, $orphan->ID );
							if ( ! in_array( $bulk_id, $ids ) )
								continue;

							$broadcast_data->add_linked_child( $blog_id, $orphan->ID );
							unset( $orphans[ $blog_id ] );		// There can only be one orphan per blog, so we're not interested in the blog anymore.

							// Update the child's broadcast data.
							$child_broadcast_data = $this->get_post_broadcast_data( $blog_id, $orphan->ID );
							$child_broadcast_data->set_linked_parent( $current_blog_id, $post_id );
							$this->set_post_broadcast_data( $blog_id, $orphan->ID, $child_broadcast_data );
						}

						// Update the broadcast data for the parent post.
						$this->set_post_broadcast_data( $current_blog_id, $post_id, $broadcast_data );
						echo $this->message_( 'The selected children were linked!' );
					break;
				}
			}
		}

		if ( count( $orphans ) < 1 )
		{
			$r .= $this->_( 'No possible child posts were found on the other blogs you have write access to. Either there are no posts with the same title as this one, or all possible orphans have already been linked.' );
		}
		else
		{
			foreach( $orphans as $blog_id => $orphan )
			{
				$row = $table->body()->row();
				$bulk_id = sprintf( '%s_%s', $blog_id, $orphan->ID );
				$table->bulk_actions()->cb( $row, $bulk_id );
				$row->td()->text( '<a href="' . $orphan->permalink . '">' . $blogs[ $blog_id ]->blogname . '</a>' );
			}
			$r .= $form->open_tag();
			$r .= $table;
			$r .= $form->close_tag();
		}

		echo $r;

		echo '<p><a href="edit.php?post_type='.$post->post_type.'">Back to post overview</a></p>';
	}

	public function user_broadcast_info()
	{
		$table = $this->table();
		$table->caption()->text( 'Information' );

		$row = $table->head()->row();
		$row->th()->text( 'Key' );
		$row->th()->text( 'Value' );

		if ( $this->debugging() )
		{
			// Debug
			$row = $table->body()->row();
			$row->td()->text( 'Debugging' );
			$row->td()->text( 'Enabled' );
		}

		// Broadcast version
		$row = $table->body()->row();
		$row->td()->text( 'Broadcast version' );
		$row->td()->text( $this->plugin_version );

		// PHP version
		$row = $table->body()->row();
		$row->td()->text( 'PHP version' );
		$row->td()->text( phpversion() );

		// SDK version
		$row = $table->body()->row();
		$text = sprintf( '%sPlainview Wordpress SDK%s',
			'<a href="https://github.com/the-plainview/sdk">',
			'</a>'
		);
		$row->td()->text( $text );
		$object = new \ReflectionObject( new \plainview\sdk\wordpress\base );
		$row->td()->text( $this->sdk_version );

		// SDK path
		$row = $table->body()->row();
		$row->td()->text( 'Plainview Wordpress SDK path' );
		$object = new \ReflectionObject( new \plainview\sdk\wordpress\base );
		$row->td()->text( $object->getFilename() );

		// PHP maximum execution time
		$row = $table->body()->row();
		$row->td()->text( 'PHP maximum execution time' );
		$text = sprintf( '%s seconds', ini_get ( 'max_execution_time' ) );
		$row->td()->text( $text );

		// PHP maximum memory limit
		$row = $table->body()->row();
		$row->td()->text( 'PHP memory limit' );
		$text = ini_get( 'memory_limit' );
		$row->td()->text( $text );

		// WP maximum memory limit
		$row = $table->body()->row();
		$row->td()->text( 'Wordpress memory limit' );
		$text = $this->p( WP_MEMORY_LIMIT . "

This can be increased by adding the following to your wp-config.php:

<code>define('WP_MEMORY_LIMIT', '512M');</code>
" );
		$row->td()->text( $text );

		// Debug info
		$row = $table->body()->row();
		$row->td()->text( 'Debug code' );
		$text = WP_MEMORY_LIMIT;
		$text = $this->p( "Add the following lines to your wp-config.php to help find out why errors or blank screens are occurring:

<code>ini_set('display_errors','On');</code>
<code>define('WP_DEBUG', true);</code>
" );
		$row->td()->text( $text );

		echo $table;
	}

	/**
		@brief		Restores a trashed post.
		@since		20131031
	**/
	public function user_restore()
	{
		// Nonce check
		global $blog_id;
		$nonce = $_GET[ '_wpnonce' ];
		$post_id = $_GET[ 'post' ];
		$child_blog_id = $_GET[ 'child' ];

		// Generate the nonce key to check against.
		$nonce_key = 'broadcast_restore';
		$nonce_key .= '_' . $child_blog_id;
		$nonce_key .= '_' . $post_id;

		if ( ! wp_verify_nonce( $nonce, $nonce_key) )
			die( __method__ . " security check failed." );

		$broadcast_data = $this->get_post_broadcast_data( $blog_id, $post_id );

		switch_to_blog( $child_blog_id );

		$child_post_id = $broadcast_data->get_linked_child_on_this_blog();
		wp_publish_post( $child_post_id );

		restore_current_blog();

		$message = $this->_( 'The child post has been restored.' );

		echo $this->message( $message);
		echo sprintf( '<p><a href="%s">%s</a></p>',
			wp_get_referer(),
			$this->_( 'Back to post overview' )
		);
	}

	/**
		@brief		Restores all of the children from the trash.
		@since		20131031
	**/
	public function user_restore_all()
	{
		// Nonce check
		global $blog_id;
		$nonce = $_GET[ '_wpnonce' ];
		$post_id = $_GET[ 'post' ];

		// Generate the nonce key to check against.
		$nonce_key = 'broadcast_restore_all';
		$nonce_key .= '_' . $post_id;

		if ( ! wp_verify_nonce( $nonce, $nonce_key) )
			die( __method__ . " security check failed." );

		$broadcast_data = $this->get_post_broadcast_data( $blog_id, $post_id );
		foreach( $broadcast_data->get_linked_children() as $child_blog_id => $child_post_id )
		{
			switch_to_blog( $child_blog_id );
			wp_publish_post( $child_post_id );
			restore_current_blog();
		}

		$message = $this->_( 'The child posts have been restored.' );

		echo $this->message( $message);
		echo sprintf( '<p><a href="%s">%s</a></p>',
			wp_get_referer(),
			$this->_( 'Back to post overview' )
		);
	}

	/**
		Trashes a broadcasted post.
	**/
	public function user_trash()
	{
		// Nonce check
		global $blog_id;
		$nonce = $_GET[ '_wpnonce' ];
		$post_id = $_GET[ 'post' ];
		$child_blog_id = $_GET[ 'child' ];

		// Generate the nonce key to check against.
		$nonce_key = 'broadcast_trash';
		$nonce_key .= '_' . $child_blog_id;
		$nonce_key .= '_' . $post_id;

		if ( ! wp_verify_nonce( $nonce, $nonce_key) )
			die( __method__ . " security check failed." );

		$broadcast_data = $this->get_post_broadcast_data( $blog_id, $post_id );
		switch_to_blog( $child_blog_id );
		$broadcasted_post_id = $broadcast_data->get_linked_child_on_this_blog();
		wp_trash_post( $broadcasted_post_id );
		restore_current_blog();

		$message = $this->_( 'The broadcasted child post has been put in the trash.' );

		echo $this->message( $message);
		echo sprintf( '<p><a href="%s">%s</a></p>',
			wp_get_referer(),
			$this->_( 'Back to post overview' )
		);
	}

	/**
		Trashes a broadcasted post.
	**/
	public function user_trash_all()
	{
		// Nonce check
		global $blog_id;
		$nonce = $_GET[ '_wpnonce' ];
		$post_id = $_GET[ 'post' ];

		// Generate the nonce key to check against.
		$nonce_key = 'broadcast_trash_all';
		$nonce_key .= '_' . $post_id;

		if ( ! wp_verify_nonce( $nonce, $nonce_key) )
			die( __method__ . " security check failed." );

		$broadcast_data = $this->get_post_broadcast_data( $blog_id, $post_id );
		foreach( $broadcast_data->get_linked_children() as $child_blog_id => $child_post_id )
		{
			switch_to_blog( $child_blog_id );
			wp_trash_post( $child_post_id );
			restore_current_blog();
		}

		$message = $this->_( 'The child posts have been put in the trash.' );

		echo $this->message( $message);
		echo sprintf( '<p><a href="%s">%s</a></p>',
			wp_get_referer(),
			$this->_( 'Back to post overview' )
		);
	}

	public function user_unlink()
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
		else
			$nonce_key .= '_all';
		$nonce_key .= '_' . $post_id;

		if ( ! wp_verify_nonce( $nonce, $nonce_key) )
			die( __method__ . " security check failed." );

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
			$action = new actions\get_post_types;
			$action->apply();
			foreach( $action->post_types as $post_type )
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
		{
			// If the user is debugging, show the box anyway.
			if ( ! $this->debugging() )
				$this->display_broadcast_meta_box = false;
		}

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

		if ( isset( $this->_is_getting_permalink ) )
			return $link;

		$this->_is_getting_permalink = true;

		$blog_id = get_current_blog_id();

		// Have we already checked this post ID for a link?
		$key = 'b' . $blog_id . '_p' . $post->ID;
		if ( property_exists( $this->permalink_cache, $key ) )
		{
			unset( $this->_is_getting_permalink );
			return $this->permalink_cache->$key;
		}

		$broadcast_data = $this->get_post_broadcast_data( $blog_id, $post->ID );

		$linked_parent = $broadcast_data->get_linked_parent();

		if ( $linked_parent === false)
		{
			$this->permalink_cache->$key = $link;
			unset( $this->_is_getting_permalink );
			return $link;
		}

		switch_to_blog( $linked_parent[ 'blog_id' ] );
		$post = get_post( $linked_parent[ 'post_id' ] );
		$permalink = get_permalink( $post );
		restore_current_blog();

		$this->permalink_cache->$key = $permalink;

		unset( $this->_is_getting_permalink );
		return $permalink;
	}

	public function post_row_actions( $actions, $post )
	{
		$this->broadcast_data_cache()->expect_from_wp_query();

		$broadcast_data = $this->broadcast_data_cache()->get_for( get_current_blog_id(), $post->ID );

		if ( $broadcast_data->get_linked_parent() === false )
		{
			$url = sprintf( 'admin.php?page=threewp_broadcast&amp;action=user_find_orphans&amp;post=%s', $post->ID );
			$url = wp_nonce_url( $url, 'broadcast_find_orphans_' . $post->ID );
			$actions[ 'broadcast_find_orphans' ] =
				sprintf( '<a href="%s" title="%s">%s</a>',
					$url ,
					$this->_( 'Find posts on other blogs that are identical to this post' ),
					$this->_( 'Find orphans' )
				);
		}
		return $actions;
	}

	public function save_post( $post_id )
	{
		// Loop check.
		if ( $this->is_broadcasting() )
			return;

		// No post?
		if ( count( $_POST ) < 1 )
			return;

		// Nothing of interest in the post?
		if ( ! isset( $_POST[ 'broadcast' ] ) )
			return;

		// Is this post a child?
		$broadcast_data = $this->get_post_broadcast_data( get_current_blog_id(), $post_id );
		if ( $broadcast_data->get_linked_parent() !== false )
			return;

		// No permission.
		if ( ! $this->role_at_least( $this->get_site_option( 'role_broadcast' ) ) )
			return;

		// Save the user's last settings.
		if ( isset( $_POST[ 'broadcast' ] ) )
			$this->save_last_used_settings( $this->user_id(), $_POST[ 'broadcast' ] );

		$this->debug( 'We are currently on blog %s (%s).', get_bloginfo( 'blogname' ), get_current_blog_id() );

		$post = get_post( $post_id );

		$meta_box_data = $this->create_meta_box( $post );

		// Allow plugins to modify the meta box with their own info.
		$action = new actions\prepare_meta_box;
		$action->meta_box_data = $meta_box_data;
		$action->apply();

		// Post the form.
		if ( ! $meta_box_data->form->has_posted )
		{
			$meta_box_data->form->post();
			$meta_box_data->form->use_post_values();
		}

		$broadcasting_data = new broadcasting_data( [
			'_POST' => $_POST,
			'meta_box_data' => $meta_box_data,
			'parent_blog_id' => get_current_blog_id(),
			'parent_post_id' => $post_id,
			'post' => $post,
			'upload_dir' => wp_upload_dir(),
		] );

		$action = new actions\prepare_broadcasting_data;
		$action->broadcasting_data = $broadcasting_data;
		$action->apply();

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
			wp_enqueue_style( $key, $value, '', $this->plugin_version );
		foreach( $meta_box_data->js as $key => $value )
			wp_enqueue_script( $key, $value, '', $this->plugin_version );

		echo $meta_box_data->html->render();
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
		$meta_box_data = $action->meta_box_data;	// Convenience.

		if ( $this->debugging() )
			$meta_box_data->html->put( 'debug', $this->p_( 'Broadcast is in debug mode. More information than usual will be shown.' ) );

		if ( $action->is_applied() )
		{
			if ( $this->debugging() )
				$meta_box_data->html->put( 'debug_applied', $this->p_( 'Broadcast is not preparing the meta box because it has already been applied.' ) );
			return;
		}

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

		$meta_box_data->last_used_settings = $this->load_last_used_settings( $this->user_id() );

		$post_type = $meta_box_data->post->post_type;
		$post_type_object = get_post_type_object( $post_type );
		$post_type_supports_thumbnails = post_type_supports( $post_type, 'thumbnail' );
		$post_type_is_hierarchical = $post_type_object->hierarchical;

		// 20140327 Because so many plugins create broken post types, assume that all post types support custom fields.
		// $post_type_supports_custom_fields = post_type_supports( $post_type, 'custom-fields' );
		$post_type_supports_custom_fields = true;

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
				->checked( isset( $meta_box_data->last_used_settings[ 'custom_fields' ] ) )
				->label_( 'Custom fields' )
				->title( 'Broadcast all the custom fields and the featured image?' );
			$meta_box_data->html->put( 'custom_fields', '' );
		}

		if ( is_super_admin() || $this->role_at_least( $this->get_site_option( 'role_taxonomies' ) ) )
		{
			$taxonomies_input = $form->checkbox( 'taxonomies' )
				->checked( isset( $meta_box_data->last_used_settings[ 'taxonomies' ] ) )
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
			->css_class( 'blogs checkboxes' )
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
			$input_name = 'blogs_' . $blog->id;
			$option = $blogs_input->input( $input_name );
			$option->get_label()->content = $form::unfilter_text( $blog->blogname );
			$option->css_class( 'blog ' . $blog->id );
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

		$js = sprintf( '<script type="">var broadcast_blogs_to_hide = %s;</script>', $this->get_site_option( 'blogs_to_hide', 5 ) );
		$meta_box_data->html->put( 'blogs_js', $js );

		// We require some js.
		$meta_box_data->js->put( 'threewp_broadcast', $this->paths[ 'url' ] . '/js/user.min.js' );
		// And some CSS
		$meta_box_data->css->put( 'threewp_broadcast', $this->paths[ 'url' ] . '/css/css.scss.min.css'  );

		if ( $this->debugging() )
		{
			$meta_box_data->html->put( 'debug_info_1', sprintf( '
				<h3>Debug info</h3>
				<ul>
				<li>High enough role to link: %s</li>
				<li>Post supports custom fields: %s</li>
				<li>Post supports thumbnails: %s</li>
				<li>High enough role to broadcast custom fields: %s</li>
				<li>High enough role to broadcast taxonomies: %s</li>
				<li>Blogs available to user: %s</li>
				</ul>',
					( $this->role_at_least( $this->get_site_option( 'role_link' ) ) ? 'yes' : 'no' ),
					( $post_type_supports_custom_fields ? 'yes' : 'no' ),
					( $post_type_supports_thumbnails ? 'yes' : 'no' ),
					( $this->role_at_least( $this->get_site_option( 'role_custom_fields' ) ) ? 'yes' : 'no' ),
					( $this->role_at_least( $this->get_site_option( 'role_taxonomies' ) ) ? 'yes' : 'no' ),
					count( $blogs )
				)
			);

			// Display a list of actions that have hooked into save_post
			global $wp_filter;
			$filters = $wp_filter[ 'save_post' ];
			ksort( $filters );
			$save_post_callbacks = [];
			//$wp_filter[$tag][$priority][$idx] = array('function' => $function_to_add, 'accepted_args' => $accepted_args);
			foreach( $filters as $priority => $callbacks )
			{
				foreach( $callbacks as $callback )
				{
					$function = $callback[ 'function' ];
					if ( is_array( $function ) )
					{
						if ( is_object( $function[ 0 ] ) )
							$function[ 0 ] = get_class( $function[ 0 ] );
						$function = sprintf( '%s::%s', $function[ 0 ], $function[ 1 ] );
					}
					$function = sprintf( '%s %s', $function, $priority );
					$save_post_callbacks[] = $function;
				}
			}
			$meta_box_data->html->put( 'debug_save_post_callbacks', sprintf( '%s%s',
				$this->p_( 'Plugins that have hooked into save_post:' ),
				$this->implode_html( $save_post_callbacks )
			) );
		}

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
		return $filter;
	}

	/**
		@brief		Convert the post_type site option to an array in the action.
		@since		2014-02-22 10:33:57
	**/
	public function threewp_broadcast_get_post_types( $action )
	{
		$post_types = $this->get_site_option( 'post_types' );
		$post_types = explode( ' ', $post_types );
		foreach( $post_types as $post_type )
			$action->post_types[ $post_type ] = $post_type;
	}

	/**
		@brief		Handle the display of the custom column.
		@since		2014-04-18 08:30:19
	**/
	public function threewp_broadcast_manage_posts_custom_column( $filter )
	{
		if ( $filter->broadcast_data->get_linked_parent() !== false)
		{
			$parent = $filter->broadcast_data->get_linked_parent();
			$parent_blog_id = $parent[ 'blog_id' ];
			switch_to_blog( $parent_blog_id );

			$html = $this->_(sprintf( 'Linked from %s', '<a href="' . get_bloginfo( 'url' ) . '/wp-admin/post.php?post=' .$parent[ 'post_id' ] . '&action=edit">' . get_bloginfo( 'name' ) . '</a>' ) );
			$filter->html->put( 'linked_from', $html );
			restore_current_blog();
		}
		elseif ( $filter->broadcast_data->has_linked_children() )
		{
			$children = $filter->broadcast_data->get_linked_children();

			if ( count( $children ) > 0 )
			{
				// Only display if there is more than one child post
				if ( count( $children ) > 1 )
				{
					$strings = new \threewp_broadcast\collections\strings_with_metadata;

					$strings->set( 'div_open', '<div class="row-actions broadcasted_blog_actions">' );
					$strings->set( 'text_all', $this->_( 'All' ) );
					$strings->set( 'div_small_open', '<small>' );

					$url = sprintf( "admin.php?page=threewp_broadcast&amp;action=user_restore_all&amp;post=%s", $filter->parent_post_id );
					$url = wp_nonce_url( $url, 'broadcast_restore_all_' . $filter->parent_post_id );
					$strings->set( 'restore_all_separator', ' | ' );
					$strings->set( 'restore_all', sprintf( '<a href="%s" title="%s">%s</a>',
						$url,
						$this->_( 'Restore all of the children from the trash' ),
						$this->_( 'Restore' )
					) );

					$url = sprintf( "admin.php?page=threewp_broadcast&amp;action=user_trash_all&amp;post=%s", $filter->parent_post_id );
					$url = wp_nonce_url( $url, 'broadcast_trash_all_' . $filter->parent_post_id );
					$strings->set( 'trash_all_separator', ' | ' );
					$strings->set( 'trash_all', sprintf( '<a href="%s" title="%s">%s</a>',
						$url,
						$this->_( 'Put all of the children in the trash' ),
						$this->_( 'Trash' )
					) );

					$url_unlink_all = sprintf( "admin.php?page=threewp_broadcast&amp;action=user_unlink_all&amp;post=%s", $filter->parent_post_id );
					$url_unlink_all = wp_nonce_url( $url_unlink_all, 'broadcast_unlink_all_' . $filter->parent_post_id );
					$strings->set( 'unlink_all_separator', ' | ' );
					$strings->set( 'unlink_all', sprintf( '<a href="%s" title="%s">%s</a>',
						$url,
						$this->_( 'Unlink all of the child posts' ),
						$this->_( 'Unlink' )
					) );

					$url = sprintf( "admin.php?page=threewp_broadcast&amp;action=user_delete_all&amp;post=%s", $filter->parent_post_id );
					$url = wp_nonce_url( $url, 'broadcast_delete_all_' . $filter->parent_post_id );
					$strings->set( 'delete_all_separator', ' | ' );
					$strings->set( 'delete_all', sprintf( '<span class="trash"><a href="%s" title="%s">%s</a></span>',
						$url,
						$this->_( 'Permanently delete all the broadcasted children' ),
						$this->_( 'Delete' )
					) );

					$strings->set( 'div_small_close', '</small>' );
					$strings->set( 'div_close', '</div>' );

					$filter->html->put( 'delete_all', $strings );
				}

				$collection = new \threewp_broadcast\collections\strings;

				foreach( $children as $child_blog_id => $child_post_id )
				{
					$strings = new \threewp_broadcast\collections\strings_with_metadata;

					$url_child = get_blog_permalink( $child_blog_id, $child_post_id );
					// The post id is for the current blog, not the target blog.

					// For get_bloginfo.
					switch_to_blog( $child_blog_id );
					$blogname = get_bloginfo( 'blogname' );
					restore_current_blog();

					$strings->metadata()->set( 'child_blog_id', $child_blog_id );
					$strings->metadata()->set( 'blogname', $blogname );

					$strings->set( 'div_open', sprintf( '<div class="child_blog_name blog_%s">', $child_blog_id ) );
					$strings->set( 'a_broadcasted_child', sprintf( '<a class="broadcasted_child" href="%s">%s </a>', $url_child, $blogname ) );
					$strings->set( 'span_row_actions_open', '<span class="row-actions broadcasted_blog_actions">' );
					$strings->set( 'small_open', '<small>' );

					$url = sprintf( "admin.php?page=threewp_broadcast&amp;action=user_restore&amp;post=%s&amp;child=%s", $filter->parent_post_id, $child_blog_id );
					$url = wp_nonce_url( $url, 'broadcast_restore_' . $child_blog_id . '_' . $filter->parent_post_id );
					$strings->set( 'restore_separator', ' | ' );
					$strings->set( 'restore', sprintf( '<a href="%s" title="%s">%s</a>',
						$url,
						$this->_( 'Restore all of the children from the trash' ),
						$this->_( 'Restore' )
					) );

					$url = sprintf( "admin.php?page=threewp_broadcast&amp;action=user_trash&amp;post=%s&amp;child=%s", $filter->parent_post_id, $child_blog_id );
					$url = wp_nonce_url( $url, 'broadcast_trash_' . $child_blog_id . '_' . $filter->parent_post_id );
					$strings->set( 'trash_separator', ' | ' );
					$strings->set( 'trash', sprintf( '<a href="%s" title="%s">%s</a>',
						$url,
						$this->_( 'Put this broadcasted child post in the trash' ),
						$this->_( 'Trash' )
					) );

					$url = sprintf( "admin.php?page=threewp_broadcast&amp;action=user_unlink&amp;post=%s&amp;child=%s", $filter->parent_post_id, $child_blog_id );
					$url = wp_nonce_url( $url, 'broadcast_unlink_' . $child_blog_id . '_' . $filter->parent_post_id );
					$strings->set( 'unlink_separator', ' | ' );
					$strings->set( 'unlink', sprintf( '<a href="%s" title="%s">%s</a>',
						$url,
						$this->_( 'Remove link to this broadcasted child post' ),
						$this->_( 'Unlink' )
					) );

					$url = sprintf( "admin.php?page=threewp_broadcast&amp;action=user_delete&amp;post=%s&amp;child=%s", $filter->parent_post_id, $child_blog_id );
					$url = wp_nonce_url( $url, 'broadcast_delete_' . $child_blog_id . '_' . $filter->parent_post_id );
					$strings->set( 'delete_separator', ' | ' );
					$strings->set( 'delete', sprintf( '<span class="trash"><a href="%s" title="%s">%s</a></span>',
						$url,
						$this->_( 'Unlink and delete this broadcasted child post' ),
						$this->_( 'Delete' )
					) );

					$strings->set( 'small_close', '</small>' );
					$strings->set( 'span_row_actions_close', '</span>' );
					$strings->set( 'div_close', '</div>' );

					$collection->set( $blogname, $strings );
				}

				$collection->sort_by( function( $child )
				{
					return $child->metadata()->get( 'blogname' );
				});

				$filter->html->put( 'broadcasted_to', $collection );
			}
		}
		$filter->applied();
	}

	/**
		@brief		Decide what to do with the POST.
		@since		2014-03-23 23:08:31
	**/
	public function threewp_broadcast_maybe_clear_post( $action )
	{
		if ( $action->is_applied() )
		{
			$this->debug( 'Not maybe clearing the POST.' );
			return;
		}

		$clear_post = $this->get_site_option( 'clear_post', true );
		if ( $clear_post )
		{

			$this->debug( 'Clearing the POST.' );
			$action->post = [];
		}
		else
			$this->debug( 'Not clearing the POST.' );
	}

	/**
		@brief		Fill the broadcasting_data object with information.

		@details

		The difference between the calculations in this filter and the actual broadcast_post method is that this filter

		1) does access checks
		2) tells broadcast_post() WHAT to broadcast, not how.

		@since		20131004
	**/
	public function threewp_broadcast_prepare_broadcasting_data( $action )
	{
		$bcd = $action->broadcasting_data;
		$allowed_post_status = [ 'pending', 'private', 'publish' ];

		if ( $bcd->post->post_status == 'draft' && $this->role_at_least( $this->get_site_option( 'role_broadcast_as_draft' ) ) )
			$allowed_post_status[] = 'draft';

		if ( $bcd->post->post_status == 'future' && $this->role_at_least( $this->get_site_option( 'role_broadcast_scheduled_posts' ) ) )
			$allowed_post_status[] = 'future';

		if ( ! in_array( $bcd->post->post_status, $allowed_post_status ) )
			return;

		$form = $bcd->meta_box_data->form;
		if ( $form->is_posting() && ! $form->has_posted )
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
		//$bcd->post_type_supports_custom_fields = post_type_supports( $bcd->post->post_type, 'custom-fields' );
		$bcd->post_type_supports_custom_fields = true;
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
			[ &$this, 'broadcast_menu_tabs' ]
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

	/**
		@brief		Allows Broadcast plugins to update the term with their own info.
		@since		2014-04-08 15:12:05
	**/
	public function threewp_broadcast_wp_insert_term( $action )
	{
		if ( ! isset( $action->term->parent ) )
			$action->term->parent = 0;

		$term = wp_insert_term(
			$action->term->name,
			$action->taxonomy,
			[
				'description' => $action->term->description,
				'parent' => $action->term->parent,
				'slug' => $action->term->slug,
			]
		);

		// Sometimes the search didn't find the term because it's SIMILAR and not exact.
		// WP will complain and give us the term tax id.
		if ( is_wp_error( $term ) )
		{
			$wp_error = $term;
			$this->debug( 'Error creating the term: %s. Error was: %s', $action->term->name, serialize( $wp_error->error_data ) );
			if ( isset( $wp_error->error_data[ 'term_exists' ] ) )
			{
				$term_id = $wp_error->error_data[ 'term_exists' ];
				$this->debug( 'Term exists already with the term ID: %s', $term_id );
				$term = get_term_by( 'id', $term_id, $action->taxonomy, ARRAY_A );
			}
			else
			{
				throw new Exception( 'Unable to create a new term.' );
			}
		}

		$term_taxonomy_id = $term[ 'term_taxonomy_id' ];

		$this->debug( 'Created the new term %s with the term taxonomy ID of %s.', $action->term->name, $term_taxonomy_id );

		$action->new_term = get_term_by( 'term_taxonomy_id', $term_taxonomy_id, $action->taxonomy, ARRAY_A );
	}

	/**
		@brief		[Maybe] update a term.
		@since		2014-04-10 14:26:23
	**/
	public function threewp_broadcast_wp_update_term( $action )
	{
		$update = true;

		// If we are given an old term, then we have a chance of checking to see if there should be an update called at all.
		if ( $action->has_old_term() )
		{
			// Assume they match.
			$update = false;
			foreach( [ 'name', 'description', 'parent' ] as $key )
				if ( $action->old_term->$key != $action->new_term->$key )
					$update = true;
		}

		if ( $update )
		{
			$this->debug( 'Updating the term %s.', $action->new_term->name );
			wp_update_term( $action->new_term->term_id, $action->taxonomy, array(
				'description' => $action->new_term->description,
				'name' => $action->new_term->name,
				'parent' => $action->new_term->parent,
			) );
			$action->updated = true;
		}
		else
			$this->debug( 'Will not update the term %s.', $action->new_term->name );
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

		// Remove Canonical Link Added By Yoast WordPress SEO Plugin
		$this->add_filter( 'wpseo_canonical', 'wp_head_remove_wordpress_seo_canonical' );;
	}

	/**
		@brief		Remove Wordpress SEO canonical link so that it doesn't conflict with the parent link.
		@since		2014-01-16 00:36:15
	**/

	public function wp_head_remove_wordpress_seo_canonical()
	{
		// Tip seen here: http://wordpress.org/support/topic/plugin-wordpress-seo-by-yoast-remove-canonical-tags-in-header?replies=10
		return false;
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
		@brief		Returns the name of the broadcast data table.
		@since		20131104
	**/
	public function broadcast_data_table()
	{
		return $this->wpdb->base_prefix . '_3wp_broadcast_broadcastdata';
	}

	/**
		@brief		Broadcast a post.
		@details	The BC data parameter contains all necessary information about what is being broadcasted, to which blogs, options, etc.
		@param		broadcasting_data		$broadcasting_data		The broadcasting data object.
		@since		20130603
	**/
	public function broadcast_post( $broadcasting_data )
	{
		$bcd = $broadcasting_data;

		$this->debug( 'Broadcasting the post %s <pre>%s</pre>', $bcd->post->ID, $this->code_export( $bcd->post ) );

		$this->debug( 'The POST was <pre>%s</pre>', $this->code_export( $bcd->_POST ) );

		// For nested broadcasts. Just in case.
		switch_to_blog( $bcd->parent_blog_id );

		if ( $bcd->link )
		{
			$this->debug( 'Linking is enabled.' );
			// Prepare the broadcast data for linked children.
			$broadcast_data = $this->get_post_broadcast_data( $bcd->parent_blog_id, $bcd->post->ID );

			// Does this post type have parent support, so that we can link to a parent?
			if ( $bcd->post_type_is_hierarchical && $bcd->post->post_parent > 0)
			{
				$parent_broadcast_data = $this->get_post_broadcast_data( $bcd->parent_blog_id, $bcd->post->post_parent );
			}
			$this->debug( 'Post type is hierarchical: %s', $this->yes_no( $bcd->post_type_is_hierarchical ) );
		}
		else
			$this->debug( 'Linking is disabled.' );

		if ( $bcd->taxonomies )
		{
			$this->debug( 'Will broadcast taxonomies.' );
			$this->collect_post_type_taxonomies( $bcd );
		}
		else
			$this->debug( 'Will not broadcast taxonomies.' );

		$bcd->attachment_data = [];
		$attached_files = get_children( 'post_parent='.$bcd->post->ID.'&post_type=attachment' );
		$has_attached_files = count( $attached_files) > 0;
		if ( $has_attached_files )
		{
			$this->debug( 'Has %s attachments.', $has_attached_files );
			foreach( $attached_files as $attached_file )
			{
				$bcd->attachment_data[ $attached_file->ID ] = attachment_data::from_attachment_id( $attached_file, $bcd->upload_dir );
				$this->debug( 'Attachment %s found.', $attached_file->ID );
			}
		}

		if ( $bcd->custom_fields )
		{
			$this->debug( 'Will broadcast custom fields.' );
			$bcd->post_custom_fields = get_post_custom( $bcd->post->ID );

			$bcd->has_thumbnail = isset( $bcd->post_custom_fields[ '_thumbnail_id' ] );

			// Check that the thumbnail ID is > 0
			$bcd->has_thumbnail = $bcd->has_thumbnail && ( reset( $bcd->post_custom_fields[ '_thumbnail_id' ] ) > 0 );

			if ( $bcd->has_thumbnail )
			{
				$this->debug( 'Post has a thumbnail (featured image).' );
				$bcd->thumbnail_id = $bcd->post_custom_fields[ '_thumbnail_id' ][0];
				$bcd->thumbnail = get_post( $bcd->thumbnail_id );
				unset( $bcd->post_custom_fields[ '_thumbnail_id' ] ); // There is a new thumbnail id for each blog.
				$bcd->attachment_data[ 'thumbnail' ] = attachment_data::from_attachment_id( $bcd->thumbnail, $bcd->upload_dir);
				// Now that we know what the attachment id the thumbnail has, we must remove it from the attached files to avoid duplicates.
				unset( $bcd->attachment_data[ $bcd->thumbnail_id ] );
			}
			else
				$this->debug( 'Post does not have a thumbnail (featured image).' );

			// Remove all the _internal custom fields.
			$bcd->post_custom_fields = $this->keep_valid_custom_fields( $bcd->post_custom_fields );
		}
		else
			$this->debug( 'Will not broadcast custom fields.' );

		// Handle any galleries.
		$bcd->galleries = new collection;
		$matches = $this->find_shortcodes( $bcd->post->post_content, 'gallery' );
		$this->debug( 'Found %s gallery shortcodes.', count( $matches[ 2 ] ) );

		// [2] contains only the shortcode command / key. No options.
		foreach( $matches[ 2 ] as $index => $key )
		{
			// We've found a gallery!
			$bcd->has_galleries = true;
			$gallery = new \stdClass;
			$bcd->galleries->push( $gallery );

			// Complete matches are in 0.
			$gallery->old_shortcode = $matches[ 0 ][ $index ];

			// Extract the IDs
			$gallery->ids_string = preg_replace( '/.*ids=\"([0-9,]*)".*/', '\1', $gallery->old_shortcode );
			$this->debug( 'Gallery %s has IDs: %s', $gallery->old_shortcode, $gallery->ids_string );
			$gallery->ids_array = explode( ',', $gallery->ids_string );
			foreach( $gallery->ids_array as $id )
			{
				$this->debug( 'Gallery has attachment %s.', $id );
				$ad = attachment_data::from_attachment_id( $id, $bcd->upload_dir );
				$bcd->attachment_data[ $id ] = $ad;
			}
		}

		$to_broadcasted_blogs = [];				// Array of blog names that we're broadcasting to. To be used for the activity monitor action.
		$to_broadcasted_blog_details = []; 		// Array of blog and post IDs that we're broadcasting to. To be used for the activity monitor action.

		// To prevent recursion
		array_push( $this->broadcasting, $bcd );

		// POST is no longer needed. Empty it so that other plugins don't use it.
		$action = new actions\maybe_clear_post;
		$action->post = $_POST;
		$action->apply();
		$_POST = $action->post;

		$action = new actions\broadcasting_started;
		$action->broadcasting_data = $bcd;
		$action->apply();

		$this->debug( 'Beginning child broadcast loop.' );

		foreach( $bcd->blogs as $child_blog )
		{
			$child_blog->switch_to();
			$bcd->current_child_blog_id = $child_blog->get_id();
			$this->debug( 'Switched to blog %s', $bcd->current_child_blog_id );

			// Create new post data from the original stuff.
			$bcd->new_post = (array) $bcd->post;

			foreach( [ 'comment_count', 'guid', 'ID', 'post_parent' ] as $key )
				unset( $bcd->new_post[ $key ] );

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
					$this->debug( 'There is already a child post on this blog: %s', $child_post_id );

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
				$this->debug( 'Creating a new post.' );
				$temp_post_data = $bcd->new_post;
				unset( $temp_post_data[ 'ID' ] );

				$result = wp_insert_post( $temp_post_data, true );

				// Did we manage to insert the post properly?
				if ( intval( $result ) < 1 )
				{
					$this->debug( 'Unable to insert the child post.' );
					continue;
				}
				// Yes we did.
				$bcd->new_post[ 'ID' ] = $result;

				$this->debug( 'New child created: %s', $result );

				if ( $bcd->link )
				{
					$this->debug( 'Adding link to child.' );
					$broadcast_data->add_linked_child( $bcd->current_child_blog_id, $bcd->new_post[ 'ID' ] );
				}
			}

			if ( $bcd->taxonomies )
			{
				$this->debug( 'Taxonomies: Starting.' );
				foreach( $bcd->parent_post_taxonomies as $parent_post_taxonomy => $parent_post_terms )
				{
					$this->debug( 'Taxonomies: %s', $parent_post_taxonomy );
					// If we're updating a linked post, remove all the taxonomies and start from the top.
					if ( $bcd->link )
						if ( $broadcast_data->has_linked_child_on_this_blog() )
							wp_set_object_terms( $bcd->new_post[ 'ID' ], [], $parent_post_taxonomy );

					// Skip this iteration if there are no terms
					if ( ! is_array( $parent_post_terms ) )
					{
						$this->debug( 'Taxonomies: Skipping %s because the parent post does not have any terms set for this taxonomy.', $parent_post_taxonomy );
						continue;
					}

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
								$this->debug( 'Taxonomies: Found existing taxonomy %s.', $parent_slug );
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

							$new_term = clone( $parent_post_term );
							$new_term->parent = $target_parent_id;
							$action = new actions\wp_insert_term;
							$action->taxonomy = $parent_post_taxonomy;
							$action->term = $new_term;
							$action->apply();
							$new_taxonomy = $action->new_term;
							$term_taxonomy_id = $new_taxonomy[ 'term_taxonomy_id' ];
							$this->debug( 'Taxonomies: Created taxonomy %s (%s).', $parent_post_term->name, $term_taxonomy_id );

							$taxonomies_to_add_to []= intval( $term_taxonomy_id );
						}
					}

					$this->debug( 'Taxonomies: Syncing terms.' );
					$this->sync_terms( $bcd, $parent_post_taxonomy );

					if ( count( $taxonomies_to_add_to ) > 0 )
					{
						// This relates to the bug mentioned in the method $this->set_term_parent()
						delete_option( $parent_post_taxonomy . '_children' );
						clean_term_cache( '', $parent_post_taxonomy );
						wp_set_object_terms( $bcd->new_post[ 'ID' ], $taxonomies_to_add_to, $parent_post_taxonomy );
					}
				}
				$this->debug( 'Taxonomies: Finished.' );
			}

			// Remove the current attachments.
			$attachments_to_remove = get_children( 'post_parent='.$bcd->new_post[ 'ID' ] . '&post_type=attachment' );
			foreach ( $attachments_to_remove as $attachment_to_remove )
			{
				$this->debug( 'Deleting existing attachment: %s', $attachment_to_remove->ID );
				wp_delete_attachment( $attachment_to_remove->ID );
			}

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
					$a->new->id = $a->new->ID;		// Lowercase is expected.
					$bcd->copied_attachments[] = $a;
					$this->debug( 'Copied attachment %s to %s', $a->old->id, $a->new->id );
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
					$this->debug( 'Modifying attachment link from %s to %s', $a->old->id, $a->new->id );
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
						$new_ids[] = $ca->new->id;
					}
				}
				$new_ids_string = implode( ',', $new_ids );
				$new_shortcode = $gallery->old_shortcode;
				$new_shortcode = str_replace( $gallery->ids_string, $new_ids_string, $gallery->old_shortcode );
				$modified_post->post_content = str_replace( $gallery->old_shortcode, $new_shortcode, $modified_post->post_content );
			}

			$bcd->modified_post = $modified_post;
			$action = new actions\broadcasting_modify_post;
			$action->broadcasting_data = $bcd;
			$action->apply();

			// Maybe updating the post is not necessary.
			if ( $unmodified_post->post_content != $modified_post->post_content )
			{
				$this->debug( 'Modifying with new post: %s', $this->code_export( $modified_post->post_content ) );
				wp_update_post( $modified_post );	// Or maybe it is.
			}

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
					if ( property_exists( $this, 'attachment_cache' ) )
						$this->attachment_cache->forget( $bcd->current_child_blog_id );

					if ( $o->attachment_data->post->post_parent > 0 )
						$o->attachment_data->post->post_parent = $bcd->new_post[ 'ID' ];

					$this->maybe_copy_attachment( $o );
					if ( $o->attachment_id !== false )
					{
						$this->debug( 'Handling post thumbnail: %s %s', $bcd->new_post[ 'ID' ], '_thumbnail_id', $o->attachment_id );
						update_post_meta( $bcd->new_post[ 'ID' ], '_thumbnail_id', $o->attachment_id );
					}
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
				$this->debug( 'Saving broadcast data of child.' );
				$new_post_broadcast_data = $this->get_post_broadcast_data( $bcd->current_child_blog_id, $bcd->new_post[ 'ID' ] );
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

		// For nested broadcasts. Just in case.
		restore_current_blog();

		// Save the post broadcast data.
		if ( $bcd->link )
		{
			$this->debug( 'Saving broadcast data.' );
			$this->set_post_broadcast_data( $bcd->parent_blog_id, $bcd->post->ID, $broadcast_data );
		}

		$action = new actions\broadcasting_finished;
		$action->broadcasting_data = $bcd;
		$action->apply();

		// Finished broadcasting.
		array_pop( $this->broadcasting );

		if ( $this->debugging() )
		{
			if ( ! $this->is_broadcasting() )
			{
				$this->debug( 'Finished broadcasting. Now stopping Wordpress.' );
				exit;
			}
			else
			{
				$this->debug( 'Still broadcasting.' );
			}
		}

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
		@brief		Dump a variable with code tags.
		@since		2014-04-06 21:49:24
	**/
	public function code_export( $variable )
	{
		return sprintf( '<pre><code>%s</code></pre>', var_export( $variable, true ) );
	}

	/**
		@brief		Collects the post type's taxonomies into the broadcasting data object.
		@details	Requires only that $bcd->post->post_type be filled in.
		@since		2014-04-08 13:40:44
	**/
	public function collect_post_type_taxonomies( $bcd )
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
			if ( isset( $bcd->post->ID ) )
				$bcd->parent_post_taxonomies[ $parent_blog_taxonomy ] = get_the_terms( $bcd->post->ID, $parent_blog_taxonomy );
			else
				$bcd->parent_post_taxonomies[ $parent_blog_taxonomy ] = get_terms( [ $parent_blog_taxonomy ] );
		}
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
	public function copy_attachment( $o )
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
			'post_author' => $o->attachment_data->post->post_author,
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
		@brief		Creates the ID column in the broadcast data table.
		@since		2014-04-20 20:19:45
	**/
	public function create_broadcast_data_id_column()
	{
		$query = sprintf( "ALTER TABLE `%s` ADD `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY COMMENT 'ID of row' FIRST;",
			$this->broadcast_data_table()
		);
		$this->query( $query );
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
		@brief		Output a string if in debug mode.
		@since		20140220
	*/
	public function debug( $string )
	{
		if ( ! $this->debugging() )
			return;

		$text = call_user_func_array( 'sprintf', func_get_args() );
		if ( $text == '' )
			$text = $string;
		$text = sprintf( '%s %s<br/>', $this->now(), $text );
		echo $text;
	}

	/**
		@brief		Is Broadcast in debug mode?
		@since		20140220
	*/
	public function debugging()
	{
		$debugging = $this->get_site_option( 'debug', false );
		if ( ! $debugging )
			return false;

		// Debugging is enabled. Now check if we should show it to this user.
		$ips = $this->get_site_option( 'debug_ips', '' );
		// Empty = no limits.
		if ( $ips == '' )
			return true;

		$lines = explode( "\n", $ips );
		foreach( $lines as $line )
			if ( strpos( $_SERVER[ 'REMOTE_ADDR' ], $line ) !== false )
				return true;

		// No match = not debugging for this user.
		return false;
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
		if ( isset( $this->_js_enqueued ) )
			return;
		wp_enqueue_script( 'threewp_broadcast', $this->paths[ 'url' ] . '/js/user.min.js', '', $this->plugin_version );
		$this->_js_enqueued = true;
	}

	/**
		@brief		Find shortcodes in a string.
		@details	Runs a preg_match_all on a string looking for specific shortcodes.
					Overrides Wordpress' get_shortcode_regex without own shortcode(s).
		@since		2014-02-26 22:05:09
	**/
	public function find_shortcodes( $string, $shortcodes )
	{
		// Make the shortcodes an array
		if ( ! is_array( $shortcodes ) )
			$shortcodes = [ $shortcodes ];

		// We use Wordpress' own function to find shortcodes.

		global $shortcode_tags;
		// Save the old global
		$old_shortcode_tags = $shortcode_tags;
		// Replace the shortcode tags with just our own.
		$shortcode_tags = array_flip( $shortcodes );
		$rx = get_shortcode_regex();
		$shortcode_tags = $old_shortcode_tags;

		// Run the preg_match_all
		$matches = '';
		preg_match_all( '/' . $rx . '/', $string, $matches );

		return $matches;
	}

	public function get_current_blog_taxonomy_terms( $taxonomy )
	{
		$terms = get_terms( $taxonomy, array(
			'hide_empty' => false,
		) );
		$terms = (array) $terms;
		$terms = $this->array_rekey( $terms, 'term_id' );
		return $terms;
	}

	/**
		@brief		Get some standardizing CSS styles.
		@return		string		A string containing the CSS <style> data, including the tags.
		@since		20131031
	**/
	public function html_css()
	{
		return file_get_contents( __DIR__ . '/html/style.css' );
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
			$new_term = (object)$source_parent;
			$new_term->parent = $target_grandparent_id;
			$action = new actions\wp_insert_term;
			$action->taxonomy = $source_post_taxonomy;
			$action->term = $new_term;
			$action->apply();
			$term_id = $action->new_term[ 'term_id' ];
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
		return count( $this->broadcasting ) > 0;
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
				$this->custom_field_blacklist_cache = array_filter( explode( ' ', $this->get_site_option( 'custom_field_blacklist' ) ) );

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
				$this->custom_field_whitelist_cache = array_filter( explode( ' ', $this->get_site_option( 'custom_field_whitelist' ) ) );

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
				'cache_results' => false,
				'name' => $attachment_data->post->post_name,
				'numberposts' => PHP_INT_MAX,
				'post_type' => 'attachment',

			] );
			$this->attachment_cache->put( $key, $attachment_posts );
		}

		// Is there an existing media file?
		// Try to find the filename in the GUID.
		foreach( $attachment_posts as $attachment_post )
		{
			if ( $attachment_post->post_name !== $attachment_data->post->post_name )
				continue;
			// We've found an existing attachment. What to do with it...
			switch( $this->get_site_option( 'existing_attachments', 'use' ) )
			{
				case 'overwrite':
					// Delete the existing attachment
					wp_delete_attachment( $attachment_post->ID, true );		// true = Don't go to trash
					break;
				case 'randomize':
					$filename = $options->attachment_data->filename_base;
					$filename = preg_replace( '/(.*)\./', '\1_' . rand( 1000000, 9999999 ) .'.', $filename );
					$options->attachment_data->filename_base = $filename;
					break;
				case 'use':
				default:
					// The ID is the important part.
					$options->attachment_id = $attachment_post->ID;
					return $options;

			}
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
				$this->sql_update_broadcast_data( $blog_id, $post_id, $broadcast_data );
	}

	/**
		@brief		Syncs the terms of a taxonomy from the parent blog in the BCD to the current blog.
		@details	If $bcd->add_new_taxonomies is set, new taxonomies will be created, else they are ignored.
		@param		broadcasting_data		$bcd			The broadcasting data.
		@param		string					$taxonomy		The taxonomy to sync.
		@since		20131004
	**/
	public function sync_terms( $bcd, $taxonomy )
	{
		$source_terms = $bcd->parent_blog_taxonomies[ $taxonomy ][ 'terms' ];
		$target_terms = $this->get_current_blog_taxonomy_terms( $taxonomy );
		$this->debug( 'Source terms for taxonomy %s: %s', $taxonomy, $this->code_export( $source_terms ) );
		$this->debug( 'Target terms for taxonomy %s: %s', $taxonomy, $this->code_export( $target_terms ) );

		$refresh_cache = false;

		// Keep track of which terms we've found.
		$found_targets = [];
		$found_sources = [];

		// Also keep track of which sources we haven't found on the target blog.
		$unfound_sources = $source_terms;

		// First step: find out which of the target terms exist on the source blog
		$this->debug( 'Find out which of the source terms exist on the target blog.' );
		foreach( $target_terms as $target_term_id => $target_term )
			foreach( $source_terms as $source_term_id => $source_term )
			{
				if ( isset( $found_sources[ $source_term_id ] ) )
					continue;
				if ( $source_term[ 'slug' ] == $target_term[ 'slug' ] )
				{
					$this->debug( 'Find source term %s. Source ID: %s. Target ID: %s.', $source_term[ 'slug' ], $source_term_id, $target_term_id );
					$found_targets[ $target_term_id ] = $source_term_id;
					$found_sources[ $source_term_id ] = $target_term_id;
					unset( $unfound_sources[ $source_term_id ] );
				}
			}

		// These sources were not found. Add them.
		if ( isset( $bcd->add_new_taxonomies ) && $bcd->add_new_taxonomies )
		{
			$this->debug( '%s taxonomies are missing on this blog.', count( $unfound_sources ) );
			foreach( $unfound_sources as $unfound_source_id => $unfound_source )
			{
				$unfound_source = (object)$unfound_source;
				unset( $unfound_source->parent );
				$action = new actions\wp_insert_term;
				$action->taxonomy = $taxonomy;
				$action->term = $unfound_source;
				$action->apply();

				$new_taxonomy = $action->new_term;
				$new_taxonomy_id = $new_taxonomy[ 'term_id' ];
				$target_terms[ $new_taxonomy_id ] = (array)$new_taxonomy;
				$found_sources[ $unfound_source_id ] = $new_taxonomy_id;
				$found_targets[ $new_taxonomy_id ] = $unfound_source_id;

				$refresh_cache = true;
			}
		}

		// Now we know which of the terms on our target blog exist on the source blog.
		// Next step: see if the parents are the same on the target as they are on the source.
		// "Same" meaning pointing to the same slug.
		$this->debug( 'About to update taxonomy terms.' );
		foreach( $found_targets as $target_term_id => $source_term_id)
		{
			$source_term = (object)$source_terms[ $source_term_id ];
			$target_term = (object)$target_terms[ $target_term_id ];

			$action = new actions\wp_update_term;
			$action->taxonomy = $taxonomy;

			// The old term is the target term, since it contains the old values.
			$action->old_term = (object)$target_terms[ $target_term_id ];
			// The new term is the source term, since it has the newer data.
			$action->new_term = (object)$source_terms[ $source_term_id ];

			// ... but the IDs have to be switched around, since the target term has the new ID.
			$action->switch_data();

			// Update the parent.
			$parent_of_equivalent_source_term = $source_term->parent;
			$parent_of_target_term = $target_term->parent;

			$new_parent = 0;
			// Does the source term even have a parent?
			if ( $parent_of_equivalent_source_term > 0 )
			{
				// Did we find the parent term?
				if ( isset( $found_sources[ $parent_of_equivalent_source_term ] ) )
					$new_parent = $found_sources[ $parent_of_equivalent_source_term ];
			}
			else
				$new_parent = 0;

			$action->new_term->parent = $new_parent;

			$action->apply();
			$refresh_cache |= $action->updated;
		}

		// wp_update_category alone won't work. The "cache" needs to be cleared.
		// see: http://wordpress.org/support/topic/category_children-how-to-recalculate?replies=4
		if ( $refresh_cache )
			delete_option( 'category_children' );
	}

	/**
		@brief		Return yes / no, depending on value.
		@since		20140220
	**/
	public function yes_no( $value )
	{
		return $value ? 'yes' : 'no';
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
			$this->broadcast_data_table(),
			$blog_id,
			implode( "', '", $post_ids )
		);
		$results = $this->query( $query );
		foreach( $results as $index => $result )
			$results[ $index ][ 'data' ] = BroadcastData::sql( $result );
		return $results;
	}

	/**
		@brief		Delete broadcast data.
		@details	If $post_id is not used, then the $blog_id is assumed to be just the row ID.

		If $post_id is used, then $blog_id is the actual $blog_id.
		@since		20131105
	**/
	public function sql_delete_broadcast_data( $blog_id, $post_id = null )
	{
		if ( $post_id === null )
			$query = sprintf( "DELETE FROM `%s` WHERE `id` = '%s'",
				$this->broadcast_data_table(),
				$blog_id
			);
		else
			$query = sprintf( "DELETE FROM `%s` WHERE blog_id = '%s' AND post_id = '%s'",
				$this->broadcast_data_table(),
				$blog_id,
				$post_id
			);
		$this->query( $query );
	}

	public function sql_update_broadcast_data( $broadcast_data )
	{
		$args = func_get_args();
		if ( count( $args ) == 1 )
			return $this->sql_update_broadcast_data_old( null, null, $broadcast_data );
		else
			return call_user_func_array( [ $this, 'sql_update_broadcast_data_old' ], $args );
	}

	public function sql_update_broadcast_data_object( $broadcast_data )
	{
	}

	public function sql_update_broadcast_data_old( $blog_id, $post_id, $bcd )
	{
		$data = serialize( $bcd->getData() );
		$data = base64_encode( $data );

		if ( $bcd->id > 0 )
		{
			$query = sprintf( "UPDATE `%s` SET `data` = '%s' WHERE `id` = '%s'",
				$this->broadcast_data_table(),
				$data,
				$bcd->id
			);
		}
		else
			$query = sprintf( "INSERT INTO `%s` (blog_id, post_id, data) VALUES ( '%s', '%s', '%s' )",
				$this->broadcast_data_table(),
				$blog_id,
				$post_id,
				$data
			);
		$this->query( $query );
	}
}

$threewp_broadcast = new ThreeWP_Broadcast();
