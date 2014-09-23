<?php

namespace plainview\sdk\wordpress\updater;

if( ! class_exists( 'EDD_SL_Plugin_Updater' ) )
	require_once( dirname( __FILE__ ) . '/EDD_SL_Plugin_Updater.php' );

/**
	@brief		Easy Digital Downloads updater support.
	@details

	In your class:

		use \plainview\sdk\wordpress\updater\edd;

	To your site options, add:

		'edd_updater_license_key' => '',
		'edd_updater_license_status' => false,

	To the constructor add:

		$this->edd_init();

	Override the following methods:

		edd_get_item_name()
		edd_get_url()

	The license should be in an admin tab:

		$tabs->tab( 'license' )
			->callback_this( 'edd_admin_license_tab' )
			->name_( 'License' );

	@since		2014-09-15 20:40:25
**/
trait edd
{
	/**
		@brief		The license administration tab.
		@since		2014-09-15 21:02:25
	**/
	public function edd_admin_license_tab()
	{
		$form = $this->form2();
		$r = '';
		$status = $this->edd_get_cached_license_status();

		switch( $status->license )
		{
			case 'site_inactive':
				$table = $this->edd_get_status_table( $status );
				$license_key = $form->hidden_input( 'license_key' )
					->value( $this->get_site_option( 'edd_updater_license_key' ) );
				$activate_button = $form->secondary_button( 'activate' )
					->value_( 'Activate license' );
				$delete_button = $form->secondary_button( 'delete' )
					->value_( 'Delete license data' );
				break;
			case 'valid':
				$table = $this->edd_get_status_table( $status );
				$deactivate_button = $form->secondary_button( 'deactivate' )
					->value_ ( 'Deactivate license' );
				break;
			default:
				$form->markup( 'status' )
					->p( 'Your license for this plugin is not activated. You will not receive any automatic updates. Enter the license key in the text field below and press the activation button.' );

				$license_key = $form->text( 'license_key' )
					->description( 'The key that you recieved in the confirmation email after your purchase.' )
					->label_( 'License key' )
					->minlength( 32 )
					->maxlength( 32 )
					->required()
					->size( 32 )
					->value( $this->get_site_option( 'edd_updater_license_key' ) );

				$activate_button = $form->secondary_button( 'activate' )
					->value_ ( 'Activate license' );
				break;
		}

		$refresh_button = $form->secondary_button( 'refresh' )
			->value_ ( 'Refresh license status' );

		if ( $form->is_posting() )
		{
			$form->post();
			$form->use_post_values();

			if ( ! $form->validates() )
			{
				foreach( $form->get_validation_errors() as $error )
					$this->error( $error );
			}
			else
			{
				// Try to activate the license.
				if ( isset( $activate_button ) && $activate_button->pressed() )
				{
					$key = $license_key->get_filtered_post_value();
					$this->update_site_option( 'edd_updater_license_key', $key );
					$this->edd_activate_license();
					if ( $this->get_site_option( 'edd_updater_license_status' ) == 'valid' )
						$this->message( 'The license has been activated! Automatic plugin updates are now activated.' );
					else
						$this->error( 'The license could not be activated. Please try again later or contact the plugin author.' );
				}

				// Try to deactivate the license.
				if ( isset( $deactivate_button ) && $deactivate_button->pressed() )
				{
					$this->edd_deactivate_license();
					if ( $this->get_site_option( 'edd_updater_license_status' ) == 'deactivated' )
						$this->message( 'The license has been deactivated! Automatic plugin updates are now deactived.' );
					else
						$this->error( 'The license could not be deactivated. Please try again later or contact the plugin author.' );
				}

				if ( isset( $delete_button ) && $delete_button->pressed() )
				{
					$this->edd_delete_license();
					$this->edd_clear_cached_license_status();
					$this->message( 'The license data has been deleted.' );
				}

				if ( $refresh_button->pressed() )
				{
					$this->edd_clear_cached_license_status();
					$this->message( 'The license data has been refreshed!' );
				}

				$_POST = [];
				return $this->edd_admin_license_tab();
			}
		}

		if ( isset( $table ) )
			$r .= $table;

		$r .= $form->open_tag();
		$r .= $form->display_form_table();
		$r .= $form->close_tag();

		echo $r;
	}

	/**
		@brief		Activate this license.
		@throws		Exception if the license was not able to be activated.
		@since		2014-09-15 21:14:52
	**/
	public function edd_activate_license()
	{
		$response = $this->edd_remote_get( [ 'edd_action' => 'activate_license' ] );

		// decode the license data
		$license_data = json_decode( wp_remote_retrieve_body( $response ) );

		// $license_data->license will be either "valid" or "invalid"
		$this->update_site_option( 'edd_updater_license_status', $license_data->license );

		// Save the status in the transient.
		$this->edd_set_cached_license_status( $license_data );
	}

	/**
		@brief		Clear the license status transient.
		@since		2014-09-15 22:52:28
	**/
	public function edd_clear_cached_license_status()
	{
		$name = $this->edd_get_cached_license_status_transient_name();
		delete_site_transient( $name );
	}

	/**
		@brief		Deactivate this license.
		@throws		Exception if the license was not able to be deactivated.
		@since		2014-09-15 21:14:52
	**/
	public function edd_deactivate_license()
	{
		$response = $this->edd_remote_get( [ 'edd_action' => 'deactivate_license' ] );

		// decode the license data
		$license_data = json_decode( wp_remote_retrieve_body( $response ) );

		if ( $license_data->license == 'deactived' )
			$status = 'invalid';
		else
			$status = 'valid';

		// $license_data->license will be either "valid" or "invalid"
		$this->update_site_option( 'edd_updater_license_status', $license_data->license );

		// Save the status in the transient.
		$this->edd_set_cached_license_status( $license_data );
	}

	/**
		@brief		Delete the license options.
		@since		2014-09-17 07:42:38
	**/
	public function edd_delete_license()
	{
		$this->delete_site_option( 'edd_updater_license_key' );
		$this->delete_site_option( 'edd_updater_license_status' );
	}

	/**
		@brief		Initialize the EDD updater.
		@since		2014-09-15 20:53:54
	**/
	public function edd_init()
	{
		$status = $this->get_site_option( 'edd_updater_license_status', 'invalid' );
		if ( $status != 'valid' )
			return;

		$edd_updater = new \EDD_SL_Plugin_Updater
		(
			$this->edd_get_url(),
			$this->paths[ '__FILE__' ],
			[
				'author' => $this->edd_get_author(),
				'license' => $this->edd_get_license_key(),
				'version' => $this->edd_get_plugin_version(),
				'item_name' => $this->edd_get_item_name(),
			]
		);
	}

	/**
		@brief		Retrieve the name of the author of this plugin.
		@since		2014-09-15 21:14:03
	**/
	public function edd_get_author()
	{
		return 'Plugin Author';
	}

	/**
		@brief		Retrieves the license status from the cache.
		@since		2014-09-15 21:47:50
	**/
	public function edd_get_cached_license_status()
	{
		$name = $this->edd_get_cached_license_status_transient_name();
		$status = get_site_transient( $name );
		if ( $status === false )
		{
			$status = $this->edd_get_license_status();
			$this->edd_set_cached_license_status( $status );
		}
		return $status;
	}

	/**
		@brief		The name of the transient for the license status.
		@since		2014-09-15 22:51:33
	**/
	public function edd_get_cached_license_status_transient_name()
	{
		$name = get_class( $this );
		$name = preg_replace( '/.*\\\\/', '', $name );
		$name = $name . 'edd_license_status';
		return $name;
	}

	/**
		@brief		Return the name of the product.
		@details	ThreeWP Broadcast Premium Pack
		@since		2014-09-17 07:46:41
	**/
	public function edd_get_item_name()
	{
		throw new \Exception( 'Override edd_get_item_name' );
	}

	/**
		@brief		Retrieves the license key.
		@since		2014-09-18 21:09:20
	**/
	public function edd_get_license_key()
	{
		return $this->get_site_option( 'edd_updater_license_key' );
	}

	/**
		@brief		Return the status of the license.
		@return		'valid' or 'invalid'.
		@since		2014-09-15 21:20:53
	**/
	public function edd_get_license_status()
	{
		$response = $this->edd_remote_get( [ 'edd_action' => 'check_license' ] );
		$license_data = json_decode( wp_remote_retrieve_body( $response ) );
		return $license_data;
	}

	/**
		@brief		Return the version of the plugin.
		@since		2014-09-18 21:10:01
	**/
	public function edd_get_plugin_version()
	{
		return $this->plugin_version;
	}

	/**
		@brief		Return a table containing the license status.
		@since		2014-09-16 22:53:39
	**/
	public function edd_get_status_table( $status )
	{
		$inactive = false;
		$valid = false;
		$rows = [];

		$table = $this->table();
		$table->caption()->text( 'Information about your license' );

		switch( $status->license )
		{
			case 'site_inactive':
				$inactive = true;
				$rows[ 'Status' ] = sprintf( 'Valid but inactive. Expires %s', $status->expires );
				break;
			case 'valid':
				$valid = true;
				$rows[ 'Status' ] = sprintf( 'Valid until %s' , $status->expires );
				break;
			default:
				$rows[ 'Status' ] = 'No license';
				break;
		}

		if ( $valid || $inactive )
		{
			$rows[ 'Key' ] = $this->get_site_option( 'edd_updater_license_key' );

			$rows[ 'Purchaser' ] = sprintf( '%s, <a href="mailto:%s">%s</a>', $status->customer_name, $status->customer_email, $status->customer_email );
			$rows[ 'Payment ID' ] = $status->payment_id;

			if ( $valid )
				$rows[ 'Activations left' ] = $status->activations_left;
		}

		foreach( $rows as $key => $value )
		{
			$row = $table->body()->row();
			$row->th()->text( $key );
			$row->td()->text( $value );
		}

		return $table;
	}

	/**
		@brief		Return the update url.
		@since		2014-09-17 07:46:41
	**/
	public function edd_get_url()
	{
		throw new \Exception( 'Override edd_get_url' );
	}

	/**
		@brief		Prepare a license reponse.
		@since		2014-09-15 21:29:01
	**/
	public function edd_remote_get( $params )
	{
		$license_key = $this->get_site_option( 'edd_updater_license_key' );
		$product = $this->edd_get_item_name();

		$api_params = array_merge( [
			'edd_action'	=> 'check_license',
			'license' 		=> $license_key,
			'item_name'		=> urlencode( $product ),
			'url'			=> network_home_url()
		], $params );

		// Call the custom API.
		$url = add_query_arg(
			$api_params,
			$this->edd_get_url()
		);
		$response = wp_remote_get( $url, [ 'timeout' => 15, 'sslverify' => false ] );

		if ( is_wp_error( $response ) )
			throw new \Exception( 'Invalid response from the updater service.' );

		return $response;
	}

	/**
		@brief		Save the license status to the transient.
		@since		2014-09-16 22:35:24
	**/
	public function edd_set_cached_license_status( $status )
	{
		$name = $this->edd_get_cached_license_status_transient_name();
		set_site_transient( $name, $status, 60 * 5 );
	}
}