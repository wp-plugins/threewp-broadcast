<?php
/*                                                                                                                                                                                                                                                             
Plugin Name: ThreeWP Broadcast
Plugin URI: http://mindreantre.se/threewp-activity-monitor/
Description: Network plugin to broadcast a post to other blogs. Whitelist, blacklist, groups and automatic category+tag+custom field posting/creation available. 
Version: 1.0
Author: Edward Hevlund
Author URI: http://www.mindreantre.se
Author Email: edward@mindreantre.se
*/

if(preg_match('#' . basename(__FILE__) . '#', $_SERVER['PHP_SELF'])) { die('You are not allowed to call this page directly.'); }

require_once('ThreeWP_Base_Broadcast.php');
class ThreeWP_Broadcast extends ThreeWP_Base_Broadcast
{
	protected $options = array(
		'role_broadcast' => 'site_admin',					// Role required to use broadcast function
		'role_link' => 'site_admin',						// Role required to use the link function
		'role_broadcast_as_draft' => 'site_admin',			// Role required to broadcast posts as templates
		'role_groups' => 'site_admin',						// Role required to use groups
		'role_categories' => 'site_admin',					// Role required to broadcast the categories
		'role_categories_create' => 'site_admin',			// Role required to create categories automatically
		'role_tags' => 'site_admin',						// Role required to broadcast the categories
		'role_tags_create' => 'site_admin',					// Role required to create categories automatically
		'role_custom_fields' => 'site_admin',				// Role required to broadcast the categories
		'requiredlist' => '',								// Comma-separated string of blogs to require
		'requirewhenbroadcasting' => true,					// Require blogs only when broadcasting?
		'save_post_priority' => 640,						// Priority of save_post action. Higher = lets other plugins do their stuff first
		'blacklist' => '',									// Comma-separated string of blogs to automatically exclude
	);
	
	public function __construct()
	{
		parent::__construct(__FILE__);
		if ($this->isNetwork)
		{
			define("_3BC", get_class($this));
			register_activation_hook(__FILE__, array(&$this, 'activate') );
			add_action('admin_menu', array(&$this, 'add_menu') );
			add_action('admin_print_styles', array(&$this, 'load_styles') );
			add_action('admin_menu', array(&$this, 'create_meta_box'));
		}
	}
	
	public function add_menu()
	{
		if (is_site_admin())
			add_submenu_page('ms-admin.php', 'ThreeWP Broadcast', 'Broadcast', 'administrator', 'ThreeWP_Broadcast', array (&$this, 'admin'));
		add_options_page('ThreeWP Broadcast', __('Broadcast', _3BC), $this->get_user_role(), 'ThreeWP_Broadcast', array (&$this, 'user'));
		if ($this->role_at_least( $this->get_option('role_link') ))
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
		$load |= strpos($_GET['page'],get_class()) !== false;
		foreach(array('post-new.php') as $string)
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
		$this->deregister_options();
		$this->query("DROP TABLE `".$this->wpdb->base_prefix."_3wp_broadcast`");
		$this->query("DROP TABLE `".$this->wpdb->base_prefix."_3wp_broadcast_broadcastdata`");
	}
	
	// --------------------------------------------------------------------------------------------
	// ----------------------------------------- Menus
	// --------------------------------------------------------------------------------------------

	public function admin()
	{
		$this->tabs(array(
			'tabs' =>		array('Settings',			'Required list',			'Blacklist',			'Uninstall'),
			'functions' =>	array('adminSettings',		'adminRequiredList',		'adminBlacklist',		'adminUninstall'),
		));
	}
	
	public function user()
	{
		$this->loadLanguages(_3BC);
		
		$tabData = array(
			'tabs'		=>	array(),
			'functions' =>	array(),
		);
		
		if (isset($_GET['action']) && $_GET['action'] == 'unlink')
		{
			$tabData['tabs'][] = __('Unlink', _3BC);
			$tabData['functions'][] = 'userUnlink';
		}
		
		if (isset($_GET['action']) && $_GET['action'] == 'trash')
		{
			$tabData['tabs'][] = __('Trash', _3BC);
			$tabData['functions'][] = 'userTrash';
		}
		
		$tabData['tabs'][] = __('Help', _3BC);
		$tabData['functions'][] = 'userHelp';
		
		if ($this->role_at_least( $this->get_option('role_groups') ))
		{
			$tabData['tabs'][] = __('ThreeWP Broadcast groups', _3BC);
			$tabData['functions'][] = 'userEditGroups';
		}

		$this->tabs($tabData);
	}
	
	protected function adminSettings()
	{
		$form = $this->form();
		
		// Collect all the roles.
		$roles = array('site_admin' => array('text' => 'Site admin', 'value' => 'site_admin'));
		foreach($this->roles as $role)
			$roles[$role['name']] = array('value' => $role['name'], 'text' => ucfirst($role['name']));
			
		if (isset($_POST['save']))
		{
			$this->update_option('save_post_priority', intval($_POST['save_post_priority']));
			foreach(array('role_broadcast', 'role_link', 'role_broadcast_as_draft', 'role_groups', 'role_categories', 'role_categories_create', 'role_tags', 'role_tags_create', 'role_custom_fields') as $key)
				$this->update_option($key, (isset($roles[$_POST[$key]]) ? $_POST[$key] : 'site_admin'));
			$this->message('Options saved!');
		}
			
		$inputs = array(
			'role_broadcast' => array(
				'name' => 'role_broadcast',
				'type' => 'select',
				'label' => 'Broadcast access role',
				'value' => $this->get_option('role_broadcast'),
				'options' => $roles,
			),
			'role_link' => array(
				'name' => 'role_link',
				'type' => 'select',
				'label' => 'Link access role',
				'value' => $this->get_option('role_link'),
				'options' => $roles,
			),
			'role_broadcast_as_draft' => array(
				'name' => 'role_broadcast_as_draft',
				'type' => 'select',
				'label' => 'Draft broadcast access role',
				'value' => $this->get_option('role_broadcast_as_draft'),
				'options' => $roles,
			),
			'role_groups' => array(
				'name' => 'role_groups',
				'type' => 'select',
				'label' => 'Group access role',
				'value' => $this->get_option('role_groups'),
				'options' => $roles,
			),
			'role_categories' => array(
				'name' => 'role_categories',
				'type' => 'select',
				'label' => 'Categories broadcast role',
				'value' => $this->get_option('role_categories'),
				'options' => $roles,
			),
			'role_categories_create' => array(
				'name' => 'role_categories_create',
				'type' => 'select',
				'label' => 'Category creation role',
				'value' => $this->get_option('role_categories_create'),
				'options' => $roles,
			),
			'role_tags' => array(
				'name' => 'role_tags',
				'type' => 'select',
				'label' => 'Tags broadcast role',
				'value' => $this->get_option('role_tags'),
				'options' => $roles,
			),
			'role_tags_create' => array(
				'name' => 'role_tags_create',
				'type' => 'select',
				'label' => 'Tag creation role',
				'value' => $this->get_option('role_tags_create'),
				'options' => $roles,
			),
			'role_custom_fields' => array(
				'name' => 'role_custom_fields',
				'type' => 'select',
				'label' => 'Custom field broadcast role',
				'value' => $this->get_option('role_custom_fields'),
				'options' => $roles,
			),
			'save_post_priority' => array(
				'name' => 'save_post_priority',
				'type' => 'text',
				'label' => 'Action priority',
				'value' => $this->get_option('save_post_priority'),
				'size' => 3,
				'maxlength' => 3,
			),
			'save' => array(
				'name' => 'save',
				'type' => 'submit',
				'value' => 'Save options',
				'cssClass' => 'button-primary',
			),
		);
		
		echo '
			'.$form->start().'

			<p>
				The broadcast access role is the user role required to use the broadcast function at all. 
			</p>

			<p class="bigp">
				'.$form->makeLabel($inputs['role_broadcast']).' '.$form->makeInput($inputs['role_broadcast']).'
			</p>


			<p>
				When a post is linked with broadcasted posts, the child posts are updated / deleted when the parent is updated. 
			</p>

			<p class="bigp">
				'.$form->makeLabel($inputs['role_link']).' '.$form->makeInput($inputs['role_link']).'
			</p>


			<p>
				Which role is needed to post drafts? 
			</p>

			<p class="bigp">
				'.$form->makeLabel($inputs['role_broadcast_as_draft']).' '.$form->makeInput($inputs['role_broadcast_as_draft']).'
			</p>


			<p>
				Role needed to administer their own groups? 
			</p>

			<p class="bigp">
				'.$form->makeLabel($inputs['role_groups']).' '.$form->makeInput($inputs['role_groups']).'
			</p>


			<p>
				Which role is needed to allow category broadcasting? The categories must have the same slug. 
			</p>

			<p class="bigp">
				'.$form->makeLabel($inputs['role_categories']).' '.$form->makeInput($inputs['role_categories']).'
			</p>


			<p>
				Which role is needed to allow category creation? Categories are created if they don\'t exist.
			</p>

			<p class="bigp">
				'.$form->makeLabel($inputs['role_categories_create']).' '.$form->makeInput($inputs['role_categories_create']).'
			</p>


			<p>
				Which role is needed to allow tag broadcasting? The tags must have the same slug. 
			</p>

			<p class="bigp">
				'.$form->makeLabel($inputs['role_tags']).' '.$form->makeInput($inputs['role_tags']).'
			</p>

			<p>
				Which role is needed to allow tag creation? Tags are created if they don\'t exist.
			</p>

			<p class="bigp">
				'.$form->makeLabel($inputs['role_tags_create']).' '.$form->makeInput($inputs['role_tags_create']).'
			</p>

			<p>
				Which role is needed to allow custom field broadcasting? 
			</p>

			<p class="bigp">
				'.$form->makeLabel($inputs['role_custom_fields']).' '.$form->makeInput($inputs['role_custom_fields']).'
			</p>

			<p>
				 A higher save-post-action priority gives other plugins more time to add their own custom fields before the post is broadcasted. <em>Raise</em> this value if you notice that plugins that use custom fields aren\'t getting their data broadcasted, but 640 should be enough for everybody.
			</p>

			<p class="bigp">
				'.$form->makeLabel($inputs['save_post_priority']).' '.$form->makeInput($inputs['save_post_priority']).'
			</p>

			<p>
				'.$form->makeInput($inputs['save']).'
			</p>

			'.$form->stop().'
		';		
	}
	
	protected function adminRequiredList()
	{
		$blogs = $this->get_blog_list();
		$form = $this->form();
		
		if (isset($_POST['save']))
		{
			$this->update_option( 'requirewhenbroadcasting', isset($_POST['requirewhenbroadcasting']) );
			$required = '';
			if (isset($_POST['broadcast']['groups']['required']))
				$required = implode(',', array_keys($_POST['broadcast']['groups']['required']));
			$this->update_option( 'requiredlist', $required );
			$this->message('Options saved!');
		}
		
		$inputs = array(
			'requirewhenbroadcasting' => array(
				'name' => 'requirewhenbroadcasting',
				'type' => 'checkbox',
				'label' => 'Apply required list only when having selected other blogs to broadcast to. Leave unchecked to always use the required list.',
				'value' => $this->get_option('requirewhenbroadcasting'),
			),
			'save' => array(
				'name' => 'save',
				'type' => 'submit',
				'value' => 'Save options',
				'cssClass' => 'button-primary',
			),
		);
		
		$requiredBlogs = explode(',', $this->get_option('requiredlist'));
		$requiredBlogs = array_flip($requiredBlogs);

		echo '
			'.$form->start().'

			<p>
				The required list specifies which blogs users with write access must broadcast to.
				The required list can also be used to force users to broadcast to the below-speficied blogs: uncheck the option below.
			</p>

			<p>The required list takes preference over the blacklist: if blogs are in both, they will be required.</p>

			<p>
				'.$form->makeInput($inputs['requirewhenbroadcasting']).' '.$form->makeLabel($inputs['requirewhenbroadcasting']).'
			</p>

			<p>Select which blogs the user will be required to broadcast to.</p>

			'.$this->showGroupBlogs($blogs, 'required', $requiredBlogs ).'

			<p>
				'.$form->makeInput($inputs['save']).'
			</p>

			'.$form->stop().'
		';	
	}
	
	protected function adminBlacklist()
	{
		$blogs = $this->get_blog_list();
		$form = $this->form();
		
		if (isset($_POST['save']))
		{
			$blacklist = '';
			if (isset($_POST['broadcast']['groups']['blacklist']))
				$blacklist = implode(',', array_keys($_POST['broadcast']['groups']['blacklist']));
			$this->update_option( 'blacklist', $blacklist );
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
		
		$blacklistedBlogs = explode(',', $this->get_option('blacklist'));
		$blacklistedBlogs = array_flip($blacklistedBlogs);

		echo '
			'.$form->start().'

			<p>The blacklist specifies which blogs the users may never broadcast to, even if they\'ve got write access.</p>

			'.$this->showGroupBlogs($blogs, 'blacklist', $blacklistedBlogs ).'

			<p>
				'.$form->makeInput($inputs['save']).'
			</p>

			'.$form->stop().'
		';	
	}
	
	protected function userEditGroups()
	{
		$user_id = $this->user_id();		// Convenience.
		$form = $this->form();
		
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
				}
				else
					unset($data['groups'][$groupID]);
			}
			$data['groups'] = $newGroups;
			$this->sqlUserSet($user_id, $data);
			$this->message(__('Group blogs have been saved.', _3BC));
		}
		
		if (isset($_POST['groupCreate']))
		{
			$groupName = trim($_POST['groupName']);
			if ($groupName == '')
				$this->error(__('The group name may not be empty!', _3BC));
			else
			{
				$data['groups'][] = array('name' => $groupName, 'blogs' => array());
				$this->sqlUserSet($user_id, $data);
				$this->message(__('The group has been created!', _3BC));
			}
		}
		
		// Get a list of blogs that this user can write to.		
		$blogs = $this->getUserWritableBlogs($user_id);
		
		$groupsText = '';
		if (count($data['groups']) == 0)
			$groupsText = '<p>'.__('You have not created any groups yet.', _3BC).'</p>';
		foreach($data['groups'] as $groupID=>$groupData)
		{
			$id = 'broadcast_group_'.$groupID;
			$groupsText .= '
				<div class="threewp_broadcast_group">
					<h4>'.__('Group', _3BC).': '.$groupData['name'].'</h4>

					<div id="'.$id.'">
						'.$this->showGroupBlogs($blogs, $groupID, $groupData['blogs']).'
					</div>
					<p>'.(count($blogs) > 2 ? $this->showGroupBlogsSelectUnselect(array('selector' => '#' . $id, 'input_label' => __('Select/deselect all the blogs in the above group.', _3BC))): '').'</p>
				</div>
			';
		}
		
		$inputs = array(
			'groupsSave' => array(
				'name' => 'groupsSave',
				'type' => 'submit',
				'value' => __('Save groups', _3BC),
				'cssClass' => 'button-primary',
			),
			'groupName' => array(
				'name' => 'groupName',
				'type' => 'text',
				'label' => __('New group name', _3BC),
				'size' => 25,
				'maxlength' => 200,
			),
			'groupCreate' => array(
				'name' => 'groupCreate',
				'type' => 'submit',
				'value' => __('Create the new group', _3BC),
				'cssClass' => 'button-secondary',
			),
		);
		
		echo '
			<h3>'.__('Your groups', _3BC).'</h3>

			'.$form->start().'

			'.$groupsText.'

			<p>
				'.$form->makeInput($inputs['groupsSave']).'
			</p>

			'.$form->stop().'

			<h3>'.__('Create a new group', _3BC).'</h3>

			'.$form->start().'

			<p>
				'.$form->makeLabel($inputs['groupName']).' '.$form->makeInput($inputs['groupName']).'
			</p>

			<p>
				'.$form->makeInput($inputs['groupCreate']).'
			</p>

			'.$form->stop().'

			<h3>'.__('Delete', _3BC).'</h3>

			<p>
				'.__('To <strong>delete</strong> a group, leave all blogs in that group unmarked and then save.', _3BC).'
			</p>
		';
	}
	
	protected function userUnlink()
	{
		// Check that we're actually supposed to be removing the link for real.
		$nonce = $_GET['_wpnonce'];
		$post_id = $_GET['post'];
		if (isset($_GET['child']))
			$blog_id = $_GET['child'];
			
		// Generate the nonce key to check against.			
		$nonce_key = 'broadcast_unlink';
		if (isset($blog_id))
			$nonce_key .= '_' . $blog_id;
		$nonce_key .= '_' . $post_id;
			
		if (!wp_verify_nonce($nonce, $nonce_key))
			die("Security check: not supposed to be unlinking broadcasted post!");
			
		// Remove just one child?
		if (isset($blog_id))
		{
			$broadcastData = $this->getPostBroadcastData($post_id);
			$broadcastData->remove_linked_child($blog_id);
			$this->setPostBroadcastData($post_id, $broadcastData);
			$message = __('Linked post has been removed!', _3BC);
		}
		else
		{
			$broadcastData = $this->getPostBroadcastData($post_id);
			$broadcastData->remove_linked_children($blog_id);
			$this->setPostBroadcastData($post_id, $broadcastData);
			$message = __('All linked posts has been removed!', _3BC);
		}
		
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
	protected function userTrash()
	{
		// Check that we're actually supposed to be removing the link for real.
		$nonce = $_GET['_wpnonce'];
		$post_id = $_GET['post'];
		$blog_id = $_GET['child'];
			
		// Generate the nonce key to check against.			
		$nonce_key = 'broadcast_trash';
		$nonce_key .= '_' . $blog_id;
		$nonce_key .= '_' . $post_id;
			
		if (!wp_verify_nonce($nonce, $nonce_key))
			die("Security check: not supposed to be unlinking broadcasted post!");
			
		$broadcastData = $this->getPostBroadcastData($post_id);
		switch_to_blog($blog_id);
		$broadcasted_post_id = $broadcastData->get_linked_child_on_this_blog();
		wp_trash_post($broadcasted_post_id);
		restore_current_blog();
		$broadcastData->remove_linked_child($blog_id);
		$this->setPostBroadcastData($post_id, $broadcastData);
		
		$message = __('The broadcasted child post has been put in the trash.', _3BC);

		echo '
			'.$this->message($message).'
			<p>
				<a href="'.wp_get_referer().'">Back to post overview</a>
			</p>
		';
	}
	
	protected function userHelp()
	{
		echo '
			<div id="broadcast_help">
				<h2>'.__('What is Broadcast?', _3BC).'</h2>
	
				<p class="float-right">
					<img src="'.$this->paths['url'].'/screenshot-1.png" alt="" title="'.__('What the Broadcast window looks like', _3BC).'" />
				</p>
	
				<p>
					'.__('With Broadcast you can post to several blogs at once. The broadcast window is first shown at the bottom right on the Add New post/page screen.', _3BC).'
					'.__('The window contains several options and a list of blogs you have access to.', _3BC).'
				</p>

				<p>
					'.__('Some settings might be disabled by the site administrator and if you do not have write access to any blogs, other than this one, the Broadcast window might not appear.', _3BC).' 
				</p>

				<p>
					'.__('To use the Broadcast plugin, simply select which blogs you want to broadcast the post to and then publish the post normally.', _3BC).' 
				</p>

				<h3>'.__('Options', _3BC).'</h3>
				
				<p>
					<em>'.__('Link this post to its children', _3BC).'</em> '.__('will create a link from this post (the parent) to all the broadcasted posts (children). Updating the parent will result in all the children being updated. Links to the children can be removed in the page / post overview.', _3BC).'
				</p>

				<p class="textcenter">
					<img class="border-single" src="'.$this->paths['url'].'/screenshot-2.png" alt="" title="'.__('Post overview with unlink options', _3BC).'" />
				</p>

				<p>
					'.__('When a post is linked to children, the children are overwritten when post is updated - all the categories, tags and fields (including featured image) are also overwritten -  and when the parent is trashed or deleted the children get the same treatment. If you want to keep any children and delete only the parent, use the unlink links in the post overview. The unlink link below the post name removes all links and the unlinks to the right remove singular links.', _3BC).'
				</p>

				<p>
					<em>'.__('Broadcast this post as a draft', _3BC).'</em> '.__('will send this post to the selected blogs and mark it as a draft.', _3BC).'
				</p>

				<p>
					<em>'.__('Broadcast categories also', _3BC).'</em> '.__('will also try to send the categories together with the post.', _3BC).'
					'.__('In order to be able to broadcast the categories, the selected blogs must have the same category names (slugs) as this blog, else the posts will be posted as uncategorized.', _3BC).'
				</p>

				<p>
					<em>'.__('Broadcast tags also', _3BC).'</em> '.__('will also mark the broadcasted posts with the same tags.', _3BC).'
				</p>

				<p>
					<em>'.__('Broadcast custom fields', _3BC).'</em> '.__('will give the broadcasted posts the same custom fields as the original. Use this setting to broadcast the featured image.', _3BC).'
				</p>

				<h3>'.__('Groups', _3BC).'</h3>

				<p>
					'.__('If the site administrator allows it you may create groups to quickly select several blogs at once. To create a group, start by typing a group name in the text box and pressing the create button.', _3BC).' 
				</p>

				<p class="textcenter">
					<img class="border-single" src="'.$this->paths['url'].'/screenshot-3.png" alt="" title="'.__('Group setup', _3BC).'" />
				</p>

				<p>
					'.__('Then select which blogs you want to be automatically selected when you choose this group when editing a new post. Press the save button when you are done. Your new group is ready to be used!', _3BC).'
					'.__('Simply choose it in the dropdown box and the blogs you specified will be automatically chosen.', _3BC).'
				</p>

				<p class="textcenter">
					<img class="border-single" src="'.$this->paths['url'].'/screenshot-4.png" alt="" title="'.__('Groups have been selected and saved', _3BC).'" />
				</p>


			</div>
		';
	}
	
	// --------------------------------------------------------------------------------------------
	// ----------------------------------------- Callbacks
	// --------------------------------------------------------------------------------------------

	public function add_meta_box_post()
	{
		return $this->add_meta_box('post');
	}
	
	public function add_meta_box_page()
	{
		return $this->add_meta_box('page');
	}
	
	private function add_meta_box($type)
	{
		global $post;
		$form = $this->form();
		
		$is_post = $type == 'post';
		$is_page = $type == 'page';
		$published = $post->post_status == 'publish';
		
		// Find out if this post is already linked
		global $post;
		$broadcastData = $this->getPostBroadcastData($post->ID);
		$has_linked_children = $broadcastData->has_linked_children();
		
		$blogs = $this->getUserWritableBlogs($this->user_id());
		// Remove the blog we're currently working on from the list of writable blogs.
		global $blog_id;
		unset($blogs[$blog_id]);

		// Broadcast as draft checkbox is shown only to those who are allowed to use it.
		if ($this->role_at_least( $this->get_option('role_link') ))
		{
			// Check the link box is the post has been published and has children OR it isn't published yet.
			$linked = (
				($published && $broadcastData->has_linked_children())
				||
				!$published
			); 
			$inputBroadcastLink = array(
				'name' => 'link',
				'type' => 'checkbox',
				'nameprefix' => '[broadcast]',
				'value' => 'link',
				'checked' => $linked,
				'title' => __('Create a link to the children, which will be updated when this post is updated, trashed when this post is trashed, etc.', _3BC),
				'label' => __('Link this post to its children', _3BC),
			);
			echo '<p>'.$form->makeInput($inputBroadcastLink).' '.$form->makeLabel($inputBroadcastLink).'</p>';
		}

		// Broadcast as draft checkbox is shown only to those who are allowed to use it.
		if ($this->role_at_least( $this->get_option('role_broadcast_as_draft') ))
		{
			$inputBroadcastAsDraft = array(
				'name' => 'broadcast_as_draft',
				'type' => 'checkbox',
				'nameprefix' => '[broadcast]',
				'value' => 'broadcast_as_draft',
				'label' => __('Broadcast this post as a draft', _3BC),
			);
			echo '<p>'.$form->makeInput($inputBroadcastAsDraft).' '.$form->makeLabel($inputBroadcastAsDraft).'</p>';
		}

		if ($this->role_at_least( $this->get_option('role_categories') ) && $is_post)
		{
			$inputCategories = array(
				'name' => 'categories',
				'type' => 'checkbox',
				'nameprefix' => '[broadcast]',
				'value' => 'categories',
				'checked' => 'true',
				'label' => __('Broadcast categories also', _3BC),
				'title' => __('The categories must have the same name (slug) on the selected blogs.', _3BC),
			);
			echo '<div style="height: 1px; background-color: #ddd;"></div>';
			echo '<p>'.$form->makeInput($inputCategories).' '.$form->makeLabel($inputCategories).'</p>';
		}
		
		if ($this->role_at_least( $this->get_option('role_categories_create') ) && $is_post)
		{
			$inputCategoriesCreate = array(
				'name' => 'categories_create',
				'type' => 'checkbox',
				'nameprefix' => '[broadcast]',
				'value' => 'categories_create',
				'checked' => 'true',
				'label' => __('Create categories automatically', _3BC),
				'title' => __('The categories will be created if they don\'t exist on the selected blogs.', _3BC),
			);
			echo '<p>'.$form->makeInput($inputCategoriesCreate).' '.$form->makeLabel($inputCategoriesCreate).'</p>';
		}
		
		if ($this->role_at_least( $this->get_option('role_tags') ) && $is_post)
		{
			$inputTags = array(
				'name' => 'tags',
				'type' => 'checkbox',
				'nameprefix' => '[broadcast]',
				'value' => 'tags',
				'checked' => 'true',
				'label' => __('Broadcast tags also', _3BC),
				'title' => __('The tags must have the same name (slug) on the selected blogs.', _3BC),
			);
			echo '<div style="height: 1px; background-color: #ddd;"></div>';
			echo '<p>'.$form->makeInput($inputTags).' '.$form->makeLabel($inputTags).'</p>';
		}
		
		if ($this->role_at_least( $this->get_option('role_tags_create') ) && $is_post)
		{
			$inputTagsCreate = array(
				'name' => 'tags_create',
				'type' => 'checkbox',
				'nameprefix' => '[broadcast]',
				'value' => 'tags_create',
				'checked' => 'true',
				'label' => __('Create tags automatically', _3BC),
				'title' => __('The tags will be created if they don\'t exist on the selected blogs.', _3BC),
			);
			echo '<p>'.$form->makeInput($inputTagsCreate).' '.$form->makeLabel($inputTagsCreate).'</p>';
		}
		
		if ($this->role_at_least( $this->get_option('role_custom_fields') ) && $is_post)
		{
			$inputCustomFields = array(
				'name' => 'custom_fields',
				'type' => 'checkbox',
				'nameprefix' => '[broadcast]',
				'value' => 'custom_fields',
				'checked' => 'true',
				'title' => __('Broadcast all the custom fields and the featured image?', _3BC),
				'label' => __('Broadcast custom fields', _3BC),
			);
			echo '<div style="height: 1px; background-color: #ddd;"></div>';
			echo '<p>'.$form->makeInput($inputCustomFields).' '.$form->makeLabel($inputCustomFields).'</p>';
		}
		
		echo '<div style="height: 1px; background-color: #ddd;"></div>';
		
		// Similarly, groups are only available to those who are allowed to use them.
		$data = $this->sqlUserGet($this->user_id());
		if ($this->role_at_least( $this->get_option('role_groups') ) && (count($data['groups'])>0))
		{
			$inputGroups = array(
				'name' => 'broadcast_group',
				'type' => 'select',
				'nameprefix' => '[broadcast]',
				'label' => __('Select blogs in group', _3BC),
				'options' => array(array('value' => '', 'text' => __('No group selected', _3BC))),
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
						$("#__broadcast__broadcast_group").change(function(){
							var blogs = $(this).val().split(" ");
							for (var counter=0; counter < blogs.length; counter++)
							{
								$("#__broadcast__groups__666__" + blogs[counter]).attr("checked", true);
							}
							$("#__broadcast_group").val("");
						})
					});
				</script>
			';
		}
		
		$selectedBlogs = array();
		
		// Preselect those children that this post has.
		$linked_children = $broadcastData->get_linked_children();
		if (count($linked_children) > 0)
		{
			foreach($linked_children as $blogID => $postID)
				$selectedBlogs[$blogID] = true;
		}

		echo '<p class="howto">'. __('Broadcast to:', _3BC) .'</p>

			<div class="blogs">
				<p>' . $this->showGroupBlogs($blogs, 666, $selectedBlogs) . '</p>
			</div>
	
			<p style="text-align: center;">'.(count($blogs) > 2 ? $this->showGroupBlogsSelectUnselect(array('selector' => '.broadcast_blogs', 'input_label' => __('Select/deselect all', _3BC))): '').'</p>
		';
	}
	
	public function save_post($post_id)
	{
		if (!$this->role_at_least( $this->get_option('role_broadcast') ))
			return;
			
		$post = get_post($post_id, 'ARRAY_A');
		if ($post['post_status'] != 'publish')		// Ignore revisions.
			return;
			
		if (!isset($_POST['broadcast']))
		{
			// Site admin is never forced to do anything.
			if (is_site_admin())
				return;
				
			// Ignore this post. It's being force broadcast.
			if (isset($_POST['broadcast_force']))
				return;
				
			if ($this->get_option('requirewhenbroadcasting') == false)
				$_POST['broadcast_force'] = true;
			else
				return;
		}
		
		$is_post = $_POST['post_type'] == 'post';
		$is_page = $_POST['post_type'] == 'page';

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

		if (!is_site_admin())
		{
			// Remove blacklisted
			foreach($blogs as $blogID=>$ignore)		// Don't user blog_id anymore.'
				if (!$this->canUserWriteToBlog($user_id, $blogID))
					unset($blogs[$blogID]);
					
			// Add required blogs.
			$requiredBlogs = $this->get_option('requiredlist');
			$requiredBlogs = explode(',', $requiredBlogs);
			foreach($requiredBlogs as $requiredBlog)
				if ($requiredBlog != '')
					$blogs[$requiredBlog] = $requiredBlog;
		}
		
		$blogs = array_flip($blogs);
		// Now to add and remove blogs: done
		
		// Do we actually need to to anything?
		if (count($blogs) < 1)
			return;

		$link = ($this->role_at_least( $this->get_option('role_link') ) && isset($_POST['broadcast']['link']));
		if ($link)
		{
			// Prepare the broadcast data for linked children.
			$broadcastData = $this->getPostBroadcastData($post_id);
		}
		
		// Only allow draft if (1) user may use drafts and (2) draft is actually in POST.
		$broadcast_as_draft = ($this->role_at_least( $this->get_option('role_broadcast_as_draft') ) && isset($_POST['broadcast']['broadcast_as_draft']));
		if ($broadcast_as_draft)
			$newPost['post_status'] = 'draft';
			
		$categories = (
			$this->role_at_least( $this->get_option('role_categories') )
			&&
			isset($_POST['broadcast']['categories'])
			&&
			$is_post
		);
		$categories_create = ($this->role_at_least( $this->get_option('role_categories_create') ) && isset($_POST['broadcast']['categories_create']));
		if ($categories)
		{
			$postCats = wp_get_post_categories($post_id);
			$sourceCategories = get_categories(array(
				'hide_empty' => false,
			));
			// Convert the category list to an array (why do they keep using stdclasses??)
			$sourceCategories = $this->objectToArray($sourceCategories);
			// And then make the cat ID the key.
			$sourceCategories = $this->array_moveKey($sourceCategories, 'term_id');
		}
		
		$tags = (
			$this->role_at_least( $this->get_option('role_tags') )
			&&
			isset($_POST['broadcast']['tags'])
			&&
			$is_post
		);
		$tags_create = ($this->role_at_least( $this->get_option('role_tags_create') ) && isset($_POST['broadcast']['tags_create']));
		if ($tags)
		{
			$postTags = wp_get_post_tags($post_id);
			// Convert the tag list to an array (why do they keep using stdclasses??)
			$postTags = $this->objectToArray($postTags);
			// And then make the tag ID the key.
			$postTags = $this->array_moveKey($postTags, 'term_id');
		}
		
		$custom_fields = (
			$this->role_at_least( $this->get_option('role_custom_fields') )
			&&
			isset($_POST['broadcast']['custom_fields'])
			&&
			$is_post
		);
		if ($custom_fields)
		{
			$postCustomFieldsTemp = get_post_custom($post_id);
			$postCustomFields = array();
			foreach($postCustomFieldsTemp as $key => $array)
				$postCustomFields[$key] = $array[0];
			
			$is_file_attached = isset($postCustomFields['_thumbnail_id']);
			if ($is_file_attached)
			{
				$attachment_id = $postCustomFields['_thumbnail_id'];
				unset( $postCustomFields['_thumbnail_id'] ); // There is a new thumbnail id for each blog.
				
				// Save the attached file (+ thumbs) and its metadata
				$attachment_data = array(
					'files' => array(),
					'metadata' => wp_get_attachment_metadata($attachment_id),
				);
				
				// We need to find out where the files are on disk.
				$upload_dir = wp_upload_dir();
				
				$attachment_data['filename'] = basename($attachment_data['metadata']['file']);
				$attachment_data['filename_path'] = $upload_dir['basedir'] . '/' . $attachment_data['metadata']['file'];
			}
			
			// Remove all the _internal custom fields.
			foreach($postCustomFieldsTemp as $key => $array)
				if (strpos($key, '_') === 0)
					unset( $postCustomFieldsTemp[$key] );
		}
		
		// To prevent recursion
		unset($_POST['broadcast']);
				
		foreach($blogs as $blogID)
		{
			if (!$this->canUserWriteToBlog($user_id, $blogID))
				continue;
			switch_to_blog($blogID);
			
			// Insert new? Or update? Depends on whether the parent post was linked before or is newly linked?
			if ($broadcastData->has_linked_child_on_this_blog())
			{
				$tempPostData = $newPost;
				$tempPostData['ID'] = $broadcastData->get_linked_child_on_this_blog();
				$newpostID = wp_update_post($tempPostData);
			}
			else
			{
				$newpostID = wp_insert_post($newPost);
				
				if ($link)
					$broadcastData->add_linked_child($blogID, $newpostID);
			}
			
			if ($categories)
			{
				// If we're updating a linked post, remove all the categories and start from the top.
				if ($broadcastData->has_linked_child_on_this_blog())
					wp_set_post_categories( $newpostID, array() );
				
				// Get a list of cats that the target blog has.
				$targetCategories = get_categories(array(
					'hide_empty' => false,
				));
				$targetCategories = $this->objectToArray($targetCategories);
				$targetCategories = $this->array_moveKey($targetCategories, 'term_id');
				
				// Go through the original post's cats and compare each slug with the slug of the target cats.
				$categoriesToAddTo = array();
				foreach($postCats as $cat_id)
				{
					$found = false;
					$sourceSlug = $sourceCategories[$cat_id]['slug'];
					foreach($targetCategories as $targetCategory_id => $targetCategory)
						if ($targetCategory['slug'] == $sourceSlug)
						{
							$found = true;
							$categoriesToAddTo[$targetCategory_id] = $targetCategory_id;
							break;
						}
						
					// Should we create the category if it doesn't exist?
					if (!$found && $categories_create)
					{
						$newCatID = wp_create_category($sourceCategories[$cat_id]['name']);
						$categoriesToAddTo[$newCatID] = $newCatID;
					}
				}
				if (count($categoriesToAddTo) > 0)
					wp_set_post_categories( $newpostID, array_keys($categoriesToAddTo) );
			}
			
			if ($tags)
			{
				// If we're updating a linked post, remove all the tags and start from the top.
				if ($broadcastData->has_linked_child_on_this_blog())
					wp_set_post_tags( $newpostID, array() );
				
				// Get a list of tags that the target blog has.
				$targetTags = get_tags(array(
					'hide_empty' => false,
				));
				
				$targetTags = $this->objectToArray($targetTags);
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
					wp_set_post_tags( $newpostID, array_keys($tagsToAddTo) );
			}
			
			if ($custom_fields)
			{
				// If we're updating a linked post, remove all the tags and start from the top.
				if ($broadcastData->has_linked_child_on_this_blog())
				{
					// Remove all old custom fields.
					$old_custom_fields = get_post_custom($newpostID);
					foreach($old_custom_fields as $key => $value)
						if (strpos($key, '_') !== 0) // But only the normal custom fields, not the _internal ones that Wordpress creates.
						{
							// Remove the key
							delete_post_meta($newpostID, $key);
						}
						else
						{
							// This post has a featured image! Remove it from disk!
							if ($key == '_thumbnail_id')
							{
								$thumbnail_post = $value[0];
								wp_delete_post($thumbnail_post);
							}
						}
				}
				
				foreach($postCustomFields as $meta_key => $meta_value)
					add_post_meta($newpostID, $meta_key, $meta_value);
				
				// Attached files are custom fields... but special custom fields. Therefore they need special treatment. Like retards. Retarded files.
				if ($is_file_attached)
				{
					// Copy the file to the blog's upload directory
					$upload_dir = wp_upload_dir();
					copy($attachment_data['filename_path'], $upload_dir['path'] . '/' . $attachment_data['filename']);
					
					// And now create the attachment stuff.
					// This is taken almost directly from http://codex.wordpress.org/Function_Reference/wp_insert_attachment
					$wp_filetype = wp_check_filetype( $attachment_data['filename'], null );
					$attachment = array(
						'post_mime_type' => $wp_filetype['type'],
						'post_title' => preg_replace('/\.[^.]+$/', '', $attachment_data['filename']),
						'post_content' => '',
						'post_status' => 'inherit'
					);
					$attach_id = wp_insert_attachment( $attachment, $upload_dir['path'] . '/' . $attachment_data['filename'], $newpostID );
					
					require_once(ABSPATH . "wp-admin" . '/includes/image.php');
					$attach_data = wp_generate_attachment_metadata( $attach_id, $upload_dir['path'] . '/' . $attachment_data['filename'] );
					wp_update_attachment_metadata( $attach_id,  $attach_data );
					
					// Actually tell Wordpress that this new post has an attachment!
					add_post_meta($newpostID, '_thumbnail_id', $attach_id);
				}
			}
			restore_current_blog();
		}
		
		// Finished broadcasting.
		// Save the post broadcast data.
		$this->setPostBroadcastData($post_id, $broadcastData);
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
		$broadcastData = $this->getPostBroadcastData($post_id);
		if ($broadcastData->has_linked_children())
		{
			foreach($broadcastData->get_linked_children() as $childBlog=>$childPost)
			{
				switch_to_blog($childBlog);
				$command($childPost);
				restore_current_blog();
			}
		}
	}
	
	public function manage_posts_columns($defaults)
	{
		$defaults['3wp_broadcast'] = '<span title="'.__('Shows which blogs have posts linked to this one', _3BC).'">'.__('Broadcasted', _3BC).'</span>';
		return $defaults;
	}
	
	public function manage_posts_custom_column($column_name, $post_id)
	{
		if ($column_name != '3wp_broadcast')
			return;
			
		global $post;
		$broadcastData = $this->getPostBroadcastData($post->ID);
		if ( $broadcastData->has_linked_children() )
		{
			$children = $broadcastData->get_linked_children();
			
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
						<a href="'.$urlUnlink.'" title="'.__('Remove links to this broadcasted child', _3BC).'">'.__('Unlink', _3BC).'</a>
						| <span class="trash"><a href="'.$urlTrash.'" title="'.__('Put this broadcasted child in the trash', _3BC).'">'.__('Trash', _3BC).'</a></span>
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
		global $post;
		$broadcastData = $this->getPostBroadcastData($post->ID);
		if ($broadcastData->has_linked_children())
			$actions = array_merge($actions, array(
				'broadcast_unlink' => '<a href="'.wp_nonce_url("options-general.php?page=ThreeWP_Broadcast&amp;action=unlink&amp;post=$post->ID", 'broadcast_unlink_' . $post->ID).'" title="'.__('Remove links to all the broadcasted children', _3BC).'">'.__('Unlink', _3BC).'</a>',
			));
		return $actions;
	}
	
	public function create_meta_box()
	{
		if ($this->role_at_least( $this->get_option('role_broadcast') ))
		{
			// If the user isn't a site admin, or if the user doesn't have any other blogs to write to...
			if ( $this->role_at_least('site_admin') || count($this->getUserWritableBlogs($this->user_id()))> 1 )	// User always has at least one to write to, if he's gotten THIS far.
			{
				$this->loadLanguages(_3BC);
				add_meta_box('threewp_broadcast', __('Broadcast', _3BC), array(&$this, 'add_meta_box_post'), 'post', 'side', 'low' );
				add_meta_box('threewp_broadcast', __('Broadcast', _3BC), array(&$this, 'add_meta_box_page'), 'page', 'side', 'low' );
				add_action('save_post', array(&$this, 'save_post'), $this->get_option('save_post_priority'));
			}
		}
	}
	
	// --------------------------------------------------------------------------------------------
	// ----------------------------------------- Misc functions
	// --------------------------------------------------------------------------------------------

	private function showGroupBlogs($blogs, $groupID, $selected)
	{
		$form = $this->form();
		$returnValue = '<ul class="broadcast_blogs">';
		$nameprefix = "[broadcast][groups][$groupID]";
		foreach($blogs as $blog)
		{
			$required = $this->isRequired($blog['blog_id']);
			$checked = isset( $selected[$blog['blog_id']] ) || $required;
			$input = array(
				'name' => $blog['blog_id'],
				'type' => 'checkbox',
				'nameprefix' => $nameprefix,
				'label' => $blog['blogname'],
				'disabled' => $required,
				'value' => 'blog_' .$checked,
				'checked' => $checked,
				'title' => $blog['siteurl'],
			);
			$returnValue .= '<li>'.$form->makeInput($input).' '.$form->makeLabel($input).'</li>';
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
	
	protected function getUserWritableBlogs($user_id)
	{
		// Super admins can write anywhere they feel like.
		if (is_site_admin())
		{
			$blogs = $this->get_blog_list();
			$blogs = $this->sortBlogs($blogs);
			return $blogs;
		}
		
		$blogs = get_blogs_of_user($user_id);
		foreach($blogs as $index=>$blog)
		{
			$blog = $this->objectToArray($blog);
			$blog['blog_id'] = $blog['userblog_id'];
			$blogs[$index] = $blog;
			if (!$this->canUserWriteToBlog($user_id, $blog['blog_id']))
				unset($blogs[$index]);
		}
		return $this->sortBlogs($blogs);
	}
	
	protected function canUserWriteToBlog($user_id, $blog_id)
	{
		// If this blog is in the blacklist, reply no.
		if ($this->isBlacklisted($blog_id))
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
		if (is_site_admin())
			return false;
		$requiredlist = $this->get_option('requiredlist');
		$requiredlist = explode(',', $requiredlist);
		$requiredlist = array_flip($requiredlist);
		return isset($requiredlist[$blog_id]);
	}
	
	/**
	 * Returns whether the site admin has blacklisted the blog.
	 */
	protected function isBlacklisted($blog_id)
	{
		if (is_site_admin())
			return false;
		$blacklist = $this->get_option('blacklist');
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
			$tempBlog = $this->objectToArray(get_blog_details($blog_id, true));
			$blogs[$blog_id]['blogname'] = $tempBlog['blogname'];
			$blogs[$blog_id]['siteurl'] = $tempBlog['siteurl'];
			$blogs[$blog_id]['domain'] = $tempBlog['domain'];
		}

		return $this->sortBlogs($blogs);
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
		$blogs = $_SESSION['threewp_broadcast_blogs'];
		if ($blogs === null)
		{
			$blogs = $this->get_blog_list();
			$_SESSION['threewp_broadcast_blogs'] = $blogs;
		}
		return $blogs;		
	}
	
	/**
	 * Sorts the blogs by name. The Site Blog is first, no matter the name.
	 */
	protected function sortBlogs($blogs)
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
	private function getPostBroadcastData($post_id)
	{
		global $blog_id;
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
	 * @param int $post_id Post ID to update
	 * @param BroadcastData $broadcastData BroadcastData file.
	 */
	private function setPostBroadcastData($post_id, $broadcastData)
	{
		global $blog_id;
		require_once('BroadcastData.php');
		if ($broadcastData->is_modified())
			if ($broadcastData->is_empty())
				$this->sqlbroadcastDataDelete($blog_id, $post_id);
			else
				$this->sqlbroadcastDataUpdate($blog_id, $post_id, $broadcastData->getData());
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