<?php
/**
 * Handles the making of labels, inputs and forms.
 * 
 * Call with an $options array for default values (nameprefix, for example).
 * Else leave blank to use just the options that are passed to makeLabel and makeInput.
 * 
	$form->addSection(array(
		'name' => 'TestSection',
		'description' => 'Just a silly old test section.',
	));
	
	$form->addSectionText('TestSection', '
		<div>
			This is where you can write stuff. If you want. Anything you put in here will go directly into the form without question.
		</div>
	');
	$form->addSectionInput('TestSection', array(
		'type' => 'text',
		'name'=>'textexample',
		'label' => 'Example of a text box',
		'value' => 'Example value',
		'description' => 'Size and maxlength are what make a textbox what it is.',
		'size'=>20,
		'maxlength'=>50,
		'validation' => array(			// Optional
			'empty' => true,			// This input is allowed to be left empty (after trim())
		),
	));
	$form->addSectionInput('TestSection', array(
		'type' => 'password',
		'name'=>'passwordexample',
		'label' => 'Example of a password box',
		'value' => 'Example value',
		'description' => 'Size and maxlength are what make a password what it is (and the asterisks).',
		'size'=>20,
		'maxlength'=>50,
		'validation' => array(			// Optional
			'passwordstrength' => 'type',			// Only type recognized is ifacms1.
		),
	));
	$form->addSectionInput('TestSection', array(
		'type' => 'textarea',
		'name'=>'textareaexample',
		'label' => 'Example of a textarea',
		'description' => 'Cols and rows are what make a textarea what it is. Text is put in the value.',
		'cols'=>80,
		'rows'=>10,
		'value' => 'Text is right here! Don\'t forget to stripslashes!',
	));
	$form->addSectionInput('TestSection', array(
		'type' => 'select',
		'name'=>'selectexample',
		'label' => 'How easy is it to make forms?',
		'value' => 'easy',
		'multiple' => true,													// Optional - enables a mutiple select.
		'size'=>5,															// Optional - how many options to show
		'options' => array(
			array('value' => 'veryeasy',	 'text' => 'Very easy'),
			array('value' => 'easy',		'text' => 'Easy'),
			array('value' => 'hard',		'text' => 'Hard'),
			array('value' => 'noopinion',	'text' => 'No opinion'),
		),
		'description' => 'Select lists are of type <em>select</em> and the <em>options</em> array contains the values.',
	));
	$form->addSectionInput('TestSection', array(
		'type' => 'radio',
		'name'=>'radiotest',
		'label' => 'Are radio options harder to make?',
		'value' => 'no',
		'options' => array(
			array('value' => 'yes',		'text' => 'Yes'),
			array('value' => 'no',		'text' => 'No'),
			array('value' => 'maybe',	'text' => 'Maybe'),
		),
		'description' => 'Radio lists / boxes are as easy to make as selects. Only the <em>type</em> changes.',
	));
	$form->addSectionInput('TestSection', array(
		'type' => 'checkbox',
		'name'=>'checkboxexample',
		'label' => 'Almost done?',
		'value' => 'on',				
		'checked' => false,					// Checked or unchecked?
		'description' => 'Are we almost done with the examples? Yes, almost...',
	));
	$form->addSectionInput('TestSection', array(
		'type' => 'image',
		'name'=>'imageexample',
		'label' => 'The IMG alt tag',
		'src' => 'images/submit.png',
	));
	$form->addSectionInput('TestSection', array(
		'type' => 'file',
		'name'=>'fileupload',
		'value' => 'Choose file',
		'title' => 'We can put a description in here if we want.',
		'description' => 'Choose a file to upload by pressing this this-here button.',
	));
	$form->addSectionInput('TestSection', array(
		'type' => 'submit',
		'name'=>'submitbutton',
		'value' => 'Quit FORMing',
		'title' => 'We can put a description in here if we want.',
		'description' => 'Enough of the forms already! We\' had enough examples, let\'s do some work.',
	));
	
	echo $form->start() . $form->display() . $form->stop();
	
	Validation
	----------
	empty			Field is allowed to be empty
	valuemin		Value: minimum
	valuemax		Value: maximum
	datetime		Date must be in this format. (Use format keywords from date(). "Y-m-d").
	datemaximum		Latest date/time allowed. Any date() format.
	lengthmin		Length: minimum
		
 */
class edwardForm
{
	private $options;
	
	private $optionalOptions = array(
		'class' => '',
		'nameprefix' => '',
		'nameprefixGlobal' => '',
		'namesuffix' => '',
		'title' => '',
		'alt' => '',
		'value' => '',
		'multiple' => false,
		'size' => 1,
		'maxlength' => 100,
		'disabled' => false,
		'readonly' => false,
		'displayTwoRows' => false,
		'style' => 'STYLE1',
		'cssClass' => '',
		'css_class' => '',
		'css_style' => '',
		'form_method' => 'post',
		'form_action' => '',
		'description' => '',
	);
	
	private $namePrefixAll = '';
	
    protected $languageData = array();
	
	private $sections = array();
	
	function __construct($options = array())
	{
		$this->options = array_merge($this->optionalOptions, $options);
		$this->languageData = edwardFormLanguage::$languageData;
	}
	
	/**
	 * Sets the default prefix for all inputs.
	 */
	public function setAllPrefixes($prefix)
	{
		$this->options['nameprefixGlobal'] = $prefix;
	}
	
	/**
	 * Returns the _POST data of the calling class.
	 */
	public static function getPostData($callingClass, $POST = null)
	{
		if ($POST === null)
			$POST = $_POST;
		if (isset( $POST[ get_class($callingClass) ] ))
			return $POST[ get_class($callingClass) ];
	}
	
	/**
	 * Converts a $_FILES-array into the array without the nameprefix.
	 */
	public static function fileExtractNameprefix($FILES, $namePrefix)
	{
		if ($FILES === null)
			return null;
		$returnValue = array();
		foreach($FILES as $index=>$fileData)
		{
			foreach($fileData[$namePrefix] as $key=>$value)
				$returnValue[$index][$key] = $value;
		}
		return $returnValue;
	}
	
	/**
	 * Cleans up the name of an input, remove illegal characters.
	 */
	public static function fixName($name)
	{
		return preg_replace('/\[|\]| |\'|\\\|\?/', '_', $name);
	}
	
	/**
	 * Returns a text ID of this input. 
	 */
	public function makeID($options)
	{
		$options = array_merge($this->options, $options);
		$options['name'] = self::fixName($options['name']);

		// Add the global prefix
		$options['nameprefix'] = $this->options['nameprefixGlobal'] . $options['nameprefix'];  
			
		return $options['class'] . '_' . preg_replace('/\[|\]/', '_', $options['nameprefix']) .  '_' . $options['name'] .  $options['namesuffix'];
	}
	
	/**
	 * Returns the name of this input. 
	 */
	public static function makeName($options)
	{
		$options['name'] = self::fixName($options['name']);
		if ($options['class']!='')
			return $options['class'] . $options['nameprefixGlobal'] . $options['nameprefix'] . '[' . $options['name'] . ']';
		else
		{
			$returnValue = $options['nameprefixGlobal'];

			// Remove the first [ and first ]. Yeah, that's how lovely forms work: the first prefix doesn't have brackets. The rest do.
			$returnValue .= ($returnValue == '' ?
				preg_replace('/\[/', '',
					preg_replace('/\]/', '', $options['nameprefix'], 1)
				, 1) : $options['nameprefix']);
			$returnValue .= ($returnValue == '' ? $options['name'] : '[' . $options['name'] . ']');
		}
		return $returnValue;
	}
	
	/**
	 * Returns a simple <form> tag (with action and method set).
	 */
	public function start()
	{
		return '<form enctype="multipart/form-data" action="'.$this->options['form_action'].'" method="'.$this->options['form_method'].'">' . "\n";
	}
	
	/**
	 * Returns </form>. That's it.
	 */
	public function stop()
	{
		return '</form>' . "\n";
	}
	
	/**
	 * Makes a small asterisk thingy that is supposed to symbolise that the field is required.
	 */
	private function makeRequiredField($options)
	{
		$returnValue = '';
		switch($options['type'])
		{
			case 'text':
			case 'textarea':
				$needed = false;
				if (!isset($options['validation']))
					$needed = true;
				else
				{
					if (!isset($options['validation']['empty']))
						$needed = true;
					if (isset($options['validation']['valuemin']) || isset($options['validation']['valuemax']))
						$needed = false;
				}
				if ($needed)
					$returnValue .= '<sup><span class="screen-reader-text aural-info"> ('.$this->l('Required field').')</span><span title="'.$this->l('Required field').'">*</span></sup>';
			break;
			default:
			break;
		}
		return $returnValue;
	}
	
	/**
	 * Returns the label of this input.
	 */
	public function makeLabel($options)
	{
		// Merge the given options with the options we were constructed with.
		$options = array_merge($this->options, $options);
		
		if (!isset($options['label']))
			return null;
			
		$extra = '';
		
		if ($options['title'] != '')
			$extra .= ' title="'.$options['title'].'"';
			
		$requiredField = $this->makeRequiredField($options);
			
		return '<label for="'.$this->makeID($options).'"'.$extra.'>'.$options['label'].$requiredField.'</label>';
	}
	
	/**
	 * Returns the description of this input.
	 */
	public function makeDescription($options)
	{
		// Merge the given options with the options we were constructed with.
		$options = array_merge($this->options, $options);
		
		if ($options['description'] == '')
			return '';
		
		$returnValue = '
			<div>
				<div class="screen-reader-text aural-info">
					'.$this->l('Description').':
				</div>
				'.$options['description'].'
			</div>';

		$returnValue .= $this->makeValidation($options);			
			
		return $returnValue;
	}
	
	private function makeValidation($options)
	{
		$validation = array();
		if (isset($options['validation']))
		{
			$validationArray = $options['validation'];
			if ( isset($validationArray['valuemin']) && !isset($validationArray['valuemax']) )
				$validation[] = $this->l('The value must be larger than or equal to') . ' ' . $validationArray['valuemin']; 
			if ( !isset($validationArray['valuemin']) && isset($validationArray['valuemax']) )
				$validation[] = $this->l('The value must be smaller or equal to') . ' ' . $validationArray['valuemax']; 
			if ( isset($validationArray['valuemin']) && isset($validationArray['valuemax']) )
				$validation[] = $this->l('Valid values') . ': ' . $validationArray['valuemin'] . ' ' . $this->l('to') . ' ' . $validationArray['valuemax']; 
			if ( isset($validationArray['lengthmin']) )
				$validation[] = $this->l('Minimum length') . ': ' . $validationArray['lengthmin'] . ' ' . $this->l('characters'); 
			if ( isset($validationArray['passwordstrength']) )
			{
				$type = $validationArray['passwordstrength'];
				$checkFunction = "passwordStrengthGet$type";
				$rules = $this->$checkFunction();
				$rules = $this->l('The password must') . ': ' . implode(', ', $rules);
				$validation[] = $rules;
			}
			if ( isset($validationArray['datetime']) )
			{
				$formatString = $validationArray['datetime'];
				$formatString = str_replace('m', $this->l('MM'), $formatString);
				$formatString = str_replace('d', $this->l('DD'), $formatString);
				$formatString = str_replace('H', $this->l('HH'), $formatString);
				$formatString = str_replace('i', $this->l('MM'), $formatString);
				$formatString = str_replace('s', $this->l('SS'), $formatString);
				$formatString = str_replace('Y', $this->l('YYYY'), $formatString);
				$validation[] = $this->l('Date format') . ': ' . $formatString;
			} 
			if ( isset($validationArray['datemaximum']) )
			{
				$validation[] = $this->l('Latest valid date') . ': ' .  $validationArray['datemaximum'];
			}
		}
			
		if (count($validation)>0)
			return '<div>' . implode(', ', $validation) . '</div>';
		else
			return '';
	}
	
	/**
	 * Makes an input string.
	 */
	public function makeInput($options)
	{
		// Merge the given options with the options we were constructed with.
		$options = array_merge($this->options, $options);
		
		$extraOptions = '';
		if ($options['disabled'])
			$extraOptions .= ' disabled="disabled" ';
		if ($options['readonly'])
			$extraOptions .= ' readonly="readonly" ';
			
		$classes = $options['type'];
		if ($options['cssClass'] != '')
			$classes .= ' ' . $options['cssClass'];
		if ($options['css_class'] != '')
			$classes .= ' ' . $options['css_class'];
		
		// Add title to all
		$extraOptions .= ' title="'.$options['title'].'"';
		// Add alt to all except textarea
		if (!in_array($options['type'], array('select', 'textarea')))
			$extraOptions .= ' alt="'.$options['alt'].'"';
		
		if ($options['css_style'] != '')
			$extraOptions .= ' style="'.$options['css_style'].'"';
			
		// Add the global prefix
		$options['nameprefix'] = $this->namePrefixAll . $options['nameprefix'];  
			
		switch ($options['type'])
		{
			case 'select':
				if ($options['multiple'])
				{
					$extraOptions .= ' multiple="multiple" ';
					$nameSuffix = '[]';		// Names for multiple selects need []
				}
				else
					$nameSuffix = '';
				
				// Convert the value text to an array
				if (!is_array($options['value']))
					$options['value'] = array($options['value']);
					
				// Make the options
				$optionsText = '';
				foreach($options['options'] as $option)
				{
					$selected = in_array($option['value'], $options['value'], true) ? 'selected="selected"' : '';
					$optionsText .= '
						<option value="'.$option['value'].'" '.$selected.'>'.$option['text'].'</option>
					';
				}
				
				$returnValue = '<select class="'.$classes.'" name="'.self::makeName($options).$nameSuffix.'" id="'.$this->makeID($options).'" size="'.$options['size'].'" '.$extraOptions.'>
					'.$optionsText.'
					</select>
				';
			break;
			case 'radio':
				// Make the options
				$returnValue = '';
				$baseOptions = array_merge($this->options, $options);
				foreach($options['options'] as $option)
				{
					$checked = ($option['value'] == $options['value']) ? 'checked="checked"' : '';
					$option = array_merge($baseOptions, $option);
					$option['namesuffix'] = $option['value'];
					$option['label'] = $option['text'];
					$returnValue .= '
						<div>
							<input class="'.$classes.'" type="'.$options['type'].'" name="'.self::makeName($options).'" value="'.$option['value'].'" id="'.$this->makeID($option).'" '.$checked.' '.$extraOptions.' />
							'.$this->makeLabel($option).'
						</div>
					';
				}
			break;
			case 'hidden':
				$returnValue = '<input class="'.$classes.'" type="'.$options['type'].'" name="'.self::makeName($options).'" value="'.$options['value'].'"'.$extraOptions.' />';
			break;
			case 'checkbox':
				if (!isset($options['checked']))
				{
					$options['checked'] = (intval($options['value']) == 1);
					if ($options['value'] == '' || $options['value'] == '0')
						$options['value'] = 1;
				}
				$checked = ($options['checked'] == true ? ' checked="checked" ' : '');
				$returnValue = '<input class="'.$classes.'" type="'.$options['type'].'" name="'.self::makeName($options).'" id="'.$this->makeID($options).'" value="'.$options['value'].'" '.$checked.' '.$extraOptions.' />';
			break;
			case 'submit':
				$returnValue = '<input class="'.$classes.'" type="'.$options['type'].'" name="'.self::makeName($options).'" id="'.$this->makeID($options).'" value="'.$options['value'].'" '.$extraOptions.' />';
			break;
			case 'file':
				$returnValue = '<input class="'.$classes.'" type="'.$options['type'].'" name="'.self::makeName($options).'" id="'.$this->makeID($options).'" value="'.$options['value'].'" '.$extraOptions.' />';
			break;
			case 'password':
				if (isset($options['size']))
				{
					$options['size'] = min($options['size'], $options['maxlength']); // Size can't be bigger than maxlength
					$text['size'] = 'size="'.$options['size'].'"';
				}
				$returnValue = '<input class="'.$classes.'" type="'.$options['type'].'" '.$text['size'].' maxlength="'.$options['maxlength'].'" name="'.self::makeName($options).'"  value="'.htmlspecialchars($options['value']).'" id="'.$this->makeID($options).'"'.$extraOptions.' />';
			break;
			case 'textarea':
				$returnValue = '<textarea class="'.$classes.'" cols="'.$options['cols'].'" rows="'.$options['rows'].'" name="'.self::makeName($options).'" id="'.$this->makeID($options).'"'.$extraOptions.'>'.$options['value'].'</textarea>';
			break;
			case 'image':
				$returnValue = '<input class="'.$classes.'" type="'.$options['type'].'" name="'.self::makeName($options).'[]" id="'.$this->makeID($options).'" value="'.$options['value'].'" title="'.$options['title'].'" src="'.$options['src'].'" '.$extraOptions.' />';
			break;
			default:	// Default = 'text'
				if (isset($options['size']))
				{
					$options['size'] = min($options['size'], $options['maxlength']); // Size can't be bigger than maxlength
					$text['size'] = 'size="'.$options['size'].'"';
				}
				
				$returnValue = '<input class="text '.$classes.'" type="text" '.$text['size'].' maxlength="'.$options['maxlength'].'" name="'.self::makeName($options).'" id="'.$this->makeID($options).'" value="'.htmlspecialchars($options['value']).'"'.$extraOptions.' />';
		}
		return $returnValue;
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
	public function addSection($section)
	{
		$section['inputs'] = array();
		$this->sections[ $section['name'] ] = $section;
	}
	
	private function implodeArray($data, &$strings, $glueBefore, $glueAfter, $stringPrefix = null, $currentString = null)
	{
		foreach($data as $key=>$value)
		{
			if (!is_array($value))
			{
				$stringToAdd = $stringPrefix . $currentString . $glueBefore . $key . $glueAfter;
				$strings[$stringToAdd] = $value;
			}
			else
				$this->implodeArray($value, $strings, $glueBefore, $glueAfter,  $stringPrefix, $currentString . $glueBefore.$key.$glueAfter);
		}
	}
	
	public function usePostValue(&$input, $settings, $postData, $key)
	{
		if ($input['type']=='submit')		// Submits don't get their values posted, so return the value.
			return $input['value'];
			
		$input['name'] = self::fixName($input['name']);
			
		// Merge the default options.
		// In case this class was created with a nameprefix and the individual inputs weren't, for example.
		$input = array_merge($this->options, $input);
			
		if ($postData === null)
		{
			if ($settings !== null)
				if (in_array($input['type'], array('text', 'textarea', 'checkbox', 'select')))
					if (@$input['value'] == '')
						$input['value'] = stripslashes( $settings->get($key) );
			return;
		}
		
		// Nameprefix? Find the right array section in the post.			
		if ($input['nameprefix'] != '')
		{
			$strings = '';
			$this->implodeArray($postData, $strings, '__', '', $this->options['class'] . '');
		}
		else
		{
			if (isset($postData[$input['name']]))
				if (!is_array($postData[$input['name']]))
					$input['value'] = @stripslashes($postData[$input['name']]);		// @ is for unchecked checkboxes. *sigh*
				else
				{
					$input['value'] = array();	// Kill the value, otherwise postvalues are just appended and therefore do nothing.
					foreach($postData[$input['name']] as $index=>$value)
						$input['value'][$index] = stripslashes($value);
				}
		}
		
		$inputID = $this->makeID($input);
		if (isset($strings[$inputID]))
		{
			switch($input['type'])
			{
				default:
					@$input['value'] = stripslashes( $strings[$inputID] );
			}
		}
	}
	
	/**
	 * Adds an input to a section.
	 * Kinda like makeInput, but with a section name.
	 */
	public function addSectionInput($section, $input, $settings, $postData = null)
	{
		if (!is_string($input))
		{
			$input = array_merge($this->options, $input);
			if ($input['type'] == 'rawtext')
				$input['name'] = rand(0, time());
			$this->usePostValue($input, $settings, $postData, $input['name']);
		}
		else
			$input = array(
				'type' => 'rawtext',
				'value' => $input,
			);
		if ($input['type'] == 'rawtext')
			$input['name'] = rand(0, time());
			
		$this->sections[ $section['name'] ]['inputs'][] = $input;
	}
	
	public function addSectionText($sectionName, $text)
	{
		$this->sections[ $sectionName ]['inputs'][] = array(
			'type' => 'rawtext',
			'value' => $text,
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
	public function addLayout($layouts, $inputData, $settings, $postData = null)
	{
		$layoutDefaults = array(
			'autoinputs' => array(),
			'inputs' => array(),
		);

		foreach($layouts as $sectionNumber => $layout)
		{
			$this->addSection($layout);
			$layout = array_merge($layoutDefaults, $layout);
			
			foreach($layout['autoinputs'] as $inputName)
			{
				$input = $inputData[$inputName];
				
				$input['name'] = $inputName;

				$this->addSectionInput($layout, $input, $settings, $postData);
			}
			foreach($layout['inputs'] as $manualInput)
				$this->addSectionInput($layout, $manualInput, $settings, $postData);
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
		
		$returnValue = $style['formStart'] . "\n";
		
		foreach($this->sections as $sectionName => $sectionData)
		{
			$sectionData = array_merge(array(
				'description' => null,
				'cssClass' => null,
			), $sectionData);
			$sectionDescription = ($sectionData['description']!=null ? $style['sectionDescriptionStart'] . $sectionData['description'] . $style['sectionDescriptionStop'] . "\n" : '');
			$returnValue .= $style['sectionStart'] . "\n" .
				$style['sectionNameStart'] . "\n" .
					'			' . $sectionName . "\n" .
				$style['sectionNameStop'] . "\n" .
				$sectionDescription;
				
			$returnValue = str_replace('%%CSSCLASS%%', $sectionData['cssClass'], $returnValue);
				
			foreach($sectionData['inputs'] as $index=>$input)
			{
				if ($input['type'] == 'hidden')
				{
					$returnValue .= $this->makeInput($input);
					continue;
				}	

				// Force validation stuff to make a description if there isn't one already.
				$validation = $this->makeValidation($input);
				if ($validation != '' && !isset($input['description']))
					$input['description'] = '';
					
				$description = (isset($input['description']) ?
					$style['descriptionContainerStart'] . "\n".
						$style['descriptionStart'] . "\n".
							$this->makeDescription($input) . "\n".
						$style['descriptionStop'] . "\n".
					$style['descriptionContainerStop'] . "\n"
				: '');
				
				if ($input['type'] == 'rawtext')
				{
					if (isset($input['cssClass']))
						$inputData = '<div class="'.$input['cssClass'].'">'.$input['value'].'</div>';
					else
						$inputData = $input['value'];
				}
				else
				{
					if (!isset($input['displayTwoRows']))
						$input['displayTwoRows'] = false;		// Standard is one row
					
					
					if ($input['type'] == 'submit')
						$input['displayTwoRows'] = true;
						
					if ($input['displayTwoRows'] == true)
					{
						$inputData =
							$style['labelContainerSingleStart'] . "\n".
								$style['labelStart'] . "\n".
									'					' . $this->makeLabel($input) . "\n".
								$style['labelStop'] . "\n".
							$style['labelContainerSingleStop'] . "\n".
							
							$style['inputContainerSingleStart'] . "\n".
								$style['inputStart'] . "\n".
									'					' . $this->makeInput($input) . "\n".
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
									'					' . $this->makeLabel($input) . "\n".
								$style['labelStop'] . "\n".
							$style['labelContainerStop'] . "\n".
								
							$style['inputContainerStart'] . "\n".
								$style['inputStart'] . "\n".
									'					' . $this->makeInput($input) . "\n".
								$style['inputStop'] . "\n".
							$style['inputContainerStop'] . "\n".
							"</div>" .
							$description;
					}
				}

				$returnValue .= 
					'		<div class="row'.($index+1).'">' . "\n" .
						$style['rowStart'] . "\n".
							$inputData .  "\n" .
						$style['rowStop']  . "\n" .
					'		</div>' . "\n";
			}
			$returnValue .= $style['sectionStop'] . "\n";
		}
		
		$returnValue .= $style['formStop'] . "\n";
		
		return str_replace('%%STYLE%%', $this->options['style'], $returnValue);
	}

	/**
	 * Generates an SQL-statement that either updates or inserts the values into a table.
	 * 
	 * On an update, it only changes the values that have changed (compares newvalues to oldvalues).
	 * 
	 * @param	inputs An array of inputs in standard input format (see top).
	 * @param	inputsToChange An array of names of which inputs to change.
	 * @param	tableName The table's name
	 */
	public static function generateChangedSQL($inputs, $inputsToChange, $tableName, $uniqueKey, $oldValues, $newValues)
	{
		if ($oldValues === null)
		{
			// Insert.
			
			// Make the keys and values paranthesis.
			$keys = '';
			$values = '';
			foreach($inputsToChange as $key)
			{
				if ($key == $uniqueKey)		// otherwise a null-value is assigned the the query has no effect.
					continue;
					
				$keys .= $key . ', ';
				
				switch($inputs[$key]['type'])
				{
					case 'rawtext':
						continue;
					break;
					case 'checkbox':
						if (isset($newValues[$key]))
							$value = "'1'";
						else
							$value = "'0'";
					break;
					default:
						if ($newValues[$key] == '')
							$value = 'NULL';
						else
							$value = "'$newValues[$key]'";
					break;
				}
				$values .= $value . ', ';
			}
			$keys = '(' . trim($keys, ', ') . ')';
			$values = '(' . trim($values, ', ') . ')';
			
			$returnValue = 'INSERT INTO ' . $tableName . " $keys VALUES $values";
		}
		else
		{
			// Update.
			
			// Sometimes, there might be no new values at all for specific keys. Make sure each newvalue key exists.
			foreach($inputsToChange as $newKey)
				if (!isset($newValues[$newKey]))
					$newValues[$newKey] = null;
			
			// Make the sets
			$sets = '';
			foreach($inputsToChange as $key)
			{
				switch($inputs[$key]['type'])
				{
					case 'rawtext':
						continue;
					break;
					case 'checkbox':
					{
						if ($oldValues[$key] == '1' && !isset($newValues[$key]))
							$sets .= "$key = '0', ";
						if ($oldValues[$key] == '0' && isset($newValues[$key]))
							if ($newValues[$key] !== null)
								$sets .= "$key = '1', ";
					}
					break;
					default:
						if ($oldValues[$key] !== $newValues[$key])
						{
							if ($newValues[$key] === '' | $newValues[$key] === null)
								$sets .= "$key = NULL, " ;
							else
							{
								// If value is an array (multiple choice select input), serialize it.
								if (is_array($newValues[$key]))
									$newValues[$key] = serialize($newValues[$key]);
								$sets .= "$key = '".addslashes($newValues[$key])."', ";
							}
						}
					break;
				}
			}
			$sets = trim($sets, ', ');
			
			if ($sets != '')
				$returnValue = 'UPDATE ' . $tableName . ' SET ' . $sets . " WHERE $uniqueKey = '$oldValues[$uniqueKey]'";
			else
				$returnValue = '';
		}
		return $returnValue;
	}

	/**
	Validate an email address.
	Provide email address (raw input)
	Returns true if the email address has the email 
	address format and the domain exists.
	*/
	public static function validEmail($email)
	{
	   $isValid = true;
	   $atIndex = strrpos($email, "@");
	   if (is_bool($atIndex) && !$atIndex)
	   {
	      $isValid = false;
	   }
	   else
	   {
	      $domain = substr($email, $atIndex+1);
	      $local = substr($email, 0, $atIndex);
	      $localLen = strlen($local);
	      $domainLen = strlen($domain);
	      if ($localLen < 1 || $localLen > 64)
	      {
	         // local part length exceeded
	         $isValid = false;
	      }
	      else if ($domainLen < 1 || $domainLen > 255)
	      {
	         // domain part length exceeded
	         $isValid = false;
	      }
	      else if ($local[0] == '.' || $local[$localLen-1] == '.')
	      {
	         // local part starts or ends with '.'
	         $isValid = false;
	      }
	      else if (preg_match('/\\.\\./', $local))
	      {
	         // local part has two consecutive dots
	         $isValid = false;
	      }
	      else if (!preg_match('/^[A-Za-z0-9\\-\\.]+$/', $domain))
	      {
	         // character not valid in domain part
	         $isValid = false;
	      }
	      else if (preg_match('/\\.\\./', $domain))
	      {
	         // domain part has two consecutive dots
	         $isValid = false;
	      }
	      else if
	(!preg_match('/^(\\\\.|[A-Za-z0-9!#%&`_=\\/$\'*+?^{}|~.-])+$/',
	                 str_replace("\\\\","",$local)))
	      {
	         // character not valid in local part unless 
	         // local part is quoted
	         if (!preg_match('/^"(\\\\"|[^"])+"$/',
	             str_replace("\\\\","",$local)))
	         {
	            $isValid = false;
	         }
	      }
	      if ($isValid && !(checkdnsrr($domain,"MX") || checkdnsrr($domain,"A")))
	      {
	         // domain not found in DNS
	         $isValid = false;
	      }
	   }
	   return $isValid;
	}

	/**
	 * Validates the values based on $inputs and which of the $inputs to check.
	 * 
	 * Returns an array of $key / 'error message' if validation fails, else returns true.
	 */		
	public function validatePost($inputs, $inputsToCheck, $values)
	{
		$returnValue = array();
		
		foreach($inputsToCheck as $key)
		{
			$input = $inputs[$key];
			
			// Because form fixes the name to remove illegal characters, we need to do the same to the key here so that we find the correct values in the POST.
			$key = self::fixName($key);
			
			$input['type'] = (isset($input['type']) ? $input['type'] : 'text');		// Assume type text.
		
			switch($input['type'])
			{
				case 'text':
					if (isset( $input['validation']['email']) )
					{
						$email = trim($values[$key]);
						if (!self::validEmail($email))
							$returnValue[$key] = $this->l('Invalid email address') . ': ' . $email;
					}
					if (isset( $input['validation']['datetime']) )
					{
						$text = trim($values[$key]);
						$date = strtotime($text);
						$dateFormat = $input['validation']['datetime'];
						
						if (date($dateFormat, $date) != $text && !isset($input['validation']['empty']))
							$returnValue[$key] = $this->l('Could not parse date') . ': ' . $text . ' (' . $input['label'] . ')';
					}
					if (isset( $input['validation']['datemaximum']) )
					{
						$date = strtotime($values[$key]);
						if ($date > strtotime($input['validation']['datemaximum']))
							$returnValue[$key] = $this->l('Invalid date') . ': ' . $text . ' (' . $input['label'] . ')';
					}
					if (isset( $input['validation']['lengthmin']) )
					{
						$text = trim($values[$key]);
						if (strlen($text) < $input['validation']['lengthmin'])
							$returnValue[$key] = '<span class="validation-field-name">' . $input['label'] . '</span> ' . $this->l('must be at least') . ' <em>' . $input['validation']['lengthmin'] . '</em> ' . $this->l('characters long') . '.';
					}

					if (isset( $input['validation']['valuemin']) )
					{
						// First convert to correct type of number...
						if (is_float($input['validation']['valuemin']))
							$value = floatval($values[$key]);
						if (is_int($input['validation']['valuemin']))
							$value = intval($values[$key]);

						if ($value < $input['validation']['valuemin'])
							$returnValue[$key] = '<span class="validation-field-name">' . $input['label'] . '</span> ' . $this->l('may not be smaller than') . ' ' . $input['validation']['valuemin'];
					}
					if (isset( $input['validation']['valuemax']) )
					{
						// First convert to correct type of number...
						if (is_float($input['validation']['valuemin']))
							$value = floatval($values[$key]);
						if (is_int($input['validation']['valuemin']))
							$value = intval($values[$key]);

						if ($value > $input['validation']['valuemax'])
							$returnValue[$key] = '<span class="validation-field-name">' . $input['label'] . '</span> ' . $this->l('may not be larger than') . ' ' . $input['validation']['valuemax'];
					}
				case 'textarea':
					// Is the value allowed to be empty?
					if (!isset( $input['validation']['empty']) )
						if (trim($values[$key]) == '')
							$returnValue[$key] = '<span class="validation-field-name">' . $input['label'] . '</span> ' . $this->l('may not be empty');
				break;
			}
		}
		
		if (count($returnValue)> 0)
			return $returnValue;
		else
			return true;
	}
	
	/**
	 * Returns the translated string, if any.
	 */
	public function l($string, $replacementString = array())
	{
		$language = $this->options['language'];
		
		if (!isset($this->languageData[$string]))
			$returnValue = $string;
		else
			if (isset($this->languageData[$string][$language]))
				$returnValue = $this->languageData[$string][$language];
			else
				$returnValue = $string;
				
		while (count($replacementString) > 0)
		{
			$returnValue = preg_replace('/%s/', reset($replacementString), $returnValue, 1);
			array_shift($replacementString);
		}
			
		return $returnValue; 
	}
}

class edwardFormLanguage
{
    public static $languageData = array(
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
?>