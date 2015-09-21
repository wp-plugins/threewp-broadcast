<?php

namespace threewp_broadcast\traits;

use \threewp_broadcast\actions;
use \threewp_broadcast\ajax;
use \threewp_broadcast\posts\actions\action as post_action;
use \threewp_broadcast\posts\actions\bulk\wp_ajax;

/**
	@brief		Methods that have to do with posts and their broadcast data.
	@since		2014-10-19 15:00:44
**/
trait post_actions
{
	/**
		@brief		Adds post row actions
		@since		20131015
	**/
	public function add_post_row_actions_and_hooks()
	{
		if ( is_super_admin() || static::user_has_roles( $this->get_site_option( 'role_link' ) ) )
		{
			if (  $this->display_broadcast_columns )
			{
				$this->add_filter( 'manage_posts_columns' );
				$this->add_filter( 'manage_pages_columns', 'manage_posts_columns' );

				$this->add_action( 'manage_posts_custom_column', 10, 2 );
				$this->add_action( 'manage_pages_custom_column', 'manage_posts_custom_column', 10, 2 );
			}

		}
	}

	public function delete_post( $post_id)
	{
		$this->trash_untrash_delete_post( 'wp_delete_post', $post_id );
	}

	public function manage_posts_columns( $defaults )
	{
		$action = new actions\get_post_bulk_actions();
		$action->execute();
		$this->add_admin_script( 'post_bulk_actions', $action->get_js() );

		$this->add_admin_script( 'post_bulk_actions_broadcast_strings', '
			<script type="text/javascript">
				var broadcast_strings = {
					broadcast : "' . $this->_( 'Broadcast' ) . '",
					post_actions : "' . $this->_( 'Post actions' ) . '"
				};
			</script>
		' );

		$defaults[ '3wp_broadcast' ] = '<span title="'.$this->_( 'Shows which blogs have posts linked to this one' ).'">'.$this->_( 'Broadcasted' ).'</span>';
		return $defaults;
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
		$action->execute();

		echo $action->render();
	}

	/**
		@brief		Fill the action with all of the post actions we offer.
		@since		2014-11-02 21:29:15
	**/
	public function threewp_broadcast_get_post_actions( $action )
	{
		foreach( [
			'delete' => 'Delete child',
			'find_unlinked' => 'Find unlinked child',
			'restore' => 'Restore child',
			'trash' => 'Trash child',
			'unlink' => 'Unlink child',
		] as $slug => $name )
		{
			$a = new post_action;
			$a->set_action( $slug );
			$a->set_id( $slug );
			$a->set_name( $name );
			$action->add( $a );
		}
	}

	/**
		@brief		Fill the action with all of the bulk actions we offer.
		@since		2014-10-31 14:11:10
	**/
	public function threewp_broadcast_get_post_bulk_actions( $action )
	{
		$ajax_action = 'broadcast_post_bulk_action';

		foreach( [
			'delete' => $this->_( 'Delete children' ),
			'find_unlinked' => $this->_( 'Find unlinked children' ),
			'restore' => $this->_( 'Restore children' ),
			'trash' => $this->_( 'Trash children' ),
			'unlink' => $this->_( 'Unlink' ),
		] as $subaction => $name )
		{
			$a = new wp_ajax;
			$a->set_ajax_action( $ajax_action );
			$a->set_data( 'subaction', $subaction );
			$a->set_id( 'bulk_' . $subaction );
			$a->set_name( $name );
			$a->set_nonce( $ajax_action . $subaction );
			$action->add( $a );
		}
	}

	/**
		@brief		Handle the display of the custom column.
		@since		2014-04-18 08:30:19
	**/
	public function threewp_broadcast_manage_posts_custom_column( $action )
	{
		$title = $this->_( "Click to modify the post's linkage" );
		$nonce = wp_create_nonce( 'broadcast_post_action_form' . $action->post->ID );
		$nonce = sprintf( 'data-nonce="%s"', $nonce );

		if ( $action->broadcast_data->get_linked_parent() !== false )
		{
			$parent = $action->broadcast_data->get_linked_parent();
			$parent_blog_id = $parent[ 'blog_id' ];
			switch_to_blog( $parent_blog_id );

			$html = $this->_(sprintf( '<a class="broadcast post" href="#" %s title="%s">&#x21e6; %s</a>', $nonce, $title, get_bloginfo( 'name' ) ) );
			$action->html->put( 'linked_from', $html );
			restore_current_blog();
		}

		if ( $action->broadcast_data->has_linked_children() )
		{
			$children = $action->broadcast_data->get_linked_children();

			// Only display if there is something to display
			if ( count( $children ) > 0 )
			{
				// How many children to display?
				$max = $this->get_site_option( 'blogs_hide_overview' );
				if( count( $children ) > $max )
				{
					$html = sprintf( '<a class="broadcast post counter" href="#" %s title="%s">&#x21e8; %s</a>', $nonce, $title, count( $children ) );
				}
				else
				{
					$links = [];
					foreach( $children as $child_blog_id => $child_post_id )
					{
						switch_to_blog( $child_blog_id );
						$info = get_blog_details();
						$blogname = $info->blogname ? $info->blogname : $info->domain . $info->path;
						$links[ $blogname ] = sprintf( '&#x21e8; %s', $blogname );
						restore_current_blog();
					}
					ksort( $links );
					$html = sprintf( '<a class="broadcast post" href="#" %s title="%s">%s</a>',
						$nonce,
						$title,
						implode( '<br/>', $links )
					);
				}
				$action->html->put( 'broadcasted_to', $html );
			}

		}

		$action->finish();
	}

	/**
		@brief		Execute an action on a post.
		@since		2014-11-02 16:35:27
	**/
	public function threewp_broadcast_post_action( $action )
	{
		$blog_id = get_current_blog_id();
		$post_id = $action->post_id;

		// In order for this method to be usable for both single and bulk post actions, do some footwork here so that we can help the actions decide whether to work on a specific child or not.
		$on_child_blog_id = 0;
		if ( isset( $action->child_blog_id ) && $action->child_blog_id > 0 )
			$on_child_blog_id = $action->child_blog_id;

		switch( $action->action )
		{
			// Delete all children
			case 'delete':
				$broadcast_data = $this->get_post_broadcast_data( $blog_id, $post_id );
				foreach( $broadcast_data->get_linked_children() as $child_blog_id => $child_post_id )
				{
					if ( ( $on_child_blog_id > 0 ) && ( $child_blog_id != $on_child_blog_id ) )
						continue;
					switch_to_blog( $child_blog_id );
					wp_delete_post( $child_post_id, true );
					$broadcast_data->remove_linked_child( $child_blog_id );
					restore_current_blog();
				}
				$broadcast_data = $this->set_post_broadcast_data( $blog_id, $post_id, $broadcast_data );
			break;
			case 'find_unlinked':
				$post = get_post( $post_id );
				$broadcast_data = $this->get_post_broadcast_data( $blog_id, $post_id );
				// Get a list of blogs that this user can link to.
				$filter = new actions\get_user_writable_blogs( $this->user_id() );
				$blogs = $filter->execute()->blogs;
				foreach( $blogs as $blog )
				{
					if ( $blog->id == $blog_id )
						continue;

					if ( $broadcast_data->has_linked_child_on_this_blog( $blog->id ) )
						continue;

					$blog->switch_to();

					$args = array(
						'cache_results' => false,
						'name' => $post->post_name,
						'numberposts' => 2,
						'post_type'=> $post->post_type,
					);
					$posts = get_posts( $args );

					// An exact match was found.
					if ( count( $posts ) == 1 )
					{
						$unlinked = reset( $posts );

						$child_broadcast_data = $this->get_post_broadcast_data( $blog->id, $unlinked->ID );
						if ( $child_broadcast_data->get_linked_parent() === false )
							if ( ! $child_broadcast_data->has_linked_children() )
							{
								$broadcast_data->add_linked_child( $blog->id, $unlinked->ID );

								// Add link info for the new child.
								$child_broadcast_data->set_linked_parent( $blog_id, $post_id );
								$this->set_post_broadcast_data( $blog->id, $unlinked->ID, $child_broadcast_data );
							}
					}

					$blog->switch_from();
				}
				$broadcast_data = $this->set_post_broadcast_data( $blog_id, $post_id, $broadcast_data );
			break;
			// Restore children
			case 'restore':
				$broadcast_data = $this->get_post_broadcast_data( $blog_id, $post_id );
				foreach( $broadcast_data->get_linked_children() as $child_blog_id => $child_post_id )
				{
					if ( ( $on_child_blog_id > 0 ) && ( $child_blog_id != $on_child_blog_id ) )
						continue;
					switch_to_blog( $child_blog_id );
					wp_publish_post( $child_post_id );
					restore_current_blog();
				}
			break;
			// Trash children
			case 'trash':
				$broadcast_data = $this->get_post_broadcast_data( $blog_id, $post_id );
				foreach( $broadcast_data->get_linked_children() as $child_blog_id => $child_post_id )
				{
					if ( ( $on_child_blog_id > 0 ) && ( $child_blog_id != $on_child_blog_id ) )
						continue;
					switch_to_blog( $child_blog_id );
					wp_trash_post( $child_post_id );
					restore_current_blog();
				}
			break;
			// Unlink children
			case 'unlink':
				// TODO: Make this more flexible when we add parent / siblings.
				$broadcast_data = $this->get_post_broadcast_data( $blog_id, $post_id );

				$parent = $broadcast_data->get_linked_parent();
				if ( $parent !== false )
				{
					// Remove the link to this child from the parent.
					$parent = (object)$parent;
					$parent_broadcast_data = $this->get_post_broadcast_data( $parent->blog_id, $parent->post_id );
					$parent_broadcast_data->remove_linked_child( $blog_id );
					$this->set_post_broadcast_data( $parent->blog_id, $parent->post_id, $parent_broadcast_data );

					// And now we remove the link to the parent.
					$broadcast_data->remove_linked_parent();
				}

				if ( $broadcast_data->has_linked_children() )
				{
					$linked_children = $broadcast_data->get_linked_children();
					foreach( $linked_children as $child_blog_id => $child_post_id )
					{
						if ( ( $on_child_blog_id > 0 ) && ( $child_blog_id != $on_child_blog_id ) )
							continue;
						$broadcast_data->remove_linked_child( $child_blog_id );
						$this->delete_post_broadcast_data( $child_blog_id, $child_post_id );
					}
				}
				$this->set_post_broadcast_data( $blog_id, $post_id, $broadcast_data );
			break;
		}
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

	public function untrash_post( $post_id)
	{
		$this->trash_untrash_delete_post( 'wp_untrash_post', $post_id );
	}

	/**
		@brief		Handle a post bulk action sent via Ajax.
		@since		2014-11-01 19:00:57
	**/
	public function wp_ajax_broadcast_post_bulk_action()
	{
		$action = new actions\post_action;
		$json = new ajax\json();

		if ( ! isset( $_REQUEST[ 'nonce' ] ) )
			wp_die( 'No nonce.' );

		if ( ! isset( $_REQUEST[ 'subaction' ] ) )
			wp_die( 'No subaction.' );

		$nonce = $_REQUEST[ 'nonce' ];
		$action->action = $_REQUEST[ 'subaction' ];
		if ( ! wp_verify_nonce( $nonce, 'broadcast_post_bulk_action' . $action->action ) )
			wp_die( 'Invalid nonce.' );

		if ( ! isset( $_REQUEST[ 'post_ids' ] ) )
			wp_die( 'No post IDs' );

		$post_ids = $_REQUEST[ 'post_ids' ];
		$post_ids = explode( ',', $post_ids );

		foreach( $post_ids as $post_id )
		{
			$action->post_id = $post_id;
			$action->execute();
		}
		$json->output();
	}

	/**
		@brief		Display and handle the actions available for a post.
		@since		2014-11-02 20:44:32
	**/
	public function wp_ajax_broadcast_post_action_form()
	{
		if ( ! isset( $_REQUEST[ 'nonce' ] ) )
			wp_die( 'No nonce.' );
		$nonce = $_REQUEST[ 'nonce' ];

		if ( ! isset( $_REQUEST[ 'post_id' ] ) )
			wp_die( 'No nonce.' );
		$post_id = $_REQUEST[ 'post_id' ];

		$action = 'broadcast_post_action_form';
		if ( ! wp_verify_nonce( $nonce, $action . $post_id ) )
			wp_die( 'Invalid nonce.' );

		// Everything is good to go.

		$this->load_language();

		$blog_id = get_current_blog_id();
		$broadcast_data = $this->get_post_broadcast_data( $blog_id, $post_id );
		$form = $this->form2();
		$form->hidden_input( 'action', $nonce );
		$form->hidden_input( 'nonce', $nonce );
		$form->hidden_input( 'post_id', $post_id );
		$form->id( 'broadcast_post_action_form' );
		$json = new ajax\json();
		$json->html = '';
		$has_links = false;

		// Linked to a parent.
		if ( $broadcast_data->get_linked_parent() !== false )
		{
			$unlink = $form->checkbox( 'unlink' )
				->description_( 'Unlink this post from its parent.' )
				->label( 'Unlink' );
			$has_links = true;
		}

		if ( $broadcast_data->has_linked_children() )
		{
			$form->blogs = [];
			// Find all options for posts.
			$action = new actions\get_post_actions();
			$action->post = get_post( $post_id );
			$action->execute();
			$options = [ '' => $this->_( 'No change' ) ];
			foreach( $action->actions as $post_action )
			{
				$options[ $post_action->action ] = $post_action->get_name();
			}
			ksort( $options );
			$options = array_flip( $options );

			$children = $broadcast_data->get_linked_children();
			foreach( $children as $child_blog_id => $child_post_id )
			{
				switch_to_blog( $child_blog_id );
				$info = get_blog_details();
				$blogname = $info->blogname ? $info->blogname : $info->domain . $info->path;
				$select = $form->select( $child_blog_id )
					->label( $blogname )
					->prefix( 'blogs' )
					->options( $options )
					;
				$select->blog_id = $child_blog_id;
				$select->post_id = $child_post_id;
				$form->blogs []= $select;
				restore_current_blog();
			}
			$has_links = true;
		}

		if ( ! $has_links )
			$json->html .= $this->p_( 'This post has no broadcast links.' );

		$submit = $form->primary_button( 'submit' )
			->value( 'Submit' );

		if ( $form->is_posting() )
		{
			$form->post()->use_post_values();
			// We have to check specifically for the submit.
			if ( $submit->pressed() )
			{
				if ( isset( $unlink ) && $unlink->is_checked() )
				{
					$post_action = new actions\post_action;
					$post_action->action = 'unlink';
					$post_action->post_id = $post_id;
					$post_action->execute();
				}
				if ( isset( $form->blogs ) )
				{
					foreach( $form->blogs as $select )
					{
						$value = $select->get_post_value();
						if( $value == '' )
							continue;
						$post_action = new actions\post_action;
						$post_action->action = $value;
						$post_action->post_id = $post_id;
						$post_action->child_blog_id = $select->blog_id;
						$post_action->execute();
					}
				}
				unset( $_POST[ 'submit' ] );
				$this->wp_ajax_broadcast_post_action_form();
			}
		}

		$json->html .= $form->open_tag();
		$json->html .= $form->display_form_table();
		$json->html .= $form->open_tag();
		$json->output();
	}
}
