<?php
/**
	@brief		Base class for the Plainview Wordpress SDK.
	@details	Provides a framework with which to build Wordpress modules.
	@author		Edward Plainview	edward@plainview.se
	@license	GPL v3
	@version	20130430
	
	@par	Changelog

	- 2013-04-30	08:54	New: ABSPATH check on construct().
	- 2013-04-25	08:53	New: string_to_emails() convert a string of e-mails to an array. \n
					12:19	New: instance() to retrieve the current instance of the object. \
					20:14	Code: Lots of static, non-wordpress specific functions moved to \plainview\base.
	- 2013-04-24	15:57	Code: Removed array_to_object. \n
							Code: Lots of functions have become static.
	- 2013-04-23	16:56	Code: mime_type() is now static.
	- 2013-04-22	17:02	New: db_aware_object added.
	- 2013-04-16	13:45	New: Added sdk_version and sdk_version_required.
							New: Added sdk version check on __construct().
							New: activate(), deactivate() and uninstall() should no longer call parent::.
							Code cleanup.
	- 2013-04-10	21:21	Fix: Converted to plainview\wordpress SDK.
							Fix: Removed object_to_array
							Fix: send_mail options are far more underscored.
							Fix: Functions better documented.
							New: open_close_tag(), h1(), h2(), h3()
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
*/

namespace plainview\wordpress;

if ( ! class_exists( '\\plainview\\base' ) )
	require_once( dirname( __FILE__ ) . '/../sdk.php' );

if ( ! trait_exists( '\\plainview\\wordpress\\db_aware_object' ) )
	require_once( dirname( __FILE__ ) . '/db_aware_object.php' );

if ( class_exists( '\\plainview\\wordpress\\base' ) )
	return;

class base
	extends \plainview\base
{
	/**
		@brief		Stores whether this blog is a network blog.
		@since		20130416
		@var		$is_network
	**/
	protected $is_network;
	
	/**
		@brief		Text domain of .PO translation.		
		If left unset will be set to the base filename minus the .php
		@var		$language_domain
		@since		20130416
	**/ 
	protected $language_domain = ''; 

	/**
		@brief		Array of options => default_values that this plugin stores locally.
		@since		20130416
		@var		$local_options
	**/ 
	protected $local_options = array();

	/**
		@brief		Contains the paths to the plugin and other places of interest.
		
		The keys in the array are:
		
		name<br />
		filename<br />
		filename_from_plugin_directory<br />
		path_from_plugin_directory<br />
		path_from_base_directory<br /> 
		url<br />

		@since		20130416
		@var		$paths
	**/
	public $paths = array();
	
	/**
		@brief		The version of this SDK file.
		@since		20130416
		@var		$sdk_version
	**/ 
	protected $sdk_version = 20130425;
	
	/**
		@brief		Use this property in your extended class to require that the SDK is a specific version.
		@since		20130416
		@var		$sdk_version_required
	**/ 
	protected $sdk_version_required = 20000101;
	
	/**
		@brief		Links to Wordpress' database object.
		@since		20130416
		@var		$wpdb
	**/
	protected $wpdb;
	
	/**
		@brief		The list of the standard user roles in Wordpress.
		
		First an array of role_name => array
		
		And then each role is an array of name => role_name and current_user_can => capability.

		@since		20130416
		@var		$roles
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
		@brief		Array of options => default_values that this plugin stores sitewide.
		@since		20130416
		@var		$site_options
	**/ 
	protected $site_options = array();

	/**
		@brief		Construct the class.		
		@param		string		$filename		The __FILE__ special variable of the parent.
		@since		20130416
	**/
	public function __construct( $filename = null )
	{
		if ( ! defined( 'ABSPATH' ) )
			wp_die( 'ABSPATH is not defined!' );
		
		parent::__construct();
		
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

		if ( $this->sdk_version_required > $this->sdk_version )
			wp_die( sprintf( 'This plugin requires Plainview SDK version %s, but only %s is available.', $this->sdk_version_required, $this->sdk_version ) );
		
		register_activation_hook( $this->paths['filename_from_plugin_directory'],	array( $this, 'activate_internal') );
		register_deactivation_hook( $this->paths['filename_from_plugin_directory'],	array( $this, 'deactivate_internal') );
		
		add_action( 'admin_init', array(&$this, 'admin_init') );
	}
	
	/**
		@brief		Overridable activation function.
		@see		activate_internal()
		@since		20130416
	**/
	public function activate()
	{
	}
	
	/**
		@brief		Internal activation function.
		
		Child plugins should override activate().
		
		@since		20130416
	**/
	public function activate_internal()
	{
		$this->register_options();
		$this->activate();
	}
	
	/**
		@brief		Queues a submenu page for adding later.
		
		@details	Used to ensure alphabetic sorting of submenu pages independent of language.
		
		Uses the same parameters as Wordpress' add_submenu_page. Uses the menu title as the sorting key.
		
		After all pages have been add_submenu_page'd, call add_submenu_pages to actually sort and add them.
		
		@since		20130416
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
		@details	Will first sort by key and then add the subpages.		
		@since		20130416
	**/
	public function add_submenu_pages()
	{
		ksort( $this->submenu_pages );
		foreach( $this->submenu_pages as $submenu )
			call_user_func_array( 'add_submenu_page', $submenu );
	}
	
	/**
		@brief		Filter for admin_init. 		
		@since		20130416
	**/
	public function admin_init()
	{
		$class_name = get_class( $this );
		if ( isset($_POST[ $class_name ]['uninstall']) )
		{
			if ( isset($_POST[ $class_name ]['sure']) )
			{
				$this->uninstall_internal();
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
		@brief		Shows the uninstall form.
		@since		20130416
	**/
	public function admin_uninstall()
	{
		$form = $this->form();
		
		if (isset($_POST[ get_class($this) ]['uninstall']))
			if (!isset($_POST['sure']))
				$this->error_( 'You have to check the checkbox in order to uninstall the plugin.' );
		
		$nameprefix = '['.get_class($this).']';
		$inputs = array(
			'sure' => array(
				'name' => 'sure',
				'nameprefix' => $nameprefix,
				'type' => 'checkbox',
				'label' => $this->_( "Yes, I'm sure I want to remove all the plugin tables and settings." ),
			),
			'uninstall' => array(
				'name' => 'uninstall',
				'nameprefix' => $nameprefix,
				'type' => 'submit',
				'css_class' => 'button-primary',
				'value' => $this->_( 'Uninstall plugin' ),
			),
		);
		
		echo $form->start().'
			<p>' . $this->_( 'This page will remove all the plugin tables and settings from the database and then deactivate the plugin.' ) . '</p>

			<p>'.$form->make_input($inputs['sure']).' '.$form->make_label($inputs['sure']).'</p>

			<p>'.$form->make_input($inputs['uninstall']).'</p>
			
			'.$form->stop();
	}
	
	/**
		@brief		Overridable deactivation function.
		@see		deactivate_internal()
		@since		20130416
	**/
	public function deactivate()
	{
	}
	
	/**
		@brief		Internal function that runs when deactivating the plugin.		
		@since		20130416
	**/
	public function deactivate_internal()
	{
		$this->deactivate();
	}
	
	/**
		@brief		Deactivates the plugin.
		@since		20130416
	**/
	public function deactivate_me()
	{
		deactivate_plugins(array(
			$this->paths['filename_from_plugin_directory']
		));
	}
	
	/**
		@brief		Loads this plugin's language files.
		
		Reads the language data from the class's name domain as default.
		
		@param		$domain
					Optional domain.		
		@since		20130416
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
		@brief		Translate a string, if possible.
		
		Like Wordpress' internal _() method except this one automatically uses the plugin's domain.
		
		Can function like sprintf, if any %s are specified.
		
		@param		string		$string		String to translate. %s will require extra arguments to the method.		
		@since		20130416
		@return		string					Translated string, or the untranslated string.		
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
				throw new \Exception( sprintf(
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
		@brief		Internal function to handle uninstallation (database removal) of module.
		@since		20130416
	**/
	public function uninstall_internal()
	{
		$this->deregister_options();
		$this->uninstall();
	}
	
	/**
		@brief		Overridable uninstall method.
		@since		20130416
	**/
	public function uninstall()
	{
	}
	
	// -------------------------------------------------------------------------------------------------
	// ----------------------------------------- USER
	// -------------------------------------------------------------------------------------------------
	
	/**
		Returns the user's role as a string.
		@return					User's role as a string.
		@since		20130416
	**/
	public function get_user_role()
	{
		foreach( $this->roles as $role )
			if ( current_user_can( $role['current_user_can'] ) )
				return $role['name'];
	}
	
	/**
		Returns the user roles as a select options array.
		@return		The user roles as a select options array.
		@since		20130416
	**/ 
	public function roles_as_options()
	{
		$r = array();
		if ( function_exists( 'is_super_admin' ) )
			$r['super_admin'] = $this->_( 'Super admin' );
		foreach( $this->roles as $role )
			$r[ $role[ 'name' ] ] = __( ucfirst( $role[ 'name' ] ) );		// See how we ask WP to translate the roles for us? See also how it doesn't. Sometimes.
		return $r;
	}
	
	/**
		Checks whether the user's role is at least $role.
		
		@param		$role		Role as string.
		@return					True if role is at least $role.
		@since		20130416
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
		@since		20130416
	**/
	public function user_id()
	{
		global $current_user;
		get_current_user();
		return $current_user->ID;
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
		@since		20130416
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
		@since		20130416
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
		@since		20130416
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
		@since		20130416
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
		@since		20130416
	**/
	public function error_( $string, $args = '' )
	{
		$args = func_get_args();
		$string = call_user_func_array( array( &$this, '_' ), $args );
		return $this->error( $string );
	}
		
	// -------------------------------------------------------------------------------------------------
	// ----------------------------------------- OPTIONS
	// -------------------------------------------------------------------------------------------------
	
	/**
		Deletes a site option.
		
		If this is a network, the site option is preferred.
		
		@param		$option		Name of option to delete.
		@since		20130416
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
		@since		20130416
	**/
	public function delete_local_option($option)
	{
		$option = $this->fix_option_name($option);
		delete_option($option);
	}
	
	/**
		Deletes a site option.
		
		@param		$option		Name of option to delete.
		@since		20130416
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
		@since		20130416
	**/
	public function fix_option_name($option)
	{
		return $this->paths['name'] . '_' . $option;
	}
	
	/**
		@brief		Removes all the options this plugin uses.
		@since		20130416
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
		@since		20130416
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
		@since		20130416
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
		@since		20130416
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
		@since		20130416
	**/
	public function register_options()
	{
/*
		foreach($this->options as $option=>$value)
		{
			if ($this->get_option($option) === false)
				$this->update_option($option, $value);
		}
*/
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
		@since		20130416
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
		@since		20130416
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
		@since		20130416
	**/
	public function update_site_option($option, $value)
	{
		$option = $this->fix_option_name($option);
		update_site_option($option, $value);
	}
	
	// -------------------------------------------------------------------------------------------------
	// ----------------------------------------- SQL
	// -------------------------------------------------------------------------------------------------
	
	/**
		Sends a query to wpdb and return the results.
		
		@param		$query		The SQL query.
		@param		$wpdb		An optional, other WPDB if the standard $wpdb isn't good enough for you.
		@return		array		The rows from the query.
		@since		20130416
	**/
	public function query($query , $wpdb = null)
	{
		if ( $wpdb === null )
			$wpdb = $this->wpdb;
		$results = $wpdb->get_results( $query, 'ARRAY_A' );
		return (is_array($results) ? $results : array());
	}
	
	/**
		Fire an SQL query and return the results only if there is one row result.
		
		@param		$query			The SQL query.
		@return						Either the row as an array, or false if more than one row.
		@since		20130416
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
		@since		20130416
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
		@since		20130416
	**/
	public function sql_encode( $object )
	{
		return base64_encode( serialize($object) );
	}
	
	/**
		Converts a base64 encoded, serialized string back into an object.
		@param		$string			Serialized, base64-encoded string.
		@return						Object, if possible.
		@since		20130416
	**/
	public function sql_decode( $string )
	{
		return unserialize( base64_decode($string) );
	}
	
	/**
		Returns whether a table exists.
		
		@param		$table_name		Table name to check for.
		@return						True if the table exists.
		@since		20130416
	**/
	public function sql_table_exists( $table_name )
	{
		$query = "SHOW TABLES LIKE '$table_name'";
		$result = $this->query( $query );
		return count($result) > 0;
	}
	
	// -------------------------------------------------------------------------------------------------
	// ----------------------------------------- TOOLS
	// -------------------------------------------------------------------------------------------------
	
	/**
		@brief		Display the time ago as human-readable string.
		@param		$time_string	"2010-04-12 15:19"
		@param		$time			An optional timestamp to base time difference on, if not now.
		@return						"28 minutes ago"
		@since		20130416
	**/
	public static function ago($time_string, $time = null)
	{
		if ($time_string == '')
			return '';
		if ( $time === null )
			$time = current_time('timestamp');
		$diff = human_time_diff( strtotime($time_string), $time );
		return '<span title="'.$time_string.'">' . sprintf( __('%s ago'), $diff) . '</span>';
	}
	
	/**
		@brief		Generate a wordpress check column in a table body row.
		
		@details	The options array is:
		
		- @e form @b [form]					Optional \plainview\wordpress\form to use, instead of creating a new one.
		- @e nameprefix @b [nameprefix]		Optional \plainview\wordpress\form name prefix. Default is '[cb]'.
		- @e row @b [row]					Optional \plainview\wordpress\table\row into which to add the check column.
		
		@param		array		$options		Options array.
		@return		mixed						Either nothing, if a table_row was supplied, or a string.
		@since		20130416
	**/
	public function check_column_body( $options )
	{
		$o = self::merge_objects( array(
			'form' => $this->form(),
			'nameprefix' => '[cb]',
			'row' => null,
			'type' => 'checkbox',
		), $options );
		
		if ( ! isset( $o->label ) )
			$o->label = $o->name;
		
		$form = $o->form;		// Conv
		
		// If there is a supplied row, use it.
		if ( $o->row !== null )
		{
			$text = $form->make_input( (array)$o );
			$text .= '<span class="screen-reader-text">' . $form->make_label( (array)$o ) . '</span>';
			
			$o->row->th()->css_class( 'check-column' )->attribute( 'scope', 'row' )->text( $text );
			return; 
		}
		
		// Else return a manual table th.
		return '
			<th scope="row" class="check-column">
				' . $form->make_input( (array)$o ) . '
				<span class="screen-reader-text">' . $form->make_label( (array)$o ) . '</span>
			</th>
		';
	}
	
	/**
		@brief		Generate a wordpress check column in a table head.
		
		The options array is:
		- @e row @b [row]					Optional \plainview\wordpress\table\row into which to add the check column.
		
		@param		array		$options		Options array.
		@return		mixed						Either nothing, if a table_row was supplied, or a string.
		@since		20130416
	**/
	public function check_column_head( $options = array() )
	{
		$o = self::merge_objects( array(
			'row' => null,
		), $options );
		
		$selected = array(
			'name' => 'check',
			'type' => 'checkbox',
		);
		
		$form = $this->form();
		
		// If there is a supplied table_row, use that.
		if ( $o->row !== null )
		{
			$text = $form->make_input( $selected ) . '<span class="screen-reader-text">' . $this->_('Selected') . '</span>';
			$o->row->th()->css_class( 'check-column' )->text( $text );
		}
		
		// Else return a manual table th.
		return '<th class="check-column">' . $form->make_input( $selected ) . '<span class="screen-reader-text">' . $this->_('Selected') . '</span></th>';
	}
	
	/**
		@brief		Displays an array of inputs using Wordpress table formatting.
		@param		array		$inputs		Array of \plainview\wordpress\form inputs.
		@param		array		$options	Array of options.
		@since		20130416
	**/
	public function display_form_table( $inputs, $options = array() )
	{
		$options = \plainview\base::merge_objects( array(
			'form' => null,
			'header' => '',
			'header_level' => 'h3',
		), $options );
		
		$r = '';
		
		if ( $options->form === null )
			$options->form = $this->form();
		
		$table = $this->table()->set_attribute( 'class', 'form-table' );
			
		foreach( $inputs as $name => $input )
		{
			if ( ! isset( $input[ 'name' ] ) )
				$input[ 'name' ] = $name;
			
			if ( $input[ 'type' ] == 'hidden' )
			{
				$r .= $options['form']->make_input( $input );
				continue;
			}
			$o = new \stdClass();
			$o->input = $input;
			$o->form = $options->form;
			
			if ( $input[ 'type' ] == 'markup' )
			{
				$table->body()->row()->td()->attr( 'colspan', 2 )->text( $options->form->make_input( $input ) );
				continue;
			}
			
			$table->body()->row()
				->th()->text( $options->form->make_label( $input ) )->row()
				->td()->textf( '<div class="input_itself">%s</div><div class="input_description">%s</div>',
					$options->form->make_input( $input ),
					$options->form->make_description( $input )
				);
		}
		
		if ( $options->header != '' )
			$r .= sprintf( '<%s>%s</%s>',
				$options->header_level,
				$options->header,
				$options->header_level
			);
		
		$r .= $table;
		
		return $r;
	}
	
	/**
		@brief		Output a file to the browser for downloading.
		@param		string		$file			Path to file on disk.
		@param		string		$name			Downloaded file's name.
		@param		string		$mime_type		Optional mime_type.
		@author		http://w-shadow.com/blog/2007/08/12/how-to-force-file-download-with-php/
		@since		20130416
		@todo		Have another look at this some time...
	**/
	public function download( $filepath, $options = array() )
	{
		if ( ! is_readable( $filepath ) )
			throw new \Exception( "The file $filepath could not be read!" );
		
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
			throw new \Exception( "File $filepath could not be opened for reading!" );
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

	/**
		@brief		Convenience function to call apply_filters.
		@details	Has same parameters as apply filters. Will insert a null if there are no arguments.
		@since		20130416
	**/
	public static function filters()
	{
		$args = func_get_args();
		
		if ( count( $args ) < 2 )
			$args[] = null;
		
		return call_user_func_array( 'apply_filters', $args );
	}
	
	/**
		@brief		Creates a new form.
		@param		array		$options	Default options to send to the form constructor.
		@return		object					A new \plainview\wordpress\form object.
		@since		20130416
	**/ 
	public function form($options = array())
	{
		$options = array_merge($options, array('language' => preg_replace('/_.*/', '', get_locale())) );
		
		if ( ! class_exists( '\\plainview\\wordpress\\form' ) )
			require_once( 'form.php' );
		
		return new \plainview\wordpress\form( $options );
	}
	
	/**
		@brief		Create a PHPmailer object.
		@return		\\plainview\\mail\\mail		Mail object.
	**/
	public static function mail()
	{
		return parent::mail();
	}
	
	/**
		@brief		Returns WP's current timestamp (corrected for UTC)	
		@return		string		Current timestamp in MYSQL datetime format.
		@since		20130416
	**/
	public static function now()
	{
		return date('Y-m-d H:i:s', current_time('timestamp'));
	}
		
	/**
		@brief		wpautop's a string, using sprintf to replace arguments.
		@param		string		$string		String to wpautop.
		@param		mixed		$args		Optional arguments to sprintf.
		@return		The wpautop'd sprintf string.
		@since		20130416
	**/
	public function p( $string, $args = '' )
	{
		$args = func_get_args();
		return wpautop( call_user_func_array( 'sprintf', $args ) );
	}
	
	/**
		@brief		Translate and wpautop a string, using sprintf to replace arguments.
		@param		string		$string		String to translate and then wpautop.
		@param		mixed		$args		Optional arguments to sprintf.
		@return		The translated, wpautop'd string.
		@since		20130416
	**/
	public function p_( $string, $args = '' )
	{
		$args = func_get_args();
		return wpautop( call_user_func_array( array( &$this, '_'), $args ) );
	}
	
	/**
		@brief		Sends mail via SMTP.
		@param		array		$mail_data					Mail data.
		@since		20130416
	**/
	public function send_mail( $mail_data )
	{
		require_once ABSPATH . WPINC . '/class-phpmailer.php';
		$mail = new \PHPMailer();
		
		// Mandatory
		$from_email		= key( $mail_data[ 'from' ] );
		$from_name		= reset( $mail_data[ 'from' ] );
		$mail->From		= $from_email;
		$mail->FromName	= $from_name;
		$mail->Sender	= $from_email;
		$mail->Subject  = $mail_data[ 'subject' ];
		
		if ( isset( $mail_data[ 'to' ] ) )
			foreach( $mail_data[ 'to' ] as $email => $name )
			{
				if ( is_int( $email) )
					$email = $name;
				$mail->AddAddress( $email, $name );
			}
			
		if ( isset( $mail_data[ 'cc' ] ) )
			foreach( $mail_data[ 'cc' ] as $email => $name )
			{
				if ( is_int( $email) )
					$email = $name;
				$mail->AddCC( $email, $name );
			}
	
		if ( isset( $mail_data[ 'bcc' ] ) )
			foreach( $mail_data[ 'bcc' ] as $email => $name )
			{
				if ( is_int( $email) )
					$email = $name;
				$mail->AddBCC( $email, $name );
			}
			
		if ( isset( $mail_data[ 'body_html' ] ) )
			$mail->MsgHTML( $mail_data[ 'body_html' ] );
	
		if ( isset( $mail_data[ 'body' ] ) )
			$mail->Body = $mail_data[ 'body' ];
		
		if ( isset( $mail_data[ 'attachments' ] ) )
			foreach( $mail_data[ 'attachments' ] as $filepath => $filename )
			{
				$encoding = 'base64';
				$mime_type = self::mime_type ( $filepath );
	
				if ( is_numeric( $filepath ) )
					$mail->AddAttachment( $filename, '', $encoding, $mime_type );
				else
					$mail->AddAttachment( $attachment, $filepath, $encoding, $mime_type );
			}
	
		if ( isset( $mail_data[ 'reply_to' ] ) )
		{
			foreach( $mail_data[ 'reply_to' ] as $email => $name )
			{
				if ( is_int( $email) )
					$email = $name;
				$mail->AddReplyTo( $email, $name );
			}
		}
				
		// Seldom used settings...
		
		if ( isset( $mail_data[ 'wordwrap' ] ) )
			$mail->WordWrap = $mail_data[ 'wordwrap' ];
	
		if ( isset( $mail_data[ 'confirm_reading_to' ] ) )
			$mail->ConfirmReadingTo = true;
		
		if ( isset( $mail_data[ 'single_to' ] ) )
		{
			$mail->SingleTo = true;
			$mail->SMTPKeepAlive = true;
		}
		
		if ( isset( $mail_data[ 'SMTP' ] ) )									// SMTP? Or just plain old mail()
		{
			$mail->IsSMTP();
			$mail->Host	= $mail_data[ 'smtp_server' ];
			$mail->Port = $mail_data[ 'smtp_port' ];
		}
		else
			$mail->IsMail();
		
		if ( isset( $mail_data[ 'charset' ] ) )
			$mail->CharSet = $mail_data[ 'charset' ];
		else
			$mail->CharSet = 'UTF-8';
		
		if ( isset( $mail_data[ 'content_type' ] ) )
			$mail->ContentType  = $mail_data[ 'content_type' ];
		
		if ( isset( $mail_data[ 'encoding' ] ) )
			$mail->Encoding  = $mail_data[ 'encoding' ];
		
		// Done setting up.
		
		if ( !$mail->Send() )
			$r = $mail->ErrorInfo;
		else 
			$r = true;
			
		$mail->SmtpClose();
		
		return $r;		
	}
	
	/**
		@brief		Sanitizes (slugs) a string.
		@param		string		$string		String to sanitize.
		@return		string					Sanitized string.
		@since		20130416
	**/
	public static function slug( $string )
	{
		return sanitize_title( $string );
	}
	
	/**
		@brief		Sanitizes the name of a tab.
	
		@param		string		$string		String to sanitize.
		@return		string					Sanitized string.
		@since		20130416
	**/
	public static function tab_slug( $string )
	{
		return self::slug( $string );
	}
	
	/**
		@brief		Creates a new table.
		@return		object		A new \plainview\wordpress\table object.
		@since		20130416
	**/ 
	public function table()
	{
		if ( ! class_exists( '\\plainview\\wordpress\\table' ) )
			require_once( 'table.php' );
		
		$table = new \plainview\wordpress\table\table( $this );
		$table->css_class( 'widefat' );
		return $table;
	}
	
	/**
		@brief		Displays Wordpress tabs.
		@param		array		$options		See options.
		@since		20130416
	**/
	public function tabs( $options )
	{
		$options = $this->merge_objects(array(
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
		$get_key = $options->get_key;		// Convenience.
		
		// Is the default not set or set to something stupid? Fix it.
		if ( ! isset( $options->tabs[ $options->default ] ) )
			$options->default = key( $options->tabs );
		
		// Select the default tab if none is selected.
		if ( ! isset( $get[ $get_key ] ) )
			$get[ $get_key ] = $options->default;
		$selected = $get[ $get_key ];
		
		$options->valid_get_keys['page'] = 'page';
		
		$r = '';
		if ( count( $options->tabs ) > 1 )
		{
			$r .= '<ul class="subsubsub">';
			$original_link = $_SERVER['REQUEST_URI'];

			foreach($get as $key => $value)
				if ( !in_array($key, $options->valid_get_keys) )
					$original_link = remove_query_arg( $key, $original_link );
			
			$index = 0;
			foreach( $options->tabs as $tab_slug => $text )
			{
				// Make the link.
				// If we're already on that tab, just return the current url.
				if ( $get[ $get_key ]  == $tab_slug )
					$link = remove_query_arg( time() );
				else
				{
					if ( $tab_slug == $options->default )
						$link = remove_query_arg( $get_key, $original_link );
					else
						$link = add_query_arg( $get_key, $tab_slug, $original_link );
				}
				
				if ( isset( $options->count[ $tab_slug ] ) )
					$text .= ' <span class="count">(' . $options->count[ $tab_slug ] . ')</span>';
				
				$separator = ( $index+1 < count($options->tabs) ? ' | ' : '' );
				
				$current = ( $tab_slug == $selected ? ' class="current"' : '' );
				
				if ($current)
					$selected_index = $tab_slug;
				
				$title = '';
				if ( isset( $options->descriptions[ $tab_slug ] ) )
					$title = 'title="' . $options->descriptions[ $tab_slug ] . '"';
				 
				$r .= '<li><a'.$current.' '. $title .' href="'.$link.'">'.$text.'</a>'.$separator.'</li>';
				$index++;
			}
			$r .= '</ul>';
		}
		
		if ( !isset($selected_index) )
			$selected_index = $options->default;
		
		if ($options->display)
		{
			ob_start();
			echo '<div class="wrap">';
			if ($options->display_tab_name)
			{
				if ( isset( $options->page_titles[ $selected_index ] ) )
					$page_title = $options->page_titles[ $selected_index ];
				else
					$page_title = $options->tabs[ $selected_index ];
				
				echo $options->display_before_tab_name . $page_title . $options->display_after_tab_name;
			}
			echo $r;
			echo '<div style="clear: both"></div>';
			if ( isset( $options->functions[ $selected_index ] ) )
			{
				$functionName = $options->functions[ $selected_index ];
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
			return $r;
	}
	
	/**
		@brief		Returns the current time(), corrected for UTC and DST.
		@return		int		Current, corrected timestamp.
		@since		20130416
	**/
	public static function time()
	{
		return current_time('timestamp');
	}
	
	/**
		@brief		Outputs the text in Wordpress admin's panel format.
		@details	To remember the correct parameter order: wrap THIS in THIS.
		@param		string		$title		H2 title to display.
		@param		string		$text		Text to display.
		@return		HTML wrapped HTML.
		@since		20130416
	**/
	public static function wrap( $text, $title )
	{
		echo "<h2>$title</h2>
			<div class=\"wrap\">
				$text
			</div>
		";
	}
}

