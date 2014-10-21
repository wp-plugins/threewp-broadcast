<?php

namespace threewp_broadcast\traits;

use threewp_broadcast\actions;

/**
	@brief		Methods that have to do with posts and their broadcast data.
	@since		2014-10-19 15:00:44
**/
trait post_methods
{
	/**
		@brief		Adds post row actions
		@since		20131015
	**/
	public function add_post_row_actions_and_hooks()
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

			// Hook into the actions so that we can keep track of the broadcast data.
			$this->add_action( 'wp_trash_post', 'trash_post' );
			$this->add_action( 'trash_post' );
			$this->add_action( 'trash_page', 'trash_post' );

			$this->add_action( 'untrash_post' );
			$this->add_action( 'untrash_page', 'untrash_post' );

			$this->add_action( 'delete_post' );
			$this->add_action( 'delete_page', 'delete_post' );
		}
	}

	public function delete_post( $post_id)
	{
		$this->trash_untrash_delete_post( 'wp_delete_post', $post_id );
	}

	public function manage_posts_columns( $defaults)
	{
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

	/**
		@brief		Handle the display of the custom column.
		@since		2014-04-18 08:30:19
	**/
	public function threewp_broadcast_manage_posts_custom_column( $action )
	{
		if ( $action->broadcast_data->get_linked_parent() !== false)
		{
			$parent = $action->broadcast_data->get_linked_parent();
			$parent_blog_id = $parent[ 'blog_id' ];
			switch_to_blog( $parent_blog_id );

			$html = $this->_(sprintf( 'Linked from %s', '<a href="' . get_bloginfo( 'url' ) . '/wp-admin/post.php?post=' .$parent[ 'post_id' ] . '&action=edit">' . get_bloginfo( 'name' ) . '</a>' ) );
			$action->html->put( 'linked_from', $html );
			restore_current_blog();
		}
		elseif ( $action->broadcast_data->has_linked_children() )
		{
			$children = $action->broadcast_data->get_linked_children();

			if ( count( $children ) > 0 )
			{
				// Only display if there is more than one child post
				if ( count( $children ) > 1 )
				{
					$strings = new \threewp_broadcast\collections\strings_with_metadata;

					$strings->set( 'div_open', '<div class="row-actions broadcasted_blog_actions">' );
					$strings->set( 'text_all', $this->_( 'All' ) );
					$strings->set( 'div_small_open', '<small>' );

					$url = sprintf( "admin.php?page=threewp_broadcast&amp;action=user_restore_all&amp;post=%s", $action->parent_post_id );
					$url = wp_nonce_url( $url, 'broadcast_restore_all_' . $action->parent_post_id );
					$strings->set( 'restore_all_separator', ' | ' );
					$strings->set( 'restore_all', sprintf( '<a href="%s" title="%s">%s</a>',
						$url,
						$this->_( 'Restore all of the children from the trash' ),
						$this->_( 'Restore' )
					) );

					$url = sprintf( "admin.php?page=threewp_broadcast&amp;action=user_trash_all&amp;post=%s", $action->parent_post_id );
					$url = wp_nonce_url( $url, 'broadcast_trash_all_' . $action->parent_post_id );
					$strings->set( 'trash_all_separator', ' | ' );
					$strings->set( 'trash_all', sprintf( '<a href="%s" title="%s">%s</a>',
						$url,
						$this->_( 'Put all of the children in the trash' ),
						$this->_( 'Trash' )
					) );

					$url = sprintf( "admin.php?page=threewp_broadcast&amp;action=user_unlink_all&amp;post=%s", $action->parent_post_id );
					$url = wp_nonce_url( $url, 'broadcast_unlink_all_' . $action->parent_post_id );
					$strings->set( 'unlink_all_separator', ' | ' );
					$strings->set( 'unlink_all', sprintf( '<a href="%s" title="%s">%s</a>',
						$url,
						$this->_( 'Unlink all of the child posts' ),
						$this->_( 'Unlink' )
					) );

					$url = sprintf( "admin.php?page=threewp_broadcast&amp;action=user_delete_all&amp;post=%s", $action->parent_post_id );
					$url = wp_nonce_url( $url, 'broadcast_delete_all_' . $action->parent_post_id );
					$strings->set( 'delete_all_separator', ' | ' );
					$strings->set( 'delete_all', sprintf( '<span class="trash"><a href="%s" title="%s">%s</a></span>',
						$url,
						$this->_( 'Permanently delete all the broadcasted children' ),
						$this->_( 'Delete' )
					) );

					$strings->set( 'div_small_close', '</small>' );
					$strings->set( 'div_close', '</div>' );

					$action->html->put( 'delete_all', $strings );
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

					$url = sprintf( "admin.php?page=threewp_broadcast&amp;action=user_restore&amp;post=%s&amp;child=%s", $action->parent_post_id, $child_blog_id );
					$url = wp_nonce_url( $url, 'broadcast_restore_' . $child_blog_id . '_' . $action->parent_post_id );
					$strings->set( 'restore_separator', ' | ' );
					$strings->set( 'restore', sprintf( '<a href="%s" title="%s">%s</a>',
						$url,
						$this->_( 'Restore all of the children from the trash' ),
						$this->_( 'Restore' )
					) );

					$url = sprintf( "admin.php?page=threewp_broadcast&amp;action=user_trash&amp;post=%s&amp;child=%s", $action->parent_post_id, $child_blog_id );
					$url = wp_nonce_url( $url, 'broadcast_trash_' . $child_blog_id . '_' . $action->parent_post_id );
					$strings->set( 'trash_separator', ' | ' );
					$strings->set( 'trash', sprintf( '<a href="%s" title="%s">%s</a>',
						$url,
						$this->_( 'Put this broadcasted child post in the trash' ),
						$this->_( 'Trash' )
					) );

					$url = sprintf( "admin.php?page=threewp_broadcast&amp;action=user_unlink&amp;post=%s&amp;child=%s", $action->parent_post_id, $child_blog_id );
					$url = wp_nonce_url( $url, 'broadcast_unlink_' . $child_blog_id . '_' . $action->parent_post_id );
					$strings->set( 'unlink_separator', ' | ' );
					$strings->set( 'unlink', sprintf( '<a href="%s" title="%s">%s</a>',
						$url,
						$this->_( 'Remove link to this broadcasted child post' ),
						$this->_( 'Unlink' )
					) );

					$url = sprintf( "admin.php?page=threewp_broadcast&amp;action=user_delete&amp;post=%s&amp;child=%s", $action->parent_post_id, $child_blog_id );
					$url = wp_nonce_url( $url, 'broadcast_delete_' . $child_blog_id . '_' . $action->parent_post_id );
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

				$action->html->put( 'broadcasted_to', $collection );
			}
		}
		$action->finish();
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
		$filter = new actions\get_user_writable_blogs( $this->user_id() );
		$blogs = $filter->execute()->blogs;

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
}