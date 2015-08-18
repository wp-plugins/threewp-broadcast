<?php

namespace plainview\sdk_broadcast\wordpress\plugin_pack;

/**
	@brief		Handles autoloading of a plugin pack.
	@since		2014-05-07 22:53:58
**/
abstract class plugin_pack
	extends \plainview\sdk_broadcast\wordpress\base
{
	/**
		@brief		The plugins object.
		@since		2014-05-09 11:17:46
	**/
	public $plugins;

	/**
		@brief		Constructor.
		@since		2014-05-07 23:11:28
	**/
	public function _construct()
	{
		$this->plugins();
	}

	/**
		@brief		Activate all of the already enabled plugins.
		@since		2014-09-27 19:09:22
	**/
	public function activate()
	{
		$this->plugins()->activate();
	}

	public function deactivate()
	{
		$this->plugins()->deactivate();
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
		@brief		Return an array of all plugin classnames.
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
		$r = '<style>
			table.plugin_pack th.plugin_group
			{
				background-color: #ddd;
				font-weight: bold;
			}
		</style>';
		$table = $this->table();

		$plugins = new plugins( $this );

		// Fill the plugins with all of the available classes
		$action = new actions\get_plugin_classes();
		// The prefix is the basename of the plugin pack class, with an underscore.
		// Not \threewp_broadcast\premium_pack\ThreeWP_Broadcast_Premium_Pack but ThreeWP_Broadcast_Premium_Pack_
		$action->__prefix = preg_replace( '/.*\\\\/', '', get_called_class() ) . '_';
		$action->add( static::get_plugin_classes() );
		$action->execute();
		$plugins->populate( $action->classes );

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
				$message = $this->_( 'No action selected.' );
				foreach( $plugins->from_ids( $ids ) as $plugin )
				{
					$classname = $plugin->get_classname();
					$this->plugins()->populate( $classname );
					$new_plugin = $this->plugins()->get( $classname )->plugin();
					switch( $action )
					{
						case 'activate_plugin':
							$new_plugin->activate_internal();
							$message = $this->_( 'The selected plugin(s) have been activated.' );
							break;
						case 'deactivate_plugin':
							$new_plugin->deactivate_internal();
							$this->plugins()->forget( $classname );
							$message = $this->_( 'The selected plugin(s) have been deactivated.' );
							break;
						case 'uninstall_plugin':
							$new_plugin->deactivate_internal();
							$new_plugin->uninstall_internal();
							$this->plugins()->forget( $classname );
							$message = $this->_( 'The selected plugin(s) have been uninstalled.' );
							break;
					}
					$this->plugins()->save();
				}
				$this->message( $message );
			}
		}

		$old_group = '';
		foreach( $plugins->by_groups() as $group => $plugins )
			foreach( $plugins as $plugin )
			{
				$group = $plugin->get_comment( 'plugin_group' );

				if ( $old_group != $group )
				{
					$old_group = $group;
					$row = $table->body()->row();
					$row->th()->css_class( 'plugin_group' )->colspan( 3 )->text( $group );
				}

				$row = $table->body()->row();
				$cb = $table->bulk_actions()->cb( $row, $plugin->get_id() );

				$td = $row->td();

				// Assemble a label.
				$label = new \plainview\sdk_broadcast\html\div();
				$label->tag = 'label';
				$label->set_attribute( 'for', $cb->get_id() );
				$label->content = $plugin->get_name();

				$td->text( $label );
				$td->css_class( 'plugin-title' );

				if ( $this->plugins()->has( $plugin->get_classname() ) )
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
		@brief		Return the plugins object.
		@since		2014-09-28 14:03:06
	**/
	public function plugins()
	{
		if ( isset( $this->__plugins ) )
			return $this->__plugins;
		$this->__plugins = new plugins( $this );
		$this->__plugins->populate( $this->get_enabled_plugins() );
		$this->__plugins->load();
		$this->__plugins->maybe_save();
		return $this->plugins();
	}

	/**
		@brief		Saves the list of enabled plugins.
		@since		2014-05-09 10:37:46
	**/
	public function set_enabled_plugins( $enabled_plugins )
	{
		$this->update_site_option( 'enabled_plugins', $enabled_plugins );
	}

	public function site_options()
	{
		return array_merge( [
			'enabled_plugins' => [],
		], parent::site_options() );
	}
}
