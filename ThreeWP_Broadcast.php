<?php
/*                                                                                                                                                                                                                                                             
Plugin Name: ThreeWP Broadcast
Plugin URI: http://mindreantre.se/program/threewp/threewp-broadcast/
Description: Network plugin to broadcast a post to other blogs. Whitelist, blacklist, groups and automatic category+tag+custom field posting/creation available. 
Version: 1.3
Author: Edward Hevlund
Author URI: http://www.mindreantre.se
Author Email: edward@mindreantre.se
*/

if(preg_match('#' . basename(__FILE__) . '#', $_SERVER['PHP_SELF'])) { die('You are not allowed to call this page directly.'); }

require_once('ThreeWP_Broadcast_3Base.php');
class ThreeWP_Broadcast extends ThreeWP_Broadcast_3Base
{
	protected $site_options = array(
		'role_broadcast' => 'super_admin',					// Role required to use broadcast function
		'role_link' => 'super_admin',						// Role required to use the link function
		'role_broadcast_as_draft' => 'super_admin',			// Role required to broadcast posts as templates
		'role_broadcast_scheduled_posts' => 'super_admin',	// Role required to broadcast scheduled, future posts
		'role_groups' => 'super_admin',						// Role required to use groups
		'role_categories' => 'super_admin',					// Role required to broadcast the categories
		'role_categories_create' => 'super_admin',			// Role required to create categories automatically
		'role_tags' => 'super_admin',						// Role required to broadcast the categories
		'role_tags_create' => 'super_admin',					// Role required to create categories automatically
		'role_custom_fields' => 'super_admin',				// Role required to broadcast the categories
		'requiredlist' => '',								// Comma-separated string of blogs to require
		'always_use_required_list' => false,				// Require blogs only when broadcasting?
		'save_post_priority' => 640,						// Priority of save_post action. Higher = lets other plugins do their stuff first
		'blacklist' => '',									// Comma-separated string of blogs to automatically exclude
		'post_types' => array('post' => array(), 'page' => array()),			// Custom post types which use broadcasting
		'override_child_permalinks' => false,				// Make the child's permalinks link back to the parent item?
		'activity_monitor_broadcasts' => false,				// Reports when a user has used the broadcast function.
		'activity_monitor_group_changes' => false,			// Reports when a user has created, changed or deleted a group (of blogs)
		'activity_monitor_unlinks' => false,				// Reports when someone unlinks a child from the parent.
		'custom_field_exceptions' => array('_wp_page_template', '_wplp_', '_aioseop_'),				// Custom fields that should be broadcasted, even though they start with _
	);
	
	private $blogs_cache = null;
	
	private $broadcasting = false;
	
	public function __construct()
	{
		parent::__construct(__FILE__);
		if ($this->isNetwork)
		{
			define("_3BC", get_class($this));
			register_activation_hook(__FILE__, array(&$this, 'activate') );
			add_action('admin_menu', array(&$this, 'add_menu') );
			add_action('admin_menu', array(&$this, 'create_meta_box'));
			add_action('admin_print_styles', array(&$this, 'load_styles') );
			if ( $this->get_site_option('override_child_permalinks') )
			{
				add_action( 'post_link', array(&$this, 'post_link'), 10, 2 );
			}
		}
	}
	
	public function add_menu()
	{
		if (is_super_admin())
			add_submenu_page('ms-admin.php', 'ThreeWP Broadcast', 'Broadcast', 'activate_plugins', 'ThreeWP_Broadcast', array (&$this, 'admin'));
		add_options_page('ThreeWP Broadcast', __('Broadcast'), 'publish_posts', 'ThreeWP_Broadcast', array (&$this, 'user'));
		if ($this->role_at_least( $this->get_site_option('role_link') ))
		{
			add_action('post_row_actions', array(&$this, 'post_row_actions'));
			add_action('page_row_actions', array(&$this, 'post_row_actions'));
			
			add_filter('manage_posts_columns', array(&$this, 'manage_posts_columns')); 
			add_action('manage_posts_custom_column', array(&$this, 'manage_posts_custom_column'), 10, 2);
			                                                                                                                                                                    
			add_filter('manage_pages_columns', array(&$this, 'manage_posts_columns')); 
			add_action('manage_pages_custom_column', array(&$this, 'manage_posts_custom_column'), 10, 2);
			
			add_action('trash_post', array(&$this, 'trash_post'));
			add_action('trash_page', array(&$this, 'trash_post'));

			add_action('untrash_post', array(&$this, 'untrash_post'));
			add_action('untrash_page', array(&$this, 'untrash_post'));

			add_action('delete_post', array(&$this, 'delete_post'));
			add_action('delete_page', array(&$this, 'delete_post'));
		}
	}
	
	public function load_styles()
	{
		$load = false;
		
		$pages = array(get_class(), 'ThreeWP_Activity_Monitor');
		
		if ( isset($_GET['page']) )
			$load |= in_array($_GET['page'], $pages);
			
		foreach(array('post-new.php', 'post.php') as $string)
			$load |= strpos($_SERVER['SCRIPT_FILENAME'], $string) !== false;
		
		if (!$load)
			return;
		
		wp_enqueue_style('3wp_broadcast', '/' . $this->paths['path_from_base_directory'] . '/css/ThreeWP_Broadcast.css', false, '0.0.1', 'screen' );
	}
	
	// --------------------------------------------------------------------------------------------
	// ----------------------------------------- Activate / Deactivate
	// --------------------------------------------------------------------------------------------

	public function activate()
	{
		parent::activate();
		
		if (!$this->isNetwork)
			wp_die("This plugin requires Network.");
			
		$this->register_options();
		
		// Remove old options
		$this->delete_site_option('requirewhenbroadcasting');
			
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
	}
	
	protected function uninstall()
	{
		parent::uninstall();
		$this->query("DROP TABLE `".$this->wpdb->base_prefix."_3wp_broadcast`");
		$this->query("DROP TABLE `".$this->wpdb->base_prefix."_3wp_broadcast_broadcastdata`");
	}
	
	// --------------------------------------------------------------------------------------------
	// ----------------------------------------- Menus
	// --------------------------------------------------------------------------------------------

	public function admin()
	{
		$this->tabs(array(
			'tabs' =>		array('Settings',			'Post Types',					'Required list',			'Blacklist',			'Activity Monitor',			'Uninstall'),
			'functions' =>	array('admin_settings',		'admin_post_types',				'admin_required_list',		'admin_blacklist',		'admin_activity_monitor',	'admin_uninstall'),
		));
	}
	
	public function user()
	{
		$this->load_language();
		
		$tab_data = array(
			'tabs'		=>	array(),
			'functions' =>	array(),
		);
		
		if (isset($_GET['action']) && $_GET['action'] == 'unlink')
		{
			$tab_data['tabs'][] = __('Unlink');
			$tab_data['functions'][] = 'user_unlink';
		}
		
		if (isset($_GET['action']) && $_GET['action'] == 'trash')
		{
			$tab_data['tabs'][] = __('Trash');
			$tab_data['functions'][] = 'user_trash';
		}
		
		$tab_data['tabs'][] = __('Help');
		$tab_data['functions'][] = 'user_help';
		
		if ($this->role_at_least( $this->get_site_option('role_groups') ))
		{
			$tab_data['tabs'][] = __('ThreeWP Broadcast groups');
			$tab_data['functions'][] = 'user_edit_groups';
		}

		$this->tabs($tab_data);
	}
	
	protected function admin_settings()
	{
		$form = $this->form();
		
		// Collect all the roles.
		$roles = array('super_admin' => array('text' => 'Site admin', 'value' => 'super_admin'));
		foreach($this->roles as $role)
			$roles[$role['name']] = array('value' => $role['name'], 'text' => ucfirst($role['name']));
			
		if (isset($_POST['save']))
		{
			// Save the exceptions
			$custom_field_exceptions = str_replace( "\r", "", trim($_POST['custom_field_exceptions']) );
			$custom_field_exceptions = explode("\n", $custom_field_exceptions );
			$this->update_site_option('save_post_priority', intval($_POST['save_post_priority']));
			$this->update_site_option('override_child_permalinks', isset($_POST['override_child_permalinks']) );
			$this->update_site_option('custom_field_exceptions', $custom_field_exceptions );
			foreach(array('role_broadcast', 'role_link', 'role_broadcast_as_draft', 'role_broadcast_scheduled_posts', 'role_groups', 'role_categories', 'role_categories_create', 'role_tags', 'role_tags_create', 'role_custom_fields') as $key)
				$this->update_site_option($key, (isset($roles[$_POST[$key]]) ? $_POST[$key] : 'super_admin'));
			$this->message('Options saved!');
		}
			
		$inputs = array(
			'role_broadcast' => array(
				'name' => 'role_broadcast',
				'type' => 'select',
				'label' => 'Broadcast access role',
				'value' => $this->get_site_option('role_broadcast'),
				'description' => 'The broadcast access role is the user role required to use the broadcast function at all.',
				'options' => $roles,
				'make_table_row' => true,		// Just a marker to tell the function to automake this input in the table.
			),
			'role_link' => array(
				'name' => 'role_link',
				'type' => 'select',
				'label' => 'Link access role',
				'value' => $this->get_site_option('role_link'),
				'description' => 'When a post is linked with broadcasted posts, the child posts are updated / deleted when the parent is updated.',
				'options' => $roles,
				'make_table_row' => true,		// Just a marker to tell the function to automake this input in the table.
			),
			'role_broadcast_as_draft' => array(
				'name' => 'role_broadcast_as_draft',
				'type' => 'select',
				'label' => 'Draft broadcast access role',
				'value' => $this->get_site_option('role_broadcast_as_draft'),
				'description' => 'Which role is needed to broadcast drafts?',
				'options' => $roles,
				'make_table_row' => true,		// Just a marker to tell the function to automake this input in the table.
			),
			'role_broadcast_scheduled_posts' => array(
				'name' => 'role_broadcast_scheduled_posts',
				'type' => 'select',
				'label' => 'Scheduled posts access role',
				'value' => $this->get_site_option('role_broadcast_scheduled_posts'),
				'description' => 'Which role is needed to broadcast scheduled (future) posts?',
				'options' => $roles,
				'make_table_row' => true,		// Just a marker to tell the function to automake this input in the table.
			),
			'role_groups' => array(
				'name' => 'role_groups',
				'type' => 'select',
				'label' => 'Group access role',
				'value' => $this->get_site_option('role_groups'),
				'description' => 'Role needed to administer their own groups?',
				'options' => $roles,
				'make_table_row' => true,		// Just a marker to tell the function to automake this input in the table.
			),
			'role_categories' => array(
				'name' => 'role_categories',
				'type' => 'select',
				'label' => 'Categories broadcast role',
				'value' => $this->get_site_option('role_categories'),
				'description' => 'Which role is needed to allow category broadcasting? The categories must have the same slug.',
				'options' => $roles,
				'make_table_row' => true,		// Just a marker to tell the function to automake this input in the table.
			),
			'role_categories_create' => array(
				'name' => 'role_categories_create',
				'type' => 'select',
				'label' => 'Category creation role',
				'value' => $this->get_site_option('role_categories_create'),
				'description' => 'Which role is needed to allow category creation? Categories are created if they don\'t exist.',
				'options' => $roles,
				'make_table_row' => true,		// Just a marker to tell the function to automake this input in the table.
			),
			'role_tags' => array(
				'name' => 'role_tags',
				'type' => 'select',
				'label' => 'Tags broadcast role',
				'value' => $this->get_site_option('role_tags'),
				'description' => 'Which role is needed to allow tag broadcasting? The tags must have the same slug.',
				'options' => $roles,
				'make_table_row' => true,		// Just a marker to tell the function to automake this input in the table.
			),
			'role_tags_create' => array(
				'name' => 'role_tags_create',
				'type' => 'select',
				'label' => 'Tag creation role',
				'value' => $this->get_site_option('role_tags_create'),
				'description' => 'Which role is needed to allow tag creation? Tags are created if they don\'t exist.',
				'options' => $roles,
				'make_table_row' => true,		// Just a marker to tell the function to automake this input in the table.
			),
			'role_custom_fields' => array(
				'name' => 'role_custom_fields',
				'type' => 'select',
				'label' => 'Custom field broadcast role',
				'value' => $this->get_site_option('role_custom_fields'),
				'description' => 'Which role is needed to allow custom field broadcasting?',
				'options' => $roles,
				'make_table_row' => true,		// Just a marker to tell the function to automake this input in the table.
			),
			'save_post_priority' => array(
				'name' => 'save_post_priority',
				'type' => 'text',
				'label' => 'Action priority',
				'value' => $this->get_site_option('save_post_priority'),
				'size' => 3,
				'maxlength' => 10,
				'description' => 'A higher save-post-action priority gives other plugins more time to add their own custom fields before the post is broadcasted. <em>Raise</em> this value if you notice that plugins that use custom fields aren\'t getting their data broadcasted, but 640 should be enough for everybody.',
				'make_table_row' => true,		// Just a marker to tell the function to automake this input in the table.
			),
			'custom_field_exceptions' => array(
				'name' => 'custom_field_exceptions',
				'type' => 'textarea',
				'label' => 'Custom field exceptions',
				'value' => implode("\n", $this->get_site_option('custom_field_exceptions')),
				'cols' => 30,
				'rows' => 5,
				'description' => 'Custom fields that begin with underscores (internal fields) are ignored. If you know of an internal field that should be broadcasted, write it down here. One custom field key per line and it can be any part of the key string.',
				'make_table_row' => true,		// Just a marker to tell the function to automake this input in the table.
			),
			'override_child_permalinks' => array(
				'name' => 'override_child_permalinks',
				'type' => 'checkbox',
				'label' => 'Override child post permalinks',
				'checked' => $this->get_site_option('override_child_permalinks'),
				'description' => 'This will force child posts (those broadcasted to other sites) to keep the original post\'s permalink. If checked, child posts will link back to the original post on the original site.',
				'make_table_row' => true,		// Just a marker to tell the function to automake this input in the table.
			),
			'save' => array(
				'name' => 'save',
				'type' => 'submit',
				'value' => 'Save options',
				'cssClass' => 'button-primary',
			),
		);
		
		$tBody = '';
		foreach( $inputs as $input )
			if ( isset($input['make_table_row']) )
				$tBody .= $this->make_table_row( $input );
			
		echo '
			'.$form->start().'

			<table class="form-table">
				<tbody>
					'.$tBody.'
				</tbody>
			</table>

			<p>
				'.$form->makeInput($inputs['save']).'
			</p>

			'.$form->stop().'
		';		
	}
	
	protected function admin_post_types()
	{
		if (isset($_POST['save_post_types']))
		{
			$post_types = array_keys( $_POST['post_types'] );
			$post_types = array_flip($post_types);
			$this->update_site_option('post_types', $post_types);
			$this->message('Custom post types saved!');
		}
		
		$post_types = $this->get_site_option('post_types');
		$all_post_types = get_post_types();
		$form = $this->form();
		
		$lis = array();
		foreach($all_post_types as $post_type)
		{
			$post_type_object = get_post_type_object($post_type);
			$input = array(
				'name' => $post_type,
				'nameprefix' => '[post_types]',
				'label' => $post_type_object->labels->name,
				'type' => 'checkbox',
				'checked' => isset($post_types[ $post_type ]),
			);
			$lis[] = $form->makeInput($input) . ' ' . $form->makeLabel($input);
		}
		
		$input_submit = array(
			'name' => 'save_post_types',
			'type' => 'submit',
			'value' => 'Save the allowed post types',
			'cssClass' => 'button-primary',
		);
		
		echo '<p>Choose which post types should be allowed to use Broadcast.</p>
		
		'.$form->start().'
		
		<ul>
			<li>'.implode('</li><li>', $lis).'</li>
		</ul>
		
		'.$form->makeInput($input_submit).'
		
		'.$form->stop().'
		';
	}
	
	protected function admin_required_list()
	{
		$blogs = $this->get_blog_list();
		$form = $this->form();
		
		if (isset($_POST['save']))
		{
			$this->update_site_option( 'always_use_required_list', isset($_POST['always_use_required_list']) );
			$required = '';
			if (isset($_POST['broadcast']['groups']['required']))
				$required = implode(',', array_keys($_POST['broadcast']['groups']['required']));
			$this->update_site_option( 'requiredlist', $required );
			$this->message('Options saved!');
		}
		
		$inputs = array(
			'always_use_required_list' => array(
				'name' => 'always_use_required_list',
				'type' => 'checkbox',
				'label' => 'Always use the required list',
				'value' => $this->get_site_option('always_use_required_list'),
			),
			'save' => array(
				'name' => 'save',
				'type' => 'submit',
				'value' => 'Save options',
				'cssClass' => 'button-primary',
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
				'.$form->makeInput($inputs['always_use_required_list']).' '.$form->makeLabel($inputs['always_use_required_list']).'
			</p>

			<p>Select which blogs the user will be required to broadcast to.</p>

			'.$this->show_group_blogs(array(
				'blogs' => $blogs,
				'nameprefix' => 'required',
				'selected' => $requiredBlogs,
			)).'

			<p>
				'.$form->makeInput($inputs['save']).'
			</p>

			'.$form->stop().'
		';	
	}
	
	protected function admin_blacklist()
	{
		$blogs = $this->get_blog_list();
		$form = $this->form();
		
		if (isset($_POST['save']))
		{
			$blacklist = '';
			if (isset($_POST['broadcast']['groups']['blacklist']))
				$blacklist = implode(',', array_keys($_POST['broadcast']['groups']['blacklist']));
			$this->update_site_option( 'blacklist', $blacklist );
			$this->message('Options saved!');
		}
		
		$inputs = array(
			'save' => array(
				'name' => 'save',
				'type' => 'submit',
				'value' => 'Save options',
				'cssClass' => 'button-primary',
			),
		);
		
		$blacklistedBlogs = explode(',', $this->get_site_option('blacklist'));
		$blacklistedBlogs = array_flip($blacklistedBlogs);

		echo '
			'.$form->start().'

			<p>The blacklist specifies which blogs the users may never broadcast to, even if they\'ve got write access.</p>

			'.$this->show_group_blogs(array(
				'blogs' => $blogs,
				'nameprefix' => 'blacklist',
				'selected' => $blacklistedBlogs,
			)).'

			<p>
				'.$form->makeInput($inputs['save']).'
			</p>

			'.$form->stop().'
		';	
	}
	
	protected function admin_activity_monitor()
	{
		$form = $this->form();

		if (isset($_POST['submit']))
		{
			foreach( array('activity_monitor_broadcasts', 'activity_monitor_group_changes', 'activity_monitor_unlinks') as $key )
				$this->update_site_option( $key, isset($_POST[ $key ]) );
			$this->message('Options saved!');
		}
			
		$inputs = array(
			'activity_monitor_broadcasts' => array(
				'name' => 'activity_monitor_broadcasts',
				'type' => 'checkbox',
				'label' => 'Broadcasts',
				'checked' => $this->get_site_option('activity_monitor_broadcasts'),
				'description' => 'Reports when a user has used the broadcast function.',
				'make_table_row' => true,		// Just a marker to tell the function to automake this input in the table.
			),
			'activity_monitor_unlinks' => array(
				'name' => 'activity_monitor_unlinks',
				'type' => 'checkbox',
				'label' => 'Unlinks',
				'checked' => $this->get_site_option('activity_monitor_unlinks'),
				'description' => 'Reports when a user unlinks posts (either from the parent or to the children).',
				'make_table_row' => true,		// Just a marker to tell the function to automake this input in the table.
			),
			'activity_monitor_group_changes' => array(
				'name' => 'activity_monitor_group_changes',
				'type' => 'checkbox',
				'label' => 'Group changes',
				'checked' => $this->get_site_option('activity_monitor_group_changes'),
				'description' => 'Reports when a user has created, changed or deleted a group.',
				'make_table_row' => true,		// Just a marker to tell the function to automake this input in the table.
			),
			'submit' => array(
				'type' => 'submit',
				'name' => 'submit',
				'value' => 'Save changes',
				'cssClass' => 'button-primary',
			),
		);

		$tBody = '';
		foreach( $inputs as $input )
			if ( isset($input['make_table_row']) )
				$tBody .= $this->make_table_row( $input );
			
		echo '
			'.$form->start().'
			
			<p>
				If you want Broadcast to send notifications of user activity to <a href="http://wordpress.org/extend/plugins/threewp-activity-monitor/">ThreeWP Activity Monitor</a>,
				select which user activities you want monitored. 
			</p>
			
			<table class="form-table">
				<tbody>
					'.$tBody.'
				</tbody>
			</table>
			
			'.$form->makeInput($inputs['submit']).'

			'.$form->stop().'
		';
	}
	
	protected function user_edit_groups()
	{
		$user_id = $this->user_id();		// Convenience.
		$form = $this->form();
		
		// Get a list of blogs that this user can write to.		
		$blogs = $this->list_user_writable_blog($user_id);
		
		$data = $this->sqlUserGet($user_id);
		
		if (isset($_POST['groupsSave']))
		{
			$newGroups = array();
			foreach($data['groups'] as $groupID=>$ignore)
			{
				if (isset($_POST['broadcast']['groups'][$groupID]))
				{
					$newGroups[$groupID]['name'] = $data['groups'][$groupID]['name'];
					$selectedBlogs =  $_POST['broadcast']['groups'][$groupID];
					$newGroups[$groupID]['blogs'] = array_flip(array_keys($selectedBlogs));
					if ( $this->get_option('activity_monitor_group_changes') )
					{
						$blog_text = count($newGroups[$groupID]['blogs']) . ' ';
						if ( count($newGroups[$groupID]['blogs']) < 2 )
							$blog_text .= 'blog: ';
						else
							$blog_text .= 'blogs: ';

						$blogs_array = array();
						foreach( $newGroups[$groupID]['blogs'] as $blogid => $ignore )
							$blogs_array[] = $blogs[$blogid]['blogname'];
						
						$blog_text .= '<em>' . implode('</em>, <em>', $blogs_array) . '</em>';
						
						$blog_text .= '.';
						do_action('threewp_activity_monitor_new_activity', array(
							'activity_type' => '3wpbc_grp_add',
							'tr_class' => 'activity_monitor_broadcast activity_monitor_broadcast_groups activity_monitor_broadcast_group_created',
							'activity' => array(
								'' => '%user_display_name_with_link% updated the blog group <em>' . $newGroups[$groupID]['name'] . '</em> with ' . $blog_text,
							),
						));
					}
				}
				else
				{
					if ( $this->get_option('activity_monitor_group_changes') )
						do_action('threewp_activity_monitor_new_activity', array(
							'activity_type' => '3wpbc_grp_del',
							'tr_class' => 'activity_monitor_broadcast activity_monitor_broadcast_groups activity_monitor_broadcast_group_deleted',
							'activity' => array(
								'' => '%user_display_name_with_link% deleted the blog group <em>' . $data['groups'][$groupID]['name'] . '</em>',
							),
						));
					unset($data['groups'][$groupID]);
				}
			}
			$data['groups'] = $newGroups;
			$this->sqlUserSet($user_id, $data);
			$this->message(__('Group blogs have been saved.'));
		}
		
		if (isset($_POST['groupCreate']))
		{
			$groupName = stripslashes( trim($_POST['groupName']) );
			if ($groupName == '')
				$this->error(__('The group name may not be empty!'));
			else
			{
				if ( $this->get_option('activity_monitor_group_changes') )
					do_action('threewp_activity_monitor_new_activity', array(
						'activity_type' => '3wpbc_grp_mod',
						'tr_class' => 'activity_monitor_broadcast activity_monitor_broadcast_groups activity_monitor_broadcast_group_modified',
						'activity' => array(
							'' => '%user_display_name_with_link% created the blog group <em>' . $groupName . '</em>',
						),
					));
				$data['groups'][] = array('name' => $groupName, 'blogs' => array());
				$this->sqlUserSet($user_id, $data);
				$this->message(__('The group has been created!'));
			}
		}
		
		$groupsText = '';
		if (count($data['groups']) == 0)
			$groupsText = '<p>'.__('You have not created any groups yet.').'</p>';
		foreach($data['groups'] as $groupID=>$groupData)
		{
			$id = 'broadcast_group_'.$groupID;
			$groupsText .= '
				<div class="threewp_broadcast_group">
					<h4>'.__('Group').': '.$groupData['name'].'</h4>

					<div id="'.$id.'">
						'.$this->show_group_blogs(array(
							'blogs' => $blogs,
							'nameprefix' => $groupID,
							'selected' => $groupData['blogs'],
						)).'
					</div>
					<p>'.(count($blogs) > 2 ? $this->showGroupBlogsSelectUnselect(array('selector' => '#' . $id, 'input_label' => __('Select/deselect all the blogs in the above group.'))): '').'</p>
				</div>
			';
		}
		
		$inputs = array(
			'groupsSave' => array(
				'name' => 'groupsSave',
				'type' => 'submit',
				'value' => __('Save groups'),
				'cssClass' => 'button-primary',
			),
			'groupName' => array(
				'name' => 'groupName',
				'type' => 'text',
				'label' => __('New group name'),
				'size' => 25,
				'maxlength' => 200,
			),
			'groupCreate' => array(
				'name' => 'groupCreate',
				'type' => 'submit',
				'value' => __('Create the new group'),
				'cssClass' => 'button-secondary',
			),
		);
		
		echo '
			<h3>'.__('Your groups').'</h3>

			'.$form->start().'

			'.$groupsText.'

			<p>
				'.$form->makeInput($inputs['groupsSave']).'
			</p>

			'.$form->stop().'

			<h3>'.__('Create a new group').'</h3>

			'.$form->start().'

			<p>
				'.$form->makeLabel($inputs['groupName']).' '.$form->makeInput($inputs['groupName']).'
			</p>

			<p>
				'.$form->makeInput($inputs['groupCreate']).'
			</p>

			'.$form->stop().'

			<h3>'.__('Delete').'</h3>

			<p>
				'.__('To <strong>delete</strong> a group, leave all blogs in that group unmarked and then save.').'
			</p>
		';
	}
	
	protected function user_unlink()
	{
		// Check that we're actually supposed to be removing the link for real.
		$nonce = $_GET['_wpnonce'];
		$post_id = $_GET['post'];
		if (isset($_GET['child']))
			$child_blog_id = $_GET['child'];
			
		// Generate the nonce key to check against.			
		$nonce_key = 'broadcast_unlink';
		if (isset($child_blog_id))
			$nonce_key .= '_' . $child_blog_id;
		$nonce_key .= '_' . $post_id;
			
		if (!wp_verify_nonce($nonce, $nonce_key))
			die("Security check: not supposed to be unlinking broadcasted post!");
			
		global $blog_id;

		$broadcast_data = $this->get_post_broadcast_data($blog_id, $post_id);
		$linked_children = $broadcast_data->get_linked_children();
		
		// Remove just one child?
		if (isset($child_blog_id))
		{
			// If we're supposed to report unlinks, then do so. Assumes that Activity Monitor is installed.
			if ( $this->get_option('activity_monitor_unlinks') )
			{
				// Get the info about this post.
				$post_data = get_post( $post_id );
				$post_url = get_permalink( $post_id );
				$post_url = '<a href="'.$post_url.'">'.$post_data->post_title.'</a>';
				
				// And about the child blog
				switch_to_blog( $child_blog_id );
				$blog_url = '<a href="'.get_bloginfo('url').'">'.get_bloginfo('name').'</a>';
				restore_current_blog();				
				
				do_action('threewp_activity_monitor_new_activity', array(
					'tr_class' => 'activity_monitor_broadcast activity_monitor_broadcast_unlinks activity_monitor_broadcast_unlinked_child',
					'activity' => array(
						'' => '%user_display_name_with_link% unlinked ' . $post_url . ' with the child post on ' . $blog_url,
					),
				));
			}

			$this->delete_post_broadcast_data($child_blog_id, $linked_children[$child_blog_id]);
			$broadcast_data->remove_linked_child($child_blog_id);
			$this->set_post_broadcast_data($blog_id, $post_id, $broadcast_data);
			$message = __('Link to child post has been removed.');
		}
		else
		{
			$blogs_url = array();
			foreach($linked_children as $linked_child_blog_id => $linked_child_post_id)
			{
				// And about the child blog
				switch_to_blog( $linked_child_blog_id );
				$blogs_url[] = '<a href="'.get_bloginfo('url').'">'.get_bloginfo('name').'</a>';
				restore_current_blog();				
				$this->delete_post_broadcast_data($linked_child_blog_id, $linked_child_post_id);
			}
			
			// If we're supposed to report unlinks, then do so. Assumes that Activity Monitor is installed.
			if ( $this->get_option('activity_monitor_unlinks') )
			{
				// Get the info about this post.
				$post_data = get_post( $post_id );
				$post_url = get_permalink( $post_id );
				$post_url = '<a href="'.$post_url.'">'.$post_data->post_title.'</a>';
				
				$blogs_url = implode(', ', $blogs_url);
				
				do_action('threewp_activity_monitor_new_activity', array(
					'tr_class' => 'activity_monitor_broadcast activity_monitor_broadcast_unlinks activity_monitor_broadcast_unlinked_parent',
					'activity' => array(
						'' => '%user_display_name_with_link% unlinked ' . $post_url . ' with the child posts on ' . $blogs_url,
					),
				));
			}
			$broadcast_data = $this->get_post_broadcast_data($blog_id, $post_id);
			$broadcast_data->remove_linked_children();
			$message = __('All links to child posts have been removed!');
		}
		
		$this->set_post_broadcast_data($blog_id, $post_id, $broadcast_data);
		
		echo '
			'.$this->message($message).'
			<p>
				<a href="'.wp_get_referer().'">Back to post overview</a>
			</p>
		';
	}
	
	/**
		Trashes a broadcasted post.
	*/
	protected function user_trash()
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
			
		if (!wp_verify_nonce($nonce, $nonce_key))
			die("Security check: not supposed to be unlinking broadcasted post!");
			
		$broadcast_data = $this->get_post_broadcast_data($blog_id, $post_id);
		switch_to_blog($child_blog_id);
		$broadcasted_post_id = $broadcast_data->get_linked_child_on_this_blog();
		wp_trash_post($broadcasted_post_id);
		restore_current_blog();
		$broadcast_data->remove_linked_child($blog_id);
		$this->set_post_broadcast_data($blog_id, $post_id, $broadcast_data);
		
		$message = __('The broadcasted child post has been put in the trash.');

		echo '
			'.$this->message($message).'
			<p>
				<a href="'.wp_get_referer().'">Back to post overview</a>
			</p>
		';
	}
	
	protected function user_help()
	{
		echo '
			<div id="broadcast_help">
				<h2>'.__('What is Broadcast?').'</h2>
	
				<p class="float-right">
					<img src="'.$this->paths['url'].'/screenshot-1.png" alt="" title="'.__('What the Broadcast window looks like').'" />
				</p>
	
				<p>
					'.__('With Broadcast you can post to several blogs at once. The broadcast window is first shown at the bottom right on the Add New post/page screen.').'
					'.__('The window contains several options and a list of blogs you have access to.').'
				</p>

				<p>
					'.__('Some settings might be disabled by the site administrator and if you do not have write access to any blogs, other than this one, the Broadcast window might not appear.').' 
				</p>

				<p>
					'.__('To use the Broadcast plugin, simply select which blogs you want to broadcast the post to and then publish the post normally.').' 
				</p>

				<h3>'.__('Options').'</h3>
				
				<p>
					<em>'.__('Link this post to its children').'</em> '.__('will create a link from this post (the parent) to all the broadcasted posts (children). Updating the parent will result in all the children being updated. Links to the children can be removed in the page / post overview.').'
				</p>

				<p class="textcenter">
					<img class="border-single" src="'.$this->paths['url'].'/screenshot-2.png" alt="" title="'.__('Post overview with unlink options').'" />
				</p>

				<p>
					'.__('When a post is linked to children, the children are overwritten when post is updated - all the categories, tags and fields (including featured image) are also overwritten -  and when the parent is trashed or deleted the children get the same treatment. If you want to keep any children and delete only the parent, use the unlink links in the post overview. The unlink link below the post name removes all links and the unlinks to the right remove singular links.').'
				</p>

				<p>
					<em>'.__('Broadcast categories also').'</em> '.__('will also try to send the categories together with the post.').'
					'.__('In order to be able to broadcast the categories, the selected blogs must have the same category names (slugs) as this blog, else the posts will be posted as uncategorized.').'
				</p>

				<p>
					<em>'.__('Broadcast tags also').'</em> '.__('will also mark the broadcasted posts with the same tags.').'
				</p>

				<p>
					<em>'.__('Broadcast custom fields').'</em> '.__('will give the broadcasted posts the same custom fields as the original. Use this setting to broadcast the featured image.').'
				</p>

				<h3>'.__('Groups').'</h3>

				<p>
					'.__('If the site administrator allows it you may create groups to quickly select several blogs at once. To create a group, start by typing a group name in the text box and pressing the create button.').' 
				</p>

				<p class="textcenter">
					<img class="border-single" src="'.$this->paths['url'].'/screenshot-5.png" alt="" title="'.__('Group setup').'" />
				</p>

				<p>
					'.__('Then select which blogs you want to be automatically selected when you choose this group when editing a new post. Press the save button when you are done. Your new group is ready to be used!').'
					'.__('Simply choose it in the dropdown box and the blogs you specified will be automatically chosen.').'
				</p>

			</div>
		';
	}
	
	// --------------------------------------------------------------------------------------------
	// ----------------------------------------- Callbacks
	// --------------------------------------------------------------------------------------------

	public function add_meta_box_type($post)
	{
		return $this->add_meta_box( $post->post_type );
	}
	
	private function add_meta_box($type)
	{
		global $blog_id;
		global $post;
		$form = $this->form();
		
		$published = $post->post_status == 'publish';
		
		// Find out if this post is already linked
		$broadcast_data = $this->get_post_broadcast_data($blog_id, $post->ID);
		
		if ( $broadcast_data->get_linked_parent() !== false)
		{
			echo '<p>';
			echo __('This post is broadcasted child post. It cannot be broadcasted further.');
			echo '</p>';
			return;
		}
		
		$has_linked_children = $broadcast_data->has_linked_children();
		
		$blogs = $this->list_user_writable_blog($this->user_id());
		// Remove the blog we're currently working on from the list of writable blogs.
		unset($blogs[$blog_id]);
		
		global $current_user;
		get_current_user();
		$user_id = $current_user->ID;
		$last_used_settings = $this->load_last_used_settings($user_id);

		$post_type = $post->post_type;
		$post_type_object = get_post_type_object($post_type);
		$post_type_supports_categories = $post_type_object->capability_type == 'post';
		$post_type_supports_tags  = $post_type_object->capability_type == 'post';
		$post_type_supports_thumbnails = post_type_supports( $post_type, 'thumbnail' );
		$post_type_supports_custom_fields = post_type_supports( $post_type, 'custom-fields' );
		$post_type_is_hierarchical = $post_type_object->hierarchical;

		if ($this->role_at_least( $this->get_site_option('role_link') ))
		{
			// Check the link box is the post has been published and has children OR it isn't published yet.
			$linked = (
				($published && $broadcast_data->has_linked_children())
				||
				!$published
			); 
			$inputBroadcastLink = array(
				'name' => 'link',
				'type' => 'checkbox',
				'nameprefix' => '[broadcast]',
				'value' => 'link',
				'checked' => $linked,
				'title' => __('Create a link to the children, which will be updated when this post is updated, trashed when this post is trashed, etc.'),
				'label' => __('Link this post to its children'),
			);
			echo '<p>'.$form->makeInput($inputBroadcastLink).' '.$form->makeLabel($inputBroadcastLink).'</p>';
		}

		echo '<div style="height: 1px; background-color: #ddd;"></div>';
		
		if ($this->role_at_least( $this->get_site_option('role_categories') ) && $post_type_supports_categories)
		{
			$inputCategories = array(
				'name' => 'categories',
				'type' => 'checkbox',
				'nameprefix' => '[broadcast]',
				'value' => 'categories',
				'checked' => isset($last_used_settings['categories']),
				'label' => __('Broadcast categories also'),
				'title' => __('The categories must have the same name (slug) on the selected blogs.'),
			);
			echo '<p class="broadcast_input_categories">'.$form->makeInput($inputCategories).' '.$form->makeLabel($inputCategories).'</p>';
		}
		
		if ($this->role_at_least( $this->get_site_option('role_categories_create') ) && $post_type_supports_categories)
		{
			$inputCategoriesCreate = array(
				'name' => 'categories_create',
				'type' => 'checkbox',
				'nameprefix' => '[broadcast]',
				'value' => 'categories_create',
				'checked' => isset($last_used_settings['categories_create']),
				'label' => __('Create categories automatically'),
				'title' => __('The categories will be created if they don\'t exist on the selected blogs.'),
			);
			echo '<p class="broadcast_input_categories_create">&emsp;'.$form->makeInput($inputCategoriesCreate).' '.$form->makeLabel($inputCategoriesCreate).'</p>';
		}
		
		if ($this->role_at_least( $this->get_site_option('role_tags') ) && $post_type_supports_tags)
		{
			$inputTags = array(
				'name' => 'tags',
				'type' => 'checkbox',
				'nameprefix' => '[broadcast]',
				'value' => 'tags',
				'checked' => isset($last_used_settings['tags']),
				'label' => __('Broadcast tags also'),
				'title' => __('The tags must have the same name (slug) on the selected blogs.'),
			);
			echo '<p class="broadcast_input_tags">'.$form->makeInput($inputTags).' '.$form->makeLabel($inputTags).'</p>';
		}
		
		if ($this->role_at_least( $this->get_site_option('role_tags_create') ) && $post_type_supports_tags)
		{
			$inputTagsCreate = array(
				'name' => 'tags_create',
				'type' => 'checkbox',
				'nameprefix' => '[broadcast]',
				'value' => 'tags_create',
				'checked' => isset($last_used_settings['tags_create']),
				'label' => __('Create tags automatically'),
				'title' => __('The tags will be created if they don\'t exist on the selected blogs.'),
			);
			echo '<p class="broadcast_input_tags_create">&emsp;'.$form->makeInput($inputTagsCreate).' '.$form->makeLabel($inputTagsCreate).'</p>';
		}
		
		if ($this->role_at_least( $this->get_site_option('role_custom_fields') ) && $post_type_supports_custom_fields)
		{
			$inputCustomFields = array(
				'name' => 'custom_fields',
				'type' => 'checkbox',
				'nameprefix' => '[broadcast]',
				'value' => 'custom_fields',
				'checked' => isset($last_used_settings['custom_fields']),
				'title' => __('Broadcast all the custom fields and the featured image?'),
				'label' => __('Broadcast custom fields'),
			);
			echo '<p>'.$form->makeInput($inputCustomFields).' '.$form->makeLabel($inputCustomFields).'</p>';
		}
		
		echo '<div style="height: 1px; background-color: #ddd;"></div>';
		
		// Similarly, groups are only available to those who are allowed to use them.
		$data = $this->sqlUserGet($this->user_id());
		if ($this->role_at_least( $this->get_site_option('role_groups') ) && (count($data['groups'])>0))
		{
			$inputGroups = array(
				'name' => 'broadcast_group',
				'type' => 'select',
				'nameprefix' => '[broadcast]',
				'label' => __('Select blogs in group'),
				'options' => array(array('value' => '', 'text' => __('No group selected'))),
			);
			
			foreach($data['groups'] as $groupIndex=>$groupData)
				$inputGroups['options'][] = array('text' => $groupData['name'], 'value' => implode(' ', array_keys($groupData['blogs'])));
			
			// The javascripts just reacts on a click to the select box and selects those checkboxes that the selected group has.			
			echo '
				<p>
				'.$form->makeLabel($inputGroups).' '.$form->makeInput($inputGroups).'
				</p>

				<script type="text/javascript">					
					jQuery(document).ready( function($) {
						window.broadcast = {
							init: function(){
								
								// Allow blogs to be mass selected, unselected.							
								$("#__broadcast__broadcast_group").change(function(){
									var blogs = $(this).val().split(" ");
									for (var counter=0; counter < blogs.length; counter++)
									{
										$("#__broadcast__groups__666__" + blogs[counter]).attr("checked", true);
									}
									$("#__broadcast_group").val("");
								});
								
								// React to changes to the tags click.
								if ( !$("#__broadcast__tags").attr("checked") )
									$("p.broadcast_input_tags_create").hide();
									
								$("#__broadcast__tags").change(function(){
									$("p.broadcast_input_tags_create").animate({
										height: "toggle"
									});
								});
								
								// React to changes to the categories click.
								if ( !$("#__broadcast__categories").attr("checked") )
									$("p.broadcast_input_categories_create").hide();
									
								$("#__broadcast__categories").change(function(){
									$("p.broadcast_input_categories_create").animate({
										height: "toggle"
									});
								});
								
							}
						};
						
						broadcast.init();
					});
				</script>
			';
		}
		
		$blog_class = array();
		$blog_title = array();
		$selectedBlogs = array();
		
		// Preselect those children that this post has.
		$linked_children = $broadcast_data->get_linked_children();
		if ( count($linked_children) > 0 )
		{
			foreach($linked_children as $temp_blog_id => $postID)
			{
				$selectedBlogs[ $temp_blog_id ] = true;
				@$blog_class[ $temp_blog_id ] .= ' blog_is_already_linked';
				@$blog_title[ $temp_blog_id ] .= __('This blog has already been linked.');
			}
		}
		
		if ($this->get_site_option('always_use_required_list'))		
			$required_blogs = $this->get_required_blogs();
		else
			$required_blogs = array();
		
		foreach ($required_blogs as $temp_blog_id => $ignore)
		{
			@$blog_class[ $temp_blog_id ] .= ' blog_is_required';
			@$blog_title[ $temp_blog_id ] .= __('This blog is required and cannot be unselected.');
		}
			
		$selectedBlogs = array_flip( array_merge(
			array_keys($selectedBlogs),
			array_keys($required_blogs)
		) );
		
		// Remove all blacklisted blogs.
		foreach($blogs as $temp_blog_id=>$ignore)	
			if ($this->is_blog_blacklisted($temp_blog_id))
				unset($blogs[ $temp_blog_id ]);
		
		// Disable all blogs that do not have this post type.
		// I think there's a bug in WP since it reports the same post types no matter which blog we've switch_to_blogged.
		// Therefore, no further action.
		
		echo '<p class="howto">'. __('Broadcast to:') .'</p>

			<p style="text-align: center;">'.(count($blogs) > 20 ? $this->showGroupBlogsSelectUnselect(array('selector' => '.broadcast_blogs', 'input_label' => __('Select/deselect all'))): '').'</p>

			<div class="blogs">
				<p>' . $this->show_group_blogs(array(
								'blogs' => $blogs,
								'blog_class' => $blog_class,
								'blog_title' => $blog_title,
								'nameprefix' => 666,
								'selected' => $selectedBlogs,
								'readonly' => $required_blogs,
								'disabled' => $required_blogs,
							)) . '
				</p>
			</div>
	
			<p style="text-align: center;">'.(count($blogs) > 2 ? $this->showGroupBlogsSelectUnselect(array('selector' => '.broadcast_blogs', 'input_label' => __('Select/deselect all'))): '').'</p>
		';
	}
	
	public function save_post($post_id)
	{
		if (!$this->role_at_least( $this->get_site_option('role_broadcast') ))
			return;
			
		$allowed_post_status = array('publish');
		
		if ( $this->role_at_least( $this->get_site_option('role_broadcast_as_draft') ) )
			$allowed_post_status[] = 'draft';
			
		if ( $this->role_at_least( $this->get_site_option('role_broadcast_scheduled_posts') ) )
			$allowed_post_status[] = 'future';
			
		$post = get_post($post_id, 'ARRAY_A');
		if ( !in_array($post['post_status'], $allowed_post_status) )
			return;
			
		if (!isset($_POST['broadcast']))
		{
			// Site admin is never forced to do anything.
			if (is_super_admin())
				return;
				
			// Ignore this post. It's being force broadcast.
			if (isset($_POST['broadcast_force']))
				return;
				
			if ($this->get_site_option('always_use_required_list') == true)
				$_POST['broadcast_force'] = true;
			else
				return;
		}
		
		$post_type = $_POST['post_type'];
		$post_type_object = get_post_type_object($post_type);
		$post_type_supports_categories = $post_type_object->capability_type == 'post';
		$post_type_supports_tags  = $post_type_object->capability_type == 'post';
		$post_type_supports_thumbnails = post_type_supports( $post_type, 'thumbnail' );
		$post_type_supports_custom_fields = post_type_supports( $post_type, 'custom-fields' );
		$post_type_is_hierarchical = $post_type_object->hierarchical;

		// Create new post data from the original stuff.
		$newPost = $post;
		foreach(array('ID', 'guid', 'menu_order', 'comment_count', 'post_parent') as $key)
			unset($newPost[$key]);
			
		if (isset($_POST['broadcast']['groups']['666']))
			$blogs = array_keys($_POST['broadcast']['groups']['666']);
		else
			$blogs = array();
			
		// Now to add and remove blogs.
		$blogs = array_flip($blogs);
		
		// Remove the blog we're currently working on. No point in broadcasting to ourselves.
		global $blog_id;
		unset($blogs[$blog_id]);
		
		$user_id = $this->user_id();		// Convenience.

		// Remove blacklisted
		foreach($blogs as $blogID=>$ignore)	
			if (!$this->is_blog_user_writable($user_id, $blogID))
				unset($blogs[$blogID]);

		// Add required blogs.
		if ($this->get_site_option('always_use_required_list'))
		{
			$requiredBlogs = $this->get_required_blogs();
			foreach($requiredBlogs as $requiredBlog=>$ignore)
				$blogs[$requiredBlog] = $requiredBlog;
		}

		$blogs = array_keys($blogs);
		// Now to add and remove blogs: done
		
		// Do we actually need to to anything?
		if (count($blogs) < 1)
			return;

		$link = ($this->role_at_least( $this->get_site_option('role_link') ) && isset($_POST['broadcast']['link']));
		if ($link)
		{
			// Prepare the broadcast data for linked children.
			$broadcast_data = $this->get_post_broadcast_data($blog_id, $post_id);
			
			// Does this post type have parent support, so that we can link to a parent?
			if ($post_type_is_hierarchical && $_POST['post_parent'] > 0)
			{
				$post_id_parent = $_POST['post_parent'];
				$parent_broadcast_data = $this->get_post_broadcast_data($blog_id, $post_id_parent);
			}
		}
			
		$categories = (
			$this->role_at_least( $this->get_site_option('role_categories') )
			&&
			isset($_POST['broadcast']['categories'])
			&&
			$post_type_supports_categories
		);
		$categories_create = ($this->role_at_least( $this->get_site_option('role_categories_create') ) && isset($_POST['broadcast']['categories_create']));
		if ($categories)
		{
			$post_categories = wp_get_post_categories($post_id);
			$source_blog_categories = $this->get_current_blog_categories();
		}
		
		$tags = (
			$this->role_at_least( $this->get_site_option('role_tags') )
			&&
			isset($_POST['broadcast']['tags'])
			&&
			$post_type_supports_tags
		);
		$tags_create = ($this->role_at_least( $this->get_site_option('role_tags_create') ) && isset($_POST['broadcast']['tags_create']));
		if ($tags)
		{
			$postTags = wp_get_post_tags($post_id);
			// Convert the tag list to an array (why do they keep using stdclasses??)
			$postTags = $this->object_to_array($postTags);
			// And then make the tag ID the key.
			$postTags = $this->array_moveKey($postTags, 'term_id');
		}
		
		require_once('AttachmentData.php');
		$upload_dir = wp_upload_dir();	// We need to find out where the files are on disk for this blog.
		$attachment_data = array();
		$attached_files =& get_children( 'post_parent='.$post_id.'&post_type=attachment' );
		$has_attached_files = count($attached_files) > 0;
		if ($has_attached_files)
		{
			foreach($attached_files as $attached_file)
				$attachment_data[$attached_file->ID] = AttachmentData::from_attachment_id($attached_file->ID, $upload_dir);
		}
		
		$custom_fields = (
			$this->role_at_least( $this->get_site_option('role_custom_fields') )
			&&
			isset($_POST['broadcast']['custom_fields'])
			&&
			$post_type_supports_custom_fields
		);
		if ($custom_fields)
		{
			$postCustomFieldsTemp = get_post_custom($post_id);
			$postCustomFields = array();
			foreach($postCustomFieldsTemp as $key => $array)
				$postCustomFields[$key] = $array[0];
			
			$has_thumbnail = isset($postCustomFields['_thumbnail_id']);
			if ($has_thumbnail)
			{
				$thumbnail_id = $postCustomFields['_thumbnail_id'];
				unset( $postCustomFields['_thumbnail_id'] ); // There is a new thumbnail id for each blog.
				$attachment_data['thumbnail'] = AttachmentData::from_attachment_id($thumbnail_id, $upload_dir);
				// Now that we know what the attachment id the thumbnail has, we must remove it from the attached files to avoid duplicates.
				unset($attachment_data[$thumbnail_id]);
			}
			
			// Remove all the _internal custom fields.
			$postCustomFields = $this->keep_valid_custom_fields($postCustomFields);
		}
		
		// Sticky isn't a tag, cat or custom_field.
		$post_is_sticky = @($_POST['sticky'] == 'sticky');
		
		// And now save the user's last settings.
		$this->save_last_used_settings($user_id, $_POST['broadcast']);		
		
		$this->broadcasting = $_POST['broadcast'];
		$to_broadcasted_blogs = array();				// Array of blog names that we're broadcasting to.

		// To prevent recursion
		unset($_POST['broadcast']);
		
		$original_blog = $blog_id;
				
		foreach($blogs as $blogID)
		{
			if (!$this->is_blog_user_writable($user_id, $blogID))
				continue;
			switch_to_blog($blogID);
			
			// Post parent
			if ($link && isset($parent_broadcast_data))
				if ($parent_broadcast_data->has_linked_child_on_this_blog())
				{
					$linked_parent = $parent_broadcast_data->get_linked_child_on_this_blog();
					$newPost['post_parent'] = $linked_parent;
				}
			
			// Insert new? Or update? Depends on whether the parent post was linked before or is newly linked?
			$need_to_insert_post = true;
			if ($link)
				if ($broadcast_data->has_linked_child_on_this_blog())
				{
					$tempPostData = $newPost;
					$tempPostData['ID'] = $broadcast_data->get_linked_child_on_this_blog();
					$new_post_id = wp_update_post($tempPostData);
					$need_to_insert_post = false;
				}

			if ($need_to_insert_post)
			{
				$new_post_id = wp_insert_post($newPost);
				
				if ($link)
					$broadcast_data->add_linked_child($blogID, $new_post_id);
			}
			
			if ($categories)
			{
				// If we're updating a linked post, remove all the categories and start from the top.
				if ($link)
					if ($broadcast_data->has_linked_child_on_this_blog())
						wp_set_post_categories( $new_post_id, array() );
				
				// Get a list of cats that the target blog has.
				$target_blog_categories = $this->get_current_blog_categories();
				
				// Go through the original post's cats and compare each slug with the slug of the target cats.
				$categories_to_add_to = array();
				$have_created_categories = false;
				foreach($post_categories as $post_category)
				{
					$found = false;
					$sourceSlug = $source_blog_categories[$post_category]['slug'];
					foreach($target_blog_categories as $target_blog_category_id => $target_blog_category)
						if ($target_blog_category['slug'] == $sourceSlug)
						{
							$found = true;
							$categories_to_add_to[$target_blog_category_id] = $target_blog_category_id;
							break;
						}
						
					// Should we create the category if it doesn't exist?
					if ( !$found && $categories_create )
					{
						$new_target_category = wp_create_category($source_blog_categories[$post_category]['name']);
						$categories_to_add_to[ $post_category ] = $new_target_category;
						$have_created_categories = true;
					}
				}
				
				if ($categories_create)
					$this->sync_categories($original_blog, $blogID);

				if ( count($categories_to_add_to) > 0 )
					wp_set_post_categories( $new_post_id, $categories_to_add_to );
			}
			
			if ($tags)
			{
				// If we're updating a linked post, remove all the tags and start from the top.
				if ($link)
					if ($broadcast_data->has_linked_child_on_this_blog())
						wp_set_post_tags( $new_post_id, array() );
				
				// Get a list of tags that the target blog has.
				$targetTags = get_tags(array(
					'hide_empty' => false,
				));
				
				$targetTags = $this->object_to_array($targetTags);
				$targetTags = $this->array_moveKey($targetTags, 'term_id');
				
				// Go through the original post's tags and compare each slug with the slug of the target tags.
				$tagsToAddTo = array();
				foreach($postTags as $tag_id=>$tagData)
				{
					$found = false;
					$sourceSlug = $tagData['slug'];
					foreach($targetTags as $targetTag_id => $targetTag)
						if ($targetTag['slug'] == $sourceSlug)
						{
							$found = true;
							$tagsToAddTo[$targetTag_id] = $targetTag_id;
							break;
						}
						
					// Should we create the tag if it doesn't exist?
					if (!$found && $tags_create)
					{
						$newTagID = wp_insert_term($tagData['name'], 'post_tag', array(
							'description' => $tagData['description'],
							'slug' => $tagData['slug'],
						));
						$newTagID = $newTagID['term_id'];
						$tagsToAddTo[$newTagID] = $newTagID;
					}
				}
				if (count($tagsToAddTo) > 0)
					wp_set_post_tags( $new_post_id, array_keys($tagsToAddTo) );
			}
			
			/**
				Remove the current attachments.
			*/
			$attachments_to_remove =& get_children( 'post_parent='.$new_post_id.'&post_type=attachment' );
			foreach ($attachments_to_remove as $attachment_to_remove)
				wp_delete_attachment($attachment_to_remove->ID);
			
			foreach($attachment_data as $key=>$attached_file)
			{
				if ($key != 'thumbnail')
					$this->copy_attachment($attached_file, $new_post_id);
			}
			
			if ($custom_fields)
			{
				// Remove all old custom fields.
				$old_custom_fields = get_post_custom($new_post_id);

				foreach($old_custom_fields as $key => $value)
				{
					// This post has a featured image! Remove it from disk!
					if ($key == '_thumbnail_id')
					{
						$thumbnail_post = $value[0];
						wp_delete_post($thumbnail_post);
					}
					
					delete_post_meta($new_post_id, $key);
				}
				
				foreach($postCustomFields as $meta_key => $meta_value)
					update_post_meta($new_post_id, $meta_key, $meta_value);
				
				// Attached files are custom fields... but special custom fields. Therefore they need special treatment. Like retards. Retarded files.
				if ($has_thumbnail)
				{
					$new_attachment_id = $this->copy_attachment($attachment_data['thumbnail'], $new_post_id);
					update_post_meta($new_post_id, '_thumbnail_id', $new_attachment_id);
				}
			}
			
			// Sticky behaviour
			$child_post_is_sticky = is_sticky($new_post_id);
			if ($post_is_sticky && !$child_post_is_sticky)
				stick_post($new_post_id);
			if (!$post_is_sticky && $child_post_is_sticky)
				unstick_post($new_post_id);
			
			if ($link)
			{			
				$new_post_broadcast_data = $this->get_post_broadcast_data($blog_id, $new_post_id);
				$new_post_broadcast_data->set_linked_parent( $original_blog, $post_id );
				$this->set_post_broadcast_data($blogID, $new_post_id, $new_post_broadcast_data);
			}

			$to_broadcasted_blogs[] = '<a href="' . get_permalink( $new_post_id ) . '">' . get_bloginfo('name') . '</a>';
			
			restore_current_blog();
		}
		
		// Finished broadcasting.
		$this->broadcasting = false;
		
		if ( $this->get_option('activity_monitor_broadcasts') )
		{
			$post_url_and_name = '<a href="' . get_permalink( $post_id ) . '">' . $post['post_title']. '</a>';
			do_action('threewp_activity_monitor_new_activity', array(
				'activity_type' => '3wp_broadcasted',
				'tr_class' => 'activity_monitor_broadcast activity_monitor_broadcasted',
				'activity' => array(
					'' => '%user_display_name_with_link% has broadcasted '.$post_url_and_name.' to: ' . implode(', ', $to_broadcasted_blogs),
				),
			));
		}

		// Save the post broadcast data.
		if ($link)
			$this->set_post_broadcast_data($blog_id, $post_id, $broadcast_data);		
	}
	
	public function trash_post($post_id)
	{
		$this->trash_untrash_delete_post('wp_trash_post', $post_id);
	}
	
	public function untrash_post($post_id)
	{
		$this->trash_untrash_delete_post('wp_untrash_post', $post_id);
	}
	
	public function delete_post($post_id)
	{
		$this->trash_untrash_delete_post('wp_delete_post', $post_id);
	}
	
	/**
	 * Issues a specific command on all the blogs that this post_id has linked children on. 
	 * @param string $command Command to run.
	 * @param int $post_id Post with linked children
	 */
	private function trash_untrash_delete_post($command, $post_id)
	{
		global $blog_id;
		$broadcast_data = $this->get_post_broadcast_data($blog_id, $post_id);
		if ($broadcast_data->has_linked_children())
		{
			foreach($broadcast_data->get_linked_children() as $childBlog=>$childPost)
			{
				if ($command == 'wp_delete_post')
				{
					// Delete the broadcast data of this child
					$this->delete_post_broadcast_data($childBlog, $childPost);
				}
				switch_to_blog($childBlog);
				$command($childPost);
				restore_current_blog();
			}
		}
		
		if ($command == 'wp_delete_post')
		{
			global $blog_id;
			// Find out if this post has a parent.
			$linked_parent_broadcast_data = $this->get_post_broadcast_data($blog_id, $post_id);
			$linked_parent_broadcast_data = $linked_parent_broadcast_data->get_linked_parent();
			if ($linked_parent_broadcast_data !== false)
			{
				// Remove ourselves as a child.
				$parent_broadcast_data = $this->get_post_broadcast_data( $linked_parent_broadcast_data['blog_id'], $linked_parent_broadcast_data['post_id'] );
				$parent_broadcast_data->remove_linked_child($blog_id);
				$this->set_post_broadcast_data( $linked_parent_broadcast_data['blog_id'], $linked_parent_broadcast_data['post_id'], $parent_broadcast_data );
			}
			
			$this->delete_post_broadcast_data($blog_id, $post_id);
		}

	}
	
	public function manage_posts_columns($defaults)
	{
		$defaults['3wp_broadcast'] = '<span title="'.__('Shows which blogs have posts linked to this one').'">'.__('Broadcasted').'</span>';
		return $defaults;
	}
	
	public function manage_posts_custom_column($column_name, $post_id)
	{
		if ($column_name != '3wp_broadcast')
			return;
			
		global $blog_id;		
		global $post;
		$broadcast_data = $this->get_post_broadcast_data($blog_id, $post->ID);
		if ( $broadcast_data->get_linked_parent() !== false)
		{
			$parent = $broadcast_data->get_linked_parent();
			$parent_blog_id = $parent['blog_id'];
			switch_to_blog( $parent_blog_id );
			echo __(sprintf('Linked from %s', '<a href="' . get_bloginfo('url') . '/wp-admin/post.php?post=' .$parent['post_id'] . '&action=edit">' . get_bloginfo('name') . '</a>' ));
			restore_current_blog();
		}
		if ( $broadcast_data->has_linked_children() )
		{
			$children = $broadcast_data->get_linked_children();
			
			if (count($children) < 0)
				return;
				
			$display = array(); // An array makes it easy to manipulate lists
			$blogs = $this->cached_blog_list();
			foreach($children as $blogID => $postID)
			{
				$urlChild = $blogs[$blogID]['siteurl'] . '/?p=' . $postID;
				// The post id is for the current blog, not the target blog.
				$urlUnlink = wp_nonce_url("options-general.php?page=ThreeWP_Broadcast&amp;action=unlink&amp;post=$post_id&amp;child=$blogID", 'broadcast_unlink_' . $blogID . '_' . $post_id);
				$urlTrash = wp_nonce_url("options-general.php?page=ThreeWP_Broadcast&amp;action=trash&amp;post=$post_id&amp;child=$blogID", 'broadcast_trash_' . $blogID . '_' . $post_id);
				$display[] = '<div class="broadcasted_blog"><a class="broadcasted_child" href="'.$urlChild.'">'.$blogs[$blogID]['blogname'].'</a>
					<div class="row-actions broadcasted_blog_actions">
						<small>
						<a href="'.$urlUnlink.'" title="'.__('Remove links to this broadcasted child').'">'.__('Unlink').'</a>
						| <span class="trash"><a href="'.$urlTrash.'" title="'.__('Put this broadcasted child in the trash').'">'.__('Trash').'</a></span>
						</small>
					</div>
				</div>
				';
			}
			echo '<ul><li>' . implode('</li><li>', $display) . '</li></ul>';
		}
	}
	
	public function post_row_actions($actions)
	{
		global $blog_id;
		global $post;
		$broadcast_data = $this->get_post_broadcast_data($blog_id, $post->ID);
		if ($broadcast_data->has_linked_children())
			$actions = array_merge($actions, array(
				'broadcast_unlink' => '<a href="'.wp_nonce_url("options-general.php?page=ThreeWP_Broadcast&amp;action=unlink&amp;post=$post->ID", 'broadcast_unlink_' . $post->ID).'" title="'.__('Remove links to all the broadcasted children').'">'.__('Unlink').'</a>',
			));
		return $actions;
	}
	
	public function create_meta_box()
	{
		if ($this->role_at_least( $this->get_site_option('role_broadcast') ))
		{
			// If the user isn't a site admin, or if the user doesn't have any other blogs to write to...
			if ( $this->role_at_least('super_admin') || count($this->list_user_writable_blog($this->user_id()))> 1 )	// User always has at least one to write to, if he's gotten THIS far.
			{
				$this->load_language();
				$post_types = $this->get_site_option('post_types');
				foreach($post_types as $post_type => $ignore)
					add_meta_box('threewp_broadcast', __('Broadcast'), array(&$this, 'add_meta_box_type'), $post_type, 'side', 'low' );
				add_action('save_post', array(&$this, 'save_post'), $this->get_site_option('save_post_priority'));
			}
		}
	}
	
	public function the_permalink($link)
	{
		global $id;
		global $blog_id;
		
		$broadcast_data = $this->get_post_broadcast_data($blog_id, $id);
		
		$linked_parent = $broadcast_data->get_linked_parent();
		
		if ($linked_parent === false)
			return $link;

		switch_to_blog( $linked_parent['blog_id'] );
		$returnValue = get_permalink( $linked_parent['post_id'] );
		restore_current_blog();
		return $returnValue;
	}
	
	public function post_link($link, $p2)
	{
		global $id;
		global $blog_id;
		
		$broadcast_data = $this->get_post_broadcast_data($blog_id, $id);
		
		$linked_parent = $broadcast_data->get_linked_parent();
		
		if ($linked_parent === false)
			return $link;

		switch_to_blog( $linked_parent['blog_id'] );
		$post = get_post( $linked_parent['post_id'] );
		restore_current_blog();
		
		return $post->guid;
	}
	
	// --------------------------------------------------------------------------------------------
	// ----------------------------------------- Misc functions
	// --------------------------------------------------------------------------------------------

	private function show_group_blogs($options)
	{
		$form = $this->form();
		$returnValue = '<ul class="broadcast_blogs">';
		$nameprefix = "[broadcast][groups][" . $options['nameprefix'] . "]";
		foreach($options['blogs'] as $blog)
		{
			$blog_id = $blog['blog_id'];	// Convience
			$required = $this->isRequired( $blog_id );
			$checked = isset( $options['selected'][ $blog_id ] ) || $required;
			$input = array(
				'name' => $blog_id,
				'type' => 'checkbox',
				'nameprefix' => $nameprefix,
				'label' => $blog['blogname'],
				'disabled' => isset($options['disabled'][ $blog_id ]),
				'readonly' => isset($options['readonly'][ $blog_id ]),
				'value' => 'blog_' .$checked,
				'checked' => $checked,
				'title' => $blog['siteurl'],
			);
			
			$blog_class = isset($options['blog_class'][$blog_id]) ? $options['blog_class'][$blog_id] : '';
			$blog_title = isset($options['blog_title'][$blog_id]) ? $options['blog_title'][$blog_id] : '';
			
			$returnValue .= '<li class="'.$blog_class.'"
				 title="'.$blog_title.'">'.$form->makeInput($input).' '.$form->makeLabel($input).'</li>';
		}
		$returnValue .= '</ul>';
		return $returnValue;
	}
	
	private function showGroupBlogsSelectUnselect($options)
	{
		$options = array_merge(array(
			'selector' => 'empty',
			'input_label' => __('Select/deselect all'),
		), $options);
		
		$id = rand(0, time());
		
		$form = $this->form();
		
		$inputSelectAll = array(
			'name' => 'broadcast_select_all' . $id,
			'type' => 'checkbox',
			'label' => $options['input_label'],
			'value' => 0,
		);

		return '
			'.$form->makeInput($inputSelectAll).' '.$form->makeLabel($inputSelectAll).'
			<script type="text/javascript">
				jQuery(document).ready( function($) {
					$("#__broadcast_select_all'.$id.'").click(function(){
						var checkedStatus = $(this).attr("checked");
						$("'.$options['selector'].' .checkbox").each(function(key, value){
							if ($(value).attr("disabled") != true)
							{
								$(value).attr("checked", checkedStatus);
							}
						});
					})
				});
			</script>
		';
	}
	
	protected function list_user_writable_blog($user_id)
	{
		// Super admins can write anywhere they feel like.
		if (is_super_admin())
		{
			$blogs = $this->get_blog_list();
			$blogs = $this->sort_blogs($blogs);
			return $blogs;
		}
		
		$blogs = get_blogs_of_user($user_id);
		foreach($blogs as $index=>$blog)
		{
			$blog = $this->object_to_array($blog);
			$blog['blog_id'] = $blog['userblog_id'];
			$blogs[$index] = $blog;
			if (!$this->is_blog_user_writable($user_id, $blog['blog_id']))
				unset($blogs[$index]);
		}
		return $this->sort_blogs($blogs);
	}
	
	protected function is_blog_user_writable($user_id, $blog_id)
	{
		// If this blog is in the blacklist, reply no.
		if ($this->is_blog_blacklisted($blog_id))
			return false;
			
		// Else, check that the user has write access.
		switch_to_blog($blog_id);
		$returnValue = current_user_can('publish_posts');                                                                                                                                                                                                        
		restore_current_blog();
		return $returnValue;
	}
	
	/**
	 * Returns whether the site admin has blacklisted the blog.
	 */
	protected function isRequired($blog_id)
	{
		if (is_super_admin())
			return false;
		$requiredlist = $this->get_site_option('requiredlist');
		$requiredlist = explode(',', $requiredlist);
		$requiredlist = array_flip($requiredlist);
		return isset($requiredlist[$blog_id]);
	}
	
	/**
	 * Returns whether the site admin has blacklisted the blog.
	 */
	protected function is_blog_blacklisted($blog_id)
	{
		$blacklist = $this->get_site_option('blacklist');
		if ($blacklist == '')
			return false;
		$blacklist = explode(',', $blacklist);
		$blacklist = array_flip($blacklist);
		return isset($blacklist[$blog_id]);
	}
	
	/**
	 * Lists ALL of the blogs. Including the main blog.
	 */
	protected function get_blog_list()
	{
		$site_id = get_current_site();
		$site_id = $site_id->id;
		
		// Get a custom list of all blogs on this site. This bypasses Wordpress' filter that removes private and mature blogs.
		$blogs = $this->query("SELECT * FROM `".$this->wpdb->base_prefix."blogs` WHERE site_id = '$site_id' ORDER BY blog_id");
		$blogs = $this->array_moveKey($blogs, 'blog_id');
		
		foreach($blogs as $blog_id=>$blog)
		{
			$tempBlog = $this->object_to_array(get_blog_details($blog_id, true));
			$blogs[$blog_id]['blogname'] = $tempBlog['blogname'];
			$blogs[$blog_id]['siteurl'] = $tempBlog['siteurl'];
			$blogs[$blog_id]['domain'] = $tempBlog['domain'];
		}

		return $this->sort_blogs($blogs);
	}
	
	/**
		Returns a list of all the, as per admin, required blogs to broadcast to.
	**/
	private function get_required_blogs()
	{
		$requiredBlogs = explode(',', $this->get_site_option('requiredlist'));
		$requiredBlogs = array_flip($requiredBlogs);
		return $requiredBlogs;
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
		if ($blogs === null)
		{
			$blogs = $this->get_blog_list();
			$this->blogs_cache = $blogs;
		}
		return $blogs;		
	}
	
	/**
	 * Sorts the blogs by name. The Site Blog is first, no matter the name.
	 */
	protected function sort_blogs($blogs)
	{
		// Make sure the main blog is saved.
		$firstBlog = array_shift($blogs);
		
		$blogs = self::array_moveKey($blogs, 'blogname');
		ksort($blogs);
		
		// Put it back up front.
		array_unshift($blogs, $firstBlog);
			
		return self::array_moveKey($blogs, 'blog_id');
	}
	
	/**
	 * Retrieves the BroadcastData for this post_id.
	 * 
	 * Will return a fully functional BroadcastData class even if the post doesn't have BroadcastData.
	 * 
	 * Use BroadcastData->is_empty() to check for that.
	 * @param int $post_id Post ID to retrieve data for.
	 */
	private function get_post_broadcast_data($blog_id, $post_id)
	{
		require_once('BroadcastData.php');
		$returnValue = $this->sqlBroadcastDataGet($blog_id, $post_id);
		
		if (count($returnValue) < 1)
			return new BroadcastData(array());
		return new BroadcastData($returnValue);
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
	private function set_post_broadcast_data($blog_id, $post_id, $broadcast_data)
	{
		require_once('BroadcastData.php');
		if ($broadcast_data->is_modified())
			if ($broadcast_data->is_empty())
				$this->sqlbroadcastDataDelete($blog_id, $post_id);
			else
				$this->sqlbroadcastDataUpdate($blog_id, $post_id, $broadcast_data->getData());
	}
	
	/**
		Deletes the broadcast data completely of a post in a blog.
	*/
	private function delete_post_broadcast_data($blog_id, $post_id)
	{
		$this->sqlbroadcastDataDelete($blog_id, $post_id);
	}
	
	/**
		Creates a new attachment, from the $attachment_data, to a post.
		
		Returns the attachment's post_id.
	*/
	private function copy_attachment($attachment_data, $post_id)
	{
		// Copy the file to the blog's upload directory
		$upload_dir = wp_upload_dir();
		copy($attachment_data->filename_path(), $upload_dir['path'] . '/' . $attachment_data->filename_base());
		
		// And now create the attachment stuff.
		// This is taken almost directly from http://codex.wordpress.org/Function_Reference/wp_insert_attachment
		$wp_filetype = wp_check_filetype( $attachment_data->filename_base(), null );
		$attachment = array(
			'post_mime_type' => $wp_filetype['type'],
			'post_title' => preg_replace('/\.[^.]+$/', '', $attachment_data->filename_base()),
			'post_content' => '',
			'post_status' => 'inherit'
		);
		$attach_id = wp_insert_attachment( $attachment, $upload_dir['path'] . '/' . $attachment_data->filename_base(), $post_id );
		
		require_once(ABSPATH . "wp-admin" . '/includes/image.php');
		$attach_data = wp_generate_attachment_metadata( $attach_id, $upload_dir['path'] . '/' . $attachment_data->filename_base() );
		wp_update_attachment_metadata( $attach_id,  $attach_data );
		return $attach_id;
	}
	
	private function load_last_used_settings($user_id)
	{
		$data = $this->sqlUserGet($user_id);
		if (!isset($data['last_used_settings']))
			$data['last_used_settings'] = array();
		return $data['last_used_settings'];
	}
	
	private function save_last_used_settings($user_id, $settings)
	{
		$data = $this->sqlUserGet($user_id);
		$data['last_used_settings'] = $settings;
		$this->sqlUserSet($user_id, $data);
	}
	
	private function get_current_blog_categories()
	{
		$categories = get_categories(array(
			'hide_empty' => false,
		));
		$categories = $this->object_to_array($categories);
		$categories = $this->array_moveKey($categories, 'term_id');
		return $categories;
	}
	
	private function sync_categories($source_blog_id, $target_blog_id)
	{
		global $wpdb;
		switch_to_blog( $source_blog_id );
		$source_categories = $this->get_current_blog_categories();
		restore_current_blog();
		
		switch_to_blog( $target_blog_id );

		$target_categories = $this->get_current_blog_categories();
		
		// Keep track of which cats we've found.
		$found_targets = array();
		$found_sources = array();
		
		// First step: find out which of the target cats exist on the source blog
		foreach($target_categories as $target_category_id => $target_category)
			foreach($source_categories as $source_category_id => $source_category)
			{
				if ( isset($found_sources[$source_category_id]) )
					continue;
				if ($source_category['slug'] == $target_category['slug'])
				{
					$found_targets[ $target_category_id ] = $source_category_id;
					$found_sources[ $source_category_id ] = $target_category_id;
				}
			}
		
		// Now we know which of the cats on our target blog exist on the source blog.
		// Next step: see if the parents are the same on the target as they are on the source.
		// "Same" meaning pointing to the same slug.
		foreach($found_targets as $target_category_id => $source_category_id)
		{
			$parent_of_target_category = $target_categories[ $target_category_id ][ 'parent' ];
			$parent_of_equivalent_source_category = $source_categories[ $source_category_id ]['parent'];
			
			if ( $parent_of_target_category != $parent_of_equivalent_source_category &&
				(isset( $found_sources[ $parent_of_equivalent_source_category ] ) || $parent_of_equivalent_source_category == 0 )
			)
			{
				if ( $parent_of_equivalent_source_category != 0)
					$new_category_parent = $found_sources[ $parent_of_equivalent_source_category ];
				else
					$new_category_parent = 0;
				$this->set_category_parent($target_category_id, $new_category_parent);
			}
		}
			
		restore_current_blog();
	}
	
	private function set_category_parent($cat_id, $parent_id)
	{
		wp_update_category(array(
			'cat_ID' => $cat_id,
			'category_parent' => $parent_id,
		));
		// wp_update_category alone won't work. The "cache" needs to be cleared.
		// see: http://wordpress.org/support/topic/category_children-how-to-recalculate?replies=4
		delete_option('category_children');
	}
	
	protected function make_table_row($input, $form = null)
	{
		if ($form === null)
			$form = $this->form();
		return '
			<tr>
				<th>'.$form->makeLabel($input).'</th>
				<td>
					<div class="input_itself">
						'.$form->makeInput($input).'
					</div>
					<div class="input_description">
						'.$form->makeDescription($input).'
					</div>
				</td>
			</tr>';
	}
	
	private function keep_valid_custom_fields($custom_fields)
	{
		foreach($custom_fields as $key => $array)
			if ( !$this->is_custom_field_valid($key) )
				unset( $custom_fields[$key] ); 

		return $custom_fields;
	}
	
	private function is_custom_field_valid($custom_field)
	{
		if ( !isset($this->custom_field_exceptions_cache) )
			$this->custom_field_exceptions_cache = $this->get_site_option('custom_field_exceptions');

		if ( strpos($custom_field, '_') !== 0 )
			return true;
		
		foreach($this->custom_field_exceptions_cache as $exception)
			if (strpos($custom_field, $exception) !== false )
				return true;
		
		return false;
	}
	
	/**
		If broadcasting, will return $_POST['broadcast'].
		Else false.
	*/
	public function is_broadcasting()
	{
		return $this->broadcasting;
	}
	
	// --------------------------------------------------------------------------------------------
	// ----------------------------------------- SQL
	// --------------------------------------------------------------------------------------------

	/**
	 * Gets the user data.
	 * 
	 * Returns an array of user data.
	 */
	private function sqlUserGet($user_id)
	{
		$returnValue = $this->query("SELECT * FROM `".$this->wpdb->base_prefix."_3wp_broadcast` WHERE user_id = '$user_id'");
		$returnValue = @unserialize( base64_decode($returnValue[0]['data']) );		// Unserialize the data column of the first row.
		if ($returnValue === false)
			$returnValue = array();
		
		// Merge/append any default values to the user's data.
		return array_merge(array(
			'groups' => array(),
		), $returnValue);
	}
	
	/**
	 * Saves the user data.
	 */
	private function sqlUserSet($user_id, $data)
	{
		$data = serialize($data);
		$data = base64_encode($data);
		$this->query("DELETE FROM `".$this->wpdb->base_prefix."_3wp_broadcast` WHERE user_id = '$user_id'");
		$this->query("INSERT INTO `".$this->wpdb->base_prefix."_3wp_broadcast` (user_id, data) VALUES ('$user_id', '$data')");
	}
	
	private function sqlBroadcastDataGet($blog_id, $post_id)
	{
		$returnValue = $this->query("SELECT data FROM `".$this->wpdb->base_prefix."_3wp_broadcast_broadcastdata` WHERE blog_id = '$blog_id' AND post_id = '$post_id'");
		$returnValue = @unserialize( base64_decode($returnValue[0]['data']) );		// Unserialize the data column of the first row.
		if ($returnValue === false)
			$returnValue = array();
		return $returnValue;
	}
	
	private function sqlBroadcastDataDelete($blog_id, $post_id)
	{
		$this->query("DELETE FROM `".$this->wpdb->base_prefix."_3wp_broadcast_broadcastdata` WHERE blog_id = '$blog_id' AND post_id = '$post_id'");
	}
		
	private function sqlBroadcastDataUpdate($blog_id, $post_id, $data)
	{
		$data = serialize($data);
		$data = base64_encode($data);
		$this->sqlBroadcastDataDelete($blog_id, $post_id);
		$this->query("INSERT INTO `".$this->wpdb->base_prefix."_3wp_broadcast_broadcastdata` (blog_id, post_id, data) VALUES ('$blog_id', '$post_id', '$data')");
	}
}

$threewp_broadcast = new ThreeWP_Broadcast();
?>