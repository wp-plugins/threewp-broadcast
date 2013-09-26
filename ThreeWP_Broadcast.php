<?php
/*
Author:			edward_plainview
Author Email:	edward@plainview.se
Author URI:		http://www.plainview.se
Description:	Network plugin to broadcast a post to other blogs. Whitelist, blacklist, groups and automatic category+tag+custom field posting/creation available.
Plugin Name:	ThreeWP Broadcast
Plugin URI:		http://plainview.se/wordpress/threewp-broadcast/
Version:		1.29
*/

namespace threewp_broadcast;

if ( ! class_exists( '\\plainview\\wordpress\\base' ) )	require_once( __DIR__ . '/plainview_sdk/plainview/autoload.php' );

class ThreeWP_Broadcast
	extends \plainview\wordpress\base
{
	private $blogs_cache = null;

	private $broadcasting = false;

	/**
		@brief	Public property used during the broadcast process.
		@see	include/Broadcasting_Data.php
		@since	20130530
		@var	$broadcasting_data
	**/
	public $broadcasting_data = null;

	/**
		@brief		Caches permalinks looked up during this page view.
		@see		post_link()
		@since		20130923
	**/
	public $permalink_cache;

	public $plugin_version = 20130926;

	protected $sdk_version_required = 20130505;		// add_action / add_filter

	protected $site_options = array(
		'always_use_required_list' => false,				// Require blogs only when broadcasting?
		'blacklist' => '',									// Comma-separated string of blogs to automatically exclude
		'canonical_url' => true,							// Override the canonical URLs with the parent post's.
		'custom_field_exceptions' => '_wp_page_template _wplp_ _aioseop_',				// Custom fields that should be broadcasted, even though they start with _
		'database_version' => 1,							// Version of database and settings
		'save_post_priority' => 640,						// Priority of save_post action. Higher = lets other plugins do their stuff first
		'override_child_permalinks' => false,				// Make the child's permalinks link back to the parent item?
		'post_types' => 'post page',						// Custom post types which use broadcasting
		'requiredlist' => '',								// Comma-separated string of blogs to require
		'role_broadcast' => 'super_admin',					// Role required to use broadcast function
		'role_link' => 'super_admin',						// Role required to use the link function
		'role_broadcast_as_draft' => 'super_admin',			// Role required to broadcast posts as templates
		'role_broadcast_scheduled_posts' => 'super_admin',	// Role required to broadcast scheduled, future posts
		'role_groups' => 'super_admin',						// Role required to use groups
		'role_taxonomies' => 'super_admin',					// Role required to broadcast the taxonomies
		'role_taxonomies_create' => 'super_admin',			// Role required to create taxonomies automatically
		'role_custom_fields' => 'super_admin',				// Role required to broadcast the custom fields
	);

	public function _construct()
	{
		if ( ! $this->is_network )
			wp_die( $this->_( 'Broadcast requires a Wordpress network to function.' ) );

		$this->add_action( 'admin_menu', 'add_menu' );
		$this->add_action( 'admin_menu', 'create_meta_box' );
		$this->add_action( 'admin_print_styles' );

		$this->add_filter( 'threewp_activity_monitor_list_activities' );

		if ( $this->get_site_option( 'override_child_permalinks' ) )
		{
			$this->add_filter( 'post_link', 10, 3 );
			$this->add_filter( 'post_type_link', 'post_link', 10, 3 );
		}

		if ( $this->get_site_option( 'canonical_url' ) )
			$this->add_action( 'wp_head', 1 );

		$this->permalink_cache = new \stdClass;
	}

	public function add_menu()
	{
		add_submenu_page( 'profile.php', 'ThreeWP Broadcast', $this->_( 'Broadcast' ), 'edit_posts', 'ThreeWP_Broadcast', array ( &$this, 'user' ) );
		if ( $this->role_at_least( $this->get_site_option( 'role_link' ) ) )
		{
			$this->add_action( 'post_row_actions', 10, 2 );
			$this->add_action( 'page_row_actions', 'post_row_actions', 10, 2 );

			$this->add_filter( 'manage_posts_columns' );
			$this->add_action( 'manage_posts_custom_column', 10, 2 );

			$this->add_filter( 'manage_pages_columns', 'manage_posts_columns' );
			$this->add_action( 'manage_pages_custom_column', 'manage_posts_custom_column', 10, 2 );

			$this->add_action( 'wp_trash_post', 'trash_post' );
			$this->add_action( 'trash_post' );
			$this->add_action( 'trash_page', 'trash_post' );

			$this->add_action( 'untrash_post' );
			$this->add_action( 'untrash_page', 'untrash_post' );

			$this->add_action( 'delete_post' );
			$this->add_action( 'delete_page', 'delete_post' );
		}
		add_submenu_page( 'options-general.php', 'ThreeWP Broadcast', 'Broadcast', 'activate_plugins', 'ThreeWP_Broadcast', array ( &$this, 'admin' ) );
	}

	public function admin_print_styles()
	{
		$load = false;

		$pages = array(get_class(), 'ThreeWP_Activity_Monitor' );

		if ( isset( $_GET['page'] ) )
			$load |= in_array( $_GET['page'], $pages);

		foreach(array( 'post-new.php', 'post.php' ) as $string)
			$load |= strpos( $_SERVER['SCRIPT_FILENAME'], $string) !== false;

		if (!$load)
			return;

		wp_enqueue_script( '3wp_broadcast', '/' . $this->paths['path_from_base_directory'] . '/js/user.js' );
		wp_enqueue_style( '3wp_broadcast', '/' . $this->paths['path_from_base_directory'] . '/css/ThreeWP_Broadcast.css', false, '0.0.1', 'screen' );
	}

	// --------------------------------------------------------------------------------------------
	// ----------------------------------------- Activate / Deactivate
	// --------------------------------------------------------------------------------------------

	public function activate()
	{
		if ( !$this->is_network )
			wp_die("This plugin requires a Wordpress Network installation.");

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

		$db_ver = $this->get_site_option( 'database_version', 1 );

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

	public function admin()
	{
		$this->load_language();

		$tabs = $this->tabs();
		$tabs->tab( 'settings' )		->callback_this( 'admin_settings' )			->name_( 'Settings' );
		$tabs->tab( 'post_types' )		->callback_this( 'admin_post_types' )		->name_( 'Post types' );
		$tabs->tab( 'required_list' )	->callback_this( 'admin_required_list' )	->name_( 'Required list' );
		$tabs->tab( 'blacklist' )		->callback_this( 'admin_blacklist' )		->name_( 'Blacklist' );
		$tabs->tab( 'uninstall' )		->callback_this( 'admin_uninstall' )		->name_( 'Uninstall' );
		echo $tabs;
	}

	public function user()
	{
		$this->load_language();

		$tabs = $this->tabs()->default_tab( 'user_help' )->get_key( 'action' );

		if ( isset( $_GET['action'] ) )
		{
			switch( $_GET[ 'action' ] )
			{
				case 'find_orphans':
					$tabs->tab( 'find_orphans' )
						->name_( 'Find orphans' );
					break;
				case 'user_trash':
					$tabs->tab( 'user_trash' )
						->name_( 'Trash2' );
					break;
				case 'unlink':
					$tabs->tab( 'unlink' )
						->name_( 'Unlink' );
					break;
			}
		}

		$tabs->tab( 'user_help' )->name_( 'Help' );

		if ( $this->role_at_least( $this->get_site_option( 'role_groups' ) ) )
			$tabs->tab( 'user_edit_groups' )
				->name_( 'ThreeWP Broadcast groups' );

		echo $tabs;
	}

	public function admin_settings()
	{
		$form = $this->form();
		$roles = $this->roles_as_options();

		if ( isset( $_POST['save'] ) )
		{
			// Save the exceptions
			$custom_field_exceptions = trim( $_POST['custom_field_exceptions'] );
			$this->update_site_option( 'custom_field_exceptions', $custom_field_exceptions );
			$this->update_site_option( 'save_post_priority', intval( $_POST['save_post_priority'] ) );
			$this->update_site_option( 'override_child_permalinks', isset( $_POST['override_child_permalinks'] ) );
			$this->update_site_option( 'canonical_url', isset( $_POST['canonical_url'] ) );
			foreach(array( 'role_broadcast',
				'role_link',
				'role_broadcast_as_draft',
				'role_broadcast_scheduled_posts',
				'role_groups',
				'role_taxonomies',
				'role_taxonomies_create',
				'role_custom_fields'
			) as $key)
				$this->update_site_option( $key, (isset( $roles[$_POST[$key]] ) ? $_POST[$key] : 'super_admin' ) );
			$this->message( 'Options saved!' );
		}

		$inputs = array(
			'role_broadcast' => array(
				'name' => 'role_broadcast',
				'type' => 'select',
				'label' => 'Broadcast access role',
				'value' => $this->get_site_option( 'role_broadcast' ),
				'description' => 'The broadcast access role is the user role required to use the broadcast function at all.',
				'options' => $roles,
			),
			'role_link' => array(
				'name' => 'role_link',
				'type' => 'select',
				'label' => 'Link access role',
				'value' => $this->get_site_option( 'role_link' ),
				'description' => 'When a post is linked with broadcasted posts, the child posts are updated / deleted when the parent is updated.',
				'options' => $roles,
			),
			'role_broadcast_as_draft' => array(
				'name' => 'role_broadcast_as_draft',
				'type' => 'select',
				'label' => 'Draft broadcast access role',
				'value' => $this->get_site_option( 'role_broadcast_as_draft' ),
				'description' => 'Which role is needed to broadcast drafts?',
				'options' => $roles,
			),
			'role_broadcast_scheduled_posts' => array(
				'name' => 'role_broadcast_scheduled_posts',
				'type' => 'select',
				'label' => 'Scheduled posts access role',
				'value' => $this->get_site_option( 'role_broadcast_scheduled_posts' ),
				'description' => 'Which role is needed to broadcast scheduled (future) posts?',
				'options' => $roles,
			),
			'role_groups' => array(
				'name' => 'role_groups',
				'type' => 'select',
				'label' => 'Group access role',
				'value' => $this->get_site_option( 'role_groups' ),
				'description' => 'Role needed to administer their own groups?',
				'options' => $roles,
			),
			'role_taxonomies' => array(
				'name' => 'role_taxonomies',
				'type' => 'select',
				'label' => 'Taxonomies broadcast role',
				'value' => $this->get_site_option( 'role_taxonomies' ),
				'description' => 'Which role is needed to allow taxonomy broadcasting? The taxonomies must have the same slug on all blogs.',
				'options' => $roles,
			),
			'role_taxonomies_create' => array(
				'name' => 'role_taxonomies_create',
				'type' => 'select',
				'label' => 'Taxonomies creation role',
				'value' => $this->get_site_option( 'role_taxonomies_create' ),
				'description' => "Which role is needed to allow taxonomy creation? Taxonomy are created if they don't exist.",
				'options' => $roles,
			),
			'role_custom_fields' => array(
				'name' => 'role_custom_fields',
				'type' => 'select',
				'label' => 'Custom field broadcast role',
				'value' => $this->get_site_option( 'role_custom_fields' ),
				'description' => 'Which role is needed to allow custom field broadcasting?',
				'options' => $roles,
			),
			'save_post_priority' => array(
				'name' => 'save_post_priority',
				'type' => 'text',
				'label' => 'Action priority',
				'value' => $this->get_site_option( 'save_post_priority' ),
				'size' => 3,
				'maxlength' => 10,
				'description' => 'A higher save-post-action priority gives other plugins more time to add their own custom fields before the post is broadcasted. <em>Raise</em> this value if you notice that plugins that use custom fields aren\'t getting their data broadcasted, but 640 should be enough for everybody.',
			),
			'custom_field_exceptions' => array(
				'name' => 'custom_field_exceptions',
				'type' => 'text',
				'label' => 'Custom field exceptions',
				'value' => $this->get_site_option( 'custom_field_exceptions' ),
				'maxlength' => 128,
				'size' => 40,
				'description' => 'Custom fields that begin with underscores (internal fields) are ignored. If you know of an internal field that should be broadcasted, write it down here. Separate the fields with a space. The exception can be any part of the key string.',
			),
			'override_child_permalinks' => array(
				'name' => 'override_child_permalinks',
				'type' => 'checkbox',
				'label' => 'Override child post permalinks',
				'checked' => $this->get_site_option( 'override_child_permalinks' ),
				'description' => 'This will force child posts (those broadcasted to other sites) to keep the original post\'s permalink. If checked, child posts will link back to the original post on the original site.',
			),
			'canonical_url' => array(
				'name' => 'canonical_url',
				'type' => 'checkbox',
				'label' => 'Canonical URLs',
				'checked' => $this->get_site_option( 'canonical_url' ),
				'description' => 'Child posts have their canonical URLs pointed to the URL of the parent post.',
			),
			'save' => array(
				'name' => 'save',
				'type' => 'submit',
				'value' => 'Save options',
				'css_class' => 'button-primary',
			),
		);

		$r = $form->start() . $this->display_form_table( $inputs ) . $form->stop();
		echo $r;
	}

	public function admin_post_types()
	{
		$form = $this->form2();

		$post_types = $this->get_site_option( 'post_types' );

		$input_pt = $form->text( 'post_types' )
			->label( 'Post types to broadcast' )
			->size( 50, 1024 )
			->value( $post_types );
		$label = $this->_( 'A space-separated list of post types that have broadcasting enabled. The default value is <code>post page</code>.' );
		$input_pt->description->set_unfiltered_label( $label );

		$form->primary_button( 'save_post_types' )
			->value( $this->_( 'Save the allowed post types' ) );

		if ( $form->is_posting() )
		{
			$form->post()->use_post_values();
			$post_types = $form->input( 'post_types' )->get_value();
			$this->update_site_option( 'post_types', $post_types);
			$this->message( 'Custom post types saved!' );
		}

		echo '

		<p>' . $this->_( 'This page lets the admin select which post types in the network should be able to be broadcasted.' ) . '</p>

		<p>' . $this->_( 'Post types must be specified using their internal Wordpress names with a space between each. It is not possible to automatically make a list of available post types on the whole network because of limitation within Wordpress.' ) . '</p>

		'.$form->open_tag().'

		' . $form->display_form_table() .'

		'.$form->close_tag().'
		';
	}

	public function admin_required_list()
	{
		$blogs = $this->get_blog_list();
		$form = $this->form();

		if ( isset( $_POST['save'] ) )
		{
			$this->update_site_option( 'always_use_required_list', isset( $_POST['always_use_required_list'] ) );
			$required = '';
			if ( isset( $_POST['broadcast']['groups']['required'] ) )
				$required = implode( ',', array_keys( $_POST['broadcast']['groups']['required'] ) );
			$this->update_site_option( 'requiredlist', $required );
			$this->message( 'Options saved!' );
		}

		$inputs = array(
			'always_use_required_list' => array(
				'name' => 'always_use_required_list',
				'type' => 'checkbox',
				'label' => 'Always use the required list',
				'value' => $this->get_site_option( 'always_use_required_list' ),
			),
			'save' => array(
				'name' => 'save',
				'type' => 'submit',
				'value' => 'Save options',
				'css_class' => 'button-primary',
			),
		);

		$requiredBlogs = $this->get_required_blogs();

		echo '
			'.$form->start().'

			<p>
				The required list specifies which blogs users with write access must broadcast to.
				The required list can also be used to force users to broadcast to the below-speficied blogs: uncheck the option below.
			</p>

			<p>The required list takes preference over the blacklist: if blogs are in both, they will be required.</p>

			<p>
				'.$form->make_input( $inputs['always_use_required_list'] ).' '.$form->make_label( $inputs['always_use_required_list'] ).'
			</p>

			<p>Select which blogs the user will be required to broadcast to.</p>

			'.$this->show_group_blogs(array(
				'blogs' => $blogs,
				'nameprefix' => 'required',
				'selected' => $requiredBlogs,
			) ).'

			<p>
				'.$form->make_input( $inputs['save'] ).'
			</p>

			'.$form->stop().'
		';
	}

	public function admin_blacklist()
	{
		$blogs = $this->get_blog_list();
		$form = $this->form();

		if ( isset( $_POST['save'] ) )
		{
			$blacklist = '';
			if ( isset( $_POST['broadcast']['groups']['blacklist'] ) )
				$blacklist = implode( ',', array_keys( $_POST['broadcast']['groups']['blacklist'] ) );
			$this->update_site_option( 'blacklist', $blacklist );
			$this->message( 'Options saved!' );
		}

		$inputs = array(
			'save' => array(
				'name' => 'save',
				'type' => 'submit',
				'value' => 'Save options',
				'css_class' => 'button-primary',
			),
		);

		$blacklistedBlogs = explode( ',', $this->get_site_option( 'blacklist' ) );
		$blacklistedBlogs = array_flip( $blacklistedBlogs);

		echo '
			'.$form->start().'

			<p>The blacklist specifies which blogs the users may never broadcast to, even if they\'ve got write access.</p>

			'.$this->show_group_blogs(array(
				'blogs' => $blogs,
				'nameprefix' => 'blacklist',
				'selected' => $blacklistedBlogs,
			) ).'

			<p>
				'.$form->make_input( $inputs['save'] ).'
			</p>

			'.$form->stop().'
		';
	}

	public function user_edit_groups()
	{
		$user_id = $this->user_id();		// Convenience.
		$form = $this->form();

		// Get a list of blogs that this user can write to.
		$blogs = $this->list_user_writable_blogs( $user_id );

		$data = $this->sql_user_get( $user_id );

		if ( isset( $_POST['groupsSave'] ) )
		{
			$newGroups = array();
			foreach( $data['groups'] as $groupID=>$ignore)
			{
				if ( isset( $_POST['broadcast']['groups'][$groupID] ) )
				{
					$newGroups[$groupID]['name'] = $data['groups'][$groupID]['name'];
					$selectedBlogs =  $_POST['broadcast']['groups'][$groupID];
					$newGroups[$groupID]['blogs'] = array_flip(array_keys( $selectedBlogs) );

					// Notify activity monitor that a group has changed.
					$blog_text = count( $newGroups[$groupID]['blogs'] ) . ' ';
					if ( count( $newGroups[$groupID]['blogs'] ) < 2 )
						$blog_text .= 'blog: ';
					else
						$blog_text .= 'blogs: ';

					$blogs_array = array();
					foreach( $newGroups[$groupID]['blogs'] as $blogid => $ignore )
						$blogs_array[] = $blogs[$blogid]['blogname'];

					$blog_text .= '<em>' . implode( '</em>, <em>', $blogs_array) . '</em>';

					$blog_text .= '.';
					do_action( 'threewp_activity_monitor_new_activity', array(
						'activity_id' => '3broadcast_group_added',
						'activity_strings' => array(
							'' => '%user_display_name_with_link% updated the blog group <em>' . $newGroups[$groupID]['name'] . '</em> with ' . $blog_text,
						),
					) );
				}
				else
				{
					do_action( 'threewp_activity_monitor_new_activity', array(
						'activity_id' => '3broadcast_group_deleted',
						'activity_strings' => array(
							'' => '%user_display_name_with_link% deleted the blog group <em>' . $data['groups'][$groupID]['name'] . '</em>',
						),
					) );
					unset( $data['groups'][$groupID] );
				}
			}
			$data['groups'] = $newGroups;
			$this->sql_user_set( $user_id, $data);
			$this->message( $this->_( 'Group blogs have been saved.' ) );
		}

		if ( isset( $_POST['groupCreate'] ) )
		{
			$groupName = stripslashes( trim( $_POST['groupName'] ) );
			if ( $groupName == '' )
				$this->error( $this->_( 'The group name may not be empty!' ) );
			else
			{
				do_action( 'threewp_activity_monitor_new_activity', array(
					'activity_id' => '3broadcast_group_modified',
					'activity_strings' => array(
						'' => '%user_display_name_with_link% created the blog group <em>' . $groupName . '</em>',
					),
				) );

				$data['groups'][] = array( 'name' => $groupName, 'blogs' => array() );
				$this->sql_user_set( $user_id, $data);
				$this->message( $this->_( 'The group has been created!' ) );
			}
		}

		$groupsText = '';
		if (count( $data['groups'] ) == 0)
			$groupsText = '<p>'.$this->_( 'You have not created any groups yet.' ).'</p>';
		foreach( $data['groups'] as $groupID=>$groupData)
		{
			$id = 'broadcast_group_'.$groupID;
			$groupsText .= '
				<div class="threewp_broadcast_group">
					<h4>'.$this->_( 'Group' ).': '.$groupData['name'].'</h4>

					<div id="'.$id.'">
						'.$this->show_group_blogs(array(
							'blogs' => $blogs,
							'nameprefix' => $groupID,
							'selected' => $groupData['blogs'],
						) ).'
					</div>
				</div>
			';
		}

		$inputs = array(
			'groupsSave' => array(
				'name' => 'groupsSave',
				'type' => 'submit',
				'value' => $this->_( 'Save groups' ),
				'css_class' => 'button-primary',
			),
			'groupName' => array(
				'name' => 'groupName',
				'type' => 'text',
				'label' => $this->_( 'New group name' ),
				'size' => 25,
				'maxlength' => 200,
			),
			'groupCreate' => array(
				'name' => 'groupCreate',
				'type' => 'submit',
				'value' => $this->_( 'Create the new group' ),
				'css_class' => 'button-secondary',
			),
		);

		echo '
			<h3>'.$this->_( 'Your groups' ).'</h3>

			'.$form->start().'

			'.$groupsText.'

			<p>
				'.$form->make_input( $inputs['groupsSave'] ).'
			</p>

			'.$form->stop().'

			<h3>'.$this->_( 'Create a new group' ).'</h3>

			'.$form->start().'

			<p>
				'.$form->make_label( $inputs['groupName'] ).' '.$form->make_input( $inputs['groupName'] ).'
			</p>

			<p>
				'.$form->make_input( $inputs['groupCreate'] ).'
			</p>

			'.$form->stop().'

			<h3>'.$this->_( 'Delete' ).'</h3>

			<p>
				'.$this->_( 'To <strong>delete</strong> a group, leave all blogs in that group unmarked and then save.' ).'
			</p>
		';
	}

	/**
		Finds orhpans for a specific post.
	**/
	public function find_orphans()
	{
		$current_blog_id = get_current_blog_id();
		$nonce = $_GET['_wpnonce'];
		$post_id = $_GET['post'];

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
		$user_id = $this->user_id();		// Convenience.
		$blogs = $this->list_user_writable_blogs( $user_id );

		$orphans = array();

		foreach( $blogs as $blog )
		{
			$temp_blog_id = $blog[ 'blog_id' ];
			if ( $temp_blog_id == $current_blog_id )
				continue;

			if ( $broadcast_data->has_linked_child_on_this_blog( $temp_blog_id ) )
				continue;

			switch_to_blog( $temp_blog_id );

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
				$orphans[ $temp_blog_id ] = $orphan;
			}

			restore_current_blog();
		}

		if ( isset( $_POST['action_submit'] ) && isset( $_POST['blogs'] ) )
		{
			if ( $_POST['action'] == 'link' )
			{
				foreach( $orphans as $blog => $orphan )
				{
					if ( isset( $_POST[ 'blogs' ][ $blog ] [ $orphan->ID ] ) )
					{
						$broadcast_data->add_linked_child( $blog, $orphan->ID );
						unset( $orphans[ $blog ] );		// There can only be one orphan per blog, so we're not interested in the blog anymore.

						$child_broadcast_data = $this->get_post_broadcast_data( $blog, $orphan->ID );
						$child_broadcast_data->set_linked_parent( $current_blog_id, $post_id );
						$this->set_post_broadcast_data( $blog, $orphan->ID, $child_broadcast_data );
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
			foreach( $orphans as $blog => $orphan )
			{
				$select = array(
					'type' => 'checkbox',
					'checked' => false,
					'label' => $orphan->ID,
					'name' => $orphan->ID,
					'nameprefix' => '[blogs][' . $blog . ']',
				);

				$t_body .= '
					<tr>
						<th scope="row" class="check-column">' . $form->make_input( $select ) . ' <span class="screen-reader-text">' . $form->make_label( $select ) . '</span></th>
						<td><a href="' . $orphan->permalink . '">' . $blogs[ $blog ][ 'blogname' ] . '</a></td>
					</tr>
				';
			}

			$input_actions = array(
				'type' => 'select',
				'name' => 'action',
				'label' => $this->_('With the selected rows'),
				'options' => array(
					array( 'value' => '', 'text' => $this->_( 'Do nothing' ) ),
					array( 'value' => 'link', 'text' => $this->_( 'Create link' ) ),
				),
			);

			$input_action_submit = array(
				'type' => 'submit',
				'name' => 'action_submit',
				'value' => $this->_('Apply'),
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
						<th class="check-column">' . $form->make_input( $select ) . '<span class="screen-reader-text">' . $this->_('Selected') . '</span></th>
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

	/**
		Trashes a broadcasted post.
	**/
	public function user_trash()
	{
		// Check that we're actually supposed to be removing the link for real.
		global $blog_id;
		$nonce = $_GET['_wpnonce'];
		$post_id = $_GET['post'];
		$child_blog_id = $_GET['child'];

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

		echo '
			'.$this->message( $message).'
			<p>
				<a href="'.wp_get_referer().'">Back to post overview</a>
			</p>
		';
	}

	public function unlink()
	{
		// Check that we're actually supposed to be removing the link for real.
		$nonce = $_GET['_wpnonce'];
		$post_id = $_GET['post'];
		if ( isset( $_GET['child'] ) )
			$child_blog_id = $_GET['child'];

		// Generate the nonce key to check against.
		$nonce_key = 'broadcast_unlink';
		if ( isset( $child_blog_id) )
			$nonce_key .= '_' . $child_blog_id;
		$nonce_key .= '_' . $post_id;

		if ( !wp_verify_nonce( $nonce, $nonce_key) )
			die("Security check: not supposed to be unlinking broadcasted post!");

		global $blog_id;

		$broadcast_data = $this->get_post_broadcast_data( $blog_id, $post_id );
		$linked_children = $broadcast_data->get_linked_children();

		// Remove just one child?
		if ( isset( $child_blog_id) )
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

			$this->delete_post_broadcast_data( $child_blog_id, $linked_children[$child_blog_id] );
			$broadcast_data->remove_linked_child( $child_blog_id );
			$this->set_post_broadcast_data( $blog_id, $post_id, $broadcast_data );
			$message = $this->_( 'Link to child post has been removed.' );
		}
		else
		{
			$blogs_url = array();
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

	public function user_help()
	{
		echo '
			<div id="broadcast_help">
				<h2>'.$this->_( 'What is Broadcast?' ).'</h2>

				<p class="float-right">
					<img src="'.$this->paths['url'].'/screenshot-1.png" alt="" title="'.$this->_( 'What the Broadcast window looks like' ).'" />
				</p>

				<p>
					'.$this->_( 'With Broadcast you can post to several blogs at once. The broadcast window is first shown at the bottom right on the Add New post/page screen.' ).'
					'.$this->_( 'The window contains several options and a list of blogs you have access to.' ).'
				</p>

				<p>
					'.$this->_( 'Some settings might be disabled by the site administrator and if you do not have write access to any blogs, other than this one, the Broadcast window might not appear.' ).'
				</p>

				<p>
					'.$this->_( 'To use the Broadcast plugin, simply select which blogs you want to broadcast the post to and then publish the post normally.' ).'
				</p>

				<h3>'.$this->_( 'Options' ).'</h3>

				<p>
					<em>'.$this->_( 'Link this post to its children' ).'</em> '.$this->_( 'will create a link from this post (the parent) to all the broadcasted posts (children). Updating the parent will result in all the children being updated. Links to the children can be removed in the page / post overview.' ).'
				</p>

				<p class="textcenter">
					<img class="border-single" src="'.$this->paths['url'].'/screenshot-2.png" alt="" title="'.$this->_( 'Post overview with unlink options' ).'" />
				</p>

				<p>
					'.$this->_( 'When a post is linked to children, the children are overwritten when post is updated - all the taxonomies, tags and fields (including featured image) are also overwritten -  and when the parent is trashed or deleted the children get the same treatment. If you want to keep any children and delete only the parent, use the unlink links in the post overview. The unlink link below the post name removes all links and the unlinks to the right remove singular links.' ).'
				</p>

				<p>
					<em>'.$this->_( 'Broadcast categories also' ).'</em> '.$this->_( 'will also try to send the taxonomies together with the post.' ).'
					'.$this->_( 'In order to be able to broadcast the taxonomies, the selected blogs must have the same taxonomy names (slugs) as this blog.' ).'
				</p>

				<p>
					<em>'.$this->_( 'Broadcast tags also' ).'</em> '.$this->_( 'will also mark the broadcasted posts with the same tags.' ).'
				</p>

				<p>
					<em>'.$this->_( 'Broadcast custom fields' ).'</em> '.$this->_( 'will give the broadcasted posts the same custom fields as the original. Use this setting to broadcast the featured image.' ).'
				</p>

				<h3>'.$this->_( 'Groups' ).'</h3>

				<p>
					'.$this->_( 'If the site administrator allows it you may create groups to quickly select several blogs at once. To create a group, start by typing a group name in the text box and pressing the create button.' ).'
				</p>

				<p class="textcenter">
					<img class="border-single" src="'.$this->paths['url'].'/screenshot-5.png" alt="" title="'.$this->_( 'Group setup' ).'" />
				</p>

				<p>
					'.$this->_( 'Then select which blogs you want to be automatically selected when you choose this group when editing a new post. Press the save button when you are done. Your new group is ready to be used!' ).'
					'.$this->_( 'Simply choose it in the dropdown box and the blogs you specified will be automatically chosen.' ).'
				</p>

			</div>
		';
	}

	// --------------------------------------------------------------------------------------------
	// ----------------------------------------- Callbacks
	// --------------------------------------------------------------------------------------------

	public function add_meta_box( $post )
	{
		global $blog_id;
		$form = $this->form();
		$html = '';
		$published = $post->post_status == 'publish';

		// Find out if this post is already linked
		$broadcast_data = $this->get_post_broadcast_data( $blog_id, $post->ID );

		if ( $broadcast_data->get_linked_parent() !== false)
		{
			$html .='<p>';
			$html .=$this->_( 'This post is broadcasted child post. It cannot be broadcasted further.' );
			$html .='</p>';
			echo $html;
			return;
		}

		$has_linked_children = $broadcast_data->has_linked_children();

		$blogs = $this->list_user_writable_blogs( $this->user_id() );
		// Remove the blog we're currently working on from the list of writable blogs.
		unset( $blogs[$blog_id] );

		$user_id = get_current_user_id();
		$last_used_settings = $this->load_last_used_settings( $user_id );

		$post_type = $post->post_type;
		$post_type_object = get_post_type_object( $post_type );
		$post_type_supports_thumbnails = post_type_supports( $post_type, 'thumbnail' );
		$post_type_supports_custom_fields = post_type_supports( $post_type, 'custom-fields' );
		$post_type_is_hierarchical = $post_type_object->hierarchical;

		if ( $this->role_at_least( $this->get_site_option( 'role_link' ) ) )
		{
			// Check the link box is the post has been published and has children OR it isn't published yet.
			$linked = (
				( $published && $broadcast_data->has_linked_children() )
				||
				!$published
			);
			$inputBroadcastLink = array(
				'name' => 'link',
				'type' => 'checkbox',
				'nameprefix' => '[broadcast]',
				'value' => 'link',
				'checked' => $linked,
				'title' => $this->_( 'Create a link to the children, which will be updated when this post is updated, trashed when this post is trashed, etc.' ),
				'label' => $this->_( 'Link this post to its children' ),
			);
			$html .= '<p>'.$form->make_input( $inputBroadcastLink).' '.$form->make_label( $inputBroadcastLink).'</p>';
		}

		$html .= '<div style="height: 1px; background-color: #ddd;"></div>';

		if ( $this->role_at_least( $this->get_site_option( 'role_taxonomies' ) ) )
		{
			$input_taxonomies = array(
				'name' => 'taxonomies',
				'type' => 'checkbox',
				'nameprefix' => '[broadcast]',
				'value' => 'taxonomies',
				'checked' => isset( $last_used_settings['taxonomies'] ),
				'label' => $this->_( 'Broadcast taxonomies also' ),
				'title' => $this->_( 'The taxonomies must have the same name (slug) on the selected blogs.' ),
			);
			$html .='<p class="broadcast_input_taxonomies">'.$form->make_input( $input_taxonomies).' '.$form->make_label( $input_taxonomies).'</p>';
		}

		if ( $this->role_at_least( $this->get_site_option( 'role_taxonomies_create' ) ) )
		{
			$input_taxonomies_create = array(
				'name' => 'taxonomies_create',
				'type' => 'checkbox',
				'nameprefix' => '[broadcast]',
				'value' => 'taxonomies_create',
				'checked' => isset( $last_used_settings['taxonomies_create'] ),
				'label' => $this->_( 'Create taxonomies automatically' ),
				'title' => $this->_( "The taxonomies will be created if they don't exist on the selected blogs." ),
			);
			$html .='<p class="broadcast_input_taxonomies_create">&emsp;'.$form->make_input( $input_taxonomies_create).' '.$form->make_label( $input_taxonomies_create).'</p>';
		}

		if ( $this->role_at_least( $this->get_site_option( 'role_custom_fields' ) ) && ( $post_type_supports_custom_fields || $post_type_supports_thumbnails) )
		{
			$inputCustomFields = array(
				'name' => 'custom_fields',
				'type' => 'checkbox',
				'nameprefix' => '[broadcast]',
				'value' => 'custom_fields',
				'checked' => isset( $last_used_settings['custom_fields'] ),
				'title' => $this->_( 'Broadcast all the custom fields and the featured image?' ),
				'label' => $this->_( 'Broadcast custom fields' ),
			);
			$html .='<p>'.$form->make_input( $inputCustomFields).' '.$form->make_label( $inputCustomFields).'</p>';
		}

		$html .= '<div style="height: 1px; background-color: #ddd;"></div>
			<script type="text/javascript">
				var broadcast_strings = {
					select_deselect_all : "' . $this->_( 'Select / deselect all' ) . '",
					invert_selection : "' . $this->_( 'Invert selection' ) . '"
				};
			</script>
		';

		// Similarly, groups are only available to those who are allowed to use them.
		$data = $this->sql_user_get( $this->user_id() );
		if ( $this->role_at_least( $this->get_site_option( 'role_groups' ) ) && (count( $data['groups'] )>0) )
		{
			$inputGroups = array(
				'name' => 'broadcast_group',
				'type' => 'select',
				'nameprefix' => '[broadcast]',
				'label' => $this->_( 'Select blogs in group' ),
				'options' => array(array( 'value' => '', 'text' => $this->_( 'No group selected' ) )),
			);

			foreach( $data['groups'] as $groupIndex=>$groupData)
				$inputGroups['options'][] = array( 'text' => $groupData['name'], 'value' => implode( ' ', array_keys( $groupData['blogs'] ) ));

			// The javascripts just reacts on a click to the select box and selects those checkboxes that the selected group has.
			$html .='
				<p>
				'.$form->make_label( $inputGroups).' '.$form->make_input( $inputGroups).'
				</p>
			';
		}

		$blog_class = array();
		$blog_title = array();
		$selectedBlogs = array();

		// Preselect those children that this post has.
		$linked_children = $broadcast_data->get_linked_children();
		if ( count( $linked_children) > 0 )
		{
			foreach( $linked_children as $temp_blog_id => $postID)
			{
				$selectedBlogs[ $temp_blog_id ] = true;
				@$blog_class[ $temp_blog_id ] .= ' blog_is_already_linked';
				@$blog_title[ $temp_blog_id ] .= $this->_( 'This blog has already been linked.' );
			}
		}

		if ( $this->get_site_option( 'always_use_required_list' ) )
			$required_blogs = $this->get_required_blogs();
		else
			$required_blogs = array();

		foreach ( $required_blogs as $temp_blog_id => $ignore)
		{
			@$blog_class[ $temp_blog_id ] .= ' blog_is_required';
			@$blog_title[ $temp_blog_id ] .= $this->_( 'This blog is required and cannot be unselected.' );
		}

		$selectedBlogs = array_flip( array_merge(
			array_keys( $selectedBlogs),
			array_keys( $required_blogs)
		) );

		// Remove all blacklisted blogs.
		foreach( $blogs as $temp_blog_id=>$ignore)
			if ( $this->is_blog_blacklisted( $temp_blog_id) )
				unset( $blogs[ $temp_blog_id ] );

		// Disable all blogs that do not have this post type.
		// I think there's a bug in WP since it reports the same post types no matter which blog we've switch_to_blogged.
		// Therefore, no further action.

		$html .= '
			<div class="broadcast_to">
				<p class="howto">'. $this->_( 'Broadcast to:' ) .'</p>

				<div class="blogs">
					<p>' . $this->show_group_blogs(array(
									'blogs' => $blogs,
									'blog_class' => $blog_class,
									'blog_title' => $blog_title,
									'nameprefix' => 666,
									'selected' => $selectedBlogs,
									'readonly' => $required_blogs,
									'disabled' => $required_blogs,
								) ) . '
					</p>
				</div>
			</div>
		';

		// Allow plugins to modify the meta box with their own info.
		// The string, $box, must be modified or appended to using string search and replace.
		$o = new \stdClass();
		$o->post = $post;
		$o->html = $html;
		do_action( 'threewp_broadcast_add_meta_box', $o );
		echo $o->html;
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
			$activity['plugin'] = 'ThreeWP Broadcast';
			$activities[ $index ] = $activity;
		}

		return $activities;
	}

	public function save_post( $post_id )
	{
		if ( $this->is_broadcasting() )
			return;

		if (!$this->role_at_least( $this->get_site_option( 'role_broadcast' ) ) )
			return;

		$allowed_post_status = array( 'pending', 'private', 'publish' );

		if ( $this->role_at_least( $this->get_site_option( 'role_broadcast_as_draft' ) ) )
			$allowed_post_status[] = 'draft';

		if ( $this->role_at_least( $this->get_site_option( 'role_broadcast_scheduled_posts' ) ) )
			$allowed_post_status[] = 'future';

		$post = get_post( $post_id );
		$post_array = (array) $post;
		if ( !in_array( $post_array[ 'post_status' ], $allowed_post_status) )
			return;

		// Check if the user hasn't marked any blogs for forced broadcasting but it the admin wants forced blogs.
		if ( !isset( $_POST[ 'broadcast' ] ) )
		{
			// Site admin is never forced to do anything.
			if ( is_super_admin() )
				return;

			if ( ! $this->get_site_option( 'always_use_required_list' ) == true )
				return;
		}

		// Begin: Add and remove blogs

		// Are there blogs to broadcast to?
		if ( isset( $_POST[ 'broadcast' ][ 'groups' ][ '666' ] ) )
			$blogs = array_keys( $_POST[ 'broadcast' ][ 'groups' ][ '666' ] );
		else
			$blogs = array();

		$blogs = array_flip( $blogs );

		// Remove the blog we're currently working on. No point in broadcasting to ourselves.
		unset( $blogs[ get_current_blog_id() ] );

		$user_id = $this->user_id();		// Convenience.

		// Remove blacklisted
		foreach( $blogs as $blogID=>$ignore)
			if ( !$this->is_blog_user_writable( $user_id, $blogID ) )
				unset( $blogs[ $blogID ] );

		// Add required blogs.
		if ( $this->get_site_option( 'always_use_required_list' ) )
		{
			$required_blogs = $this->get_required_blogs();
			foreach( $required_blogs as $required_blog=>$ignore)
				$blogs[ $required_blog ] = $required_blog;
		}

		// End: Add and remove blogs

		// Do we actually need to to anything?
		if ( count( $blogs ) < 1 )
			return;

		$this->broadcast_post( array(
			'_POST' => $_POST,
			'blogs' => array_keys( $blogs ),
			'post' => $post,
		) );
	}

	/**
	 * Recursively adds the missing ancestors of the given source term at the
	 * target blog.
	 *
	 * @param array $source_post_term           The term to add ancestors for
	 * @param array $source_post_taxonomy       The taxonomy we're working with
	 * @param array $target_blog_terms          The existing terms at the target
	 * @param array $source_blog_taxonomy_terms The existing terms at the source
	 * @return int The ID of the target parent term
	 */
	public function insert_term_ancestors( $source_post_term, $source_post_taxonomy, $target_blog_terms, $source_blog_taxonomy_terms )
	{
		// Fetch the parent of the current term among the source terms
		foreach ( $source_blog_taxonomy_terms as $term )
		{
			if ( $term['term_id'] == $source_post_term['parent'] )
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
			if ( $term['slug'] === $source_parent['slug'] )
			{
				// The parent already exists, return its ID
				return $term['term_id'];
			}
		}

		// Does the parent also have a parent, and if so, should we create the parent?
		$target_grandparent_id = 0;
		if ( 0 != $source_parent['parent'] )
		{
			// Recursively insert ancestors, and get the newly inserted parent's ID
			$target_grandparent_id = $this->insert_term_ancestors( $source_parent, $source_post_taxonomy, $target_blog_terms, $source_blog_taxonomy_terms );
		}

		// Check if the parent exists at the target grandparent
		$term_id = term_exists( $source_parent['name'], $source_post_taxonomy, $target_grandparent_id );

		if ( is_null( $term_id ) || 0 == $term_id )
		{
			// The target parent does not exist, we need to create it
			$new_term = wp_insert_term(
				$source_parent['name'],
				$source_post_taxonomy,
				array(
					'slug'        => $source_parent['slug'],
					'description' => $source_parent['description'],
					'parent'      => $target_grandparent_id,
				)
			);

			$term_id = $new_term['term_id'];
		}
		elseif ( is_array( $term_id ) )
		{
			// The target parent exists and we got an array as response, extract parent id
			$term_id = $term_id['term_id'];
		}

		return $term_id;
	}

	public function trash_post( $post_id)
	{
		$this->trash_untrash_delete_post( 'wp_trash_post', $post_id );
	}

	public function untrash_post( $post_id)
	{
		$this->trash_untrash_delete_post( 'wp_untrash_post', $post_id );
	}

	public function delete_post( $post_id)
	{
		$this->trash_untrash_delete_post( 'wp_delete_post', $post_id );
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
				$parent_broadcast_data = $this->get_post_broadcast_data( $linked_parent_broadcast_data['blog_id'], $linked_parent_broadcast_data['post_id'] );
				$parent_broadcast_data->remove_linked_child( $blog_id );
				$this->set_post_broadcast_data( $linked_parent_broadcast_data['blog_id'], $linked_parent_broadcast_data['post_id'], $parent_broadcast_data );
			}

			$this->delete_post_broadcast_data( $blog_id, $post_id );
		}
	}

	public function manage_posts_columns( $defaults)
	{
		$defaults['3wp_broadcast'] = '<span title="'.$this->_( 'Shows which blogs have posts linked to this one' ).'">'.$this->_( 'Broadcasted' ).'</span>';
		return $defaults;
	}

	public function manage_posts_custom_column( $column_name, $post_id )
	{
		if ( $column_name != '3wp_broadcast' )
			return;

		global $blog_id;
		global $post;

		$broadcast_data = $this->get_post_broadcast_data( $blog_id, $post_id );
		if ( $broadcast_data->get_linked_parent() !== false)
		{
			$parent = $broadcast_data->get_linked_parent();
			$parent_blog_id = $parent['blog_id'];
			switch_to_blog( $parent_blog_id );
			echo $this->_(sprintf( 'Linked from %s', '<a href="' . get_bloginfo( 'url' ) . '/wp-admin/post.php?post=' .$parent['post_id'] . '&action=edit">' . get_bloginfo( 'name' ) . '</a>' ) );
			restore_current_blog();
		}

		if ( $broadcast_data->has_linked_children() )
		{
			$children = $broadcast_data->get_linked_children();

			if (count( $children) < 0)
				return;

			$display = array(); // An array makes it easy to manipulate lists
			$blogs = $this->cached_blog_list();
			foreach( $children as $blogID => $postID)
			{
				switch_to_blog( $blogID );
				$url_child = get_permalink( $postID );
				restore_current_blog();
				// The post id is for the current blog, not the target blog.
				$url_unlink = wp_nonce_url("profile.php?page=ThreeWP_Broadcast&amp;action=unlink&amp;post=$post_id&amp;child=$blogID", 'broadcast_unlink_' . $blogID . '_' . $post_id );
				$url_trash = wp_nonce_url("profile.php?page=ThreeWP_Broadcast&amp;action=user_trash&amp;post=$post_id&amp;child=$blogID", 'broadcast_trash_' . $blogID . '_' . $post_id );
				$display[] = '<div class="broadcasted_blog"><a class="broadcasted_child" href="'.$url_child.'">'.$blogs[$blogID]['blogname'].'</a>
					<div class="row-actions broadcasted_blog_actions">
						<small>
						<a href="'.$url_unlink.'" title="'.$this->_( 'Remove links to this broadcasted child' ).'">'.$this->_( 'Unlink' ).'</a>
						| <span class="trash"><a href="'.$url_trash.'" title="'.$this->_( 'Put this broadcasted child in the trash' ).'">'.$this->_( 'Trash' ).'</a></span>
						</small>
					</div>
				</div>
				';
			}
			echo '<ul><li>' . implode( '</li><li>', $display) . '</li></ul>';
		}
		else
			echo '&nbsp;';
	}

	public function post_row_actions( $actions, $post )
	{
		global $blog_id;
		$broadcast_data = $this->get_post_broadcast_data( $blog_id, $post->ID );
		if ( $broadcast_data->has_linked_children() )
			$actions = array_merge( $actions, array(
				'broadcast_unlink' => '<a href="'.wp_nonce_url("profile.php?page=ThreeWP_Broadcast&amp;action=unlink&amp;post=".$post->ID."", 'broadcast_unlink_' . $post->ID).'" title="'.$this->_( 'Remove links to all the broadcasted children' ).'">'.$this->_( 'Unlink' ).'</a>',
			) );
		$actions['broadcast_find_orphans'] = '<a href="'.wp_nonce_url("profile.php?page=ThreeWP_Broadcast&amp;action=find_orphans&amp;post=".$post->ID."", 'broadcast_find_orphans_' . $post->ID).'" title="'.$this->_( 'Find posts on other blogs that are identical to this post' ).'">'.$this->_( 'Find orphans' ).'</a>';
		return $actions;
	}

	public function create_meta_box()
	{
		if ( $this->role_at_least( $this->get_site_option( 'role_broadcast' ) ) )
		{
			// If the user isn't a site admin, or if the user doesn't have any other blogs to write to...
			if ( $this->role_at_least( 'super_admin' ) || count( $this->list_user_writable_blogs( $this->user_id() ) ) > 0 )	// User always has at least one to write to, if he's gotten THIS far.
			{
				$this->load_language();
				$post_types = $this->get_site_option( 'post_types' );
				foreach( explode( ' ', $post_types ) as $post_type )
					add_meta_box( 'threewp_broadcast', $this->_( 'Broadcast' ), array( &$this, 'add_meta_box' ), $post_type, 'side', 'low' );
				add_action( 'save_post', array( &$this, 'save_post' ), $this->get_site_option( 'save_post_priority' ) );
			}
		}
	}

	public function post_link( $link, $post )
	{
		global $blog_id;

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

		switch_to_blog( $linked_parent['blog_id'] );
		$post = get_post( $linked_parent['post_id'] );
		$permalink = get_permalink( $post );
		restore_current_blog();

		$this->permalink_cache->$key = $permalink;

		return $permalink;
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
		switch_to_blog( $linked_parent['blog_id'] );
		$url = get_permalink( $linked_parent['post_id'] );
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
		@brief		Broadcast a post.
		@details

		The options array should contain:
		- @i array @b _POST The _POST array.
		- @i array @b blogs An array of blogs to broadcast to. Blog_ID as value.
		- @i array @b post The WP_Post object.

		The _POST array should contain the following:
		- @i string	@b post_type	The type of post.
		- @i int	@b post_parent	The ID of the post's parent.
		- @i array	@b broadcast
		- @i bool	@b broadcast[custom_fields]		True if custom fields should be broadcasted.
		- @i bool	@b broadcast[link]				True to link the post to the children.
		- @i bool	@b broadcast[taxonomies]		True to broadcast the post's taxonomies.
		- @i bool	@b broadcast[taxonomies_create]	True if create unknown taxonomies.
		- @i bool	@b sticky						True to mark the child posts as stickies.

		@param		array		$options		Options array.
		@since		20130603
	**/
	public function broadcast_post( $options )
	{
		$options = self::merge_objects( array(), $options );

		require_once( 'include/Broadcasting_Data.php' );
		$this->broadcasting_data = new Broadcasting_Data();		// Global copy.
		$bcd = $this->broadcasting_data;						// Convenience.
		$bcd->_POST( $options->_POST );

		// If there is no post parent set, set a dummy one.
		if ( ! isset( $bcd->_POST[ 'post_parent' ] ) )
			$bcd->_POST[ 'post_parent' ] = null;

		$bcd->blog_id->parent = get_current_blog_id();
		$bcd->blog_id->children = $options->blogs;
		$bcd->post = $options->post;
		$bcd->upload_dir = wp_upload_dir();

		$bcd->post_type = $bcd->_POST[ 'post_type' ];
		$bcd->post_type_object = get_post_type_object( $bcd->post_type );
		$bcd->post_type_supports_thumbnails = post_type_supports( $bcd->post_type, 'thumbnail' );
		$bcd->post_type_supports_custom_fields = post_type_supports( $bcd->post_type, 'custom-fields' );
		$bcd->post_type_is_hierarchical = $bcd->post_type_object->hierarchical;

		// Create new post data from the original stuff.
		$bcd->new_post = (array) $bcd->post;
		foreach( array( 'comment_count', 'guid', 'ID', 'menu_order', 'post_parent' ) as $key )
			unset( $bcd->new_post[ $key ] );

		$bcd->link = ( $this->role_at_least( $this->get_site_option( 'role_link' ) ) && isset( $bcd->_POST[ 'broadcast' ][ 'link' ] ) );
		if ( $bcd->link)
		{
			// Prepare the broadcast data for linked children.
			$broadcast_data = $this->get_post_broadcast_data( $bcd->blog_id->parent, $bcd->post->ID );

			// Does this post type have parent support, so that we can link to a parent?
			if ( $bcd->post_type_is_hierarchical && $bcd->_POST[ 'post_parent' ] > 0)
			{
				$bcd->parent_post_id = $bcd->_POST[ 'post_parent' ];
				$parent_broadcast_data = $this->get_post_broadcast_data( $bcd->blog_id->parent, $bcd->parent_post_id );
			}
		}

		$bcd->taxonomies = (
			$this->role_at_least( $this->get_site_option( 'role_taxonomies' ) )
			&&
			isset( $bcd->_POST[ 'broadcast' ][ 'taxonomies' ] )
		);
		$bcd->create_taxonomies = ( $this->role_at_least( $this->get_site_option( 'role_taxonomies_create' ) ) && isset( $bcd->_POST[ 'broadcast' ][ 'taxonomies_create' ] ) );
		if ( $bcd->taxonomies )
		{
			$source_blog_taxonomies = get_object_taxonomies( array(
				'object_type' => $bcd->post_type,
			), 'array' );
			$source_post_taxonomies = array();
			foreach( $source_blog_taxonomies as $source_blog_taxonomy => $taxonomy )
			{
				// Source blog taxonomy terms are used for creating missing target term ancestors
				$source_blog_taxonomies[ $source_blog_taxonomy ] = array(
					'taxonomy' => $taxonomy,
					'terms'    => $this->get_current_blog_taxonomy_terms( $source_blog_taxonomy ),
				);
				$source_post_taxonomies[ $source_blog_taxonomy ] = get_the_terms( $bcd->post->ID, $source_blog_taxonomy );
			}
		}

		require_once( 'include/AttachmentData.php' );
		$bcd->attachment_data = array();
		$attached_files = get_children( 'post_parent='.$bcd->post->ID.'&post_type=attachment' );
		$has_attached_files = count( $attached_files) > 0;
		if ( $has_attached_files )
			foreach( $attached_files as $attached_file )
				$bcd->attachment_data[ $attached_file->ID ] = AttachmentData::from_attachment_id( $attached_file, $bcd->upload_dir );

		$bcd->custom_fields = (
			$this->role_at_least( $this->get_site_option( 'role_custom_fields' ) )
			&&
			isset( $bcd->_POST[ 'broadcast' ][ 'custom_fields' ] )
			&&
			( $bcd->post_type_supports_custom_fields || $bcd->post_type_supports_thumbnails)
		);
		if ( $bcd->custom_fields )
		{
			$bcd->post_custom_fields = get_post_custom( $bcd->post->ID );

			$bcd->has_thumbnail = isset( $bcd->post_custom_fields[ '_thumbnail_id' ] );
			if ( $bcd->has_thumbnail )
			{
				$bcd->thumbnail_id = $bcd->post_custom_fields[ '_thumbnail_id' ][0];
				$bcd->thumbnail = get_post( $bcd->thumbnail_id );
				unset( $bcd->post_custom_fields[ '_thumbnail_id' ] ); // There is a new thumbnail id for each blog.
				$bcd->attachment_data[ 'thumbnail' ] = AttachmentData::from_attachment_id( $bcd->thumbnail, $bcd->upload_dir);
				// Now that we know what the attachment id the thumbnail has, we must remove it from the attached files to avoid duplicates.
				unset( $bcd->attachment_data[ $bcd->thumbnail_id ] );
			}

			// Remove all the _internal custom fields.
			$bcd->post_custom_fields = $this->keep_valid_custom_fields( $bcd->post_custom_fields );
		}

		// Sticky isn't a tag, taxonomy or custom_field.
		$bcd->post_is_sticky = @( $bcd->_POST[ 'sticky' ] == 'sticky' );

		// And now save the user's last settings.
		$user_id = get_current_user_id();
		$this->save_last_used_settings( $user_id, $bcd->_POST[ 'broadcast' ] );

		$to_broadcasted_blogs = array();				// Array of blog names that we're broadcasting to. To be used for the activity monitor action.
		$to_broadcasted_blog_details = array(); 		// Array of blog and post IDs that we're broadcasting to. To be used for the activity monitor action.

		// To prevent recursion
		$this->broadcasting = $bcd->_POST[ 'broadcast' ];
		unset( $_POST[ 'broadcast' ] );

		do_action( 'threewp_brodcast_broadcasting_started', $bcd );

		foreach( $bcd->blog_id->children as $child_blog_id )
		{
			// Another safety check. Goes with the safety dance.
			if ( !$this->is_blog_user_writable( $user_id, $child_blog_id ) )
				continue;
			switch_to_blog( $child_blog_id );
			$bcd->blog_id->child = $child_blog_id;

			do_action( 'threewp_brodcast_broadcasting_after_switch_to_blog', $bcd );

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
				$bcd->new_post[ 'ID' ] = wp_insert_post( $bcd->new_post );

				if ( $bcd->link )
					$broadcast_data->add_linked_child( $child_blog_id, $bcd->new_post[ 'ID' ] );
			}

			if ( $bcd->taxonomies )
			{
				foreach( $source_post_taxonomies as $source_post_taxonomy => $source_post_terms )
				{
					// If we're updating a linked post, remove all the taxonomies and start from the top.
					if ( $bcd->link )
						if ( $broadcast_data->has_linked_child_on_this_blog() )
							wp_set_object_terms( $bcd->new_post[ 'ID' ], array(), $source_post_taxonomy );

					// Skip this iteration if there are no terms
					if ( ! is_array( $source_post_terms ) )
						continue;

					// Get a list of terms that the target blog has.
					$target_blog_terms = $this->get_current_blog_taxonomy_terms( $source_post_taxonomy );

					// Go through the original post's terms and compare each slug with the slug of the target terms.
					$taxonomies_to_add_to = array();
					$have_created_taxonomies = false;
					foreach( $source_post_terms as $source_post_term )
					{
						$found = false;
						$source_slug = $source_post_term->slug;
						foreach( $target_blog_terms as $target_blog_term )
						{
							if ( $target_blog_term[ 'slug' ] == $source_slug )
							{
								$found = true;
								$taxonomies_to_add_to[ $target_blog_term[ 'term_id' ] ] = intval( $target_blog_term[ 'term_id' ] );
								break;
							}
						}

						// Should we create the taxonomy if it doesn't exist?
						if ( ! $found && $bcd->create_taxonomies )
						{
							// Does the term have a parent?
							$target_parent_id = 0;
							if ( $source_post_term->parent != 0 )
							{
								// Recursively insert ancestors if needed, and get the target term's parent's ID
								$target_parent_id = $this->insert_term_ancestors(
									(array) $source_post_term,
									$source_post_taxonomy,
									$target_blog_terms,
									$source_blog_taxonomies[ $source_post_taxonomy ][ 'terms' ]
								);
							}

							$new_taxonomy = wp_insert_term(
								$source_post_term->name,
								$source_post_taxonomy,
								array(
									'slug' => $source_post_term->slug,
									'description' => $source_post_term->description,
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
								$have_created_taxonomies = true;
							}

							$taxonomies_to_add_to[] = $term_taxonomy_id;
						}
					}

					if ( $bcd->create_taxonomies )
						$this->sync_terms( $source_post_taxonomy, $bcd->blog_id->parent, $child_blog_id );

					if ( count( $taxonomies_to_add_to) > 0 )
					{
						// This relates to the bug mentioned in the method $this->set_term_parent()
						delete_option( $source_post_taxonomy . '_children' );
						clean_term_cache( '', $source_post_taxonomy );
						wp_set_object_terms( $bcd->new_post[ 'ID' ], $taxonomies_to_add_to, $source_post_taxonomy );
					}
				}
			}

			// Remove the current attachments.
			$attachments_to_remove = get_children( 'post_parent='.$bcd->new_post[ 'ID' ].'&post_type=attachment' );
			foreach ( $attachments_to_remove as $attachment_to_remove )
				wp_delete_attachment( $attachment_to_remove->ID );

			// Copy the attachments
			$bcd->copied_attachments = array();
			foreach( $bcd->attachment_data as $key => $attachment )
			{
				if ( $key != 'thumbnail' )
				{
					$o = clone( $bcd );
					$o->attachment_data = $attachment;
					$o->post_id = $bcd->new_post[ 'ID' ];
					$new_attachment_id = $this->copy_attachment( $o );
					$a = new \stdClass();
					$a->old = $attachment;
					$a->new = get_post( $new_attachment_id );
					$bcd->copied_attachments[] = $a;
				}
			}

			// If there were any image attachments copied...
			if ( count( $bcd->copied_attachments ) > 0 )
			{
				// Update the URLs in the post to point to the new images.
				$new_upload_dir = wp_upload_dir();
				$unmodified_post = (object)$bcd->new_post;
				$modified_post = clone( $unmodified_post );
				foreach( $bcd->copied_attachments as $a )
				{
					// Replace the GUID with the new one.
					$modified_post->post_content = str_replace( $a->old->guid, $a->new->guid, $modified_post->post_content );
					// And replace the IDs present in any image captions.
					$modified_post->post_content = str_replace( 'id="attachment_' . $a->old->id . '"', 'id="attachment_' . $a->new->id . '"', $modified_post->post_content );
				}

				// Update any [gallery] shortcodes found.
				$rx = get_shortcode_regex();
				$matches = '';
				preg_match_all( '/' . $rx . '/', $modified_post->post_content, $matches );

				// [2] contains only the shortcode command / key. No options.
				foreach( $matches[ 2 ] as $index => $key )
				{
					// Look for only the gallery shortcode.
					if ( $key !== 'gallery' )
						continue;
					// Complete matches are in 0.
					$old_shortcode = $matches[ 0 ][ $index ];
					// Extract the IDs
					$ids = preg_replace( '/.*ids=\"([0-9,]*)".*/', '\1', $old_shortcode );
					// And put in the new IDs.
					$new_ids = array();
					// Try to find the equivalent new attachment ID.
					// If no attachment found
					foreach( explode( ',', $ids ) as $old_id )
						foreach( $bcd->copied_attachments as $a )
						{
							if ( $old_id == $a->old->id )
								$new_ids[] = $a->new->ID;
						}
					$new_shortcode = str_replace( $ids, implode( ',', $new_ids ) , $old_shortcode );
					$modified_post->post_content = str_replace( $old_shortcode, $new_shortcode, $modified_post->post_content );
				}
				// Maybe updating the post is not necessary.
				if ( $unmodified_post->post_content != $modified_post->post_content )
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
						wp_delete_post( $thumbnail_post);
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
					$o->post_id = $bcd->new_post[ 'ID' ];
					$new_attachment_id = $this->copy_attachment( $o );
					if ( $new_attachment_id !== false )
						update_post_meta( $bcd->new_post[ 'ID' ], '_thumbnail_id', $new_attachment_id );
				}
			}

			// Sticky behaviour
			$child_post_is_sticky = is_sticky( $bcd->new_post[ 'ID' ] );
			if ( $bcd->post_is_sticky && ! $child_post_is_sticky )
				stick_post( $bcd->new_post[ 'ID' ] );
			if ( ! $bcd->post_is_sticky && $child_post_is_sticky )
				unstick_post( $bcd->new_post[ 'ID' ] );

			if ( $bcd->link)
			{
				$new_post_broadcast_data = $this->get_post_broadcast_data( $bcd->blog_id->parent, $bcd->new_post[ 'ID' ] );
				$new_post_broadcast_data->set_linked_parent( $bcd->blog_id->parent, $bcd->post->ID );
				$this->set_post_broadcast_data( $child_blog_id, $bcd->new_post[ 'ID' ], $new_post_broadcast_data );
			}

			$to_broadcasted_blogs[] = '<a href="' . get_permalink( $bcd->new_post[ 'ID' ] ) . '">' . get_bloginfo( 'name' ) . '</a>';
			$to_broadcasted_blog_details[] = array( 'blog_id' => $child_blog_id, 'post_id' => $bcd->new_post[ 'ID' ], 'inserted' => $need_to_insert_post );

			do_action( 'threewp_brodcast_broadcasting_before_restore_current_blog', $bcd );

			restore_current_blog();
		}

		// Save the post broadcast data.
		if ( $bcd->link )
			$this->set_post_broadcast_data( $bcd->blog_id->parent, $bcd->post->ID, $broadcast_data );

		do_action( 'threewp_brodcast_broadcasting_finished', $bcd );

		// Finished broadcasting.
		$this->broadcasting = false;
		$this->broadcasting_data = null;

		$this->load_language();

		$post_url_and_name = '<a href="' . get_permalink( $bcd->post->ID ) . '">' . $options->post->post_title. '</a>';
		do_action( 'threewp_activity_monitor_new_activity', array(
			'activity_id' => '3broadcast_broadcasted',
			'activity_strings' => array(
				'' => '%user_display_name_with_link% has broadcasted '.$post_url_and_name.' to: ' . implode( ', ', $to_broadcasted_blogs ),
			),
			'activity_details' => $to_broadcasted_blog_details,
		) );
	}

	/**
	 * Provides a cached list of blogs.
	 *
	 * Since the _SESSION variable isn't saved between page loads this cache function works just fine for once-per-load caching.
	 * Keep the list cached for anything longer than a page refresh (a minute?) could mean that it becomes stale - admin creates a
	 * new blog or blog access is removed or whatever.
	 */
	private function cached_blog_list()
	{
		$blogs = $this->blogs_cache;
		if ( $blogs === null)
		{
			$blogs = $this->get_blog_list();
			$this->blogs_cache = $blogs;
		}
		return $blogs;
	}

	/**
		@brief		Creates a new attachment.
		@details

		The $o object is an extension of Broadcasting_Data and must contain:
		- @i attachment_data An AttachmentData object containing the attachmend info.
		- @i post_id The ID of the post to which to attach the new attachment.

		@param		object		$o		Options.
		@return		@i int The attachment's new post ID.
		@since		20130530
		@version	20130530
	*/
	private function copy_attachment( $o )
	{
		if ( ! file_exists( $o->attachment_data->filename_path ) )
			return false;

		// Copy the file to the blog's upload directory
		$upload_dir = wp_upload_dir();

		copy( $o->attachment_data->filename_path, $upload_dir['path'] . '/' . $o->attachment_data->filename_base );

		// And now create the attachment stuff.
		// This is taken almost directly from http://codex.wordpress.org/Function_Reference/wp_insert_attachment
		$wp_filetype = wp_check_filetype( $o->attachment_data->filename_base, null );
		$attachment = array(
			'guid' => $upload_dir['url'] . '/' . $o->attachment_data->filename_base,
			'menu_order' => $o->attachment_data->menu_order,
			'post_excerpt' => $o->attachment_data->post_excerpt,
			'post_mime_type' => $wp_filetype['type'],
			'post_title' => $o->attachment_data->post_title,
			'post_content' => '',
			'post_status' => 'inherit',
		);
		$attach_id = wp_insert_attachment( $attachment, $upload_dir['path'] . '/' . $o->attachment_data->filename_base, $o->post_id );

		// Now to maybe handle the metadata.
		if ( $o->attachment_data->file_metadata )
		{
			// 1. Create new metadata for this attachment.
			require_once(ABSPATH . "wp-admin" . '/includes/image.php' );
			$attach_data = wp_generate_attachment_metadata( $attach_id, $upload_dir['path'] . '/' . $o->attachment_data->filename_base );

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
				update_post_meta( $attach_id, $key, $value );
			}

			// 3. Overwrite the metadata that needs to be overwritten with fresh data.
			wp_update_attachment_metadata( $attach_id,  $attach_data );
		}

		return $attach_id;
	}

	/**
		Deletes the broadcast data completely of a post in a blog.
	*/
	public function delete_post_broadcast_data( $blog_id, $post_id)
	{
		$this->sql_delete_broadcast_data( $blog_id, $post_id );
	}

	/**
	 * Lists ALL of the blogs. Including the main blog.
	 */
	public function get_blog_list()
	{
		$site_id = get_current_site();
		$site_id = $site_id->id;

		// Get a custom list of all blogs on this site. This bypasses Wordpress' filter that removes private and mature blogs.
		$blogs = $this->query("SELECT * FROM `".$this->wpdb->base_prefix."blogs` WHERE site_id = '$site_id' ORDER BY blog_id");
		$blogs = $this->array_rekey( $blogs, 'blog_id' );

		foreach( $blogs as $blog_id=>$blog)
		{
			$tempBlog = (array) get_blog_details( $blog_id, true);
			$blogs[$blog_id]['blogname'] = $tempBlog['blogname'];
			$blogs[$blog_id]['siteurl'] = $tempBlog['siteurl'];
			$blogs[$blog_id]['domain'] = $tempBlog['domain'];
		}

		return $this->sort_blogs( $blogs);
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
		require_once( 'include/BroadcastData.php' );
		$r = $this->sql_get_broadcast_data( $blog_id, $post_id );

		if ( count( $r ) < 1 )
			return new BroadcastData( [] );
		return new BroadcastData( $r );
	}

	/**
		Returns a list of all the, as per admin, required blogs to broadcast to.
	**/
	private function get_required_blogs()
	{
		$requiredBlogs = array_filter( explode( ',', $this->get_site_option( 'requiredlist' ) ) );
		$requiredBlogs = array_flip( $requiredBlogs);
		return $requiredBlogs;
	}

	public function is_blog_user_writable( $user_id, $blog_id)
	{
		// If this blog is in the blacklist, reply no.
		if ( $this->is_blog_blacklisted( $blog_id) )
			return false;

		// Else, check that the user has write access.
		switch_to_blog( $blog_id );

		global $current_user;
		wp_get_current_user();
		$r = current_user_can( 'edit_posts' );

		restore_current_blog();
		return $r;
	}

	/**
	 * Returns whether the site admin has blacklisted the blog.
	 */
	public function is_blog_blacklisted( $blog_id)
	{
		$blacklist = $this->get_site_option( 'blacklist' );
		if ( $blacklist == '' )
			return false;
		$blacklist = explode( ',', $blacklist);
		$blacklist = array_flip( $blacklist);
		return isset( $blacklist[$blog_id] );
	}

	/**
	 * Returns whether the site admin has blacklisted the blog.
	 */
	public function is_required( $blog_id)
	{
		if ( is_super_admin() )
			return false;
		$requiredlist = $this->get_site_option( 'requiredlist' );
		$requiredlist = explode( ',', $requiredlist);
		$requiredlist = array_flip( $requiredlist);
		return isset( $requiredlist[$blog_id] );
	}

	public function list_user_writable_blogs( $user_id )
	{
		// Super admins can write anywhere they feel like.
		if (is_super_admin() )
		{
			$blogs = $this->get_blog_list();
			$blogs = $this->sort_blogs( $blogs);
			return $blogs;
		}

		$blogs = get_blogs_of_user( $user_id );
		foreach( $blogs as $index=>$blog)
		{
			$blog = (array) $blog;
			$blog['blog_id'] = $blog['userblog_id'];
			$blogs[$index] = $blog;
			if (!$this->is_blog_user_writable( $user_id, $blog['blog_id'] ) )
				unset( $blogs[$index] );
		}
		return $this->sort_blogs( $blogs);
	}

	private function load_last_used_settings( $user_id)
	{
		$data = $this->sql_user_get( $user_id );
		if (!isset( $data['last_used_settings'] ) )
			$data['last_used_settings'] = array();
		return $data['last_used_settings'];
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

	private function keep_valid_custom_fields( $custom_fields )
	{
		foreach( $custom_fields as $key => $array)
			if ( !$this->is_custom_field_valid( $key) )
				unset( $custom_fields[$key] );

		return $custom_fields;
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
		if ( !isset( $this->custom_field_exceptions_cache) )
			$this->custom_field_exceptions_cache = explode( ' ', $this->get_site_option( 'custom_field_exceptions' ) );

		// If the field does not start with an underscore, it is automatically valid.
		if ( strpos( $custom_field, '_' ) !== 0 )
			return true;

		foreach( $this->custom_field_exceptions_cache as $exception)
			if ( strpos( $custom_field, $exception) !== false )
				return true;

		return false;
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

	private function save_last_used_settings( $user_id, $settings )
	{
		$data = $this->sql_user_get( $user_id );
		$data['last_used_settings'] = $settings;
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
		require_once( 'include/BroadcastData.php' );
		if ( $broadcast_data->is_modified() )
			if ( $broadcast_data->is_empty() )
				$this->sql_delete_broadcast_data( $blog_id, $post_id );
			else
				$this->sql_update_broadcast_data( $blog_id, $post_id, $broadcast_data->getData() );
	}

	private function set_term_parent( $taxonomy, $term_id, $parent_id )
	{
		wp_update_term( $term_id, $taxonomy, array(
			'parent' => $parent_id,
		) );

		// wp_update_category alone won't work. The "cache" needs to be cleared.
		// see: http://wordpress.org/support/topic/category_children-how-to-recalculate?replies=4
		delete_option( 'category_children' );
	}

	private function show_group_blogs( $options)
	{
		$form = $this->form();
		$r = '<ul class="broadcast_blogs">';
		$nameprefix = "[broadcast][groups][" . $options['nameprefix'] . "]";
		foreach( $options['blogs'] as $blog)
		{
			$blog_id = $blog['blog_id'];	// Convience
			$required = $this->is_required( $blog_id );
			$checked = isset( $options['selected'][ $blog_id ] ) || $required;
			$input = array(
				'name' => $blog_id,
				'type' => 'checkbox',
				'nameprefix' => $nameprefix,
				'label' => $blog['blogname'],
				'disabled' => isset( $options['disabled'][ $blog_id ] ),
				'readonly' => isset( $options['readonly'][ $blog_id ] ),
				'value' => 'blog_' .$checked,
				'checked' => $checked,
				'title' => $blog['siteurl'],
			);

			$blog_class = isset( $options['blog_class'][$blog_id] ) ? $options['blog_class'][$blog_id] : '';
			$blog_title = isset( $options['blog_title'][$blog_id] ) ? $options['blog_title'][$blog_id] : '';

			$r .= '<li class="'.$blog_class.'"
				 title="'.$blog_title.'">'.$form->make_input( $input).' '.$form->make_label( $input).'</li>';
		}
		$r .= '</ul>';
		return $r;
	}

	/**
	 * Sorts the blogs by name. The Site Blog is first, no matter the name.
	 */
	public function sort_blogs( $blogs )
	{
		// Make sure the main blog is saved.
		$firstBlog = array_shift( $blogs);

		$blogs = self::array_rekey( $blogs, 'blogname' );
		ksort( $blogs);

		// Put it back up front.
		array_unshift( $blogs, $firstBlog);

		return self::array_rekey( $blogs, 'blog_id' );
	}

	// --------------------------------------------------------------------------------------------
	// ----------------------------------------- SQL
	// --------------------------------------------------------------------------------------------

	/**
	 * Gets the user data.
	 *
	 * Returns an array of user data.
	 */
	private function sql_user_get( $user_id)
	{
		$r = $this->query("SELECT * FROM `".$this->wpdb->base_prefix."_3wp_broadcast` WHERE user_id = '$user_id'");
		$r = @unserialize( base64_decode( $r[0]['data'] ) );		// Unserialize the data column of the first row.
		if ( $r === false)
			$r = array();

		// Merge/append any default values to the user's data.
		return array_merge(array(
			'groups' => array(),
		), $r);
	}

	/**
	 * Saves the user data.
	 */
	private function sql_user_set( $user_id, $data)
	{
		$data = serialize( $data);
		$data = base64_encode( $data);
		$this->query("DELETE FROM `".$this->wpdb->base_prefix."_3wp_broadcast` WHERE user_id = '$user_id'");
		$this->query("INSERT INTO `".$this->wpdb->base_prefix."_3wp_broadcast` (user_id, data) VALUES ( '$user_id', '$data' )");
	}

	private function sql_get_broadcast_data( $blog_id, $post_id)
	{
		$r = $this->query("SELECT data FROM `".$this->wpdb->base_prefix."_3wp_broadcast_broadcastdata` WHERE blog_id = '$blog_id' AND post_id = '$post_id'");
		$r = @unserialize( base64_decode( $r[0]['data'] ) );		// Unserialize the data column of the first row.
		if ( $r === false)
			$r = array();
		return $r;
	}

	private function sql_delete_broadcast_data( $blog_id, $post_id)
	{
		$this->query("DELETE FROM `".$this->wpdb->base_prefix."_3wp_broadcast_broadcastdata` WHERE blog_id = '$blog_id' AND post_id = '$post_id'");
	}

	private function sql_update_broadcast_data( $blog_id, $post_id, $data)
	{
		$data = serialize( $data);
		$data = base64_encode( $data);
		$this->sql_delete_broadcast_data( $blog_id, $post_id );
		$this->query("INSERT INTO `".$this->wpdb->base_prefix."_3wp_broadcast_broadcastdata` (blog_id, post_id, data) VALUES ( '$blog_id', '$post_id', '$data' )");
	}

	private function sync_terms( $taxonomy, $source_blog_id, $target_blog_id )
	{
		global $wpdb;
		switch_to_blog( $source_blog_id );
		$source_terms = $this->get_current_blog_taxonomy_terms( $taxonomy );
		restore_current_blog();

		switch_to_blog( $target_blog_id );

		$target_terms = $this->get_current_blog_taxonomy_terms( $taxonomy );

		// Keep track of which terms we've found.
		$found_targets = array();
		$found_sources = array();

		// First step: find out which of the target terms exist on the source blog
		foreach( $target_terms as $target_term_id => $target_term )
			foreach( $source_terms as $source_term_id => $source_term )
			{
				if ( isset( $found_sources[ $source_term_id ] ) )
					continue;
				if ( $source_term['slug'] == $target_term['slug'] )
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
			$parent_of_equivalent_source_term = $source_terms[ $source_term_id ]['parent'];

			if ( $parent_of_target_term != $parent_of_equivalent_source_term &&
				(isset( $found_sources[ $parent_of_equivalent_source_term ] ) || $parent_of_equivalent_source_term == 0 )
			)
			{
				if ( $parent_of_equivalent_source_term != 0)
					$new_term_parent = $found_sources[ $parent_of_equivalent_source_term ];
				else
					$new_term_parent = 0;
				$this->set_term_parent( $taxonomy, $target_term_id, $new_term_parent );
			}
		}

		restore_current_blog();
	}
}

$threewp_broadcast = new ThreeWP_Broadcast();
