<?php

namespace threewp_broadcast\traits;

use \threewp_broadcast\actions;
use \threewp_broadcast\maintenance;

/**
	@brief		Methods that handle the menu in the admin interface.
	@since		2014-10-19 14:21:16
**/
trait admin_menu
{
	public function admin_menu()
	{
		$this->load_language();

		$action = new actions\admin_menu;
		$action->execute();

		$action = new actions\menu;
		$action->broadcast = $this;
		$action->execute();

		// Hook into save_post, no matter is the meta box is displayed or not.
		$this->add_action( 'save_post', intval( $this->get_site_option( 'save_post_priority' ) ) );
	}

	public function admin_print_styles()
	{
		$this->enqueue_js();
		wp_enqueue_style( 'threewp_broadcast', $this->paths[ 'url' ] . '/css/css.css', '', $this->plugin_version  );
	}

	public function admin_menu_broadcast_info()
	{
		if ( ! is_super_admin() )
		{
			echo $this->p( 'No information available.' );
			return;
		}

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

		// WP upload path
		$row = $table->body()->row();
		$row->td()->text( 'Wordpress upload directory array' );
		$row->td()->text( '<pre>' . var_export( wp_upload_dir(), true ) . '</pre>' );

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
			->label_( 'Custom post types to broadcast' )
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
			$this->message_( 'Custom post types saved!' );
		}

		$r .= $this->p_( 'Custom post types must be specified using their internal Wordpress names on a new line each. It is not possible to automatically make a list of available post types on the whole network because of a limitation within Wordpress (the current blog knows only of its own custom post types).' );

		$blog_post_types = $this->get_blog_post_types();
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
		$contents = file_get_contents( __DIR__ . '/../../html/premium_pack_info.html' );
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

		$fs = $form->fieldset( 'roles' );
		$fs->legend->label_( 'Roles' );

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

		$fs = $form->fieldset( 'seo' );
		$fs->legend->label_( 'SEO' );

		$override_child_permalinks = $fs->checkbox( 'override_child_permalinks' )
			->checked( $this->get_site_option( 'override_child_permalinks' ) )
			->description_( "Use the parent post's permalink for the children. If checked, child posts will link back to the parent post." )
			->label_( "Use parent permalink" );

		$canonical_url = $fs->checkbox( 'canonical_url' )
			->checked( $this->get_site_option( 'canonical_url' ) )
			->description_( "Child posts have their canonical URLs pointed to the URL of the parent post. This automatically disables the canonical URL from Yoast's Wordpress SEO plugin." )
			->label_( 'Canonical URL' );

		$fs = $form->fieldset( 'custom_field_handling' );
		$fs->legend->label_( 'Custom field handling' );

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

		$protectlist = $this->get_site_option( 'custom_field_protectlist' );
		$protectlist = str_replace( ' ', "\n", $protectlist );
		$custom_field_protectlist = $fs->textarea( 'custom_field_protectlist' )
			->cols( 40, 10 )
			->description_( 'When broadcasting internal custom fields, do not overwrite the following fields on the child blogs.' )
			->label_( 'Internal field protectlist' )
			->trim()
			->value( $protectlist );

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

		$fs = $form->fieldset( 'misc' );
		$fs->legend->label_( 'Miscellaneous' );

		$clear_post = $fs->checkbox( 'clear_post' )
			->description_( 'The POST PHP variable is data sent when updating posts. Most plugins are fine if the POST is cleared before broadcasting, while others require that the data remains intact. Uncheck this setting if you notice that child posts are not being treated the same on the child blogs as they are on the parent blog.' )
			->label_( 'Clear POST' )
			->checked( $this->get_site_option( 'clear_post' ) );

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

		$blogs_hide_overview = $fs->number( 'blogs_hide_overview' )
			->description_( 'How many children to display in the overview before making the list into a summary.' )
			->label_( 'Display in overview' )
			->min( 1 )
			->required()
			->size( 3, 3 )
			->value( $this->get_site_option( 'blogs_hide_overview' ) );

		$existing_attachments = $fs->select( 'existing_attachments' )
			->description_( 'Action to take when attachments with the same filename already exist on the child blog.' )
			->label_( 'Existing attachments' )
			->option( 'Use the existing attachment on the child blog', 'use' )
			->option( 'Overwrite the attachment', 'overwrite' )
			->option( 'Create a new attachment with a randomized suffix', 'randomize' )
			->required()
			->value( $this->get_site_option( 'existing_attachments', 'use' ) );

		$this->add_debug_settings_to_form( $form );

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

			$protectlist = $custom_field_protectlist->get_post_value();
			$protectlist = $this->lines_to_string( $protectlist );
			$this->update_site_option( 'custom_field_protectlist', $protectlist );

			$this->update_site_option( 'clear_post', $clear_post->is_checked() );
			$this->update_site_option( 'save_post_priority', $save_post_priority->get_post_value() );
			$this->update_site_option( 'blogs_to_hide', $blogs_to_hide->get_post_value() );
			$this->update_site_option( 'blogs_hide_overview', $blogs_hide_overview->get_post_value() );
			$this->update_site_option( 'existing_attachments', $existing_attachments->get_post_value() );

			$this->save_debug_settings_from_form( $form );

			$this->message( 'Options saved!' );

			$_POST = [];
			echo $this->admin_menu_settings();
			return;
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
		$tabs->tab( 'post_types' )		->callback_this( 'admin_menu_post_types' )		->name_( 'Custom post types' );
		$tabs->tab( 'maintenance' )		->callback_this( 'admin_menu_maintenance' )		->name_( 'Maintenance' );
		$tabs->tab( 'uninstall' )		->callback_this( 'admin_uninstall' )			->name_( 'Uninstall' );

		echo $tabs;
	}

	/**
		@brief		Allow tabs to be shown when deleting / trashing / whatever a post from the post overview.
		@since		2014-10-19 14:22:54
	**/
	public function broadcast_menu_tabs()
	{
		$this->load_language();

		$tabs = $this->tabs()
			->default_tab( 'admin_menu_broadcast_info' )
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

		$tabs->tab( 'admin_menu_broadcast_info' )->name_( 'Broadcast information' );

		$action = new actions\broadcast_menu_tabs();
		$action->tabs = $tabs;
		$action->execute();

		echo $tabs;
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
			[ &$this, 'broadcast_menu_tabs' ],
			'none'
		);

		$this->add_submenu_pages();
	}

}