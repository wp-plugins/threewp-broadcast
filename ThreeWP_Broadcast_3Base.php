<?php
/**
 * Base class with some common functions.
 * 
 * Version 2011-01-25 00:39
 
 2011-01-25	13:14	load_language assumes filename as domain.
 2011-01-25	13:14	loadLanguages -> load_language.
 */
class ThreeWP_Broadcast_3Base
{
	protected $wpdb;							// Link to Wordpress' database class.
	protected $isNetwork;						// Stores whether this blog is a network blog.
	protected $paths = array();					// Contains paths to the plugin and such. 
	protected $options = array();				// The options this module uses. (optionName => defaultValue). Deprecated
	protected $site_options = array();			// Site options (sitewide)
	protected $local_options = array();			// Local options

	protected $language_domain = '';			// The domain of the loaded languages. If left unset will be set to the base filename minus the .php

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
		$this->is_network = MULTISITE;
		$this->isNetwork = $this->is_network;
		
		$this->paths = array(
			'name' => get_class($this),
			'filename' => basename($filename),
			'filename_from_plugin_directory' => basename(dirname($filename)) . '/' . basename($filename),
			'path_from_plugin_directory' => basename(dirname($filename)),
			'path_from_base_directory' => PLUGINDIR . '/' . basename(dirname($filename)),
			'url' => WP_PLUGIN_URL . '/' . basename(dirname($filename)),
		);
		
		add_action( 'admin_init', array(&$this, 'adminUninstall_post') );
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
	}
	
	protected function deactivate_me()
	{
		deactivate_plugins(array(
			$this->paths['filename_from_plugin_directory']
		));
	}
	
	/**
	 * Uninstall function that is inherited.
	 */
	protected function uninstall()
	{
		$this->deregister_options();
	}
	
	/**
	 * Handles post data
	 */
	public function adminUninstall_post()
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
	 * Shows uninstall form.
	 */
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
	 * Loads this plugin's language files.
	 */
	protected function load_language($domain = '')
	{
		if ( $domain != '')
			$this->language_domain = $domain;
		
		if ($this->language_domain == '')
			$this->language_domain = str_replace( '.php', '', $this->paths['filename'] );
		load_plugin_textdomain($this->language_domain, false, $this->paths['path_from_plugin_directory'] . '/lang');
	}
	
	protected function _($string)
	{
		return __( $string, $this->language_domain );
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
	 * Fire an SQL query and return the results only if there is one row result.
	 */
	protected function query_single($query)
	{
		$results = $this->wpdb->get_results($query, 'ARRAY_A');
		if ( count($results) != 1)
			return false;
		return $results[0];
	}
	
	/**
	 * Fire an SQL query and return the row ID of the inserted row.
	 */
	protected function query_insert_id($query)
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
	 * Is the user's role at least 'super_admin', 'administrator', etc...
	 */
	protected function role_at_least($role)
	{
		if ($role == '')
			return true;

		if ($role == 'super_admin')
			if (function_exists('is_super_admin'))
				return is_super_admin();
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
	protected function form($options = array())
	{
		$options = array_merge($options, array('language' => preg_replace('/_.*/', '', get_locale())) );
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
	protected function fix_option_name($option)
	{
		return $this->paths['name'] . '_' . $option;
	}
	
	/**
	 * Gets a [site] option.
	 */
	protected function get_option($option)
	{
		$option = $this->fix_option_name($option);
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
		$option = $this->fix_option_name($option);
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
		$option = $this->fix_option_name($option);
		if ($this->isNetwork)
			delete_site_option($option);
		else
			delete_option($option);
	}
	
	/**
	 * Gets a local option.
	 */
	protected function get_local_option($option)
	{
		$option = $this->fix_option_name($option);
		return get_option($option);
	}
	
	/**
	 * Updates a local option.
	 */
	protected function update_local_option($option, $value)
	{
		$option = $this->fix_option_name($option);
		update_option($option, $value);
	}
	
	/**
	 * Deletes a local option.
	 */
	protected function delete_local_option($option)
	{
		$option = $this->fix_option_name($option);
		delete_option($option);
	}
	
	/**
	 * Gets a site option.
	 */
	protected function get_site_option($option)
	{
		$option = $this->fix_option_name($option);
		return get_site_option($option);
	}
	
	/**
	 * Updates a site option.
	 */
	protected function update_site_option($option, $value)
	{
		$option = $this->fix_option_name($option);
		update_site_option($option, $value);
	}
	
	/**
	 * Deletes a site option.
	 */
	protected function delete_site_option($option)
	{
		$option = $this->fix_option_name($option);
		delete_site_option($option);
	}
	
	/**
	 * Registers all the options this plugin uses.
	 */
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

		if ($this->isNetwork)
			foreach($this->site_options as $option=>$value)
			{
				$option = $this->fix_option_name($option);
				if (get_site_option($option) === false)
					update_site_option($option, $value);
			}
	}
	
	/**
	 * Removes all options this plugin uses.
	 */
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

		if ($this->isNetwork)
			foreach($this->site_options as $option=>$value)
			{
				$option = $this->fix_option_name($option);
				delete_site_option($option);
			}
	}
	
	// -------------------------------------------------------------------------------------------------
	// ----------------------------------------- MESSAGES
	// -------------------------------------------------------------------------------------------------
	
	/**
	 * Displays a message.
	 * 
	 * Autodetects HTML.
	 */
	public function displayMessage($type, $string)
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
	public function error($string)
	{
		$this->displayMessage('error', $string);
	}
	
	/**
	 * Displays a information message.
	 * 
	 * Text or HTML is autodetected.
	 */
	public function message($string)
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
			'valid_get_keys' => array(),			// Display only these _GET keys.
			'default' => 0,							// Default tab index.
		), $options);
		
		$getKey = $options['getKey'];			// Convenience.
		if (!isset($_GET[$getKey]))	// Select the default tab if none is selected.
			$_GET[$getKey] = sanitize_title( $options['tabs'][$options['default']] );
		$selected = $_GET[$getKey];
		
		$options['valid_get_keys']['page'] = 'page';
		
		$returnValue = '';
		if (count($options['tabs'])>1)
		{
			$returnValue .= '<ul class="subsubsub">';
			$link = $_SERVER['REQUEST_URI'];

			foreach($_GET as $key => $value)
				if ( !in_array($key, $options['valid_get_keys']) )
					$link = remove_query_arg($key, $link);
			
			foreach($options['tabs'] as $index=>$tab)
			{
				$slug = $this->tab_slug($tab);
				$link = ($index == $options['default'] ? self::urlMake($getKey, null, $link) : self::urlMake($getKey, $slug, $link));
				
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
		
		if ( !isset($selectedIndex) )
			$selectedIndex = $options['default'];
		
		if ($options['display'])
		{
			ob_start();
			echo '<div class="wrap">';
			if ($options['displayTabName'])
				echo $options['displayBeforeTabName'] . $options['tabs'][$selectedIndex] . $options['displayAfterTabName'];
			echo $returnValue;
			echo '<div style="clear: both"></div>';
			if (isset($options['functions'][$selectedIndex]))
			{
				$functionName = $options['functions'][$selectedIndex];
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
	**/
	protected function tab_slug($name)
	{
		return sanitize_title($name);
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
	
	protected function object_to_array($object)
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
	
	protected function ago($time_string)
	{
		if ($time_string == '')
			return '';
		$diff = human_time_diff( strtotime($time_string), current_time('timestamp') );
		return '<span title="'.$time_string.'">' . sprintf( __('%s ago'), $diff) . '</span>';
	}
	
	/**
		Returns WP's current timestamp (corrected for UTC)
	*/
	protected function now()
	{
		return date('Y-m-d H:i:s', current_time('timestamp'));
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
		See send_mail
	**/
	public function sendMail($mailData)
	{
		$this->send_mail($mailData);
	}

	/**
	 * Sends mail via SMTP.
	 * 
	*/
	public function send_mail($mail_data)
	{
		require_once ABSPATH . WPINC . '/class-phpmailer.php';
		$mail = new PHPMailer();
		
		// Mandatory
		$mail->From		= key($mail_data['from']);
		$mail->FromName	= current($mail_data['from']);
		
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
			
		if (isset($mail_data['bodyhtml']))
			$mail->MsgHTML($mail_data['bodyhtml'] );
	
		if (isset($mail_data['body']))
			$mail->Body = $mail_data['body'];
		
		if (isset($mail_data['attachments']))
			foreach($mail_data['attachments'] as $attachment=>$filename)
				if (is_numeric($attachment))
					$mail->AddAttachment($filename);
				else
					$mail->AddAttachment($attachment, $filename);
				
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
			
		$mail->CharSet = 'UTF-8';
		
		// Done setting up.
				
		if(!$mail->Send())
			$returnValue = $mail->ErrorInfo;
		else 
			$returnValue = true;
			
		$mail->SmtpClose();
		
		return $returnValue;		
	}
}
?>