<?php
/**
	@brief	plainview\sdk\form is a XHTML form class that handles creation, display and validation of form elements and complete form layouts.
	@par	Using the class
	
	First, create the class:
	
	$form = new SD_Forms();
	
	During your display of the HTML:
	
	<pre>
	echo $form->start();
	</pre>
	
	Now you can build your forms using arrays as inputs.
	
	<pre>
	$input_text = array(
	  'name' => 'my_text_example',
	  'type' => 'text',
	  'size' => 60,  // recommended that you have any size specified
	  'css_class' => 'text_area_orange',  // optional
	  'css_style' => 'font-size: 500%;',  // optional
	  'value' => 'This is the default text displayed', // optional
	  'description' => 'This is a short, optional description. Note that you'll need to put divs around the description in order to do interesting stuff with it.',
	);
	</pre>	
	
	<pre>
	echo $form->make_label( $input_text ); // The accessible label
	echo $form->make_input( $input_text ); // The input itself
	echo $form->make_description( $input_text ); // displays only the description, including validation info.
	</pre>
	
	And to finish up the form after displaying:
	
	<pre>
	echo $form->stop();
	</pre>
	
	@par	Input types
	
	Most of the input types have the css_class, css_style, description, value fields in common.
			checkbox
	
	<pre>
	$input_checkbox = array(
	  'name' => 'checkbox_example',
	  'type' => 'checkbox',
	  'label' => 'Label of checkbox',
	  'checked' => true,
	);
	</pre>
	
	value, description is optional.
	
	
			file
	
	<pre>
	$input_file = array(
	  'name' => 'file_example',
	  'type' => 'file',
	  'value' => 'Text on button',
	);
	</pre>
	
			image
	
	An image is used instead of a submit button.
	
	<pre>
	$input_image = array(
	  'name' => 'image_example',
	  'type' => 'image',
	  'label' => 'ALT value of image'
	  'src' => 'images/submit.png',
	);
	</pre>
	
			password
	
	Similar to text, except that the value isn't displayed.
	
	<pre>
	$input_password = array(
	  'name' => 'password_example',
	  'type' => 'password',
	  'label' => 'Displayed label',
	);
	</pre>
	
			radio
	
	<pre>
	$input_radio = array(
	  'name' => 'radio_example',
	  'type' => 'radio
	  'label' => 'Label of radio box',
	  'options' => array(
		'option001' => 'First radio option',
		'option002' => 'Second radio option',
	  ),
	);
	</pre>
	
			select
	
	<pre>
	$input_select = array(
	  'name' => 'select_example',
	  'type' => 'select',
	  'label' => 'Label of select',
	  'multiple' => false,  // Allow selection of multiple values
	  'size' => 5,  // How many option rows to display.
	  'options' => array(
		'option001' => 'First select option',
		'option002' => 'Second select option',
	  ),
	);
	</pre>
	
	
	
			submit
	
	<pre>
	$input_submit = array(
	  'name' => 'submit_example',
	  'type' => 'submit',
	  'value' => 'Displayed on the button',
	);
	</pre>
	
	
	
			text
	
	<pre>
	$input_text = array(
	  'name' => 'text_example',
	  'type' => 'text
	  'label' => 'Label of text box',
	);
	</pre>
	
	
			textarea
	
	<pre>
	$input_textarea = array(
	  'name' => 'textarea_example',
	  'type' => 'textarea',
	  'label' => 'Label of text area',
	  'rows' => 80,
	  'cols' => 10,
	);
	</pre>
	
	@par	Validation
	
	To add validation to your input, add a key called validation and make it an array with any of the following keys:
	
		empty			Field is allowed to be empty
		minimum_value		Integer value: minimum
		maximum_value		Integer value: maximum
		datetime		Date must be in this format. (Use format keywords from date(). "Y-m-d").
		maximum_date		Latest date/time allowed. Any date() format.
		minimum_length		Length: minimum
		
	Validation example:
	
	$input_text = array(
	  'name' => 'text_example',
	  'type' => 'text
	  'label' => 'Label of text box',
	  'validation' => array(
		'empty' => true,
	  ),
	);
			
	@par	Changelog
	
	- 2013-04-24	New: use_post_values
	- 2013-04-10	Removed generate_changed_sql()
					Removed valid_email()
					Rawtext has become markup, with the markup itself in the markup property.
	- 2012-11-30	Required field span now has a class: required_field.
	- 2012-11-30	Form has ID
	- 2012-11-07	Relaxed select selected selection (ints and strings are treated equally).
	- 2012-11-06	Validation requires that each input is an array. Else the "input" is ignored.
	- 2012-04-14	make_input can make markup types.
	- 2011-08-08	More documentation.
	- 2011-08-01	More documentation.

	@author		Edward Plainview	edward.plainview@sverigedemokraterna.se
	@version	20130501

*/

namespace plainview;

class form
{
	private $options;
	
	private $default_options = array(
		'class' => '',
		'global_nameprefix' => '',
		'nameprefix' => '',
		'namesuffix' => '',
		'title' => '',
		'alt' => '',
		'value' => '',
		'multiple' => false,
		'size' => 1,
		'maxlength' => 255,
		'disabled' => false,
		'readonly' => false,
		'display_two_rows' => false,
		'style' => 'STYLE1',
		'css_class' => '',
		'css_style' => '',
		'form_method' => 'post',
		'form_action' => '',
		'form_id' => '',
		'description' => '',
	);
	
    protected $language_data = array();
	
	private $sections = array();
	
	function __construct( $options = array() )
	{
		$this->options = \plainview\sdk\base::merge_objects( $this->default_options, $options );
		$this->language_data = form_language::$language_data;
	}

	/**
		@brief		Cleans the name of an input. Removed illegal characters.
		@param		string		$name		Input name.
	**/
	public static function fix_name( $name )
	{
		return preg_replace( '/\[|\]| |\'|\\\|\?/', '_', $name );
	}
	
	/**
		@brief		Create an ID for this input.
	**/
	
	/**
	 * Returns a text ID of this input. 
	 */
	public function make_id( $options )
	{
		$options = \plainview\sdk\base::merge_objects( $this->options, $options );
		$options->name = self::fix_name( $options->name );

		// Add the global prefix
		$options->nameprefix = $this->options->global_nameprefix . $options->nameprefix;  
			
		return $options->class . '_' . preg_replace( '/\[|\]/', '_', $options->nameprefix ) .  '_' . $options->name .  $options->namesuffix;
	}
	
	/**
	 * Returns the name of this input. 
	 */
	public static function make_name($options)
	{
		$options->name = self::fix_name( $options->name) ;
		if ( $options->class!='')
			return $options->class . $options->global_nameprefix . $options->nameprefix . '[' . $options->name . ']';
		else
		{
			$rv = $options->global_nameprefix;

			// Remove the first [ and first ]. Yeah, that's how lovely forms work: the first prefix doesn't have brackets. The rest do.
			$rv .= ( $rv == '' ?
				preg_replace('/\[/', '',
					preg_replace('/\]/', '', $options->nameprefix, 1)
				, 1) : $options->nameprefix);
			$rv .= ( $rv == '' ? $options->name : '[' . $options->name . ']');
		}
		return $rv;
	}
	
	/**
	 * Returns a simple &lt;form&gt; tag (with action and method set).
	 */
	public function start()
	{
		$id = '';
		if ( $this->options->form_id != '' )
			$id = ' id="' . $this->options->form_id . '" '; 
		return '<form enctype="multipart/form-data" ' . $id . ' action="'.$this->options->form_action.'" method="'.$this->options->form_method.'">' . "\n";
	}
	
	/**
	 * Returns &lt;/form&gt;. That's it.
	 */
	public function stop()
	{
		return '</form>' . "\n";
	}
	
	/**
	 * Makes a small asterisk thingy that is supposed to symbolise that the field is required.
	 */
	private function make_required_field($options)
	{
		$rv = '';
		switch( $options->type )
		{
			case 'text':
			case 'textarea':
				$needed = false;
				if ( property_exists( $options, 'required' ) )
					$needed = true;
				if ( property_exists( $options, 'minimum_value' ) || property_exists( $options, 'maximum_value' ) )
					$needed = true;
				if ( $needed)
					$rv .= '<sup><span class="screen-reader-text aural-info"> (' . $this->_( 'Required field' ) . ')</span><span class="required_field" title="' . $this->_( 'Required field' ) . '">*</span></sup>';
			break;
			default:
			break;
		}
		return $rv;
	}
	
	/**
	 * Returns the label of this input.
	 */
	public function make_label( $options )
	{
		// Merge the given options with the options we were constructed with.
		$options = \plainview\sdk\base::merge_objects( $this->options, $options );
		
		if ( ! isset( $options->label ) )
			return null;
			
		$extra = '';
		
		if ( $options->title != '')
			$extra .= ' title="' . $options->title . '"';
			
		$requiredField = $this->make_required_field( $options );
			
		return '<label for="' . $this->make_id( $options ) . '"' . $extra . '>' . $options->label . $requiredField . '</label>';
	}
	
	/**
	 * Returns the description of this input.
	 */
	public function make_description($options)
	{
		// Merge the given options with the options we were constructed with.
		$options = \plainview\sdk\base::merge_objects($this->options, $options);
		
		if ( $options->description == '')
			return '';
		
		$rv = '
			<div>
				<div class="screen-reader-text aural-info">
					'.$this->_('Description').':
				</div>
				'.$options->description.'
			</div>';

		$rv .= $this->make_validation($options);			
			
		return $rv;
	}
	
	private function make_validation($options)
	{
		$rv = '';
		$validation = array();

		if ( isset( $options->minimum_value ) && !isset( $options->maximum_value ) )
			$validation[] = $this->_('The value must be larger than or equal to') . ' ' . $options->minimum_value; 
		if ( !isset( $options->minimum_value) && isset( $options->maximum_value) )
			$validation[] = $this->_('The value must be smaller or equal to') . ' ' . $options->maximum_value; 
		if ( isset( $options->minimum_value) && isset( $options->maximum_value) )
			$validation[] = $this->_('Valid values') . ': ' . $options->minimum_value . ' ' . $this->_('to') . ' ' . $options->maximum_value; 
		if ( isset( $options->minimum_length ) )
			$validation[] = $this->_('Minimum length') . ': ' . $options->minimum_length . ' ' . $this->_('characters');
		
		if ( isset( $options->datetime ) )
		{
			$formatString = $options->datetime ;
			$formatString = str_replace('m', $this->_('MM'), $formatString);
			$formatString = str_replace('d', $this->_('DD'), $formatString);
			$formatString = str_replace('H', $this->_('HH'), $formatString);
			$formatString = str_replace('i', $this->_('MM'), $formatString);
			$formatString = str_replace('s', $this->_('SS'), $formatString);
			$formatString = str_replace('Y', $this->_('YYYY'), $formatString);
			$validation[] = $this->_('Date format') . ': ' . $formatString;
		} 
		if ( isset( $options->maximum_date ) )
			$validation[] = $this->_('Latest valid date') . ': ' .  $options->maximum_date;
			
		if ( count( $validation ) > 0 )
			$rv = '<div>' . implode(', ', $validation) . '</div>';
		return $rv;
	}
	
	/**
	 * Makes an input string.
	 */
	public function make_input( $options )
	{
		// Merge the given options with the options we were constructed with.
		$options = \plainview\sdk\base::merge_objects( $this->options, $options );
		
		$extraOptions = '';
		if ( $options->disabled)
			$extraOptions .= ' disabled="disabled" ';
		if ( $options->readonly)
			$extraOptions .= ' readonly="readonly" ';
			
		$classes = $options->type;
		if ( $options->css_class != '')
			$classes .= ' ' . $options->css_class;
		
		// Add title to all
		$extraOptions .= ' title="'.$options->title.'"';
		// Add alt to all except textarea
		if (!in_array($options->type, array('select', 'textarea')))
			$extraOptions .= ' alt="'.$options->alt.'"';
		
		if ( $options->css_style != '')
			$extraOptions .= ' style="'.$options->css_style.'"';
			
		// Add the global prefix
		$options->nameprefix = $options->global_nameprefix . $options->nameprefix;  
			
		switch ( $options->type)
		{
			case 'checkbox':
				if (!isset( $options->checked))
				{
					$options->checked = (intval($options->value) == 1);
					if ( $options->value == '' || $options->value == '0')
						$options->value = 1;
				}
				$checked = ( $options->checked == true ? ' checked="checked" ' : '');
				$rv = '<input class="'.$classes.'" type="'.$options->type.'" name="'.self::make_name($options).'" id="'.$this->make_id($options).'" value="'.$options->value.'" '.$checked.' '.$extraOptions.' />';
				break;
			case 'file':
				$rv = '<input class="'.$classes.'" type="'.$options->type.'" name="'.self::make_name($options).'" id="'.$this->make_id($options).'" value="'.$options->value.'" '.$extraOptions.' />';
				break;
			case 'hidden':
				$rv = '<input class="'.$classes.'" type="'.$options->type.'" name="'.self::make_name($options).'" value="'.$options->value.'"'.$extraOptions.' />';
				break;
			case 'image':
				$rv = '<input class="'.$classes.'" type="'.$options->type.'" name="'.self::make_name($options).'[]" id="'.$this->make_id($options).'" value="'.$options->value.'" title="'.$options->title.'" src="'.$options->src.'" '.$extraOptions.' />';
				break;
			case 'password':
				if ( isset( $options->size))
				{
					$options->size = min($options->size, $options->maxlength); // Size can't be bigger than maxlength
					$text['size'] = 'size="'.$options->size.'"';
				}
				$rv = '<input class="'.$classes.'" type="'.$options->type.'" '.$text['size'].' maxlength="'.$options->maxlength.'" name="'.self::make_name($options).'"  value="'.htmlspecialchars($options->value).'" id="'.$this->make_id($options).'"'.$extraOptions.' />';
				break;
			case 'radio':
				// Make the options
				$rv = '';
				$baseOptions = \plainview\sdk\base::merge_objects( $this->options, $options);
				foreach( $options->options as $option_value => $option_text )
				{
					$checked = ( $option_value == $options->value) ? 'checked="checked"' : '';
					$option = $baseOptions;
					$option->namesuffix = $option_value;
					$option->label = $option_text;
					$rv .= '
						<div>
							<input class="'.$classes.'" type="'.$options->type.'" name="'.self::make_name($options).'" value="'.$option_value.'" id="'.$this->make_id($option).'" '.$checked.' '.$extraOptions.' />
							'.$this->make_label($option).'
						</div>
					';
				}
				break;
			case 'markup':
				$rv = '<div>' . $options->markup . '</div>';
				break;
			case 'select':
				if ( $options->multiple)
				{
					$extraOptions .= ' multiple="multiple" ';
					$nameSuffix = '[]';		// Names for multiple selects need []
				}
				else
					$nameSuffix = '';
				
				// Convert the value text to an array
				if (!is_array($options->value))
					$options->value = array($options->value);
					
				// Make the options
				$optionsText = '';
				foreach($options->options as $option_value => $option_text )
				{
					// 2011-07-25 - options array is now an array of value => text, so this is for backwards compatability.
					if ( is_array( $option_text ) )
					{
						$option_value = $option_text['value'];
						$option_text = $option_text['text'];
					}

					$selected = in_array( $option_value, $options->value ) ? 'selected="selected"' : '';
					
					$optionsText .= '
						<option value="'.$option_value.'" '.$selected.'>'.$option_text.'</option>
					';
				}
				
				$rv = '<select class="'.$classes.'" name="'.self::make_name($options).$nameSuffix.'" id="'.$this->make_id($options).'" size="'.$options->size.'" '.$extraOptions.'>
					'.$optionsText.'
					</select>
				';
				break;
			case 'submit':
				$rv = '<input class="'.$classes.'" type="'.$options->type.'" name="'.self::make_name($options).'" id="'.$this->make_id($options).'" value="'.$options->value.'" '.$extraOptions.' />';
				break;
			case 'textarea':
				$rv = '<textarea class="'.$classes.'" cols="'.$options->cols.'" rows="'.$options->rows.'" name="'.self::make_name($options).'" id="'.$this->make_id($options).'"'.$extraOptions.'>'.$options->value.'</textarea>';
				break;
			default:	// Default = 'text'
				if ( isset( $options->size))
				{
					$options->size = min($options->size, $options->maxlength); // Size can't be bigger than maxlength
					$text['size'] = 'size="'.$options->size.'"';
				}
				$rv = '<input class="text '.$classes.'" type="text" '.$text['size'].' maxlength="'.$options->maxlength.'" name="'.self::make_name($options).'" id="'.$this->make_id($options).'" value="'.htmlspecialchars($options->value).'"'.$extraOptions.' />';
		}
		return $rv;
	}

	/**
	 * Adds a section.
	 * 
	 * $section = array(
	 * 	'name' => 'Name of the section',
	 * 	'description' => 'Section description that tells the user what this section is about.',
	 * 	'inputs' => RESERVED
	 * )
	 */		
	public function add_section($section)
	{
		$section['inputs'] = array();
		$this->sections[ $section['name'] ] = $section;
	}
	
	private function implode_array($data, &$strings, $glueBefore, $glueAfter, $stringPrefix = null, $currentString = null)
	{
		foreach($data as $key=>$value)
		{
			if (!is_array($value))
			{
				$stringToAdd = $stringPrefix . $currentString . $glueBefore . $key . $glueAfter;
				$strings[$stringToAdd] = $value;
			}
			else
				$this->implode_array($value, $strings, $glueBefore, $glueAfter,  $stringPrefix, $currentString . $glueBefore.$key.$glueAfter);
		}
	}
	
	public function use_post_value( &$input, $post_data = null )
	{
		if ( $input[ 'type' ] == 'markup' )
			return;
		
		if ( $input['type']=='submit')		// Submits don't get their values posted, so return the value.
			return $input['value'];
			
		$input['name'] = self::fix_name($input['name']);
		$name = $input['name'];				// Conv
		
		if ( $post_data === null )
			$post_data = $_POST;
		
		if ( count( $post_data ) < 1 )
			return;
		
		// Merge the default options.
		// In case this class was created with a nameprefix and the individual inputs weren't, for example.
		$input = array_merge( (array)$this->options, $input );
			
		// Nameprefix? Find the right array section in the post.			
		if ( $input['nameprefix'] != '')
		{
			$strings = '';
			$this->implode_array( $post_data, $strings, '__', '', $this->options->class . '' );
		}
		else
		{
			switch( $input[ 'type' ] )
			{
				case 'checkbox':
					$input[ 'checked' ] = isset( $post_data[ $name ] );
				default:
					if ( isset( $post_data[$input['name']] ) )
					{
						if ( ! is_array( $post_data[$input['name']] ) )
							$input[ 'value' ] = stripslashes( $post_data[ $name ] );
						else
						{
							$input[ 'value' ] = array();	// Kill the value, otherwise postvalues are just appended and therefore do nothing.
							foreach( $post_data[ $name ] as $index => $value )
								$input[ 'value' ][$index] = stripslashes( $value );
						}
					}
				break;
			}
		}
		
		$inputID = $this->make_id( $input );
		if ( isset( $strings[$inputID] ) )
		{
			switch($input['type'])
			{
				default:
					@$input[ 'value' ] = stripslashes( $strings[$inputID] );
			}
		}
	}
	
	/**
		@brief		Convenience function that uses an array of inputs.
		@see		use_post_value
	**/
	public function use_post_values( &$inputs, $post_data = null )
	{
		foreach( $inputs as $name => $input )
		{
			$inputs[ $name ][ 'name' ] = $name;
			$this->use_post_value( $inputs[ $name ], $post_data );
		}
	}
	
	/**
	 * Adds an input to a section.
	 * Kinda like makeInput, but with a section name.
	 */
	public function add_section_input($section, $input, $settings, $post_data = null)
	{
		if ( !is_string($input) )
		{
			$input = array_merge( (array)$this->options, $input);
			if ( $input['type'] == 'markup')
				$input['name'] = rand(0, time());
			$this->use_post_value( $input, $post_data );
		}
		else
			$input = array(
				'type' => 'markup',
				'markup' => $input,
			);

		if ( $input['type'] == 'markup')
			$input['name'] = rand( 0, time() );
			
		$this->sections[ $section['name'] ]['inputs'][ $input['name'] ] = $input;
	}
	
	public function add_text_section($sectionName, $text)
	{
		$this->sections[ $sectionName ]['inputs'][] = array(
			'type' => 'markup',
			'markup' => $text,
		);
	}
	
	/**
		Adds sections + inputs "automatically".
		
		Using $layout, the modules $settings module and the postdata, it creates a form with
		section and inputs.
		
		Example layout
		
		private $inputLayout = array(
			0 => array(
				'name' => 'ExampleSection',
				'description' => 'This section deals with examples. Examples are good.',
				'autoinputs' => array(
			    	'IFACMS_STAGE_PAGE_TITLE_SEPARATOR' => array('text'),
				),  // Inputs
				'inputs' => array(
						array(
							'type' => 'submit',
							'name'=>'CHANGE_SETTINGS_BASE',
							'value' => 'Change settings',
						)
				), // Manualinputs
			),	// 0
		); // inputLayout
		
		Tip: If your module requires several pages/tabs of settings, put the layouts in one big
		layoutarray and make the tab name the key.
		
	*/
	public function add_layout($layouts, $inputData, $settings, $post_data = null)
	{
		$layoutDefaults = array(
			'autoinputs' => array(),
			'inputs' => array(),
		);

		foreach($layouts as $sectionNumber => $layout)
		{
			$this->add_section($layout);
			$layout = array_merge($layoutDefaults, $layout);
			
			foreach($layout['autoinputs'] as $inputName)
			{
				$input = $inputData[$inputName];
				$input['name'] = $inputName;
				$this->add_section_input($layout, $input, $settings, $post_data);
			}
			foreach($layout['inputs'] as $input )
				$this->add_section_input( $layout, $input, $settings, $post_data );
		}
	}
	
	/**
	 * Displays a whole form with sections and all the section inputs.
	 */
	public function display()
	{
		$style = array(
			'formStart'							=> '<div class="form-%%STYLE%%-start">',
			'formStop'							=> '</div>',
			'sectionStart'						=> '	<fieldset class="form-%%STYLE%%-section-start %%CSSCLASS%%">',
			'sectionStop'						=> '	</fieldset>',
			'sectionNameStart'					=> '		<legend class="form-%%STYLE%%-section-name">',
			'sectionNameStop'					=> '		</legend>',
			'sectionDescriptionStart'			=> '		<div class="form-%%STYLE%%-section-description">',
			'sectionDescriptionStop'			=> '		</div>',
			'rowStart'							=> '			<div class="form-%%STYLE%%-row-start">',
			'rowStop'							=> '			</div>',
			'labelContainerStart'				=> '				<div class="form-%%STYLE%%-labelcontainer-start">',
			'labelContainerStop'				=> '				</div>',
			'labelContainerSingleStart'			=> '				<div class="form-%%STYLE%%-labelcontainer-single-start">',
			'labelContainerSingleStop'			=> '				</div>',
			'labelStart'						=> '					<div class="form-%%STYLE%%-label-start">',
			'labelStop'							=> '					</div>',
			'inputContainerStart'				=> '				<div class="form-%%STYLE%%-inputcontainer-start">',
			'inputContainerStop'				=> '				</div>',
			'inputContainerSingleStart'			=> '				<div class="form-%%STYLE%%-inputcontainer-single-start">',
			'inputContainerSingleStop'			=> '				</div>',
			'inputStart'						=> '					<div class="form-%%STYLE%%-input-start">',
			'inputStop'							=> '					</div>',
			'descriptionContainerStart'			=> '				<div class="form-%%STYLE%%-descriptioncontainer-start">',
			'descriptionContainerStop'			=> '				</div>',
			'descriptionStart'					=> '					<div class="form-%%STYLE%%-description-start">',
			'descriptionStop'					=> '					</div>',
		);
		
		$rv = $style['formStart'] . "\n";
		
		foreach($this->sections as $sectionName => $sectionData)
		{
			$sectionData = array_merge(array(
				'description' => null,
				'css_class' => null,
			), $sectionData);
			$sectionDescription = ( $sectionData['description']!=null ? $style['sectionDescriptionStart'] . $sectionData['description'] . $style['sectionDescriptionStop'] . "\n" : '');
			$rv .= $style['sectionStart'] . "\n" .
				$style['sectionNameStart'] . "\n" .
					'			' . $sectionName . "\n" .
				$style['sectionNameStop'] . "\n" .
				$sectionDescription;
				
			$rv = str_replace('%%CSSCLASS%%', $sectionData['css_class'], $rv);
				
			foreach($sectionData['inputs'] as $index=>$input)
			{
				if ( $input['type'] == 'hidden')
				{
					$rv .= $this->make_input($input);
					continue;
				}	

				// Force validation stuff to make a description if there isn't one already.
				$validation = $this->make_validation($input);
				if ( $validation != '' && !isset( $input['description']))
					$input['description'] = '';
					
				$description = ( isset( $input['description']) ?
					$style['descriptionContainerStart'] . "\n".
						$style['descriptionStart'] . "\n".
							$this->make_description($input) . "\n".
						$style['descriptionStop'] . "\n".
					$style['descriptionContainerStop'] . "\n"
				: '');
				
				if ( $input['type'] == 'markup')
				{
					if ( isset( $input['css_class']))
						$inputData = '<div class="'.$input['css_class'].'">'.$input['markup'].'</div>';
					else
						$inputData = $input['markup'];
				}
				else
				{
					if (!isset( $input['display_two_rows']))
						$input['display_two_rows'] = false;		// Standard is one row
					
					
					if ( $input['type'] == 'submit')
						$input['display_two_rows'] = true;
						
					if ( $input['display_two_rows'] == true)
					{
						$inputData =
							$style['labelContainerSingleStart'] . "\n".
								$style['labelStart'] . "\n".
									'					' . $this->make_label($input) . "\n".
								$style['labelStop'] . "\n".
							$style['labelContainerSingleStop'] . "\n".
							
							$style['inputContainerSingleStart'] . "\n".
								$style['inputStart'] . "\n".
									'					' . $this->make_input($input) . "\n".
								$style['inputStop'] . "\n".
							$style['inputContainerSingleStop'] . "\n".
							
							$description;
					}
					else
					{
						$inputData =
							'<div class="container_for_'.$input['type'].'">' .
							$style['labelContainerStart'] . "\n".
								$style['labelStart'] . "\n".
									'					' . $this->make_label($input) . "\n".
								$style['labelStop'] . "\n".
							$style['labelContainerStop'] . "\n".
								
							$style['inputContainerStart'] . "\n".
								$style['inputStart'] . "\n".
									'					' . $this->make_input($input) . "\n".
								$style['inputStop'] . "\n".
							$style['inputContainerStop'] . "\n".
							"</div>" .
							$description;
					}
				}

				$rv .= 
					'		<div class="row'.($index+1).'">' . "\n" .
						$style['rowStart'] . "\n".
							$inputData .  "\n" .
						$style['rowStop']  . "\n" .
					'		</div>' . "\n";
			}
			$rv .= $style['sectionStop'] . "\n";
		}
		
		$rv .= $style['formStop'] . "\n";
		
		return str_replace('%%STYLE%%', $this->options->style, $rv);
	}

	/**
	 * Validates the values based on $inputs and which of the $inputs to check.
	 * 
	 * Returns an array of $key / 'error message' if validation fails, else returns true.
	 */		
	public function validate_post( $inputs, $inputs_to_check, $values )
	{
		$rv = array();
		
		foreach( $inputs_to_check as $key )
		{
			$input = (object)$inputs[ $key ];
			
			if ( ! is_object( $input ) )
				continue;
			
			// Because form fixes the name to remove illegal characters, we need to do the same to the key here so that we find the correct values in the POST.
			$key = self::fix_name( $key );
			
			$input->type = ( isset( $input->type ) ? $input->type : 'text');		// Assume type text.
			switch( $input->type )
			{
				case 'text':
				
					if (isset( $input->email) )
					{
						$email = trim($values[$key]);
						if ( ! \plainview\sdk\wordpress\base::is_email($email) )
							$rv[ $key ] = $this->_('Invalid email address') . ': ' . $email;
					}
					
					if ( isset( $input->datetime ) )
					{
						$text = trim($values[$key]);
						$date = strtotime($text);
						$dateFormat = $input->datetime;
						
						if ( ( date( $dateFormat, $date) != $text ) && property_exists( $input, 'required' ) )
							$rv[ $key ] = $this->_('Could not parse date') . ': ' . $text . ' (' . $input->label . ')';
					}
					
					if ( isset( $input->maximum_date) )
					{
						$date = strtotime($values[$key]);
						if ( $date > strtotime($input->maximum_date))
							$rv[ $key ] = $this->_('Invalid date') . ': ' . $text . ' (' . $input->label . ')';
					}
					
					if ( property_exists( $input, 'required' ) )
						$input->minimum_length = 1;
					
					if ( isset( $input->minimum_length ) )
					{
						$text = trim($values[$key]);
						if (strlen($text) < $input->minimum_length)
							$rv[ $key ] = '<span class="validation-field-name">' . $input->label . '</span> ' . $this->_('must be at least') . ' <em>' . $input->minimum_length . '</em> ' . $this->_('characters long') . '.';
					}

					if (isset( $input->minimum_value) )
					{
						// First convert to correct type of number...
						if (is_float($input->minimum_value))
							$value = floatval($values[$key]);
						if (is_int($input->minimum_value))
							$value = intval($values[$key]);

						if ( $value < $input->minimum_value)
							$rv[ $key ] = '<span class="validation-field-name">' . $input->label . '</span> ' . $this->_('may not be smaller than') . ' ' . $input->minimum_value;
					}
					
					if (isset( $input->maximum_value) )
					{
						// First convert to correct type of number...
						if (is_float($input->minimum_value))
							$value = floatval($values[$key]);
						if (is_int($input->minimum_value))
							$value = intval($values[$key]);

						if ( $value > $input->maximum_value)
							$rv[ $key ] = '<span class="validation-field-name">' . $input->label . '</span> ' . $this->_('may not be larger than') . ' ' . $input->maximum_value;
					}
				case 'textarea':
					// Is the value allowed to be empty?
					if ( property_exists( $input, 'required' ) )
						if (trim($values[$key]) == '')
							$rv[ $key ] = '<span class="validation-field-name">' . $input->label . '</span> ' . $this->_('may not be empty');
				break;
			}
		}
		
		if (count($rv)> 0)
			return $rv;
		else
			return true;
	}
	
	/**
	 * Returns the translated string, if any.
	 */
	public function _($string, $replacementString = array())
	{
		$language = $this->options->language;
		
		if (!isset( $this->language_data[$string]))
			$rv = $string;
		else
			if ( isset( $this->language_data[$string][$language]))
				$rv = $this->language_data[$string][$language];
			else
				$rv = $string;
				
		while (count($replacementString) > 0)
		{
			$rv = preg_replace('/%s/', reset($replacementString), $rv, 1);
			array_shift($replacementString);
		}
			
		return $rv; 
	}
}

class form_language
{
    public static $language_data = array(
		'Could not parse date' => array(
			'sv' => 'Kunde inte tolka datumet',
		),
		'DD' => array(
			'sv' => 'DD',
		),
		'Date format' => array(
			'sv' => 'Datumformat',
		),
		'Description' => array(
			'sv' => 'Beskrivning',
		),
		'HH' => array(
			'sv' => 'TT',
		),
		'Invalid date' => array(
			'sv' => 'Ogiltigt datum',
		),
		'Invalid email address' => array(
			'sv' => 'Ogiltig epostadress',
		),
		'Latest valid date' => array(
			'sv' => 'Senaste till&aring;tna datum',
		),
		'MM' => array(
			'sv' => 'MM',
		),
		'Minimum length' => array(
			'sv' => 'Minimuml&auml;ngd',
		),
		'Required field' => array(
			'sv' => 'M&aring;ste fyllas i',
		),
		'SS' => array(
			'sv' => 'SS',
		),
		'The value must be larger than or equal to' => array(
			'sv' => 'V&auml;rdet m&aring;ste vara st&ouml;rre eller lika med',
		),
		'The value must be smaller or equal to' => array(
			'sv' => 'V&auml;rdet m&aring;ste vara mindre eller lika med',
		),
		'Valid values' => array(
			'sv' => 'Giltiga v&auml;rden',
		),
		'YYYY' => array(
			'sv' => '&Aring;&Aring;&Aring;&Aring;',
		),
		'characters' => array(
			'sv' => 'tecken',
		),
		'characters long' => array(
			'sv' => 'tecken',
		),
		'may not be empty' => array(
			'sv' => 'f&aring;r ej l&auml;mnas tomt',
		),
		'may not be larger than' => array(
			'sv' => 'f&aring;r inte vara st&ouml;rre &auml;n',
		),
		'may not be smaller than' => array(
			'sv' => 'f&aring;r inte vara mindre &auml;n',
		),
		'must be at least' => array(
			'sv' => 'm&aring;ste best&aring; av minst',
		),
		'The password must' => array(
			'sv' => 'L&ouml;senordet m&aring;ste',
		),
		'to' => array(
			'sv' => 'till',
		),
	);
}
