<?php

namespace threewp_broadcast\traits;

use threewp_broadcast\actions;

trait attachments
{
	/**
		@brief		Creates a new attachment.
		@details

		The $o object is an extension of Broadcasting_Data and must contain:
		- @i attachment_data An attachment_data object containing the attachment info.

		@param		object		$o		Options.
		@return		@i int The attachment's new post ID.
		@since		20130530
		@version	20131003
	*/
	public function threewp_broadcast_copy_attachment( $action )
	{
		if ( $action->is_finished() )
			return;

		$attachment_data = $action->attachment_data;
		$is_url = false;
		$source = $attachment_data->filename_path;

		if ( file_exists( $source ) )
		{
			$this->debug( 'Copy attachment: File "%s" is on local file-system', $source );
		}
		else
		{
			if ( $attachment_data->is_url() && curl_init( $attachment_data->filename_path ) )
			{
				$is_url = true;
				$this->debug( 'Copy attachment: File "%s" is an external URL', $source );
			}
			else
			{
				// File does not exist.
				$this->debug( 'Copy attachment: File "%s" does not exist!', $source );
				return false;
			}
		}

		// Copy the file to the blog's upload directory
		$upload_dir = wp_upload_dir();

		$target = $upload_dir[ 'path' ] . '/' . $attachment_data->filename_base;
		if( ! $attachment_data->is_url() )
		{
			// Only copy the file if it is local.
			$this->debug( 'Copy attachment: Copying from %s to %s', $source, $target );
			copy( $source, $target );
			$this->debug( 'Copy attachment: File sizes: %s %s ; %s %s', $source, filesize( $source ), $target, filesize( $target ) );
			$target_path = $target;
		}
		else
		{
			// PW 24/04/2015 - for files with a remote source we will just create a reference in the media manager, no need to download.
			$target = $source;
			// PW 30/04/2015 - not accurate but required for wp_generate_attachment_metadata
			$target_path = $upload_dir[ 'path' ] . '/' . $attachment_data->filename_base;
		}

		// And now create the attachment stuff.
		// This is taken almost directly from http://codex.wordpress.org/Function_Reference/wp_insert_attachment
		$this->debug( 'Copy attachment: Checking filetype.' );
		$wp_filetype = wp_check_filetype( $target, null );
		$attachment = [
			'guid' => $upload_dir[ 'url' ] . '/' . $attachment_data->filename_base,
			'menu_order' => $attachment_data->post->menu_order,
			'post_author' => $attachment_data->post->post_author,
			'post_excerpt' => $attachment_data->post->post_excerpt,
			'post_mime_type' => $wp_filetype[ 'type' ],
			'post_name' => $attachment_data->post->post_name,
			'post_title' => $attachment_data->post->post_title,
			'post_content' => $attachment_data->post->post_content,
			'post_status' => 'inherit',
		];
		$this->debug( 'Copy attachment: Inserting attachment: %s', $attachment );
		$attachment_id = wp_insert_attachment( $attachment, $target, $attachment_data->post->post_parent );
		$action->set_attachment_id( $attachment_id );

		// Now to maybe handle the metadata.
		if ( ! $is_url )
		{
			if ( $attachment_data->file_metadata )
			{
				$this->debug( 'Copy attachment: Handling metadata.' );
				// 1. Create new metadata for this attachment.
				$this->debug( 'Copy attachment: Requiring image.php.' );
				require_once( ABSPATH . "wp-admin" . '/includes/image.php' );
				$this->debug( 'Copy attachment: Generating metadata for %s.', $target );
				$attach_data = wp_generate_attachment_metadata( $action->attachment_id, $target_path );
				$this->debug( 'Copy attachment: Metadata is %s', $attach_data );

				// 2. Write the old metadata first.

				foreach( $attachment_data->post_custom as $key => $value )
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
					update_post_meta( $action->attachment_id, $key, $value );
				}

				// 3. Overwrite the metadata that needs to be overwritten with fresh data.
				$this->debug( 'Copy attachment: Updating metadata.' );
				wp_update_attachment_metadata( $action->attachment_id,  $attach_data );
			}
		}
		else
		{
			// Copy all of the metadata straight off.
			$this->debug( 'Copy attachment: Directly copying all metadata.' );
			foreach( $attachment_data->post_custom as $key => $value )
			{
				$value = reset( $value );
				$value = maybe_unserialize( $value );
				update_post_meta( $action->attachment_id, $key, $value );
			}
		}

		$this->debug( 'Copy attachment: File sizes again: %s %s ; %s %s', $source, filesize( $source ), $target, filesize( $target ) );
		$action->finish();
	}

	/**
		@brief		Will only copy the attachment if it doesn't already exist on the target blog.
		@details	The return value is an object, with the most important property being ->attachment_id.

		@param		object		$options		See the parameter for copy_attachment.
		@return		object		$options		The attachment_id property should be > 0.
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
		$copy_attachment_action = new actions\copy_attachment();
		$copy_attachment_action->attachment_data = $attachment_data;
		$copy_attachment_action->execute();
		$options->attachment_id = $copy_attachment_action->attachment_id;
	}
}
