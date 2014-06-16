<?php

namespace plainview\sdk\wordpress\plugin_pack;

/**
	@brief		Handles autoloading of a plugin pack.
	@since		2014-05-07 22:53:58
**/
abstract class plugin_pack
	extends \plainview\sdk\wordpress\base
{
	/**
		@brief		The plugins object.
		@since		2014-05-09 11:17:46
	**/
	public $plugins;

	protected $site_options = array(
		'enabled_plugins' => [],				// Array of autoloaded plugins.
	);

	/**
		@brief		Constructor.
		@since		2014-05-07 23:11:28
	**/
	public function _construct()
	{
		$this->plugins = new plugins( $this );
		$this->plugins->populate( $this->get_enabled_plugins() );
		$this->plugins->load();
		$this->plugins->maybe_save();
	}

	/**
		@brief		Load the list of enabled plugins (classnames).
		@since		2014-05-09 10:38:31
	**/
	public function get_enabled_plugins()
	{
		return $this->get_site_option( 'enabled_plugins', [] );
	}

	/**
		@brief		Return an array of all plugin files.
		@details	This method must be implemented by the subclass because plugin_pack doesn't know where you keep your plugin files.
		@since		2014-05-08 15:53:31
	**/
	abstract public function get_plugin_classes();

	/**
		@brief		Clean up the plugin class name.
		@since		2014-05-08 16:20:46
	**/
	public function get_plugin_classname( $classname )
	{
		$classname = preg_replace( '/.*\\\\/', '', $classname );
		$classname = preg_replace( '/_/', ' ', $classname );
		return $classname;
	}

	/**
		@brief		Return the plugins table.
		@since		2014-05-08 15:48:39
	**/
	public function get_plugins_table()
	{
		$form = $this->form2();
		$r = '';
		$table = $this->table();

		$plugins = new plugins( $this );
		// Fill the plugins with all of the available classes
		$plugins->populate( static::get_plugin_classes() );

		// Plugins class for the coloring.
		$table = $this->table()->css_class( 'plugin_pack plugins' );
		$row = $table->head()->row();
		$table->bulk_actions()
			->form( $form )
			->add( $this->_( 'Activate plugin' ), 'activate_plugin' )
			->add( $this->_( 'Deactivate plugin' ), 'deactivate_plugin' )
			->add( $this->_( 'Uninstall plugin' ), 'uninstall_plugin' )
			->cb( $row );
		$row->th()->text_( 'Plugin' );
		$row->th()->text_( 'Description' );

		if ( $form->is_posting() )
		{
			if ( $table->bulk_actions()->pressed() )
			{
				$ids = $table->bulk_actions()->get_rows();

				$action = $table->bulk_actions()->get_action();
				foreach( $plugins->from_ids( $ids ) as $plugin )
				{
					$classname = $plugin->get_classname();
					$this->plugins->populate( $classname );
					$new_plugin = $this->plugins->get( $classname );
					switch( $action )
					{
						case 'activate_plugin':
							$new_plugin->activate();
							break;
						case 'deactivate_plugin':
							$new_plugin->deactivate();
							$this->plugins->forget( $classname );
							break;
						case 'uninstall_plugin':
							$new_plugin->deactivate();
							$new_plugin->uninstall();
							$this->plugins->forget( $classname );
							break;
					}
					$this->plugins->save();
				}
			}
		}

		foreach( $plugins as $plugin )
		{
			$row = $table->body()->row();
			$table->bulk_actions()->cb( $row, $plugin->get_id() );

			$td = $row->td();
			$td->text( $plugin->get_name() );
			$td->css_class( 'plugin-title' );

			if ( $this->plugins->has( $plugin->get_classname() ) )
				$row->css_class( 'active' );
			else
				$row->css_class( 'inactive' );

			$text = $plugin->get_brief_description();
			$row->td()->text( $text );
		}

		$r .= $form->open_tag();
		$r .= $table;
		$r .= $form->close_tag();

		return $r;
	}

	/**
		@brief		Saves the list of enabled plugins.
		@since		2014-05-09 10:37:46
	**/
	public function set_enabled_plugins( $enabled_plugins )
	{
		$this->update_site_option( 'enabled_plugins', $enabled_plugins );
	}
}
