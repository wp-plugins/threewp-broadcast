<?php

namespace threewp_broadcast\blog_groups;

/**
	@brief		Adds blog group support to Broadcast
**/
class ThreeWP_Broadcast_Blog_Groups
	extends \plainview\sdk_broadcast\wordpress\base
{
	protected $sdk_version_required = 20131006;		// tabs->get_is()

	public function _construct()
	{
		$this->add_action( 'threewp_broadcast_prepare_meta_box' );
		$this->add_action( 'threewp_broadcast_menu' );
	}

	// --------------------------------------------------------------------------------------------
	// ----------------------------------------- Activate / Deactivate
	// --------------------------------------------------------------------------------------------

	public function activate()
	{
		$db_ver = $this->get_site_option( 'database_version', 0 );

		if ( $db_ver < 1 )
		{
			$this->query("CREATE TABLE IF NOT EXISTS `".$this->wpdb->base_prefix."3wp_broadcast_blog_groups` (
				`id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Row ID',
				`data` longtext NOT NULL COMMENT 'Serialized broadcasting_data object',
				`user_id` int(11) NOT NULL COMMENT 'ID of user that broadcasted',
				PRIMARY KEY (`id`),
				KEY `user_id` (`user_id`)
			) ENGINE=MyISAM DEFAULT CHARSET=latin1;
			");

			$db_ver = 1;
		}

		if ( $db_ver < 2 )
		{
			foreach( [
				'role_use_groups'
			] as $old_role_option )
			{
				$old_value = $this->get_site_option( $old_role_option );
				if ( is_array( $old_value ) )
					continue;
				$new_value = ThreeWP_Broadcast()->convert_old_role( $old_value );
				$this->update_site_option( $old_role_option, $new_value );
			}
			$db_ver = 2;
		}

		$this->update_site_option( 'database_version', $db_ver );
	}

	public function uninstall()
	{
		$this->query("DROP TABLE `".$this->wpdb->base_prefix."3wp_broadcast_blog_groups`");
	}

	// --------------------------------------------------------------------------------------------
	// ----------------------------------------- Admin
	// --------------------------------------------------------------------------------------------

	/**
		@brief		An overview of the user's groups.
		@since		20131006
	**/
	public function admin_menu_overview()
	{
		$form = $this->form2();
		$form->text( 'name' )
			->label_( 'Group name' )
			->minlength( 2, 128 )
			->size( 40, 128 )
			->trim()
			->required();
		$form->primary_button( 'create' )
			->value_( 'Create the blog group' );

		if ( $form->is_posting() )
		{
			$form->post()->use_post_values();
			if ( $form->validates() )
			{
				$blog_group = new blog_group;
				$name = $form->text( 'name' )->get_value();
				$blog_group->data->name = $name;
				$blog_group->user_id = $this->user_id();
				$blog_group->db_insert();
				$this->message_( 'The blog group %s has been created!', $name );
			}
			else
			{
				foreach( $form->get_validation_errors() as $validation_error )
					$this->error( $validation_error );
			}
		}

		$blog_groups = $this->get_blog_groups_for_user( $this->user_id() );

		$r = $this->p_( 'Blog groups help you to quickly add blogs to the broadcast. First create a blog group and select the blogs you want. When broadcasting the list of blog groups will appear above the blog list.' );

		if ( count( $blog_groups ) < 1 )
		{
			$r .= $this->p_( 'You have not created any blog groups yet.' );
		}
		else
		{
			$r .= $this->p_( 'Click on the group name to edit or delete it.' );

			$blogs = new \threewp_broadcast\actions\get_user_writable_blogs;
			$blogs->user_id = $this->user_id();
			$blogs = $blogs->execute()->blogs;

			$table = $this->table();
			$row = $table->head()->row();
			$row->th()->text_( 'Name' );
			$row->th()->text_( 'Blogs' );
			foreach( $blog_groups as $blog_group )
			{
				$url = add_query_arg( [
					'id' => $blog_group->id,
					'tab' => 'edit',
				] );
				$row = $table->body()->row();
				$name = sprintf( '<a href="%s" title="%s">%s</a>',
					$url,
					$this->_( 'Edit or delete this blog group' ),
					$blog_group->data->name
				);
				$row->td()->text_( $name );

				if ( count( $blog_group->data->blogs ) < 1 )
				{
					$text = $this->_( 'The group has no blogs assigned.' );
				}
				else
				{
					$text = [];
					foreach( $blog_group->data->blogs as $blog_id )
					{
						$blog = $blogs->get( $blog_id );
						if ( ! $blog )
							continue;
						$text []= $blog->get_name();
					}
					$text = implode( ', ', $text );
				}
				$row->td()->text( $text );

			}

			$r .= $table;
		}

		// Form to add a new blog group.
		$r .= $this->h3( $this->_( 'Create a new blog group' ) );
		$r .= $form->open_tag();
		$r .= $form->display_form_table();
		$r .= $form->close_tag();


		echo $r;
	}

	public function admin_menu_settings()
	{
		$form = $this->form2();
		$roles = $this->roles_as_options();
		$roles = array_flip( $roles );

		$fs = $form->fieldset( 'roles' )
			->label_( 'Roles' );

		$role_use_groups = $fs->select( 'role_use_groups' )
			->value( $this->get_site_option( 'role_use_groups' ) )
			->description_( 'Role needed to use the group function.' )
			->label_( 'Use groups' )
			->multiple()
			->options( $roles );


		$save = $form->primary_button( 'save' )
			->value_( 'Save settings' );

		if ( $form->is_posting() )
		{
			$form->post();
			$form->use_post_values();

			$this->update_site_option( 'role_use_groups', $role_use_groups->get_post_value() );

			$this->message( 'Options saved!' );
		}

		$r = $form->open_tag();
		$r .= $form->display_form_table();
		$r .= $form->close_tag();

		echo $r;
	}

	/**
		@brief		Show all the tabs.
		@since		20131006
	**/
	public function admin_menu_tabs()
	{
		$this->load_language();
		$tabs = $this->tabs();

		$tabs->tab( 'overview' )		->callback_this( 'admin_menu_overview' )		->name_( 'Overview' );

		if ( $tabs->get_is( 'edit' ) )
			$tabs->tab( 'edit' )
				->callback_this( 'user_edit_blog_group' )
				->name_( 'Edit blog group' )
				->parameters( $_GET[ 'id' ] );

		if ( is_super_admin() )
		{
			$tabs->tab( 'settings' )		->callback_this( 'admin_menu_settings' )		->name_( 'Settings' );
			$tabs->tab( 'uninstall' )		->callback_this( 'admin_uninstall' )		->name_( 'Uninstall' );
		}

		echo $tabs;
	}

	// --------------------------------------------------------------------------------------------
	// ----------------------------------------- Callbacks
	// --------------------------------------------------------------------------------------------

	public function threewp_broadcast_prepare_meta_box( $action )
	{
		if ( ! ThreeWP_Broadcast()->user_has_roles( $this->get_site_option( 'role_use_groups' ) ) )
			return;

		// Are there any groups to display?
		$blog_groups = $this->get_blog_groups_for_user( $this->user_id() );
		if ( count( $blog_groups ) < 1 )
			return;

		$form = $action->meta_box_data->form;
		$input_blog_groups = $form->select( 'blog_groups' )
			->label_( 'Blog groups' )
			->option_( 'No group selected', '' );
		foreach( $blog_groups as $blog_group )
		{
			if ( ! is_array( $blog_group->data->blogs ) )
				continue;
			$values = implode( ' ', $blog_group->data->blogs );
			$name = $form->unfilter_text( $blog_group->data->name );
			$input_blog_groups->option( $name, $values );
		}

		$action->meta_box_data->html->insert_before( 'blogs', 'blog_groups', '' );
		$action->meta_box_data->convert_form_input_later( 'blog_groups' );
	}

	/**
		@brief		Add ourself to Broadcast's menu.
		@since		20131006
	**/
	public function threewp_broadcast_menu( $action )
	{
		if ( ! is_super_admin() AND ! ThreeWP_Broadcast()->user_has_roles( $this->get_site_option( 'role_use_groups' ) ) )
			return;

		$action->broadcast->add_submenu_page(
			'threewp_broadcast',
			$this->_( 'Blog groups' ),
			$this->_( 'Blog groups' ),
			'edit_posts',
			'threewp_broadcast_blog_groups',
			[ &$this, 'admin_menu_tabs' ]
		);
	}

	// --------------------------------------------------------------------------------------------
	// ----------------------------------------- SQL
	// --------------------------------------------------------------------------------------------

	/**
		@brief		Retrieve a blog_group object.
		@since		20131006
	**/
	public function get_blog_group( $id )
	{
		$query = ( "SELECT * FROM `".$this->wpdb->base_prefix."3wp_broadcast_blog_groups` as b
			WHERE b.`id` = '$id'
			LIMIT 1"
		);
		$result = $this->query_single($query);
		return blog_group::sql( $result );
	}

	/**
		@brief		Retrieve a user's blog_group object.
		@since		20131006
	**/
	public function get_blog_groups_for_user( $user_id )
	{
		$query = ( "SELECT * FROM `".$this->wpdb->base_prefix."3wp_broadcast_blog_groups` as b
			WHERE b.`user_id` = '$user_id'"
		);
		$result = $this->query($query);
		return blog_group::sqls( $result );
	}

	// --------------------------------------------------------------------------------------------
	// ----------------------------------------- User
	// --------------------------------------------------------------------------------------------

	/**
		@brief		Edit a blog group
		@since		20131006
	**/
	public function user_edit_blog_group( $id )
	{
		$blog_group = $this->get_blog_group( $id );
		if ( ! $blog_group )
			wp_die( 'Blog group does not exist' );
		if ( $blog_group->user_id != $this->user_id() )
			wp_die( 'This is not your blog group.' );

		$edit_form = $this->form2();

		$fs = $edit_form->fieldset( 'general' )->label_( 'General settings' );

		$name_input = $fs->text( 'name' )
			->label_( 'Group name' )
			->minlength( 2, 128 )
			->size( 40, 128 )
			->trim()
			->required()
			->value( $edit_form->unfilter_text( $blog_group->data->name ) );

		$blogs = new \threewp_broadcast\actions\get_user_writable_blogs;
		$blogs->user_id = $this->user_id();
		$blogs = $blogs->execute()->blogs;

		$input_blogs = $edit_form->checkboxes( 'blogs' )
			->label_( 'Blogs' );
		foreach( $blogs as $blog )
		{
			$input_blogs->option( $blog->get_name(), $blog->get_id() );
			$option = $input_blogs->input( 'blogs_' . $blog->get_id() );
			if ( $blog->is_disabled() )
				$option->disabled();
			if ( $blog->is_required() )
				$option->required();
			if ( $blog->is_selected() )
				$option->checked();
		}

		foreach( $blog_group->data->blogs as $blog_id )
		{
			$option = $input_blogs->input( 'blogs_' . $blog_id );
			if ( ! $option )
				continue;
			$option->checked();
		}

		$input_save = $edit_form->primary_button( 'save' )
			->value_( 'Update the blog group' );

		$r = '';

		if ( $edit_form->is_posting() )
		{
			$edit_form->post();
			$edit_form->use_post_values();
			if ( $input_save->pressed() )
			{
				$ids = $input_blogs->get_post_value();
				$blog_group->data->name = $name_input->get_post_value();
				$blog_group->data->blogs = $ids;
				$blog_group->db_update();
				$this->message_( 'Blog group saved!' );
			}
		}

		// Display edit form
		$r .= $edit_form->open_tag();
		$r .= $edit_form->display_form_table();
		$r .= $edit_form->close_tag();

		// Display delete form
		$delete_form = $this->form2();

		$fs = $delete_form->fieldset( 'delete' )->label_( 'Delete this blog group' );

		$fs->checkbox( 'sure' )
			->label_( 'I want to delete this blog group' )
			->required();

		$input_delete = $fs->secondary_button( 'delete' )
			->value_( 'Delete the group' );

		$r .= $delete_form->open_tag();
		$r .= $delete_form->display_form_table();
		$r .= $delete_form->close_tag();

		if ( $delete_form->is_posting() )
		{
			$delete_form->post();
			if ( $input_delete->pressed() )
			{
				$blog_group->db_delete();
				$this->message_( 'The blog group has been deleted. Please return to the overview.' );
				return;
			}
		}

		echo $r;
	}

	// --------------------------------------------------------------------------------------------
	// ----------------------------------------- MISC
	// --------------------------------------------------------------------------------------------

	public function load_language( $domain = '' )
	{
		parent::load_language( 'ThreeWP_Broadcast' );
	}

	public function site_options()
	{
		return array_merge( [
			'database_version' => 0,
			'role_use_groups' => [ 'super_admin' ],					// Role required to use the groups function
		], parent::site_options() );
	}
}
