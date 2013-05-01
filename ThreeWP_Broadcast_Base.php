<?php
/**
	@brief		Base class for the SD series of Wordpress plugins.
	
	Provides a simple framework with common Wordpress functions.
	
	@par	Changelog

	- 2013-04-08	20:20	New: sd_table classes.
							New: merge_objects
							Fix: check_column and check_column_body accept the table as a parameter.
	- 2013-03-07	15:43	New: add_submenu_page and add_submenu_pages
	- 2013-03-05	12:45	Fix: check_plain also stripslashes.
	- 2013-03-01	10:25	New: check_column() and check_column_body().
	- 2013-03-01	10:25	Fix: display_form_table inserted input names automatically (based on the array key).
	- 2013-02-15	04:51	Fix: role_at_least optimized for super admin queries.
	- 2012-12-14	08:47	Fix: role_at_least works again. It appears as current_user_can is broken somehow.
	- 2012-12-13	19:55	Fix: super admin can do everything when asked role_at_least
	- 2012-12-12	12:16	Added is_email function to check for e-mail validity.
	- 2012-12-10	19:37	Fixed mime typing when attaching files to mails.
	- 2012-11-07	16:36	Removed static styling from message boxes, added CSS classes.
	- 2012-11-07	12:39	Fix: error_ returns an error, not a message.
	- 2012-10-10	19:10	Added message()_ and error_().
	- 2012-09-17	15:35	Fix neverending loop in array_sort_subarrays.
	- 2012-05-25	11:20	All functions are now public. No more bickering about protected here and there.
	- 2012-05-21	20:00	tabs now work on tab_slugs, not slugged tab names.
	- 2012-05-18	13:54	Refactoring of tabs() variable names.
	- 2012-05-16	19:03	_() can act as sprint.
							Display form table now has ( $inputs, $options )
	- 2012-05-08	13:15	Tries to get mime type of attached files when sending mail.
	- 2012-02-10			display_form_table ignores hidden inputs.
	- 2011-08-03			tab functions can now be arrays (class, method).
	- 2011-08-02			array_to_object.
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
	public $paths = array();
	
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
		@brief		Queues a submenu page for adding later.
		
		Used to ensure alphabetic sorting of submenu pages independent of language.
		
		Uses the same parameters as Wordpress' add_submenu_page. Uses the menu title as the sorting key.
		
		After all pages have been add_submenu_page'd, call add_submenu_pages to actually sort and add them.
	**/
	public function add_submenu_page()
	{
		if ( ! isset( $this->submenu_pages ) )
			$this->submenu_pages = array();
		
		$args = func_get_args();
		$key = $args[ 2 ];
		$key = $this->strtolower( $key );
		$this->submenu_pages[ $key ] = $args;
	}
	
	/**
		@brief		Flush the add_submenu_page cache.
		
		Will first sort by key and then add the subpages.
	**/
	public function add_submenu_pages()
	{
		ksort( $this->submenu_pages );
		foreach( $this->submenu_pages as $submenu )
			call_user_func_array( 'add_submenu_page', $submenu );
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
	public function admin_uninstall()
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
		Overridable method to deactive the plugin.
	**/
	public function deactivate()
	{
	}
	
	/**
		Deactivates the plugin.
	**/
	public function deactivate_me()
	{
		deactivate_plugins(array(
			$this->paths['filename_from_plugin_directory']
		));
	}
	
	/**
		Loads this plugin's language files.
		
		Reads the language data from the class's name domain as default.
		
		@param		$domain
					Optional domain.
	**/
	public function load_language($domain = '')
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
		
		Can function like sprintf, if any %s are specified.
		
		@param		$string
					String to translate. %s will require extra arguments to the method.
		
		@return		Translated string, or the untranslated string.
	**/
	public function _( $string )
	{
		$new_string = __( $string, $this->language_domain );

		$count = substr_count( $string, '%s' );
		if ( $count > 0 )
		{
			$args = func_get_args();
			array_shift( $args );
			
			if ( $count != count( $args ) )
				throw new Exception( sprintf(
					'_() requires the same amount of arguments as occurrences of %%s. Needed: %s, given %s',
					$count,
					count( $args )
				) );

			array_unshift( $args, $new_string );
			$new_string =  call_user_func_array( 'sprintf', $args );
		}
		
		return $new_string;
	}
	
	/**
		Overridable uninstall method.
	**/
	public function uninstall()
	{
		$this->deregister_options();
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
	public function query($query , $wpdb = null)
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
	public function query_single($query)
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
	public function query_insert_id($query)
	{
		$this->wpdb->query($query);
		return $this->wpdb->insert_id;
	}
	
	/**
		Converts an object to a base64 encoded, serialized string, ready to be inserted into sql.
		
		@param		$object		An object.
		@return					Serialized, base64-encoded string.
	**/
	public function sql_encode( $object )
	{
		return base64_encode( serialize($object) );
	}
	
	/**
		Converts a base64 encoded, serialized string back into an object.
		@param		$string			Serialized, base64-encoded string.
		@return						Object, if possible.
	**/
	public function sql_decode( $string )
	{
		return unserialize( base64_decode($string) );
	}
	
	/**
		Returns whether a table exists.
		
		@param		$table_name		Table name to check for.
		@return						True if the table exists.
	**/
	public function sql_table_exists( $table_name )
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
	public function get_user_role()
	{
		foreach($this->roles as $role)
			if (current_user_can($role['current_user_can']))
				return $role['name'];
	}
	
	/**
		Returns the user roles as a select options array.
		@return		The user roles as a select options array.
	**/ 
	public function roles_as_options()
	{
		$rv = array();
		if (function_exists('is_super_admin'))
			$rv['super_admin'] = $this->_( 'Super admin');
		foreach( $this->roles as $role )
			$rv[ $role[ 'name' ] ] = __( ucfirst( $role[ 'name' ] ) );		// See how we ask WP to translate the roles for us? See also how it doesn't. Sometimes.
		return $rv;
	}
	
	/**
		Checks whether the user's role is at least $role.
		
		@param		$role		Role as string.
		@return					True if role is at least $role.
	**/
	public function role_at_least($role)
	{
		global $current_user;
		wp_get_current_user();
		
		if ( $current_user === null )
		    return false;

		if ($role == '')
			return true;
			
		if (function_exists('is_super_admin') && is_super_admin() )
			return true;
		
		if ( $role == 'super_admin' )
			return false;			
		
		// This was previously done by current_user_can, but for some reason it doesn't work all the time in WP3.5.
		// So now I have to check "manually", which probably means that filters are rendered ineffective.
		$role_cap = $this->roles[$role]['current_user_can'];
		return isset( $current_user->allcaps[ $role_cap ] ) && $current_user->allcaps[ $role_cap ] == true;
	}
	
	/**
		Return the user_id of the current user.
	
		@return		int						The user's ID.
	**/
	public function user_id()
	{
		global $current_user;
		get_current_user();
		return $current_user->ID;
	}
	
	// -------------------------------------------------------------------------------------------------
	// ----------------------------------------- OPTIONS
	// -------------------------------------------------------------------------------------------------
	
	/**
		Deletes a site option.
		
		If this is a network, the site option is preferred.
		
		@param		$option		Name of option to delete.
	**/
	public function delete_option($option)
	{
		$option = $this->fix_option_name($option);
		if ($this->is_network)
			delete_site_option($option);
		else
			delete_option($option);
	}
	
	/**
		Deletes a local option.
		
		@param		$option		Name of option to delete.
	**/
	public function delete_local_option($option)
	{
		$option = $this->fix_option_name($option);
		delete_option($option);
	}
	
	/**
		Deletes a site option.
		
		@param		$option		Name of option to delete.
	**/
	public function delete_site_option($option)
	{
		$option = $this->fix_option_name($option);
		delete_site_option($option);
	}
	
	/**
		Normalizes the name of an option.
		
		Will prepend the class name in front, to make the options easily findable in the table.
		
		@param		$option		Option name to fix.
	**/
	public function fix_option_name($option)
	{
		return $this->paths['name'] . '_' . $option;
	}
	
	/**
		Removes all the options this plugin uses.
	**/
	public function deregister_options()
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
	
	/**
		Get a site option.
		
		If this is a network, the site option is preferred.
		
		@param		$option		Name of option to get.
		@return					Value.
	**/
	public function get_option($option)
	{
		$option = $this->fix_option_name($option);
		if ($this->is_network)
			return get_site_option($option);
		else
			return get_option($option);
	}
	
	/**
		Gets the value of a local option.
		
		@param		$option			Name of option to get.
		@param		$default		The default value if the option === false
		@return						Value.
	**/
	public function get_local_option($option, $default = false)
	{
		$option = $this->fix_option_name($option);
		$value = get_option($option);
		if ( $value === false )
			return $default;
		else
			return $value;
	}
	
	/**
		Gets the value of a site option.
		
		@param		$option		Name of option to get.
		@param		$default	The default value if the option === false
		@return					Value.
	**/
	public function get_site_option($option, $default = false)
	{
		$option = $this->fix_option_name($option);
		$value = get_site_option($option);
		if ( $value === false )
			return $default;
		else
			return $value;
	}
	
	/**
		Registers all the options this plugin uses.
	**/
	public function register_options()
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
		Updates a site option.
		
		If this is a network, the site option is preferred.
		
		@param		$option		Name of option to update.
		@param		$value		New value
	**/
	public function update_option($option, $value)
	{
		$option = $this->fix_option_name($option);
		if ($this->is_network)
			update_site_option($option, $value);
		else
			update_option($option, $value);
	}
	
	/**
		Updates a local option.
		
		@param		option		Name of option to update.
		@param		$value		New value
	**/
	public function update_local_option($option, $value)
	{
		$option = $this->fix_option_name($option);
		update_option($option, $value);
	}
	
	/**
		Updates a site option.
		
		@param		$option		Name of option to update.
		@param		$value		New value
	**/
	public function update_site_option($option, $value)
	{
		$option = $this->fix_option_name($option);
		update_site_option($option, $value);
	}
	
	// -------------------------------------------------------------------------------------------------
	// ----------------------------------------- MESSAGES
	// -------------------------------------------------------------------------------------------------
	
	/**
		Displays a message.
		
		Autodetects HTML / text.
		
		@param		$type
					Type of message: error, warning, whatever. Free content.
					
		@param		$string
					The message to display.
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
		echo '<div class="sd_message_box '.$type.'">
			<p class="message_timestamp">'.$this->now().'</p>
			<p>'.$string.'</p></div>';
	}
	
	/**
		Displays an informational message.
		
		@param		$string
					String to display.
	**/
	public function message( $string )
	{
		$this->display_message( 'updated', $string );
	}
	
	/**
		@brief		Convenience function to translate and then create a message from a string and optional sprintf arguments.
		
		@param		$string
					String to translate and create into a message.
		
		@return		A translated message.
	**/
	public function message_( $string, $args = '' )
	{
		$args = func_get_args();
		$string = call_user_func_array( array( &$this, '_' ), $args );
		return $this->message( $string );
	}
		
	/**
		Displays an error message.
		
		The only thing that makes it an error message is that the div has the class "error".
		
		@param		$string
					String to display.
	**/
	public function error( $string )
	{
		$this->display_message( 'error', $string );
	}
	
	/**
		@brief		Convenience function to translate and then create an error message from a string and optional sprintf arguments.
		
		@param		$string
					String to translate and create into an error message.
		
		@return		A translated error message.
	**/
	public function error_( $string, $args = '' )
	{
		$args = func_get_args();
		$string = call_user_func_array( array( &$this, '_' ), $args );
		return $this->error( $string );
	}
		
	// -------------------------------------------------------------------------------------------------
	// ----------------------------------------- TOOLS
	// -------------------------------------------------------------------------------------------------
	
	/**
		@brief		Insert an array into another.
		
		Like array_splice but better, because it even inserts the new key.
		
		@param		$array
						Array into which to insert the new array.

		@param		$position
						Position into which to insert the new array.

		@param		$new_array
						The new array which is to be inserted.
		
		@return		The complete array.
	**/
	public function array_insert( $array, $position, $new_array )
	{
		$part1 = array_slice( $array, 0, $position, true ); 
		$part2 = array_slice( $array, $position, null, true ); 
		return $part1 + $new_array + $part2;
	}
	
	/**
		@brief		Sort an array of arrays using a specific key in the subarray as the sort key.
		
		@param		$array
					An array of arrays.
		@param		$key
					Key in subarray to use as sort key.
		
		@return		The array of arrays. 
	**/
	public function array_sort_subarrays( $array, $key )
	{
		// In order to be able to sort a bunch of objects, we have to extract the key and use it as a key in another array.
		// But we can't just use the key, since there could be duplicates, therefore we attach a random value.
		$sorted = array();
		
		$is_array = is_array( reset( $array ) );
		
		foreach( $array as $index => $item )
		{
			$item = (object) $item;
			do
			{
				$rand = rand(0, PHP_INT_MAX / 2);
				if ( is_int( $item->$key ) )
					$random_key = $rand + $item->$key;
				else
					$random_key = $item->$key . '-' . $rand;
			}
			while ( isset( $sorted[ $random_key ] ) );
			
			$sorted[ $random_key ] = array( 'key' => $index, 'value' => $item );
		}
		ksort( $sorted );
		
		// The array has been sorted, we want the original array again.
		$rv = array();
		foreach( $sorted as $item )
		{
			$value = ( $is_array ? (array)$item[ 'value' ] : $item[ 'value' ] );
			$rv[ $item['key'] ] = $item['value'];
		}
			
		return $rv;
	}
	
	/**
		Make a value a key.
		
		Given an array of arrays, take the key from the subarray and makes it the key of the main array.
	
		@param		$array		Array to rearrange.
		@param		$key		Which if the subarray keys to make the key in the main array.
		@return		array		Rearranged array.
	**/
	public function array_rekey($array, $key)
	{
		$rv = array();
		foreach( $array as $value )
			$rv[ $value[ $key ] ] = $value;
		return $rv;
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
	public function ago($time_string, $time = null)
	{
		if ($time_string == '')
			return '';
		if ( $time === null )
			$time = current_time('timestamp');
		$diff = human_time_diff( strtotime($time_string), $time );
		return '<span title="'.$time_string.'">' . sprintf( __('%s ago'), $diff) . '</span>';
	}
	
	public function check_column( $options = array() )
	{
		$o = self::merge_objects( array(
			'sd_table_row' => null,
		), $options );
		
		$selected = array(
			'name' => 'check',
			'type' => 'checkbox',
		);
		$form = $this->form();
		
		// If there is a supplied sd_table_row, use that.
		if ( $o->sd_table_row !== null )
		{
			$row = $o->sd_table_row;	// Conv
			$text = $form->make_input( $selected ) . '<span class="screen-reader-text">' . $this->_('Selected') . '</span>';
			$row->th()->css_class( 'check-column' )->text( $text );
		}
		
		// Else return a manual table th.
		return '<th class="check-column">' . $form->make_input( $selected ) . '<span class="screen-reader-text">' . $this->_('Selected') . '</span></th>';
	}
	
	public function check_column_body( $options )
	{
		$options = array_merge( array(
			'form' => $this->form(),
			'nameprefix' => '[cb]',
			'type' => 'checkbox',
			'sd_table_row' => null,
		), $options );
		
		if ( ! isset( $options[ 'label' ] ) )
			$options[ 'label' ] = $options[ 'name' ];
		
		$form = $options[ 'form' ];		// Conv
		
		// If there is a supplied sd_table_row, use it.
		if ( $options[ 'sd_table_row' ] !== null )
		{
			$text = $form->make_input( $options );
			$text .= '<span class="screen-reader-text">' . $form->make_label( $options ) . '</span>';
			
			$row = $options[ 'sd_table_row' ];
			$row->th()->css_class( 'check-column' )->attribute( 'scope', 'row' )->text( $text );
			return; 
		}
		
		// Else return a manual table th.
		return '
			<th scope="row" class="check-column">
				' . $form->make_input( $options ) . '
				<span class="screen-reader-text">' . $form->make_label( $options ) . '</span>
			</th>
		';
	}
	
	public function check_plain( $text )
	{
		$text = strip_tags( $text );
		$text = stripslashes( $text );
		return $text;
	}
	
	public function display_form_table( $inputs, $options = array() )
	{
		$options = array_merge(array(
			'header' => '',
			'header_level' => 'h3',
		), $options);
		
		$tr = array();
		
		$rv = '';
		
		if ( !isset($options['form']) )
			$options['form'] = $this->form();
			
		foreach( $inputs as $name => $input )
		{
			if ( ! isset( $input[ 'name' ] ) )
				$input[ 'name' ] = $name;
			
			if ( $input[ 'type' ] == 'hidden' )
			{
				$rv .= $options['form']->make_input( $input );
				continue;
			}
			$tr[] = $this->display_form_table_row( $input, $options['form'] );
		}
		
		if ( $options['header'] != '' )
			$rv .= '<'.$options['header_level'].'>' . $options['header'] . '</'.$options['header_level'].'>';
		
		$rv .= '
			<table class="form-table">
				' . implode('', $tr) . '
			</table>
		';
		
		return $rv;
	}

	public function display_form_table_row($input, $form = null)
	{
		if ($form === null)
			$form = $this->form();
		
		if ( $input[ 'type' ] == 'rawtext' )
			return '<tr><td colspan="2">' . $form->make_input( $input ) . '</td></tr>';

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
		@brief		Output a file to the browser for downloading.
		
		@param		$file
					Path to file on disk.
		
		@param		$name
					Downloaded file's name.
		
		@param		$mime_type
					Optional mime_type.

		@author		http://w-shadow.com/blog/2007/08/12/how-to-force-file-download-with-php/
	**/
	public function download( $filepath, $options = array() )
	{
		if ( ! is_readable( $filepath ) )
			throw new Exception( "The file $filepath could not be read!" );
		
		$o = (object) array_merge( array(
			'cache' => true,
			'content_disposition' => 'attachment',
			'content_type' => '',
			'etag' => true,
			'expires' => 3600 * 24 * 7,		// one week
			'filemtime' => filemtime( $filepath ),
			'filename' => '',
			'filesize' => filesize( $filepath ),
			'md5_file' => true,
		), $options );
		
		// 304 support
		if ( $o->cache && isset( $_SERVER[ 'HTTP_IF_MODIFIED_SINCE' ] ) )
		{
			$since = $_SERVER[ 'HTTP_IF_MODIFIED_SINCE' ];
			$since = strtotime( $since );
			if ( $since >= $o->filemtime )
			{
				header( 'HTTP/1.1 304 Not Modified' );
				return;
			} 
		}
		
		if ( $o->filename == '' )
			$o->filename = basename( $filepath );
		
		$headers = array(
			'Accept-Ranges: bytes',
			'Content-Disposition: ' . $o->content_disposition . '; filename="' . $o->filename . '"',
			'Content-Transfer-Encoding: binary',
			'Last-Modified: ' . gmdate( 'D, d M Y H:i:s', $o->filemtime ) . ' GMT', 
		);
		
		if ( $o->content_type == '' )
		{
			$mime_types = array
			(
				'doc' => 'application/msword',
				'exe' => 'application/octet-stream',
				'gif' => 'image/gif',
				'htm' => 'text/html',
				'html' => 'text/html',
				'jpeg'=> 'image/jpg',
				'jpg' => 'image/jpg',
				'pdf' => 'application/pdf',
				'php' => 'text/plain',
				'ppt' => 'application/vnd.ms-powerpoint',
				'png' => 'image/png',
				'txt' => 'text/plain',
				'xls' => 'application/vnd.ms-excel',
				'zip' => 'application/zip',
			);
			$file_extension = strtolower( substr( strrchr( $o->filename, '.' ), 1 ) );
			if ( isset( $mime_types[ $file_extension ] ) )
				$o->content_type = $mime_types[ $file_extension ];
			else
				$o->content_type = 'application/octet-stream';
		}
		$headers[] = 'Content-Type: ' . $o->content_type;

		if ( $o->cache === false )
		{
			$headers[] = 'Cache-Control: no-cache, no-store';
	 		$headers[] = 'Pragma: private';
	 		$headers[] = 'Expires: Mon, 26 Jul 1995 00:00:00 GMT';
		}
		else
		{
			$expires = $o->filemtime + $o->expires;
			$headers[] = 'Expires: ' . gmdate( 'D, d M Y H:i:s', $expires ) . ' GMT'; 
			$headers[] = 'Cache-Control: max-age=' . $o->expires;
		}
		
		if ( $o->etag !== false )
		{
			$etag = ( $o->etag !== true ? $o->etag : md5( filesize( $o->filepath ) . filemtime( $o->filepath ) ) );
			$headers[] = 'ETag: ' . $etag;
		}
		
		if ( $o->md5_file == true )
			$headers[]  = 'Content-MD5: ' . base64_encode( md5_file( $filepath ) );

		// Resume support.
/**
		if ( isset($_SERVER['HTTP_RANGE']) )
		{
			if ( ! function_exists( 'download_416' ) )
				function download_416( $filesize )
				{
					header('HTTP/1.1 416 Requested Range Not Satisfiable');
					header('Content-Range: bytes *\/' . $o->filesize); // Required in 416.		*\/ *\/ *\/
				}
				
			if (!preg_match('^bytes=\d*-\d*(,\d*-\d*)*$', $_SERVER['HTTP_RANGE'])) {
			{
				download_416( $o->filesize );
				return;
			}
			
			$ranges = explode(',', substr($_SERVER['HTTP_RANGE'], 6) );
			foreach ( $ranges as $range )
			{
				$parts = explode('-', $range);
				$start = $parts[0];		// If this is empty, this should be 0.
				$end = $parts[1];		// If this is empty or greater than than filelength - 1, this should be filelength - 1.
				
				if ($start > $end)
				{
					download_416( $o->filesize );
					return;
				}
			}
		}
		else
**/		
		{
			$size_to_send = $o->filesize;
		}
		$headers[] = 'Content-Length: ' . $size_to_send;
		
		foreach( $headers as $header )
			header( $header );
 
		$chunksize = 65536;
		$bytes_sent = 0;
		$file = fopen($filepath, 'r');
		if ( ! $file )
			throw new Exception( "File $filepath could not be opened for reading!" );
/*		
		if ( isset( $_SERVER['HTTP_RANGE'] ) )
			fseek( $file, $range );
**/			
		while ( ! feof( $file ) && ( ! connection_aborted() ) && ( $bytes_sent < $size_to_send ) )
		{
			$buffer = fread( $file, $chunksize );
			echo ( $buffer );
			flush();
			$bytes_sent += strlen( $buffer );
		}
		fclose( $file );
	}   

	public function filters( $filter_name )
	{
		$args = func_get_args();
		
		if ( count( $args ) < 2 )
			$args[] = null;
		
		return call_user_func_array( 'apply_filters', $args );
	}
	
	/**
		Creates a new SD_Form.
		
		@param		$options	Default options to send to the SD form constructor.
		@return					A new SD form class.
	**/ 
	public function form($options = array())
	{
		$options = array_merge($options, array('language' => preg_replace('/_.*/', '', get_locale())) );
		if ( ! class_exists('SD_Form') )
			require_once( 'SD_Form.php' );
		return new SD_Form( $options );
	}
	
	/**
		Creates a new SD_Table.
		
		@return		sd_table		A new sd_table object.
	**/ 
	public function table()
	{
		if ( ! class_exists('sd_table') )
			require_once( 'SD_Table.php' );
		
		$rv = new sd_table();
		$rv->css_class( 'widefat' );
		return $rv;
	}
	
	/**
		Returns a hash value of a string. The standard hash type is sha512 (64 chars).
		
		@param		$string			String to hash.
		@param		$type			Hash to use. Default is sha512.
		@return						Hashed string.
	**/
	public function hash($string, $type = 'sha512')
	{
		return hash($type, $string);
	}
	
	/**
		@brief		Implode an array in an HTML-friendly way.
		
		Used to implode arrays using HTML tags before, between and after the array. Good for lists.
		
		@param		$prefix
					li
		
		@param		$suffix
					/li
		
		@param		$array
					The array of strings to implode.
		
		@return		The imploded string.
	**/
	public function implode_html( $prefix, $suffix, $array )
	{
		return $prefix . implode( $suffix . $prefix, $array ) . $suffix;
	}
	
	/**
		@brief		Check en e-mail address for validity.
		
		@param		string		$address		Address to check.
		
		@return		boolean		True, if the e-mail address is valid.
	**/
	public function is_email( $address )
	{
		if ( ! is_email( $address ) )
			return false;
		
		// Check the DNS record.
		$host = preg_replace( '/.*@/', '', $address );
		if ( ! checkdnsrr( $host, 'MX' ) )
			return false;
		
		return true;
	}
	
	/**
		@brief		Merge two objects.
		
		@param		mixed		$base		An array or object into which to append the new properties.
		@param		mixed		$new		New properties to append to $base.
		@return		object					The expanded $base object.
	**/
	public static function merge_objects( $base, $new )
	{
		$base = (object)$base;
		foreach( (array)$new as $key => $value )
			$base->$key = $value;
		return $base;
	}
	
	/**
		Returns the number corrected into the min and max values.
	*/
	public function minmax($number, $min, $max)
	{
		$number = min($max, $number);
		$number = max($min, $number);
		return $number;
	}
	
	public function mime_type( $filename )
	{
		$rv = 'application/octet-stream';

		if ( is_executable( '/usr/bin/file' ) )
		{
			exec( "file -bi '$filename'", $rv );
			$rv = reset( $rv );
			$rv = preg_replace( '/;.*/', '', $rv );
			return $rv;
		}
		
		if ( class_exists( 'finfo' ) )
		{
			$fi = new finfo( FILEINFO_MIME, '/usr/share/file/magic' );
			$rv = $fi->buffer(file_get_contents( $filename ));
			return $rv;
		}
		
		if ( function_exists( 'mime_content_type' ) )
		{
			$rv = mime_content_type ( $filename );
			return $rv;
		}
		
		return $rv;
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
		Convert an object to an array.
	
		@param		$object		Object or array of objects to convert to a simple array.
		@return		Array.
	**/
	public function object_to_array( $object )
	{
		if (is_array( $object ))
		{
			$rv = array();
			foreach($object as $o)
				$rv[] = get_object_vars($o);
			return $rv;
		}
		else
			return get_object_vars($object);
	}
	
	/**
		@brief		wpautop's a string, using sprintf to replace arguments.
		
		@param		$string
					String to wpautop.
		@param		$args
					Optional arguments to sprintf.
		@return		The wpautop'd sprintf string.
	**/
	public function p( $string, $args = '' )
	{
		$args = func_get_args();
		return wpautop( call_user_func_array( 'sprintf', $args ) );
	}
	
	/**
		@brief		wpautop's a translated string, using sprintf to replace arguments.

		@param		$string
					String to translate and then wpautop.
		@param		$args
					Optional arguments to _() / sprintf.
		@return		The wpautop'd, translated string.
	**/
	public function p_( $string, $args = '' )
	{
		$args = func_get_args();
		return wpautop( call_user_func_array( array( &$this, '_'), $args ) );
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
		$from_email		= key( $mail_data['from'] );
		$from_name		= reset( $mail_data['from'] );
		$mail->From		= $from_email;
		$mail->FromName	= $from_name;
		$mail->Sender	= $from_email;
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
			{
				$encoding = 'base64';
				$mime_type = $this->mime_type ( $attachment );
	
				if (is_numeric($attachment))
					$mail->AddAttachment($filename, '', $encoding, $mime_type);
				else
					$mail->AddAttachment($attachment, $filename, $encoding, $mime_type);
			}
	
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
		if (!$mail->Send())
			$rv = $mail->ErrorInfo;
		else 
			$rv = true;
			
		$mail->SmtpClose();
		
		return $rv;		
	}
	
	/**
		Sanitizes a string.
	
		@param		$string		String to sanitize.
	**/
	public function slug( $string )
	{
		return sanitize_title( $string );
	}
	
	public function strip_post_slashes( $post = null )
	{
		if ( $post === null )
			$post = $_POST;
		foreach( $post as $key => $value )
			if ( ! is_array( $value ) && strlen( $value ) > 1 )
				$post[ $key ] = stripslashes( $value );
		
		return $post;
	}
	
	/**
		Multibyte strtolower.
	
		@param		$string			String to lowercase.
		@return						Lowercased string.
	**/
	public function strtolower( $string )
	{
		return mb_strtolower( $string ); 
	}
	
	/**
		Multibyte strtoupper.
	
		@param		$string			String to uppercase.
		@return						Uppercased string.
	**/
	public function strtoupper( $string )
	{
		return mb_strtoupper( $string ); 
	}
	
	/**
		Sanitizes the name of a tab.
	
		@param		$string		String to sanitize.
	**/
	public function tab_slug( $string )
	{
		return $this->slug( $string );
	}
	
	/**
		Displays Wordpress tabs.
		
		@param		$options		See options.
	**/
	public function tabs( $options )
	{
		$options = array_merge(array(
			'count' =>			array(),			// Optional array of a strings to display after each tab name. Think: page counts.
			'default' => null,						// Default tab index.
			'descriptions' =>	array(),			// Descriptions (link titles) for each tab
			'display' => true,						// Display the tabs or return them.
			'display_tab_name' => true,				// If display==true, display the tab name.
			'display_before_tab_name' => '<h2>',	// If display_tab_name==true, what to display before the tab name.
			'display_after_tab_name' => '</h2>',	// If display_tab_name==true, what to display after the tab name.
			'functions' =>	array(),				// Array of functions associated with each tab name.
			'get_key' =>	'tab',					// $_GET key to get the tab value from.
			'page_titles' =>	array(),			// Array of page titles associated with each tab.
			'tabs' =>			array(),			// Array of tab names
			'valid_get_keys' => array(),			// Display only these _GET keys.
		), $options);
		
		$get = $_GET;						// Work on a copy of the _GET.
		$get_key = $options['get_key'];		// Convenience.
		
		// Is the default not set or set to something stupid? Fix it.
		if ( ! isset( $options['tabs'][ $options['default'] ] ) )
			$options['default'] = key( $options['tabs'] );
		
		// Select the default tab if none is selected.
		if ( ! isset( $get[ $get_key ] ) )
			$get[ $get_key ] = $options['default'];
		$selected = $get[ $get_key ];
		
		$options['valid_get_keys']['page'] = 'page';
		
		$rv = '';
		if ( count( $options['tabs'] ) > 1 )
		{
			$rv .= '<ul class="subsubsub">';
			$original_link = $_SERVER['REQUEST_URI'];

			foreach($get as $key => $value)
				if ( !in_array($key, $options['valid_get_keys']) )
					$original_link = remove_query_arg( $key, $original_link );
			
			$index = 0;
			foreach( $options['tabs'] as $tab_slug => $text )
			{
				// Make the link.
				// If we're already on that tab, just return the current url.
				if ( $get[ $get_key ]  == $tab_slug )
					$link = remove_query_arg( time() );
				else
				{
					if ( $tab_slug == $options['default'] )
						$link = remove_query_arg( $get_key, $original_link );
					else
						$link = add_query_arg( $get_key, $tab_slug, $original_link );
				}
				
				if ( isset( $options[ 'count' ][ $tab_slug ] ) )
					$text .= ' <span class="count">(' . $options['count'][ $tab_slug ] . ')</span>';
				
				$separator = ( $index+1 < count($options['tabs']) ? ' | ' : '' );
				
				$current = ( $tab_slug == $selected ? ' class="current"' : '' );
				
				if ($current)
					$selected_index = $tab_slug;
				
				$title = '';
				if ( isset( $options[ 'descriptions' ][ $tab_slug ] ) )
					$title = 'title="' . $options[ 'descriptions' ][ $tab_slug ] . '"';
				 
				$rv .= '<li><a'.$current.' '. $title .' href="'.$link.'">'.$text.'</a>'.$separator.'</li>';
				$index++;
			}
			$rv .= '</ul>';
		}
		
		if ( !isset($selected_index) )
			$selected_index = $options['default'];
		
		if ($options['display'])
		{
			ob_start();
			echo '<div class="wrap">';
			if ($options['display_tab_name'])
			{
				if ( isset( $options[ 'page_titles' ][ $selected_index ] ) )
					$page_title = $options[ 'page_titles' ][ $selected_index ];
				else
					$page_title = $options[ 'tabs' ][ $selected_index ];
				
				echo $options[ 'display_before_tab_name' ] . $page_title . $options[ 'display_after_tab_name' ];
			}
			echo $rv;
			echo '<div style="clear: both"></div>';
			if ( isset( $options[ 'functions' ][ $selected_index ] ) )
			{
				$functionName = $options[ 'functions' ][ $selected_index ];
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
			return $rv;
	}
	
	/**
		Returns the current time(), corrected for UTC and DST.

		@return		int							Current, corrected timestamp.
	**/
	public function time()
	{
		return current_time('timestamp');
	}
	
	/**
		@brief		Outputs the text in Wordpress admin's panel format.
		
		@param		$title
					H2 title to display.
		
		@param		$text
					Text to display.
		
		@return		HTML wrapped HTML.
	**/
	public function wrap( $text, $title )
	{
		echo "<h2>$title</h2>
			<div class=\"wrap\">
				$text
			</div>
		";
	}
}

