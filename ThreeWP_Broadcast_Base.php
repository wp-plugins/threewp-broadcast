<?php
/**
	Provides a simple framework with common Wordpress functions.
	
	@par	Changelog
	
	- 2011-08-03			tab functions can now be arrays (class, method)
	- 2011-08-02			array_to_object
	- 2011-07-28			activate, decactivate and uninstall are public.
	- 2011-07-19			Documentation added.
	- 2011-05-12			displayMessage now uses now() instead of date.
	- 2011-04-30			Uses ThreeWP_Form instead of edwardForm.
	- 2011-04-29	09:19	site options are registered even when using single Wordpress.
	- 2011-01-25	13:14	load_language assumes filename as domain.
	- 2011-01-25	13:14	loadLanguages -> load_language.
	- 2011-09-19	12:43	No more need to register activation or deactivation hooks.
	- 2011-09-22	14:29	+roles_as_options
	- 2011-09-26	12:05	_() method made public. 
	- 2011-09-29	21:16	role_at_least checks that there is a user logged in at all.
	- 2011-10-08	07:34	Uses SD_Form instead of ThreeWP_Form (name change).
	- 2011-10-17	09:34	paths also includes __FILE__.
	- 2011-10-22	19:16	get_site_option and get_local_option have default parameters.
	- 2011-10-28	14:00	URLMake removed. tabs() now uses Wordpress' add_query_arg and remove_query_arg.
	- 2011-11-04	09:42	now() is public
	- 2011-11-07	16:11	new: rmdir().
	
	@brief		Base class for the SD series of Wordpress plugins.
	@author		Edward Plainview	edward.plainview@sverigedemokraterna.se
*/
class ThreeWP_Broadcast_Base
{
	/**
		Stores whether this blog is a network blog.
		@var	$is_network
	**/
	protected $is_network;
	
	/**
		Contains the paths to the plugin and other places of interest.
		
		The keys in the array are:
		
		name<br />
		filename<br />
		filename_from_plugin_directory<br />
		path_from_plugin_directory<br />
		path_from_base_directory<br /> 
		url<br />

		@var	$paths
	**/
	protected $paths = array();
	
	/**
		Array of options => default_values that this plugin stores sitewide.
		
		@var	$site_options
	**/ 
	protected $site_options = array();

	/**
		Array of options => default_values that this plugin stores locally.
		@var	$local_options
	**/ 
	protected $local_options = array();

	/**
		Array of options => default_values that this plugin stores locally or globally.
		@var	$options
		@deprecated 
	**/ 
	protected $options = array();

	/**
		Text domain of .PO translation.
		
		If left unset will be set to the base filename minus the .php
		
		@var	$language_domain
	**/ 
	protected $language_domain = ''; 

	/**
		Links to Wordpress' database object.
		@var	$wpdb
	**/
	protected $wpdb;
	
	/**
		The list of the standard user roles in Wordpress.
		
		First an array of role_name => array
		
		And then each role is an array of name => role_name and current_user_can => capability.

		@var	$roles
	**/
	protected $roles = array(
		'administrator' => array(
			'name' => 'administrator',
			'current_user_can' => 'manage_options',
		),
		'editor' => array(
			'name' => 'editor',
			'current_user_can' => 'manage_links',
		),
		'author' => array(
			'name' => 'author',
			'current_user_can' => 'publish_posts',
		),
		'contributor' => array(
			'name' => 'contributor',
			'current_user_can' => 'edit_posts',
		),
		'subscriber' => array(
			'name' => 'subscriber',
			'current_user_can' => 'read',
		),
	);
	
	/**
		Construct the class.
		
		@param		$filename		The full path of the parent class.
	**/
	public function __construct($filename)
	{
		global $wpdb;
		$this->wpdb = $wpdb;
		$this->is_network = MULTISITE;

		$this->paths = array(
			'__FILE__' => $filename,
			'name' => get_class($this),
			'filename' => basename($filename),
			'filename_from_plugin_directory' => basename(dirname($filename)) . '/' . basename($filename),
			'path_from_plugin_directory' => basename(dirname($filename)),
			'path_from_base_directory' => PLUGINDIR . '/' . basename(dirname($filename)),
			'url' => WP_PLUGIN_URL . '/' . basename(dirname($filename)),
		);
		
		register_activation_hook( $this->paths['filename_from_plugin_directory'],	array( $this, 'activate') );
		register_deactivation_hook( $this->paths['filename_from_plugin_directory'],	array( $this, 'deactivate') );
		
		add_action( 'admin_init', array(&$this, 'admin_init') );
	}
	
	/**
		Overridable activation function.
		
		It's here in case plugins need a common activation method in the future.
	**/
	public function activate()
	{
		$this->register_options();
	}
	
	/**
		Overridable method to deactive the plugin.
	**/
	public function deactivate()
	{
	}
	
	/**
		Deactivates the plugin.
	**/
	protected function deactivate_me()
	{
		deactivate_plugins(array(
			$this->paths['filename_from_plugin_directory']
		));
	}
	
	/**
		Overridable uninstall method.
	**/
	public function uninstall()
	{
		$this->deregister_options();
	}
	
	/**
		Filter for admin_init. 
	**/
	public function admin_init()
	{
		$class_name = get_class($this);
		if ( isset($_POST[ $class_name ]['uninstall']) )
		{
			if ( isset($_POST[ $class_name ]['sure']) )
			{
				$this->uninstall();
				$this->deactivate_me();
				if ($this->is_network)
					wp_redirect( 'ms-admin.php' );
				else
					wp_redirect( 'index.php' );
				exit;
			}
		}
	}

	/**
		Shows the uninstall form.
		
		Form is currently only available in English.
	**/
	protected function admin_uninstall()
	{
		$form = $this->form();
		
		if (isset($_POST[ get_class($this) ]['uninstall']))
			if (!isset($_POST['sure']))
				$this->error('You have to check the checkbox in order to uninstall the plugin.');
		
		$nameprefix = '['.get_class($this).']';
		$inputs = array(
			'sure' => array(
				'name' => 'sure',
				'nameprefix' => $nameprefix,
				'type' => 'checkbox',
				'label' => "Yes, I'm sure I want to remove all the plugin tables and settings.",
			),
			'uninstall' => array(
				'name' => 'uninstall',
				'nameprefix' => $nameprefix,
				'type' => 'submit',
				'css_class' => 'button-primary',
				'value' => 'Uninstall plugin',
			),
		);
		
		echo '
			'.$form->start().'
			<p>
				This page will remove all the plugin tables and settings from the database and then deactivate the plugin.
			</p>

			<p>
				'.$form->make_input($inputs['sure']).' '.$form->make_label($inputs['sure']).'
			</p>

			<p>
				'.$form->make_input($inputs['uninstall']).'
			</p>
			'.$form->stop().'
		';
	}
	
	/**
		Loads this plugin's language files.
		
		Reads the language data from the class's name domain as default.
		
		@param		$domain		Optional domain.
	**/
	protected function load_language($domain = '')
	{
		if ( $domain != '')
			$this->language_domain = $domain;
		
		if ($this->language_domain == '')
			$this->language_domain = str_replace( '.php', '', $this->paths['filename'] );
		load_plugin_textdomain($this->language_domain, false, $this->paths['path_from_plugin_directory'] . '/lang');
	}
	
	/**
		Translate a string, if possible.
		
		Like Wordpress' internal _() method except this one automatically uses the plugin's domain.
		
		@param		$string		String to translate.
		@return					Translated string, or the untranslated string.
	**/
	public function _($string)
	{
		return __( $string, $this->language_domain );
	}
	
	// -------------------------------------------------------------------------------------------------
	// ----------------------------------------- SQL
	// -------------------------------------------------------------------------------------------------
	
	/**
		Sends a query to wpdb and return the results.
		
		@param		$query		The SQL query.
		@param		$wpdb		An optional, other WPDB if the standard $wpdb isn't good enough for you.
		@return		array		The rows from the query.
	**/
	protected function query($query , $wpdb = null)
	{
		if ( $wpdb === null )
			$wpdb = $this->wpdb;
		$results = $wpdb->get_results($query, 'ARRAY_A');
		return (is_array($results) ? $results : array());
	}
	
	/**
		Fire an SQL query and return the results only if there is one row result.
		
		@param		$query			The SQL query.
		@return						Either the row as an array, or false if more than one row.
	**/
	protected function query_single($query)
	{
		$results = $this->wpdb->get_results($query, 'ARRAY_A');
		if ( count($results) != 1)
			return false;
		return $results[0];
	}
	
	/**
		Fire an SQL query and return the row ID of the inserted row.

		@param		$query		The SQL query.
		@return					The inserted ID.
	**/
	protected function query_insert_id($query)
	{
		$this->wpdb->query($query);
		return $this->wpdb->insert_id;
	}
	
	/**
		Converts an object to a base64 encoded, serialized string, ready to be inserted into sql.
		
		@param		$object		An object.
		@return					Serialized, base64-encoded string.
	**/
	protected function sql_encode( $object )
	{
		return base64_encode( serialize($object) );
	}
	
	/**
		Converts a base64 encoded, serialized string back into an object.
		@param		$string			Serialized, base64-encoded string.
		@return						Object, if possible.
	**/
	protected function sql_decode( $string )
	{
		return unserialize( base64_decode($string) );
	}
	
	/**
		Returns whether a table exists.
		
		@param		$table_name		Table name to check for.
		@return						True if the table exists.
	**/
	protected function sql_table_exists( $table_name )
	{
		$query = "SHOW TABLES LIKE '$table_name'";
		$result = $this->query( $query );
		return count($result) > 0;
	}
	
	// -------------------------------------------------------------------------------------------------
	// ----------------------------------------- USER
	// -------------------------------------------------------------------------------------------------
	
	/**
		Returns the user's role as a string.
		@return					User's role as a string.
	**/
	protected function get_user_role()
	{
		foreach($this->roles as $role)
			if (current_user_can($role['current_user_can']))
				return $role['name'];
	}
	
	/**
		Checks whether the user's role is at least $role.
		
		@param		$role		Role as string.
		@return					True if role is at least $role.
	**/
	protected function role_at_least($role)
	{
		global $current_user;
		wp_get_current_user();
		
		if ( $current_user === null )
		    return false;

		if ($role == '')
			return true;

		if ($role == 'super_admin')
			if (function_exists('is_super_admin'))
				return is_super_admin();
			else
				return false;
		
		return current_user_can( $this->roles[$role]['current_user_can'] );
	}
	
	/**
		Returns the user roles as a select options array.
		@return		The user roles as a select options array.
	**/ 
	protected function roles_as_options()
	{
		$returnValue = array();
		if (function_exists('is_super_admin'))
			$returnValue['super_admin'] = $this->_( 'Super admin');
		foreach( $this->roles as $role )
			$returnValue[ $role[ 'name' ] ] = __( ucfirst( $role[ 'name' ] ) );		// See how we ask WP to translate the roles for us? See also how it doesn't. Sometimes.
		return $returnValue;
	}
	
	/**
		Return the user_id of the current user.
	
		@return		int						The user's ID.
	**/
	protected function user_id()
	{
		global $current_user;
		get_current_user();
		return $current_user->ID;
	}
	
	/**
		Creates a new SD_Form.
		
		@param		$options	Default options to send to the SD form constructor.
		@return					A new SD form class.
	**/ 
	protected function form($options = array())
	{
		$options = array_merge($options, array('language' => preg_replace('/_.*/', '', get_locale())) );
		if (class_exists('SD_Form'))
			return new SD_Form($options);
		require_once('SD_Form.php');
		return new SD_Form($options);
	}
	
	// -------------------------------------------------------------------------------------------------
	// ----------------------------------------- OPTIONS
	// -------------------------------------------------------------------------------------------------
	
	/**
		Normalizes the name of an option.
		
		Will prepend the class name in front, to make the options easily findable in the table.
		
		@param		$option		Option name to fix.
	**/
	protected function fix_option_name($option)
	{
		return $this->paths['name'] . '_' . $option;
	}
	
	/**
		Get a site option.
		
		If this is a network, the site option is preferred.
		
		@param		$option		Name of option to get.
		@return					Value.
	**/
	protected function get_option($option)
	{
		$option = $this->fix_option_name($option);
		if ($this->is_network)
			return get_site_option($option);
		else
			return get_option($option);
	}
	
	/**
		Updates a site option.
		
		If this is a network, the site option is preferred.
		
		@param		$option		Name of option to update.
		@param		$value		New value
	**/
	protected function update_option($option, $value)
	{
		$option = $this->fix_option_name($option);
		if ($this->is_network)
			update_site_option($option, $value);
		else
			update_option($option, $value);
	}
	
	/**
		Deletes a site option.
		
		If this is a network, the site option is preferred.
		
		@param		$option		Name of option to delete.
	**/
	protected function delete_option($option)
	{
		$option = $this->fix_option_name($option);
		if ($this->is_network)
			delete_site_option($option);
		else
			delete_option($option);
	}
	
	/**
		Gets the value of a local option.
		
		@param		$option			Name of option to get.
		@param		$default		The default value if the option === false
		@return						Value.
	**/
	protected function get_local_option($option, $default = false)
	{
		$option = $this->fix_option_name($option);
		$value = get_option($option);
		if ( $value === false )
			return $default;
		else
			return $value;
	}
	
	/**
		Updates a local option.
		
		@param		option		Name of option to update.
		@param		$value		New value
	**/
	protected function update_local_option($option, $value)
	{
		$option = $this->fix_option_name($option);
		update_option($option, $value);
	}
	
	/**
		Deletes a local option.
		
		@param		$option		Name of option to delete.
	**/
	protected function delete_local_option($option)
	{
		$option = $this->fix_option_name($option);
		delete_option($option);
	}
	
	/**
		Gets the value of a site option.
		
		@param		$option		Name of option to get.
		@param		$default	The default value if the option === false
		@return					Value.
	**/
	protected function get_site_option($option, $default = false)
	{
		$option = $this->fix_option_name($option);
		$value = get_site_option($option);
		if ( $value === false )
			return $default;
		else
			return $value;
	}
	
	/**
		Updates a site option.
		
		@param		$option		Name of option to update.
		@param		$value		New value
	**/
	protected function update_site_option($option, $value)
	{
		$option = $this->fix_option_name($option);
		update_site_option($option, $value);
	}
	
	/**
		Deletes a site option.
		
		@param		$option		Name of option to delete.
	**/
	protected function delete_site_option($option)
	{
		$option = $this->fix_option_name($option);
		delete_site_option($option);
	}
	
	/**
		Registers all the options this plugin uses.
	**/
	protected function register_options()
	{
		foreach($this->options as $option=>$value)
		{
			if ($this->get_option($option) === false)
				$this->update_option($option, $value);
		}

		foreach($this->local_options as $option=>$value)
		{
			$option = $this->fix_option_name($option);
			if (get_option($option) === false)
				update_option($option, $value);
		}

		if ($this->is_network)
		{
			foreach($this->site_options as $option=>$value)
			{
				$option = $this->fix_option_name($option);
				if (get_site_option($option) === false)
					update_site_option($option, $value);
			}
		}
		else
		{
			foreach($this->site_options as $option=>$value)
			{
				$option = $this->fix_option_name($option);
				if (get_option($option) === false)
					update_option($option, $value);
			}
		}
	}
	
	/**
		Removes all the options this plugin uses.
	**/
	protected function deregister_options()
	{
		foreach($this->options as $option=>$value)
		{
			$this->delete_option($option);
		}

		foreach($this->local_options as $option=>$value)
		{
			$option = $this->fix_option_name($option);
			delete_option($option);
		}

		if ($this->is_network)
			foreach($this->site_options as $option=>$value)
			{
				$option = $this->fix_option_name($option);
				delete_site_option($option);
			}
		else
		{
			foreach($this->site_options as $option=>$value)
			{
				$option = $this->fix_option_name($option);
				delete_option($option, $value);
			}
		}
	}
	
	// -------------------------------------------------------------------------------------------------
	// ----------------------------------------- MESSAGES
	// -------------------------------------------------------------------------------------------------
	
	/**
		Displays a message.
		
		Autodetects HTML / text.
		
		@param		$type		Type of message: error, warning, whatever. Free content.
		@param		$string		The message to display.
	**/
	public function display_message($type, $string)
	{
		// If this string has html codes, then output it as it.
		$stripped = strip_tags($string);
		if (strlen($stripped) == strlen($string))
		{
			$string = explode("\n", $string);
			$string = implode('</p><p>', $string);
		}
		echo '<div class="'.$type.'">
			<p style="margin-right: 1em; float: left; color: #888;" class="message_timestamp">'.$this->now().'</p>
			<p>'.$string.'</p></div>';
	}
	
	/**
		Displays an error message.
		
		The only thing that makes it an error message is that the div has the class "error".
		
		@param		$string		String to display.
	**/
	public function error($string)
	{
		$this->display_message('error', $string);
	}
	
	/**
		Displays an informational message.
		
		@param		$string		String to display.
	**/
	public function message($string)
	{
		$this->display_message('updated', $string);
	}
		
	// -------------------------------------------------------------------------------------------------
	// ----------------------------------------- TOOLS
	// -------------------------------------------------------------------------------------------------
	
	/**
		Displays Wordpress tabs.
		
		@param		$options		See options.
	**/
	protected function tabs($options)
	{
		$options = array_merge(array(
			'count' =>		array(),				// Optional array of a strings to display after each tab name. Think: page counts.
			'default' => null,						// Default tab index.
			'display' => true,						// Display the tabs or return them.
			'displayTabName' => true,				// If display==true, display the tab name.
			'displayBeforeTabName' => '<h2>',		// If displayTabName==true, what to display before the tab name.
			'displayAfterTabName' => '</h2>',		// If displayTabName==true, what to display after the tab name.
			'functions' =>	array(),				// Array of functions associated with each tab name.
			'get_key' =>	'tab',						// $_GET key to get the tab value from.
			'page_titles' =>	array(),			// Array of page titles associated with each tab.
			'tabs' =>		array(),				// Array of tab names
			'valid_get_keys' => array(),			// Display only these _GET keys.
		), $options);
		
		// Work on a copy of the _GET.
		$get = $_GET;
		
		$get_key = $options['get_key'];			// Convenience.
		
		// Is the default not set or set to something stupid? Fix it.
		if ( ! isset( $options['tabs'][ $options['default'] ] ) )
			$options['default'] = key( $options['tabs'] );

		// Select the default tab if none is selected.
		if (!isset($get[$get_key]))
			$get[$get_key] = sanitize_title( $options['tabs'][$options['default']] );
		$selected = $get[$get_key];
		
		$options['valid_get_keys']['page'] = 'page';
		
		$returnValue = '';
		if (count($options['tabs'])>1)
		{
			$returnValue .= '<ul class="subsubsub">';
			$original_link = $_SERVER['REQUEST_URI'];

			foreach($get as $key => $value)
				if ( !in_array($key, $options['valid_get_keys']) )
					$original_link = remove_query_arg($key, $original_link);
			
			$index = 0;
			foreach($options['tabs'] as $tab_index => $tab)
			{
				$slug = $this->tab_slug($tab);
				
				// Make the link.
				// If we're already on that tab, just return the current url.
				if ( $get[$get_key] == $slug )
					$link = remove_query_arg( time() );
				else
				{
					if ( $index == $options['default'] )
						$link = remove_query_arg( $get_key, $original_link );
					else
						$link = add_query_arg( $get_key, $slug, $original_link );
				}
				
				$text = $tab;
				if (isset($options['count'][$index]))
					$text .= ' <span class="count">(' . $options['count'][$index] . ')</span>';
				
				$separator = ($index+1 < count($options['tabs']) ? ' | ' : '');
				
				$current = ($slug == $selected ? ' class="current"' : '');
				
				if ($current)
					$selected_index = $tab_index;
				 
				$returnValue .= '<li><a'.$current.' href="'.$link.'">'.$text.'</a>'.$separator.'</li>';
				$index++;
			}
			$returnValue .= '</ul>';
		}
		
		if ( !isset($selected_index) )
			$selected_index = $options['default'];
	
		if ($options['display'])
		{
			ob_start();
			echo '<div class="wrap">';
			if ($options['displayTabName'])
			{
				if ( isset( $options['page_titles'][$selected_index] ) )
					$page_title = $options['page_titles'][$selected_index];
				else
					$page_title = $options['tabs'][$selected_index];
				
				echo $options['displayBeforeTabName'] . $page_title . $options['displayAfterTabName'];
			}
			echo $returnValue;
			echo '<div style="clear: both"></div>';
			if (isset($options['functions'][$selected_index]))
			{
				$functionName = $options['functions'][$selected_index];
				if ( is_array( $functionName ) )
				{
					$functionName[0]->$functionName[1]();
				}
				else 
					$this->$functionName();
			}
			echo '</div>';
			ob_end_flush();
		}
		else
			return $returnValue;
	}
	
	/**
		Sanitizes the name of a tab.
	
		@param		$string		String to sanitize.
	**/
	protected function tab_slug( $string )
	{
		return $this->slug( $string );
	}
	
	/**
		Sanitizes a string.
	
		@param		$string		String to sanitize.
	**/
	protected function slug( $string )
	{
		return sanitize_title( $string );
	}
	
	protected function display_form_table($options)
	{
		$options = array_merge(array(
			'header' => '',
			'header_level' => 'h3',
		), $options);
		
		$tr = array();
		
		if ( !isset($options['form']) )
			$options['form'] = $this->form();
			
		foreach( $options['inputs'] as $input )
			$tr[] = $this->display_form_table_row( $input, $options['form'] );
		
		$returnValue = '';
		
		if ( $options['header'] != '' )
			$returnValue .= '<'.$options['header_level'].'>' . $options['header'] . '</'.$options['header_level'].'>';
		
		$returnValue .= '
			<table class="form-table">
				<tr>' . implode('</tr><tr>', $tr) . '</tr>
			</table>
		';
		
		return $returnValue;
	}

	protected function display_form_table_row($input, $form = null)
	{
		if ($form === null)
			$form = $this->form();
		return '
			<tr>
				<th>'.$form->make_label($input).'</th>
				<td>
					<div class="input_itself">
						'.$form->make_input($input).'
					</div>
					<div class="input_description">
						'.$form->make_description($input).'
					</div>
				</td>
			</tr>';
	}
	
	/**
		Make a value a key.
		
		Given an array of arrays, take the key from the subarray and makes it the key of the main array.
	
		@param		$array		Array to rearrange.
		@param		$key		Which if the subarray keys to make the key in the main array.
		@return		array		Rearranged array.
	**/
	public function array_moveKey($array, $key)
	{
		$returnArray = array();
		foreach($array as $value)
			$returnArray[ $value[$key] ] = $value;
		return $returnArray;
	}
	
	/**
		Convert an object to an array.
	
		@param		$object		Object or array of objects to convert to a simple array.
		@return		Array.
	**/
	public function object_to_array( $object )
	{
		if (is_array( $object ))
		{
			$returnValue = array();
			foreach($object as $o)
				$returnValue[] = get_object_vars($o);
			return $returnValue;
		}
		else
			return get_object_vars($object);
	}
	
	/**
		Convert an array to stdClass, or appends the $array to an existing $object.
		
		@param	$array		Array to convert.
		@param	$object		Existing object, if any, to append data to.
		@return				Object with fields from the array.
	**/ 
	public function array_to_object( $array, $object = null )
	{
		if ( ! is_array( $array ) )
			$array = array();

		if ( $object === null )
			$object = new stdClass();
		
		foreach( $array as $key => $value )
			$object->$key = $value;
		
		return $object;
	}
	
	/**
		Display the time ago as human-readable string.
		
		@param		$time_string	"2010-04-12 15:19"
		@param		$time			An optional timestamp to base time difference on, if not now.
		@return						"28 minutes ago"
	**/
	protected function ago($time_string, $time = null)
	{
		if ($time_string == '')
			return '';
		if ( $time === null )
			$time = current_time('timestamp');
		$diff = human_time_diff( strtotime($time_string), $time );
		return '<span title="'.$time_string.'">' . sprintf( __('%s ago'), $diff) . '</span>';
	}
	
	/**
		Returns WP's current timestamp (corrected for UTC)
		
		@return						Current timestamp in MYSQL datetime format.
	*/
	public function now()
	{
		return date('Y-m-d H:i:s', current_time('timestamp'));
	}
	
	/**
		Recursively removes a directory.
		
		Assumes that all files in the directory, and the dir itself, are writeable.
		
		@param		$directory		Directory to remove.
	**/
	public function rmdir( $directory )
	{
		$directory = rtrim( $directory, '/' );
		if ( $directory == '.' || $directory == '..' )
			return;
		if ( is_file( $directory ) )
			unlink ( $directory );
		else
		{
			$files = glob( $directory . '/*' );
			foreach( $files as $file )
				$this->rmdir( $file );
			rmdir( $directory );
		}
	}
	
	/**
		Returns the current time(), corrected for UTC and DST.

		@return		int							Current, corrected timestamp.
	**/
	protected function time()
	{
		return current_time('timestamp');
	}
	
	/**
		Returns the number corrected into the min and max values.
	*/
	protected function minmax($number, $min, $max)
	{
		$number = min($max, $number);
		$number = max($min, $number);
		return $number;
	}
	
	/**
		Returns a hash value of a string. The standard hash type is sha512 (64 chars).
		
		@param		$string			String to hash.
		@param		$type			Hash to use. Default is sha512.
		@return						Hashed string.
	**/
	protected function hash($string, $type = 'sha512')
	{
		return hash($type, $string);
	}
	
	/**
		Multibyte strtolower.
	
		@param		$string			String to lowercase.
		@return						Lowercased string.
	**/
	protected function strtolower( $string )
	{
		return mb_strtolower( $string ); 
	}
	
	/**
		Multibyte strtoupper.
	
		@param		$string			String to uppercase.
		@return						Uppercased string.
	**/
	protected function strtoupper( $string )
	{
		return mb_strtoupper( $string ); 
	}
	
	/**
		Sends mail via SMTP.
		
		@param		$mail_data					Mail data.
	**/
	public function send_mail($mail_data)
	{
		// backwards compatability
		if (isset($mail_data['bodyhtml']))
			$mail_data['body_html'] = $mail_data['bodyhtml'];
		
		require_once ABSPATH . WPINC . '/class-phpmailer.php';
		$mail = new PHPMailer();
		
		// Mandatory
		$mail->From		= key($mail_data['from']);
		$mail->FromName	= reset($mail_data['from']);
		
		$mail->Subject  = $mail_data['subject'];
		
		// Optional
		
		// Often used settings...
	
		if (isset($mail_data['to']))
			foreach($mail_data['to'] as $email=>$name)
			{
				if (is_int($email))
					$email = $name;
				$mail->AddAddress($email, $name);
			}
			
		if (isset($mail_data['cc']))
			foreach($mail_data['cc'] as $email=>$name)
			{
				if (is_int($email))
					$email = $name;
				$mail->AddCC($email, $name);
			}
	
		if (isset($mail_data['bcc']))
			foreach($mail_data['bcc'] as $email=>$name)
			{
				if (is_int($email))
					$email = $name;
				$mail->AddBCC($email, $name);
			}
			
		if (isset($mail_data['body_html']))
			$mail->MsgHTML($mail_data['body_html'] );
	
		if (isset($mail_data['body']))
			$mail->Body = $mail_data['body'];
		
		if (isset($mail_data['attachments']))
			foreach($mail_data['attachments'] as $attachment=>$filename)
				if (is_numeric($attachment))
					$mail->AddAttachment($filename);
				else
					$mail->AddAttachment($attachment, $filename);

		if ( isset( $mail_data['reply_to'] ) )
		{
			foreach($mail_data['reply_to'] as $email=>$name)
			{
				if (is_int($email))
					$email = $name;
				$mail->AddReplyTo($email, $name);
			}
		}
				
		// Seldom used settings...
		
		if (isset($mail_data['wordwrap']))
			$mail->WordWrap = $mail_data[wordwrap];
	
		if (isset($mail_data['ConfirmReadingTo']))
			$mail->ConfirmReadingTo = true;
		
		if (isset($mail_data['SingleTo']))
		{
			$mail->SingleTo = true;
			$mail->SMTPKeepAlive = true;
		}
		
		if (isset($mail_data['SMTP']))									// SMTP? Or just plain old mail()
		{
			$mail->IsSMTP();
			$mail->Host	= $mail_data['smtpserver'];
			$mail->Port = $mail_data['smtpport'];
		}
		else
			$mail->IsMail();
		
		if ( isset($mail_data['charset']) )
			$mail->CharSet = $mail_data['charset'];
		else
			$mail->CharSet = 'UTF-8';
		
		if ( isset($mail_data['content_type']) )
			$mail->ContentType  = $mail_data['content_type'];
		
		if ( isset($mail_data['encoding']) )
			$mail->Encoding  = $mail_data['encoding'];
		
		// Done setting up.
		if(!$mail->Send())
			$returnValue = $mail->ErrorInfo;
		else 
			$returnValue = true;
			
		$mail->SmtpClose();
		
		return $returnValue;		
	}
}
