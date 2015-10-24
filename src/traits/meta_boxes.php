<?php

namespace threewp_broadcast\traits;

use \threewp_broadcast\actions;
use \threewp_broadcast\meta_box;

/**
	@brief		Methods related to the broadcast meta box.
	@since		2014-10-19 15:44:39
**/
trait meta_boxes
{
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
			$action->execute();
			foreach( $action->post_types as $post_type )
				add_meta_box( 'threewp_broadcast', $this->_( 'Broadcast' ), array( &$this, 'threewp_broadcast_add_meta_box' ), $post_type, 'side', 'low' );
			return;
		}

		// No decision yet. Decide.
		$this->display_broadcast_meta_box |= is_super_admin();
		$this->display_broadcast_meta_box |= static::user_has_roles( $this->get_site_option( 'role_broadcast' ) );

		// No access to any other blogs = no point in displaying it.
		$filter = new actions\get_user_writable_blogs( $this->user_id() );
		$blogs = $filter->execute()->blogs;
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
		@brief		Prepare and display the meta box data.
		@since		20131003
	**/
	public function threewp_broadcast_add_meta_box( $post )
	{
		$meta_box_data = $this->create_meta_box( $post );

		// Allow plugins to modify the meta box with their own info.
		$action = new actions\prepare_meta_box;
		$action->meta_box_data = $meta_box_data;
		$action->execute();

		foreach( $meta_box_data->css as $key => $value )
			wp_enqueue_style( $key, $value, '', $this->plugin_version );
		foreach( $meta_box_data->js as $key => $value )
			wp_enqueue_script( $key, $value, '', $this->plugin_version );

		echo $meta_box_data->html->render();
	}

	/**
		@brief		Prepare and display the meta box data.
		@since		20131010
	**/
	public function threewp_broadcast_prepare_meta_box( $action )
	{
		$meta_box_data = $action->meta_box_data;	// Convenience.

		// Add translation strings
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

		if ( $this->debugging() )
			$meta_box_data->html->put( 'debug', $this->p_( 'Broadcast is in debug mode. More information than usual will be shown.' ) );

		if ( $action->is_finished() )
		{
			if ( $this->debugging() )
				$meta_box_data->html->put( 'debug_applied', $this->p_( 'Broadcast is not preparing the meta box because it has already been applied.' ) );
			return;
		}

		if ( $meta_box_data->broadcast_data->get_linked_parent() !== false)
		{
			$meta_box_data->html->put( 'already_broadcasted',  sprintf( '<p>%s</p>',
				$this->_( 'This post is a broadcasted child post. It cannot be broadcasted further.' )
			) );
			$action->finish();
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

		if ( is_super_admin() OR static::user_has_roles( $this->get_site_option( 'role_link' ) ) )
		{
			// Link checkbox should always be on.
			$link_input = $form->checkbox( 'link' )
				->checked( true )
				->label_( 'Link this post to its children' )
				->title( $this->_( 'Create a link to the children, which will be updated when this post is updated, trashed when this post is trashed, etc.' ) );
			$meta_box_data->html->put( 'link', '' );
			$meta_box_data->convert_form_input_later( 'link' );
		}

		// 20140327 Because so many plugins create broken post types, assume that all post types support custom fields.
		// $post_type_supports_custom_fields = post_type_supports( $post_type, 'custom-fields' );
		$post_type_supports_custom_fields = true;

		if (
			( $post_type_supports_custom_fields OR $post_type_supports_thumbnails )
			AND
			( is_super_admin() OR static::user_has_roles( $this->get_site_option( 'role_custom_fields' ) ) )
		)
		{
			$custom_fields_input = $form->checkbox( 'custom_fields' )
				->checked( isset( $meta_box_data->last_used_settings[ 'custom_fields' ] ) )
				->label_( 'Custom fields' )
				->title( 'Broadcast all the custom fields and the featured image?' );
			$meta_box_data->html->put( 'custom_fields', '' );
			$meta_box_data->convert_form_input_later( 'custom_fields' );
		}

		if ( is_super_admin() OR static::user_has_roles( $this->get_site_option( 'role_taxonomies' ) ) )
		{
			$taxonomies_input = $form->checkbox( 'taxonomies' )
				->checked( isset( $meta_box_data->last_used_settings[ 'taxonomies' ] ) )
				->label_( 'Taxonomies' )
				->title( 'The taxonomies must have the same name (slug) on the selected blogs.' );
			$meta_box_data->html->put( 'taxonomies', '' );
			$meta_box_data->convert_form_input_later( 'taxonomies' );
		}

		$filter = new actions\get_user_writable_blogs( $this->user_id() );
		$blogs = $filter->execute()->blogs;

		$blogs_input = $form->checkboxes( 'blogs' )
			->css_class( 'blogs checkboxes' )
			->label_( 'Broadcast to' )
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
			$label = $form::unfilter_text( $blog->get_name() );
			if ( $label == '' )
				$label = $blog->domain;

			$blogs_input->option( $label, $blog->id );
			$input_name = 'blogs_' . $blog->id;
			$option = $blogs_input->input( $input_name );
			$option->get_label()->content = $label;
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
		$meta_box_data->convert_form_input_later( 'blogs' );

		$unchecked_child_blogs = $form->select( 'unchecked_child_blogs' )
			->css_class( 'blogs checkboxes' )
			// Input title
			->title_( 'What to do with unchecked, linked child blogs' )
			// Input label
			->label_( 'With the unchecked child blogs' )
			// With the unchecked child blogs:
			->option_( 'Do not update', '' )
			// With the unchecked child blogs:
			->option_( 'Delete the child post', 'delete' )
			// With the unchecked child blogs:
			->option_( 'Trash the child post', 'trash' )
			// With the unchecked child blogs:
			->option_( 'Unlink the child post', 'unlink' );
		$meta_box_data->html->put( 'unchecked_child_blogs', '' );
		$meta_box_data->convert_form_input_later( 'unchecked_child_blogs' );

		$js = sprintf( '<script type="text/javascript">var broadcast_blogs_to_hide = %s;</script>', $this->get_site_option( 'blogs_to_hide', 5 ) );
		$meta_box_data->html->put( 'blogs_js', $js );

		// We require some js.
		$meta_box_data->js->put( 'threewp_broadcast', $this->paths[ 'url' ] . '/js/js.js' );
		// And some CSS
		$meta_box_data->css->put( 'threewp_broadcast', $this->paths[ 'url' ] . '/css/css.css'  );

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
					( static::user_has_roles( $this->get_site_option( 'role_link' ) ) ? 'yes' : 'no' ),
					( $post_type_supports_custom_fields ? 'yes' : 'no' ),
					( $post_type_supports_thumbnails ? 'yes' : 'no' ),
					( static::user_has_roles( $this->get_site_option( 'role_custom_fields' ) ) ? 'yes' : 'no' ),
					( static::user_has_roles( $this->get_site_option( 'role_taxonomies' ) ) ? 'yes' : 'no' ),
					count( $blogs )
				)
			);

			// Display a list of actions that have hooked into save_post
			$save_post_callbacks = $this->get_hooks( 'save_post' );
			$meta_box_data->html->put( 'debug_save_post_callbacks', sprintf( '%s%s',
				$this->p_( 'Plugins that have hooked into save_post:' ),
				$this->implode_html( $save_post_callbacks )
			) );
		}

		$action->finish();
	}

	/**
		@brief		Fix up the inputs.
		@since		20131010
	**/
	public function threewp_broadcast_prepared_meta_box( $action )
	{
		$action->meta_box_data->convert_form_inputs_now();
	}

}
