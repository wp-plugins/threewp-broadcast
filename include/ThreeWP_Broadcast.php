<?php

namespace threewp_broadcast;

use \threewp_broadcast\broadcast_data\blog;

class ThreeWP_Broadcast
	extends \plainview\sdk\wordpress\base
{
	use \plainview\sdk\wordpress\traits\debug;

	use traits\admin_menu;
	use traits\broadcast_data;
	use traits\broadcasting;
	use traits\meta_boxes;
	use traits\post_methods;
	use traits\terms_and_taxonomies;

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

	public $plugin_version = THREEWP_BROADCAST_VERSION;

	// 20140501 when debug trait is moved to SDK.
	protected $sdk_version_required = 20130505;		// add_action / add_filter

	public function _construct()
	{
		if ( ! $this->is_network )
			wp_die( $this->_( 'Broadcast requires a Wordpress network to function.' ) );

		$this->add_action( 'add_meta_boxes' );
		$this->add_action( 'admin_menu' );
		$this->add_action( 'admin_print_styles' );

		if ( $this->get_site_option( 'override_child_permalinks' ) )
		{
			$this->add_filter( 'post_link', 10, 3 );
			$this->add_filter( 'post_type_link', 'post_link', 10, 3 );
		}

		$this->add_filter( 'threewp_broadcast_add_meta_box' );
		$this->add_filter( 'threewp_broadcast_admin_menu', 'add_post_row_actions_and_hooks', 100 );
		$this->add_filter( 'threewp_broadcast_broadcast_post' );
		$this->add_action( 'threewp_broadcast_get_user_writable_blogs', 11 );		// Allow other plugins to do this first.
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

		$this->permalink_cache = (object)[];
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
	// ----------------------------------------- Callbacks
	// --------------------------------------------------------------------------------------------

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

	/**
		@brief		Return a collection of blogs that the user is allowed to write to.
		@since		20131003
	**/
	public function threewp_broadcast_get_user_writable_blogs( $action )
	{
		if ( $action->is_finished() )
			return;

		$blogs = get_blogs_of_user( $action->user_id, true );
		foreach( $blogs as $blog)
		{
			$blog = blog::make( $blog );
			$blog->id = $blog->userblog_id;
			if ( ! $this->is_blog_user_writable( $action->user_id, $blog ) )
				continue;
			$action->blogs->set( $blog->id, $blog );
		}

		$action->blogs->sort_logically();
		$action->finish();
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
		@brief		Decide what to do with the POST.
		@since		2014-03-23 23:08:31
	**/
	public function threewp_broadcast_maybe_clear_post( $action )
	{
		if ( $action->is_finished() )
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
		{
			$this->debug( 'Copy attachment: File %s does not exist!', $o->attachment_data->filename_path );
			return false;
		}

		// Copy the file to the blog's upload directory
		$upload_dir = wp_upload_dir();

		$source = $o->attachment_data->filename_path;
		$target = $upload_dir[ 'path' ] . '/' . $o->attachment_data->filename_base;
		$this->debug( 'Copy attachment: Copying from %s to %s', $source, $target );
		copy( $source, $target );

		// And now create the attachment stuff.
		// This is taken almost directly from http://codex.wordpress.org/Function_Reference/wp_insert_attachment
		$this->debug( 'Copy attachment: Checking filetype.' );
		$wp_filetype = wp_check_filetype( $target, null );
		$attachment = [
			'guid' => $upload_dir[ 'url' ] . '/' . $target,
			'menu_order' => $o->attachment_data->post->menu_order,
			'post_author' => $o->attachment_data->post->post_author,
			'post_excerpt' => $o->attachment_data->post->post_excerpt,
			'post_mime_type' => $wp_filetype[ 'type' ],
			'post_title' => $o->attachment_data->post->post_title,
			'post_content' => '',
			'post_status' => 'inherit',
		];
		$this->debug( 'Copy attachment: Inserting attachment.' );
		$o->attachment_id = wp_insert_attachment( $attachment, $target, $o->attachment_data->post->post_parent );

		// Now to maybe handle the metadata.
		if ( $o->attachment_data->file_metadata )
		{
			$this->debug( 'Copy attachment: Handling metadata.' );
			// 1. Create new metadata for this attachment.
			$this->debug( 'Copy attachment: Requiring image.php.' );
			require_once( ABSPATH . "wp-admin" . '/includes/image.php' );
			$this->debug( 'Copy attachment: Generating metadata for %s.', $target );
			$attach_data = wp_generate_attachment_metadata( $o->attachment_id, $target );
			$this->debug( 'Copy attachment: Metadata is %s', $attach_data );

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
			$this->debug( 'Copy attachment: Updating metadata.' );
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

	/**
		@brief		Return an array of all callbacks of a hook.
		@since		2014-04-30 00:11:30
	**/
	public function get_hooks( $hook )
	{
		global $wp_filter;
		$filters = $wp_filter[ $hook ];
		ksort( $filters );
		$hook_callbacks = [];
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
				if ( is_a( $function, 'Closure' ) )
					$function = '[Anonymous function]';
				$hook_callbacks[] = $function;
			}
		}
		return $hook_callbacks;
	}

	/**
		@brief		Get some standardizing CSS styles.
		@return		string		A string containing the CSS <style> data, including the tags.
		@since		20131031
	**/
	public function html_css()
	{
		return file_get_contents( __DIR__ . '/../html/style.css' );
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

	/**
		@brief		Load the user's last used settings from the user meta table.
		@details	Remove the sql_user_get call in v9 or v10, giving time for people to move the settings from the table to the user meta.
		@since		2014-10-09 06:27:32
	**/
	public function load_last_used_settings( $user_id )
	{
		$settings = get_user_meta( $user_id, 'broadcast_last_used_settings', true );
		if ( ! $settings )
		{
			$settings = $this->sql_user_get( $user_id );
			$settings = $settings[ 'last_used_settings' ];
		}
		if ( ! is_array( $settings ) )
			$settings = [];
		return $settings;
	}

	/**
		@brief		Will only copy the attachment if it doesn't already exist on the target blog.
		@details	The return value is an object, with the most important property being ->attachment_id.

		@param		object		$options		See the parameter for copy_attachment.
	**/
	public function maybe_copy_attachment( $options )
	{
		$attachment_data = $options->attachment_data;		// Convenience.

		$key = get_current_blog_id();

		$this->debug( 'Maybe copy attachment: Searching for attachment posts with the name %s.', $attachment_data->post->post_name );

		// Start by assuming no attachments.
		$attachment_posts = [];

		global $wpdb;
		// The post_name is the important part.
		$query = sprintf( "SELECT `ID` FROM `%s` WHERE `post_type` = 'attachment' AND `post_name` = '%s'",
			$wpdb->posts,
			$attachment_data->post->post_name
		);
		$results = $this->query( $query );
		if ( count( $results ) > 0 )
			foreach( $results as $result )
				$attachment_posts[] = get_post( $result[ 'ID' ] );
		$this->debug( 'Maybe copy attachment: Found %s attachment posts.', count( $attachment_posts ) );

		// Is there an existing media file?
		// Try to find the filename in the GUID.
		foreach( $attachment_posts as $attachment_post )
		{
			if ( $attachment_post->post_name !== $attachment_data->post->post_name )
			{
				$this->debug( "The attachment post name is %s, and we are looking for %s. Ignoring attachment.", $attachment_post->post_name, $attachment_data->post->post_name );
				continue;
			}
			$this->debug( "Found attachment %s and we are looking for %s.", $attachment_post->post_name, $attachment_data->post->post_name );
			// We've found an existing attachment. What to do with it...
			$existing_action = $this->get_site_option( 'existing_attachments', 'use' );
			$this->debug( 'Maybe copy attachment: The action for existing attachments is to %s.', $existing_action );
			switch( $existing_action )
			{
				case 'overwrite':
					// Delete the existing attachment
					$this->debug( 'Maybe copy attachment: Deleting current attachment %s', $attachment_post->ID );
					wp_delete_attachment( $attachment_post->ID, true );		// true = Don't go to trash
					break;
				case 'randomize':
					$filename = $options->attachment_data->filename_base;
					$filename = preg_replace( '/(.*)\./', '\1_' . rand( 1000000, 9999999 ) .'.', $filename );
					$options->attachment_data->filename_base = $filename;
					$this->debug( 'Maybe copy attachment: Randomizing new attachment filename to %s.', $options->attachment_data->filename_base );
					break;
				case 'use':
				default:
					// The ID is the important part.
					$options->attachment_id = $attachment_post->ID;
					$this->debug( 'Maybe copy attachment: Using existing attachment %s.', $attachment_post->ID );
					return $options;

			}
		}

		// Since it doesn't exist, copy it.
		$this->debug( 'Maybe copy attachment: Really copying attachment.' );
		$this->copy_attachment( $options );
		return $options;
	}

	/**
		@brief		Save the user's last used settings.
		@details	Since v8 the data is stored in the user's meta.
		@since		2014-10-09 06:19:53
	**/
	public function save_last_used_settings( $user_id, $settings )
	{
		update_user_meta( $user_id, 'broadcast_last_used_settings', $settings );
	}

	public function site_options()
	{
		return array_merge( [
			'blogs_to_hide' => 5,								// How many blogs to auto-hide
			'broadcast_internal_custom_fields' => true,		// Broadcast internal custom fields?
			'canonical_url' => true,							// Override the canonical URLs with the parent post's.
			'clear_post' => true,								// Clear the post before broadcasting.
			'custom_field_whitelist' => '_wp_page_template _wplp_ _aioseop_',				// Internal custom fields that should be broadcasted.
			'custom_field_blacklist' => '',						// Internal custom fields that should not be broadcasted.
			'custom_field_protectlist' => '',					// Internal custom fields that should not be overwritten on broadcast
			'database_version' => 0,							// Version of database and settings
			'debug' => false,									// Display debug information?
			'debug_ips' => '',									// List of IP addresses that can see debug information, when debug is enabled.
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
		], parent::site_options() );
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
}
