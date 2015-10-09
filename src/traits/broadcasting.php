<?php

namespace threewp_broadcast\traits;

use \Exception;
use \plainview\sdk_broadcast\collections\collection;
use \threewp_broadcast\actions;
use \threewp_broadcast\attachment_data;
use \threewp_broadcast\broadcasting_data;
use \threewp_broadcast\broadcast_data\blog;

/**
	@brief		Methods related to actual broadcasting of a post.
	@details

	Before you try to broadcast anything, check if Broadcast is installed.

	<code>
	if ( ! function_exists( 'ThreeWP_Broadcast' ) )
		return false;
	</code>

	Now you are sure that the broadcasting_data object exists.

	<code>
	// Broadcast the post 12345, from this blog, to blogs 123, 456 and 789.
	$bcd = \threewp_broadcast\broadcasting_data::make( 12345, [ 123, 456, 789 ] );

	// Call on Broadcast to send it all away.
	do_action( 'threewp_broadcast_broadcast_post', $bcd );
	</code>

	@since		2014-10-19 15:44:39
**/
trait broadcasting
{
	/**
		@brief		Broadcast a post.
		@details	The BC data parameter contains all necessary information about what is being broadcasted, to which blogs, options, etc.
		@param		broadcasting_data		$broadcasting_data		The broadcasting data object.
		@since		20130603
	**/
	public function broadcast_post( $broadcasting_data )
	{
		$bcd = $broadcasting_data;

		$this->debug( 'Broadcast version %s.', THREEWP_BROADCAST_VERSION );

		$this->debug( 'Broadcasting the post %s <pre>%s</pre>', $bcd->post->ID, $bcd->post );

		$this->debug( 'The POST is <pre>%s</pre>', $bcd->_POST );

		// For nested broadcasts. Just in case.
		switch_to_blog( $bcd->parent_blog_id );

		if ( $bcd->link )
		{
			$this->debug( 'Linking is enabled.' );

			if ( $broadcasting_data->broadcast_data === null )
			{
				// Prepare the broadcast data for linked children.
				$bcd->broadcast_data = $this->get_post_broadcast_data( $bcd->parent_blog_id, $bcd->post->ID );

				// Does this post type have parent support, so that we can link to a parent?
				if ( $bcd->post_type_is_hierarchical && $bcd->post->post_parent > 0)
				{
					$parent_broadcast_data = $this->get_post_broadcast_data( $bcd->parent_blog_id, $bcd->post->post_parent );
				}
				$this->debug( 'Post type is hierarchical: %s', $this->yes_no( $bcd->post_type_is_hierarchical ) );
			}
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
			$this->debug( 'Has %s attachments.', count( $attached_files ) );
			foreach( $attached_files as $attached_file )
			{
				try
				{
					$data = attachment_data::from_attachment_id( $attached_file, $bcd->upload_dir );
					$data->set_attached_to_parent( $bcd->post );
					$bcd->attachment_data[ $attached_file->ID ] = $data;
					$this->debug( 'Attachment %s found.', $attached_file->ID );
				}
				catch( Exception $e )
				{
					$this->debug( 'Exception adding attachment: ' . $e->getMessage() );
				}
			}
		}

		if ( $bcd->custom_fields !== false )
		{
			if ( ! is_object( $bcd->custom_fields ) )
				$bcd->custom_fields = (object)[];

			$this->debug( 'Custom fields: Will broadcast custom fields.' );

			if ( isset( $GLOBALS[ 'wpseo_metabox' ] ) )
			{
				$this->debug( 'Yoast SEO detected. Activating workaround. Asking metabox to save its settings.' );
				$GLOBALS[ 'wpseo_metabox' ]->save_postdata( $bcd->post->ID );
			}


			// Save the original custom fields for future use.
			$bcd->custom_fields->original = get_post_custom( $bcd->post->ID );
			// Obsolete!
			$bcd->post_custom_fields = $bcd->custom_fields->original;

			$this->debug( 'The custom fields are <pre>%s</pre>', $bcd->custom_fields()->to_array() );

			// Start handling the thumbnail
			unset( $bcd->thumbnail );
			$bcd->has_thumbnail = $bcd->custom_fields()->has( '_thumbnail_id' );

			// Check that the thumbnail ID is > 0
			if ( $bcd->has_thumbnail )
			{
				$thumbnail_id = $bcd->custom_fields()->get_single( '_thumbnail_id' );
				$thumbnail_post = get_post( $thumbnail_id );
				$bcd->has_thumbnail = ( $thumbnail_id > 0 ) && ( $thumbnail_post !== null );
			}

			if ( $bcd->has_thumbnail )
			{
				$bcd->thumbnail_id = $thumbnail_id;
				$this->debug( 'Custom fields: Post has a thumbnail (featured image): %s', $bcd->thumbnail_id );
				$bcd->thumbnail = $thumbnail_post;
				$bcd->custom_fields()->forget( '_thumbnail_id' );	// There is a new thumbnail id for each blog.
				try
				{
					$data = attachment_data::from_attachment_id( $bcd->thumbnail, $bcd->upload_dir);
					$data->set_attached_to_parent( $bcd->post );
					$bcd->attachment_data[ 'thumbnail' ] = $data;
					// Now that we know what the attachment id the thumbnail has, we must remove it from the attached files to avoid duplicates.
					unset( $bcd->attachment_data[ $bcd->thumbnail_id ] );
				}
				catch( Exception $e )
				{
					$this->debug( 'Exception adding attachment: ' . $e->getMessage() );
				}
			}
			else
				$this->debug( 'Custom fields: Post does not have a thumbnail (featured image).' );

			$bcd->custom_fields->blacklist = array_filter( explode( ' ', $this->get_site_option( 'custom_field_blacklist' ) ) );
			$this->debug( 'The custom field blacklist is: %s', $bcd->custom_fields->blacklist );
			$bcd->custom_fields->protectlist = array_filter( explode( ' ', $this->get_site_option( 'custom_field_protectlist' ) ) );
			$this->debug( 'The custom field protectlist is: %s', $bcd->custom_fields->protectlist );
			$bcd->custom_fields->whitelist = array_filter( explode( ' ', $this->get_site_option( 'custom_field_whitelist' ) ) );
			$this->debug( 'The custom field whitelist is: %s', $bcd->custom_fields->whitelist );

			foreach( $bcd->custom_fields() as $custom_field => $ignore )
			{
				$keep = true;

				// Skip the exceptions.
				if ( $bcd->custom_fields()->blacklist_has( $custom_field ) )
					$keep = false;

				// If we do not broadcast them, then check the whitelist.
				if ( ! $keep AND $bcd->custom_fields()->whitelist_has( $custom_field ) )
					$keep = true;

				if ( ! $keep )
				{
					$this->debug( 'Custom fields: Deleting custom field %s', $custom_field );
					$bcd->custom_fields()->forget( $custom_field );
				}
				else
					$this->debug( 'Custom fields: Keeping custom field %s', $custom_field );
			}
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
			$gallery = (object)[];
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
				try
				{
					$data = attachment_data::from_attachment_id( $id, $bcd->upload_dir );
					$data->set_attached_to_parent( $bcd->post );
					$bcd->attachment_data[ $id ] = $data;
				}
				catch( Exception $e )
				{
					$this->debug( 'Exception adding attachment: ' . $e->getMessage() );
				}
			}
		}

		// To prevent recursion
		array_push( $this->broadcasting, $bcd );

		// Handle sticky.
		$bcd->post_is_sticky = isset( $_POST[ 'sticky' ]  );
		$this->debug( 'Post sticky status: %s', intval( $bcd->post_is_sticky ) );

		// POST is no longer needed. Empty it so that other plugins don't use it.
		$action = new actions\maybe_clear_post;
		$action->post = $_POST;
		$action->execute();
		$_POST = $action->post;

		// This is a stupid exception: edit_post() checks the _POST for the sticky checkbox.
		// And edit_post() is run after save_post()... :(
		// So if the post is sticky, we have to put the checkbox back in the post.
		// This can be avoided by either not clearing the post or forcing the user to update twice.
		// Neither solution is any good: not clearing the post makes _some_ plugins go crazy, updating twice is not expected behavior.
		if ( $bcd->post_is_sticky )
			$_POST[ 'sticky'] = 'sticky';

		$action = new actions\broadcasting_started;
		$action->broadcasting_data = $bcd;
		$action->execute();

		$this->debug( 'The attachment data is: %s', $bcd->attachment_data );

		$this->debug( 'Beginning child broadcast loop.' );

		foreach( $bcd->blogs as $child_blog )
		{
			$child_blog->switch_to();
			$bcd->current_child_blog_id = $child_blog->get_id();
			$this->debug( 'Switched to blog %s (%s)', get_bloginfo( 'name' ), $bcd->current_child_blog_id );

			// Create new post data from the original stuff.
			$bcd->new_post = clone( $bcd->post );
			$bcd->new_child_created = false;

			foreach( [ 'comment_count', 'guid', 'ID', 'post_parent' ] as $key )
				unset( $bcd->new_post->$key );

			$action = new actions\broadcasting_after_switch_to_blog;
			$action->broadcasting_data = $bcd;
			$action->execute();

			if ( ! $action->broadcast_here )
			{
				$this->debug( 'Skipping this blog.' );
				$child_blog->switch_from();
				continue;
			}

			// Force a reload the broadcast data?
			$bcd->broadcast_data = $this->get_post_broadcast_data( $bcd->parent_blog_id, $bcd->post->ID );

			// Post parent
			if ( $bcd->link && isset( $parent_broadcast_data) )
				if ( $parent_broadcast_data->has_linked_child_on_this_blog() )
				{
					$linked_parent = $parent_broadcast_data->get_linked_child_on_this_blog();
					$bcd->new_post->post_parent = $linked_parent;
				}

			// Insert new? Or update? Depends on whether the parent post was linked before or is newly linked?
			$need_to_insert_post = true;
			if ( $bcd->broadcast_data !== null )
				if ( $bcd->broadcast_data->has_linked_child_on_this_blog() )
				{
					$child_post_id = $bcd->broadcast_data->get_linked_child_on_this_blog();
					$this->debug( 'There is already a child post on this blog: %s', $child_post_id );

					// Does this child post still exist?
					$child_post = get_post( $child_post_id );
					if ( $child_post !== null )
					{
						$temp_post_data = $bcd->new_post;
						$temp_post_data->ID = $child_post_id;
						wp_update_post( $temp_post_data );
						$bcd->new_post->ID = $child_post_id;
						$need_to_insert_post = false;
					}
					else
					{
						$this->debug( 'Warning: The child post has disappeared. Recreating.' );
						$need_to_insert_post = true;
					}
				}

			if ( $need_to_insert_post )
			{
				$temp_post_data = clone( $bcd->new_post );
				$this->debug( 'Creating a new post: %s', $temp_post_data );
				unset( $temp_post_data->ID );

				$result = wp_insert_post( $temp_post_data, true );

				// Did we manage to insert the post properly?
				if ( intval( $result ) < 1 )
				{
					$this->debug( 'Unable to insert the child post.' );
					continue;
				}
				// Yes we did.
				$bcd->new_post->ID = $result;

				$bcd->new_child_created = true;

				$this->debug( 'New child created: %s', $result );

				if ( $bcd->link )
				{
					$this->debug( 'Adding link to child.' );
					$bcd->broadcast_data->add_linked_child( $bcd->current_child_blog_id, $bcd->new_post( 'ID' ) );
				}
			}

			$bcd->equivalent_posts()->set( $bcd->parent_blog_id, $bcd->post->ID, $bcd->current_child_blog_id, $bcd->new_post( 'ID' ) );
			$this->debug( 'Equivalent of %s/%s is %s/%s', $bcd->parent_blog_id, $bcd->post->ID, $bcd->current_child_blog_id, $bcd->new_post( 'ID' )  );

			if ( $bcd->taxonomies )
			{
				$this->debug( 'Taxonomies: Starting.' );
				foreach( $bcd->parent_post_taxonomies as $parent_post_taxonomy => $parent_post_terms )
				{
					$this->debug( 'Taxonomies: %s', $parent_post_taxonomy );
					// If we're updating a linked post, remove all the taxonomies and start from the top.
					if ( $bcd->link )
						if ( $bcd->broadcast_data->has_linked_child_on_this_blog() )
							wp_set_object_terms( $bcd->new_post( 'ID' ), [], $parent_post_taxonomy );

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
							if ( $target_blog_term->slug == $parent_slug )
							{
								$this->debug( 'Taxonomies: Found existing taxonomy %s.', $parent_slug );
								$found = true;
								$taxonomies_to_add_to[] = intval( $target_blog_term->term_id );
								break;
							}
						}

						// Should we create the taxonomy term if it doesn't exist?
						if ( ! $found )
						{
							// Does the term have a parent?
							$target_parent_id = 0;
							if ( $parent_post_term->parent != 0 )
							{
								// Recursively insert ancestors if needed, and get the target term's parent's ID
								$target_parent_id = $this->insert_term_ancestors(
									$parent_post_term,
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
							$action->execute();
							if ( $action->new_term )
							{
								$term_id = intval( $action->new_term->term_id );
								$taxonomies_to_add_to []= $term_id;
								$this->debug( 'Taxonomies: Created taxonomy %s (%s).', $parent_post_term->name, $term_id );
							}
							else
								$this->debug( 'Taxonomies: Taxonomy %s was not created.', $parent_post_term->name );
						}
					}

					$this->debug( 'Taxonomies: Syncing terms.' );
					$this->sync_terms( $bcd, $parent_post_taxonomy );
					$this->debug( 'Taxonomies: Synced terms.' );

					if ( count( $taxonomies_to_add_to ) > 0 )
					{
						// This relates to the bug mentioned in the method $this->set_term_parent()
						delete_option( $parent_post_taxonomy . '_children' );
						clean_term_cache( '', $parent_post_taxonomy );
						$this->debug( 'Setting taxonomies for %s: %s', $parent_post_taxonomy, $taxonomies_to_add_to );
						wp_set_object_terms( $bcd->new_post( 'ID' ), $taxonomies_to_add_to, $parent_post_taxonomy );
					}
				}
				$this->debug( 'Taxonomies: Finished.' );
			}

			// Maybe remove the current attachments.
			if ( $bcd->delete_attachments )
			{
				$attachments_to_remove = get_children( 'post_parent='.$bcd->new_post( 'ID' ) . '&post_type=attachment' );
				$this->debug( '%s attachments to remove.', count( $attachments_to_remove ) );
				foreach ( $attachments_to_remove as $attachment_to_remove )
				{
					$this->debug( 'Deleting existing attachment: %s', $attachment_to_remove->ID );
					wp_delete_attachment( $attachment_to_remove->ID );
				}
			}
			else
				$this->debug( 'Not deleting child attachments.' );

			// Copy the attachments
			$bcd->copied_attachments = [];
			$this->debug( 'Looking through %s attachments.', count( $bcd->attachment_data ) );
			foreach( $bcd->attachment_data as $key => $attachment )
			{
				if ( $key == 'thumbnail' )
					continue;
				$o = clone( $bcd );
				$o->attachment_data = clone( $attachment );
				$o->attachment_data->post = clone( $attachment->post );
				$this->debug( "The attachment's post parent is %s.", $o->attachment_data->post->post_parent );
				if ( $o->attachment_data->is_attached_to_parent() )
				{
					$this->debug( 'Assigning new post parent ID (%s) to attachment %s.', $bcd->new_post( 'ID' ), $o->attachment_data->post->ID );
					$o->attachment_data->post->post_parent = $bcd->new_post( 'ID' );
				}
				else
				{
					$this->debug( 'Resetting post parent for attachment %s.', $o->attachment_data->post->ID );
					$o->attachment_data->post->post_parent = 0;
				}
				$this->maybe_copy_attachment( $o );
				$bcd->copied_attachments()->add( $attachment->post, get_post( $o->attachment_id ) );
				$this->debug( 'Copied attachment %s to %s', $attachment->post->ID, $o->attachment_id );
			}

			// Maybe modify the post content with new URLs to attachments and what not.
			$unmodified_post = (object)$bcd->new_post;
			$modified_post = clone( $unmodified_post );

			// If there were any image attachments copied...
			if ( count( $bcd->copied_attachments() ) > 0 )
			{
				$this->debug( '%s attachments were copied.', count( $bcd->copied_attachments() ) );
				// Update the URLs in the post to point to the new images.
				$new_upload_dir = wp_upload_dir();
				foreach( $bcd->copied_attachments() as $a )
				{
					$count = 0;
					// Replace the GUID with the new one.
					$modified_post->post_content = str_replace( $a->old->guid, $a->new->guid, $modified_post->post_content, $count );
					if ( $count > 0 )
						$this->debug( 'Modified attachment guid from %s to %s: %s times', $a->old->guid, $a->new->guid, $count );
					// And replace the IDs present in any image captions.
					$modified_post->post_content = str_replace( 'id="attachment_' . $a->old->id . '"', 'id="attachment_' . $a->new->id . '"', $modified_post->post_content, $count );
					if ( $count > 0 )
						$this->debug( 'Modified attachment link from %s to %s: %s times', $a->old->id, $a->new->id, $count );
				}
			}
			else
				$this->debug( 'No attachments were copied.' );

			// If there are galleries...
			$this->debug( '%s galleries are to be handled.', count( $bcd->galleries ) );
			foreach( $bcd->galleries as $gallery )
			{
				// Work on a copy.
				$gallery = clone( $gallery );
				$new_ids = [];

				// Go through all the attachment IDs
				foreach( $gallery->ids_array as $id )
				{
					$new_id = $bcd->copied_attachments()->get( $id );
					if ( $new_id )
						$new_ids[] = $new_id;
				}
				$new_ids_string = implode( ',', $new_ids );
				$new_shortcode = $gallery->old_shortcode;
				$new_shortcode = str_replace( $gallery->ids_string, $new_ids_string, $gallery->old_shortcode );
				$this->debug( 'Replacing gallery shortcode %s with %s.', $gallery->old_shortcode, $new_shortcode );
				$modified_post->post_content = str_replace( $gallery->old_shortcode, $new_shortcode, $modified_post->post_content );
			}

			$bcd->modified_post = $modified_post;
			$action = new actions\broadcasting_modify_post;
			$action->broadcasting_data = $bcd;
			$action->execute();

			$this->debug( 'Checking for post modifications.' );
			$post_modified = false;
			foreach( (array)$unmodified_post as $key => $value )
				if ( $unmodified_post->$key != $modified_post->$key )
				{
					$this->debug( 'Post has been modified because of %s.', $key );
					$post_modified = true;
				}

			// Maybe updating the post is not necessary.
			if ( $post_modified )
			{
				$this->debug( 'Modifying new post.' );
				wp_update_post( $modified_post );	// Or maybe it is.
			}
			else
				$this->debug( 'No need to modify the post.' );

			if ( $bcd->custom_fields )
			{
				$this->debug( 'Custom fields: Started.' );

				$child_fields = $bcd->custom_fields()->child_fields();
				$child_fields->load();

				// new_post_old_custom_fields is obsolete. Remove the first part in a few versions.
				$bcd->new_post_old_custom_fields = $child_fields;

				$this->debug( 'Custom fields of the child post: %s', $child_fields->to_array() );

				$protected_field = [];

				foreach( $child_fields as $key => $value )
				{
					// Do we delete this custom field?
					$delete = true;

					// For the protectlist to work the custom field has to already exist on the child.
					if ( $bcd->custom_fields()->protectlist_has( $key ) )
					{
						if ( ! $child_fields->has( $key ) )
							continue;
						if ( ! $bcd->custom_fields()->has( $key ) )
							continue;
						$protected_field[ $key ] = true;
						$delete = false;
					}

					if ( $delete )
					{
						$this->debug( 'Custom fields: Deleting custom field %s.', $key );
						$child_fields->delete_meta( $key );
					}
					else
						$this->debug( 'Custom fields: Keeping custom field %s.', $key );
				}

				foreach( $bcd->custom_fields() as $meta_key => $meta_value )
				{
					// Protected = ignore.
					if ( isset( $protected_field[ $meta_key ] ) )
						continue;

					if ( is_array( $meta_value ) )
					{
						foreach( $meta_value as $single_meta_value )
						{
							$single_meta_value = maybe_unserialize( $single_meta_value );
							$this->debug( 'Custom fields: Adding array value %s', $meta_key );
							$child_fields->add_meta( $meta_key, $single_meta_value );
						}
					}
					else
					{
						$meta_value = maybe_unserialize( $meta_value );
						$this->debug( 'Custom fields: Adding value %s', $meta_key );
						$child_fields->add_meta( $meta_key, $meta_value );
					}
				}

				// Attached files are custom fields... but special custom fields.
				if ( $bcd->has_thumbnail )
				{
					$o = clone( $bcd );
					$o->attachment_data = $bcd->attachment_data[ 'thumbnail' ];
					$o->attachment_id = $bcd->copied_attachments()->get( $o->attachment_data->post->ID );

					if ( $o->attachment_id > 0 )
					{
						$this->debug( 'Custom fields: Thumbnail already exists as another attachment. Using that.' );
					}
					else
					{
						$this->debug( 'Custom fields: Re-adding thumbnail.' );
						if ( $o->attachment_data->is_attached_to_parent() )
						{
							$this->debug( 'Assigning new parent ID (%s) to attachment %s.', $bcd->new_post( 'ID' ), $o->attachment_data->post->ID );
							$o->attachment_data->post->post_parent = $bcd->new_post( 'ID' );
						}
						else
						{
							$this->debug( 'Resetting post parent for attachment %s.', $o->attachment_data->post->ID );
							$o->attachment_data->post->post_parent = 0;
						}

						$this->debug( 'Custom fields: Maybe copying attachment.' );
						$this->maybe_copy_attachment( $o );
						$this->debug( 'Custom fields: Maybe copied attachment.' );

						$bcd->copied_attachments()->add( $o->attachment_data->post, get_post( $o->attachment_id ) );
					}

					if ( $o->attachment_id !== false )
					{
						$this->debug( 'Handling post thumbnail for post %s. Thumbnail ID is now %s', $bcd->new_post( 'ID' ), $o->attachment_id );
						update_post_meta( $bcd->new_post( 'ID' ), '_thumbnail_id', $o->attachment_id );
					}
				}
				$this->debug( 'Custom fields: Finished.' );
			}

			// Sticky behaviour
			$child_post_is_sticky = is_sticky( $bcd->new_post( 'ID' ) );
			$this->debug( 'Sticky status: %s', intval( $child_post_is_sticky ) );
			if ( $bcd->post_is_sticky && ! $child_post_is_sticky )
			{
				$this->debug( 'Sticking post.' );
				stick_post( $bcd->new_post( 'ID' ) );
			}
			if ( ! $bcd->post_is_sticky && $child_post_is_sticky )
			{
				$this->debug( 'Unsticking post.' );
				unstick_post( $bcd->new_post( 'ID' ) );
			}

			if ( $bcd->link )
			{
				$new_post_broadcast_data = $this->get_post_broadcast_data( $bcd->current_child_blog_id, $bcd->new_post( 'ID' ) );
				$new_post_broadcast_data->set_linked_parent( $bcd->parent_blog_id, $bcd->post->ID );
				$this->debug( 'Saving broadcast data of child: %s', $new_post_broadcast_data );
				$this->set_post_broadcast_data( $bcd->current_child_blog_id, $bcd->new_post( 'ID' ), $new_post_broadcast_data );

				// Save the parent also.
				$this->debug( 'Saving parent broadcast data: %s', $bcd->broadcast_data );
				$this->set_post_broadcast_data( $bcd->parent_blog_id, $bcd->post->ID, $bcd->broadcast_data );
			}

			$action = new actions\broadcasting_before_restore_current_blog;
			$action->broadcasting_data = $bcd;
			$action->execute();

			$child_blog->switch_from();
		}

		// For nested broadcasts. Just in case.
		restore_current_blog();

		$action = new actions\broadcasting_finished;
		$action->broadcasting_data = $bcd;
		$action->execute();

		// Finished broadcasting.
		array_pop( $this->broadcasting );

		if ( $this->debugging_to_browser() )
		{
			if ( ! $this->is_broadcasting() )
			{
				if ( isset( $bcd->stop_after_broadcast ) && ! $bcd->stop_after_broadcast )
				{
					$this->debug( 'Finished broadcasting.' );
				}
				else
				{
					$this->debug( 'Finished broadcasting. Now stopping Wordpress.' );
					exit;
				}
			}
			else
			{
				$this->debug( 'Still broadcasting.' );
			}
		}

		$this->load_language();

		return $bcd;
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

	public function save_post( $post_id )
	{
		// We must be on the source blog.
		if ( ms_is_switched() )
		{
			$this->debug( 'Blog is switched. Not broadcasting.' );
			return;
		}

		// Loop check.
		if ( $this->is_broadcasting() )
		{
			$this->debug( 'Already broadcasting.' );
			return;
		}

		// We must handle this post type.
		$post = get_post( $post_id );
		$action = new actions\get_post_types;
		$action->execute();
		if ( ! in_array( $post->post_type, $action->post_types ) )
		{
			$this->debug( 'We do not care about the %s post type.', $post->post_type );
			return;
		}

		// No post?
		if ( count( $_POST ) < 1 )
			return;

		// Is this post a child?
		$broadcast_data = $this->get_post_broadcast_data( get_current_blog_id(), $post_id );
		if ( $broadcast_data->get_linked_parent() !== false )
			return;

		// No permission.
		if ( ! static::user_has_roles( $this->get_site_option( 'role_broadcast' ) ) )
		{
			$this->debug( 'User does not have permission to use Broadcast.' );
			return;
		}

		// Save the user's last settings.
		if ( isset( $_POST[ 'broadcast' ] ) )
			$this->save_last_used_settings( $this->user_id(), $_POST[ 'broadcast' ] );

		$this->debug( 'We are currently on blog %s (%s).', get_bloginfo( 'blogname' ), get_current_blog_id() );

		$broadcasting_data = new broadcasting_data( [
			'parent_post_id' => $post_id,
		] );

		$this->debug( 'Preparing the broadcasting data.' );

		// This is to fetch the selected blogs from the meta box.
		$action = new actions\prepare_broadcasting_data;
		$action->broadcasting_data = $broadcasting_data;
		$action->execute();

		$this->debug( 'Prepared.' );

		if ( $broadcasting_data->has_blogs() )
			$this->filters( 'threewp_broadcast_broadcast_post', $broadcasting_data );
		else
		{
			$this->debug( 'No blogs are selected. Not broadcasting anything.' );
		}
	}

	/**
		@brief		Broadcasts a post.
		@param		broadcasting_data		$broadcasting_data		Object containing broadcasting instructions.
		@since		20130927
	**/
	public function threewp_broadcast_broadcast_post( $broadcasting_data )
	{
		if ( ! is_a( $broadcasting_data, '\\threewp_broadcast\\broadcasting_data' ) )
			return $broadcasting_data;
		return $this->broadcast_post( $broadcasting_data );
	}

	/**
		@brief		Modifies the broadcasting_data according to the users's input in the meta box (blogs) and the user's roles.
		@details	Only does any real good when parsing user input from a meta box, for example during normal editing or using Send To Many or UBS Post.
		@since		20131004
	**/
	public function threewp_broadcast_prepare_broadcasting_data( $action )
	{
		$bcd = $action->broadcasting_data;
		$allowed_post_status = [ 'pending', 'private', 'publish' ];

		if ( $bcd->post->post_status == 'draft' && static::user_has_roles( $this->get_site_option( 'role_broadcast_as_draft' ) ) )
			$allowed_post_status[] = 'draft';

		if ( $bcd->post->post_status == 'future' && static::user_has_roles( $this->get_site_option( 'role_broadcast_scheduled_posts' ) ) )
			$allowed_post_status[] = 'future';

		if ( ! in_array( $bcd->post->post_status, $allowed_post_status ) )
			return;

		$form = $bcd->meta_box_data->form;

		// Collect the list of blogs from the meta box.
		$blogs_input = $form->input( 'blogs' );
		foreach( $blogs_input->inputs() as $blog_input )
			if ( $blog_input->is_checked() )
			{
				$blog_id = $blog_input->get_name();
				$blog_id = str_replace( 'blogs_', '', $blog_id );
				$bcd->broadcast_to( $blog_id );
			}

		// Remove the parent blog
		$bcd->blogs->forget( $bcd->parent_blog_id );

		$bcd->custom_fields = $form->checkbox( 'custom_fields' )->get_post_value()
			&& ( is_super_admin() || static::user_has_roles( $this->get_site_option( 'role_custom_fields' ) ) );
		if ( $bcd->custom_fields )
			$bcd->custom_fields = (object)[];

		$bcd->link = $form->checkbox( 'link' )->get_post_value()
			&& ( is_super_admin() || static::user_has_roles( $this->get_site_option( 'role_link' ) ) );

		$bcd->taxonomies = $form->checkbox( 'taxonomies' )->get_post_value()
			&& ( is_super_admin() || static::user_has_roles( $this->get_site_option( 'role_taxonomies' ) ) );

		// Handle the unchecking of the linked children.
		// We could do this earlier, when foreaching the blogs_input, but it makes the code look uglier. Therefore we keep it separate.
		$unchecked_child_blogs_action = $form->checkbox( 'unchecked_child_blogs' )->get_post_value();
		// The user wanted something done.
		if ( $unchecked_child_blogs_action != '' )
		{
			// The interesting blogs are those that are LINKED and NOT CHECKED.

			$linked_blogs = [];
			$linked_children = $bcd->meta_box_data->broadcast_data->get_linked_children();
			foreach( $linked_children as $blog_id => $ignore )
				$linked_blogs []= $blog_id;

			$unchecked_blogs = [];
			foreach( $blogs_input->inputs() as $blog_input )
				if ( ! $blog_input->is_checked() )
				{
					$blog_id = $blog_input->get_name();
					$blog_id = str_replace( 'blogs_', '', $blog_id );
					$unchecked_blogs []= $blog_id;
				}

			$blogs_to_modify = array_intersect( $linked_blogs, $unchecked_blogs );

			foreach( $blogs_to_modify as $blog_id )
			{
				switch_to_blog( $blog_id );

				$post_id = $bcd->meta_box_data->broadcast_data->get_linked_child_on_this_blog();
				$unlink = false;

				switch( $unchecked_child_blogs_action )
				{
					case 'delete':
						$this->debug( 'Deleting child post %s', $post_id );
						wp_delete_post( $post_id, true );
						$unlink = true;
						break;

					case 'trash':
						$this->debug( 'Trashing child post %s', $post_id );
						wp_delete_post( $post_id );
						break;

					case 'unlink':
						$unlink = true;
						break;
					break;
				}

				if ( $unlink )
				{
					$this->debug( 'Unlinking child post %s', $post_id );
					$bcd->meta_box_data->broadcast_data->remove_linked_child( $blog_id );
					$this->delete_post_broadcast_data( get_current_blog_id(), $post_id );
				}

				restore_current_blog();

				$this->debug( 'Resaving braodcast data.' );
				$this->set_post_broadcast_data( get_current_blog_id(), $bcd->post->ID, $bcd->meta_box_data->broadcast_data );
			}
		}
	}

}