 <?php
 /**
  * @version		
  * @copyright	Copyright (C) 2005 - 2010 Open Source Matters, Inc. All rights reserved.
  * @license		GNU General Public License version 2 or later; see LICENSE.txt
  */

 defined('JPATH_BASE') or die;


 // Include EmailOctopus wrapper class
 $component_path = JPATH_SITE . '/components/com_biodiv';

//error_log("Component path = " . $component_path );

require_once($component_path.'/local.php');
require_once($component_path.'/BiodivOctopus.php');

  /**
   * An example custom profile plugin.
   *
   * @package		Joomla.Plugins
   * @subpackage	user.profile
   * @version		1.6
   */
  class plgUserProfileMW extends JPlugin
  {
	/**
	 * @param	string	The context for the data
	 * @param	int		The user id
	 * @param	object
	 * @return	boolean
	 * @since	1.6
	 */
	function onContentPrepareData($context, $data)
	{
		
			
		// Check we are manipulating a valid form.
		if (!in_array($context, array('com_users.profile','com_users.registration','com_users.user','com_admin.profile'))){
			return true;
		}

		$userId = isset($data->id) ? $data->id : 0;
		
		// Load the profile data from the database.
		$db = JFactory::getDbo();
		$db->setQuery(
			'SELECT profile_key, profile_value FROM #__user_profiles' .
			' WHERE user_id = '.(int) $userId .
			' AND profile_key LIKE \'profileMW.%\'' .
			' ORDER BY ordering'
		);
		$results = $db->loadRowList();

		// Check for a database error.
		if ($db->getErrorNum()) {
			$this->_subject->setError($db->getErrorMsg());
			return false;
		}

		// Merge the profile data.
		$data->profileMW = array();
		foreach ($results as $v) {
			$k = str_replace('profileMW.', '', $v[0]);
			$data->profileMW[$k] = json_decode($v[1], true);
		}
		
		$input = JFactory::getApplication()->input;
		$gbwNum = $input->getString("u", 0);
		
		if ( $gbwNum ) {
			$data->profileMW['gardenbw'] = $gbwNum;
		}

		return true;
	}

	/**
	 * @param	JForm	The form to be altered.
	 * @param	array	The associated data for the form.
	 * @return	boolean
	 * @since	1.6
	 */
	function onContentPrepareForm($form, $data)
	{
		// Load user_profile plugin language
		$lang = JFactory::getLanguage();
		$lang->load('plg_user_profileMW', JPATH_ADMINISTRATOR);

		if (!($form instanceof JForm)) {
			$this->_subject->setError('JERROR_NOT_A_FORM');
			return false;
		}
		// Check we are manipulating a valid form.
		if (!in_array($form->getName(), array('com_users.profile', 'com_users.registration','com_users.user','com_admin.profile'))) {
			return true;
		}
		if ($form->getName()=='com_users.profile')
		{
			// Add the profile fields to the form.
			JForm::addFormPath(dirname(__FILE__).'/profiles');
			$form->loadFile('profile', false);
	
			// Toggle whether the something field is required.
			if ($this->params->get('profile-require_subscribe', 1) > 0) {
				$form->setFieldAttribute('subscribe', 'required', $this->params->get('profile-require_subscribe') == 2, 'profileMW');
			} else {
				$form->removeField('subscribe', 'profileMW');
			}
			if ($this->params->get('profile-require_wherehear', 1) > 0) {
				$form->setFieldAttribute('wherehear', 'required', $this->params->get('profile-require_wherehear') == 2, 'profileMW');
			} else {
				$form->removeField('wherehear', 'profileMW');
			}
			if ($this->params->get('profile-require_gardenbw', 1) > 0) {
				$form->setFieldAttribute('gardenbw', 'required', $this->params->get('profile-require_gardenbw') == 2, 'profileMW');
			} else {
				$form->removeField('gardenbw', 'profileMW');
			}
		}

		//In this example, we treat the frontend registration and the back end user create or edit as the same. 
		elseif ($form->getName()=='com_users.registration' || $form->getName()=='com_users.user' )
		{		
			// Add the registration fields to the form.
			JForm::addFormPath(dirname(__FILE__).'/profiles');
			$form->loadFile('profile', false);
			
			/* Try removing this section and using the xml file to specify whether something is required
			// Toggle whether the something field is required.
			if ($this->params->get('register-require_subscribe', 1) > 0) {
				$form->setFieldAttribute('subscribe', 'required', ($this->params->get('register-require_subscribe') == 2) ? 'required' : '', 'profileMW');
				//$form->setFieldAttribute('subscribe', 'required', $this->params->get('register-require_subscribe') == 2, 'profileMW');
			} else {
				$form->removeField('subscribe', 'profileMW');
			}
			if ($this->params->get('register-require_wherehear', 1) > 0) {
				$form->setFieldAttribute('wherehear', 'required', ($this->params->get('register-require_wherehear') == 2) ? 'required' : '', 'profileMW');
				//$form->setFieldAttribute('wherehear', 'required', $this->params->get('register-require_wherehear') == 2, 'profileMW');
			} else {
				$form->removeField('wherehear', 'profileMW');
			}
			if ($this->params->get('register-require_tos', 1) > 0) {
				$form->setFieldAttribute('tos', 'required', 'required', 'profileMW');
				//$form->setFieldAttribute('tos', 'required', ($this->params->get('register-require_tos') == 2) ? 'required' : '', 'profileMW');
				//$form->setFieldAttribute('tos', 'required', $this->params->get('register-require_tos') == 2, 'profileMW');
			} else {
				$form->removeField('tos', 'profileMW');
			}
			if ($this->params->get('register-require_gardenbw', 1) > 0) {
				$form->setFieldAttribute('gardenbw', 'required', ($this->params->get('register-require_gardenbw') == 2) ? 'required' : '', 'profileMW');
			} else {
				$form->removeField('gardenbw', 'profileMW');
			}
			*/
		}			
	}
	
	function onUserBeforeSave ($user, $isnew, $data)
	{
		// Check that the tos is checked if required ie only in registration from frontend.
		$task       = JFactory::getApplication()->input->getCmd('task');
		$option     = JFactory::getApplication()->input->getCmd('option');
		
		$tosenabled = ($this->params->get('register-require_tos', 0) == 2);

		// Check that the tos is checked.
		if ($task === 'register' && $tosenabled && $option === 'com_users' && array_key_exists('tos', $data['profileMW']) && !$data['profileMW']['tos'])
		{
			throw new InvalidArgumentException(JText::_('PLG_USER_PROFILEMW_FIELD_TOS_DESC_SITE'));
		}
		
		return true;
	}

	function onUserAfterSave($data, $isNew, $result, $error)
	{
		$userId	= JArrayHelper::getValue($data, 'id', 0, 'int');
		
		if ($userId && $result && isset($data['profileMW']) && (count($data['profileMW'])))
		{
			try
			{
				$db = JFactory::getDbo();
				$db->setQuery('DELETE FROM #__user_profiles WHERE user_id = '.$userId.' AND profile_key LIKE \'profileMW.%\'');
				if (!$db->query()) {
					throw new Exception($db->getErrorMsg());
				}

				$tuples = array();
				$order	= 1;
				$toSubscribe = false;
				foreach ($data['profileMW'] as $k => $v) {
					//$tuples[] = '('.$userId.', '.$db->quote('profileMW.'.$k).', '.$db->quote(json_encode($v)).', '.$order++.')';
					$tuples[] = '('.$userId.', '.$db->quote('profileMW.'.$k).', '.json_encode($v).', '.$order++.')';
					
					if ( $k == 'subscribe' and $v == '1' ) {
						$toSubscribe = true;
						//error_log ("User " . $userId . " requests subscription to newsletter");
					}
				}

				$db->setQuery('INSERT INTO #__user_profiles VALUES '.implode(', ', $tuples));
				if (!$db->query()) {
					throw new Exception($db->getErrorMsg());
				}
				
				if ( $toSubscribe ) {
					
					$email	= JArrayHelper::getValue($data, 'email', 0, 'string');$name	= JArrayHelper::getValue($data, 'name', 0, 'string');
		
					$octopus = new BiodivOctopus();
					
					$subscribeSuccess = $octopus->subscribe ( $email, $name );
					
					if ( !$subscribeSuccess ) {
						
						error_log ("Failed to subscribe to newsletter for user " . $userId . ", email " . $email . ", name " . $name );
					}
				}
				
				
			}
			catch (JException $e) {
				$this->_subject->setError($e->getMessage());
				return false;
			}
		}

		return true;
	}

	/**
	 * Remove all user profile information for the given user ID
	 *
	 * Method is called after user data is deleted from the database
	 *
	 * @param	array		$user		Holds the user data
	 * @param	boolean		$success	True if user was succesfully stored in the database
	 * @param	string		$msg		Message
	 */
	function onUserAfterDelete($user, $success, $msg)
	{
		if (!$success) {
			return false;
		}

		$userId	= JArrayHelper::getValue($user, 'id', 0, 'int');

		if ($userId)
		{
			try
			{
				$db = JFactory::getDbo();
				$db->setQuery(
					'DELETE FROM #__user_profiles WHERE user_id = '.$userId .
					" AND profile_key LIKE 'profileMW.%'"
				);

				if (!$db->query()) {
					throw new Exception($db->getErrorMsg());
				}
			}
			catch (JException $e)
			{
				$this->_subject->setError($e->getMessage());
				return false;
			}
		}

		return true;
	}


 }