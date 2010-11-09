<?php
/**
 * Base class with some common functions.
 * 
 * Version 2010-07-04 15:55
 */
class ThreeWP_Base_Broadcast
{
	protected $wpdb;							// Link to Wordpress' database class.
	protected $isNetwork;						// Stores whether this blog is a network blog.
	protected $paths = array();					// Contains paths to the plugin and such. 
	protected $options = array();				// The options this module uses. (optionName => defaultValue)

	/**
	 * List of wordpress user roles.
	 */
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

	public function __construct($filename)
	{
		global $wpdb;
		$this->wpdb = $wpdb;
		$this->isNetwork = function_exists('is_site_admin');
		
		$this->paths = array(
			'name' => get_class($this),
			'filename' => basename($filename),
			'filename_from_plugin_directory' => basename(dirname($filename)) . '/' . basename($filename),
			'path_from_plugin_directory' => basename(dirname($filename)),
			'path_from_base_directory' => PLUGINDIR . '/' . basename(dirname($filename)),
			'url' => WP_PLUGIN_URL . '/' . basename(dirname($filename)),
		);
	}
	
	/**
	 * Does nothing. Like the goggles.
	 * 
	 * It's here in case I find a good use for it in the future.
	 */
	protected function activate()
	{
	}
	
	/**
	 * Deactivates this plugin.
	 */
	protected function deactivate()
	{
		deactivate_plugins($this->paths['filename_from_plugin_directory']);
	}
	
	/**
	 * Uninstall function that is inherited.
	 */
	protected function uninstall()
	{
	}
	
	/**
	 * Shows uninstall form.
	 */
	protected function adminUninstall()
	{
		$form = $this->form();
		
		if (isset($_POST['uninstall']))
		{
			if (!isset($_POST['sure']))
				$this->error('You have to check the checkbox in order to uninstall the plugin.');
			else
			{
				$this->uninstall();
				$this->message('Plugin has been uninstalled and deactivated.');
				$this->deactivate();
			}
		}
		
		$inputs = array(
			'sure' => array(
				'name' => 'sure',
				'type' => 'checkbox',
				'label' => "Yes, I'm sure I want to remove all the plugin tables and settings.",
			),
			'uninstall' => array(
				'name' => 'uninstall',
				'type' => 'submit',
				'cssClass' => 'button-primary',
				'value' => 'Uninstall plugin',
			),
		);
		
		echo '
			'.$form->start().'
			<p>
				This page will remove all the plugin tables and settings from the database and then deactivate the plugin.
			</p>

			<p>
				'.$form->makeInput($inputs['sure']).' '.$form->makeLabel($inputs['sure']).'
			</p>

			<p>
				'.$form->makeInput($inputs['uninstall']).'
			</p>
			'.$form->stop().'
		';
	}
	
	/**
	 * Loads this plugin's language files.;
	 */
	protected function loadLanguages($domain)
	{
		load_plugin_textdomain($domain, false, $this->paths['path_from_plugin_directory'] . '/lang');
	}
	
	// -------------------------------------------------------------------------------------------------
	// ----------------------------------------- SQL
	// -------------------------------------------------------------------------------------------------
	
	/**
	 * Fire an SQL query and return the results in an array.
	 */
	protected function query($query)
	{
		$results = $this->wpdb->get_results($query, 'ARRAY_A');
		return (is_array($results) ? $results : array());
	}
	
	/**
	 * Fire an SQL query and return the row ID of the inserted row.
	 */
	protected function queryInsertID($query)
	{
		$this->wpdb->query($query);
		return $this->wpdb->insert_id;
	}
	
	// -------------------------------------------------------------------------------------------------
	// ----------------------------------------- USER
	// -------------------------------------------------------------------------------------------------
	
	/**
	 * Returns the user's role as a string.
	 */
	protected function get_user_role()
	{
		foreach($this->roles as $role)
			if (current_user_can($role['current_user_can']))
				return $role['name'];
	}
	
	/**
	 * Is the user's role at least 'site_admin', 'administrator', etc...
	 */
	protected function role_at_least($role)
	{
		if ($role == 'site_admin')
			if (function_exists('is_site_admin'))
			{
				if (is_site_admin())
					return true;
			}
			else
				return false;
		return current_user_can($this->roles[$role]['current_user_can']);
	}
	
	/**
	 * Return the user_id of the current user.
	 */
	protected function user_id()
	{
		global $current_user;
		get_current_user();
		return $current_user->ID;
	}
	
	/**
	 * Creats a new edwardForm.
	 */
	protected function form()
	{
		$options = array('language' => preg_replace('/_.*/', '', get_locale()));
		if (class_exists('edwardForm'))
			return new edwardForm($options);
		require_once('edwardForm.php');
		return new edwardForm($options);
	}
	
	// -------------------------------------------------------------------------------------------------
	// ----------------------------------------- OPTIONS
	// -------------------------------------------------------------------------------------------------
	
	/**
	 * Normalizes the name of an option.
	 */
	protected function fixOptionName($option)
	{
		return $this->paths['name'] . '_' . $option;
	}
	
	/**
	 * Gets a [site] option.
	 */
	protected function get_option($option)
	{
		$option = $this->fixOptionName($option);
		if ($this->isNetwork)
			return get_site_option($option);
		else
			return get_option($option);
	}
	
	/**
	 * Updates a [site] option.
	 */
	protected function update_option($option, $value)
	{
		$option = $this->fixOptionName($option);
		if ($this->isNetwork)
			update_site_option($option, $value);
		else
			update_option($option, $value);
	}
	
	/**
	 * Deletes a [site] option.
	 */
	protected function delete_option($option)
	{
		$option = $this->fixOptionName($option);
		if ($this->isNetwork)
			delete_site_option($option);
		else
			delete_option($option);
	}
	
	/**
	 * Registers all the options this plugin uses.
	 */
	protected function register_options()
	{
		foreach($this->options as $option=>$value)
			if ($this->get_option($option) === false)
				$this->update_option($option, $value);
	}
	
	/**
	 * Removes all options this plugin uses.
	 */
	protected function deregister_options()
	{
		foreach($this->options as $option=>$value)
			$this->delete_option($option);
	}
	
	// -------------------------------------------------------------------------------------------------
	// ----------------------------------------- MESSAGES
	// -------------------------------------------------------------------------------------------------
	
	/**
	 * Displays a message.
	 * 
	 * Autodetects HTML.
	 */
	protected function displayMessage($type, $string)
	{
		// If this string has html codes, then output it as it.
		$stripped = strip_tags($string);
		if (strlen($stripped) == strlen($string))
		{
			$string = explode("\n", $string);
			$string = implode('</p><p>', $string);
		}
		echo '<div class="'.$type.'"><p>'.$string.'</p></div>';
	}
	
	/**
	 * Displays an error message.
	 * 
	 * Text or HTML is autodetected.
	 */
	protected function error($string)
	{
		$this->displayMessage('error', $string);
	}
	
	/**
	 * Displays a information message.
	 * 
	 * Text or HTML is autodetected.
	 */
	protected function message($string)
	{
		$this->displayMessage('updated', $string);
	}
		
	// -------------------------------------------------------------------------------------------------
	// ----------------------------------------- TOOLS
	// -------------------------------------------------------------------------------------------------
	
	/**
		Replaces an existing &OPTION=VALUE pair from the uri.
		If value is NULL, will remove the option completely.
		If pair does not exist, the pair will be placed at the end of the uri.
		
		Examples:
			URLmake("sortorder", "name", "index.php?page=start")
			=> "index.php?page=start&sortorder=name"
			
			URLmake("sortorder", "name", "index.php?page=start&sortorder=date")
			=> "index.php?page=start&sortorder=name"
		
			URLmake("sortorder", null, "index.php?page=start&sortorder=date")
			=> "index.php?page=start"
		
			URLmake("page", null, "index.php?page=start&sortorder=date")
			=> "index.php?sortorder=date"
		
			URLmake("sortorder", "name", "index.php?page=start&sortorder=date&security=none")
			=> "index.php?page=start&security=none&sortorder=name"
	*/	
	public static function urlMake($option, $value = null, $url = null)
	{
		if ($url === null)
			$url = $_SERVER['REQUEST_URI'];
		
		$url = html_entity_decode($url);
		
		// Replace all ? with & and add an & at the end
		$url = preg_replace('/\?/', '&', $url);
		$url = preg_replace('/&+$/', '&', $url . '&');
		
		// Remove the value?
		if ($value === null)
		{
			// Remove the key
			$url = preg_replace('/&'.$option.'=?(.*)&/U', '&', $url);
		}
		else
		{
			$value = (string)$value;		// Else we have 0-problems.
			// Fix the value
			if ($value != '')
				$value = '=' . $value;
			// Does the key exist? Replace
			if (strpos($url, '&'.$option) !== false)
				$url = preg_replace('/&'.$option.'=(.*)&|&'.$option.'&/U', '&' . $option . $value . '&', $url);
			else	// Or append
				$url .= $option . $value . '&';
		}
		
		// First & becomes a question mark
		$url = preg_replace('/&(.*)&$/U', '?\1', $url);
		
		// Remove & at the end
		$url = preg_replace('/&$/', '', $url);

		return htmlentities($url);
	}
	
	/**
	 * Displays "tabs".
	 * 
	 * The tabs are similar to those links displayed when editing pages.
	 * 
	 * @param	array		$options			See options.
	 */
	protected function tabs($options)
	{
		$options = array_merge(array(
			'tabs' =>		array(),				// Array of tab names
			'functions' =>	array(),				// Array of functions associated with each tab name.
			'count' =>		array(),				// Optional array of a strings to display after each tab name. Think: page counts.
			'display' => true,						// Display the tabs or return them.
			'displayTabName' => true,				// If display==true, display the tab name.
			'displayBeforeTabName' => '<h2>',		// If displayTabName==true, what to display before the tab name.
			'displayAfterTabName' => '</h2>',		// If displayTabName==true, what to display after the tab name.
			'getKey' =>	'tab',						// $_GET key to get the tab value from.
			'default' => 0,							// Default tab index.
		), $options);
		
		$getKey = $options['getKey'];			// Convenience.
		if (!isset($_GET[$getKey]))	// Select the default tab if none is selected.
			$_GET[$getKey] = sanitize_title( $options['tabs'][$options['default']] );
		$selected = $_GET[$getKey];
		
		$returnValue = '';
		if (count($options['tabs'])>1)
		{
			$returnValue .= '<ul class="subsubsub">';
			foreach($options['tabs'] as $index=>$tab)
			{
				$slug = sanitize_title($tab);
				$link = ($index == $options['default'] ? self::urlMake($getKey, null) : self::urlMake($getKey, $slug));
				$text = $tab;
				if (isset($options['count'][$index]))
					$text .= ' <span class="count">(' . $options['count'][$index] . ')</span>';
					
				$separator = ($index+1 < count($options['tabs']) ? ' | ' : '');
				
				$current = ($slug == $selected ? ' class="current"' : '');
				
				if ($current)
					$selectedIndex = $index;
				 
				$returnValue .= '<li><a'.$current.' href="'.$link.'">'.$text.'</a>'.$separator.'</li>';
			}
			$returnValue .= '</ul>';
		}
		else
			$selectedIndex = 0;
		
		if ($options['display'])
		{
			if ($options['displayTabName'])
				echo $options['displayBeforeTabName'] . $options['tabs'][$selectedIndex] . $options['displayAfterTabName'];
			echo $returnValue;
			echo '<div style="clear: both"></div>';
			if (isset($options['functions'][$selectedIndex]))
			{
				$functionName = $options['functions'][$selectedIndex];
				$this->$functionName();
			}
		}
		else
			return $returnValue;
	}
	
	/**
	 * Make a value a key.
	 */
	protected function array_moveKey($array, $key)
	{
		$returnArray = array();
		foreach($array as $value)
			$returnArray[ $value[$key] ] = $value;
		return $returnArray;
	}
	
	protected function objectToArray($object)
	{
		if (is_array($object))
		{
			$returnValue = array();
			foreach($object as $o)
				$returnValue[] = get_object_vars($o);
			return $returnValue;
		}
		else
			return get_object_vars($object);
	}
}
?>