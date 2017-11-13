<?php
/* <one line to give the program's name and a brief idea of what it does.>
 * Copyright (C) 2015 ATM Consulting <support@atm-consulting.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * \file    class/actions_googlecontactsync.class.php
 * \ingroup googlecontactsync
 * \brief   This file is an example hook overload class file
 *          Put some comments here
 */

/**
 * Class ActionsgoogleContactSync
 */
class Actionsgooglecontactsync
{
	/**
	 * @var array Hook results. Propagated to $hookmanager->resArray for later reuse
	 */
	public $results = array();

	/**
	 * @var string String displayed by executeHook() immediately after return
	 */
	public $resprints;

	/**
	 * @var array Errors
	 */
	public $errors = array();

	/**
	 * Constructor
	 */
	public function __construct()
	{
	}

	/**
	 * Overloading the doActions function : replacing the parent's function with the one below
	 *
	 * @param   array()         $parameters     Hook metadatas (context, etc...)
	 * @param   CommonObject    &$object        The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param   string          &$action        Current action (if set). Generally create or edit or null
	 * @param   HookManager     $hookmanager    Hook manager propagated to allow calling another hook
	 * @return  int                             < 0 on error, 0 on success, 1 to replace standard code
	 */
	function formObjectOptions($parameters, &$object, &$action, $hookmanager)
	{
		global $user;
		if (in_array('usercard', explode(':', $parameters['context']))
				&& $user->id == $object->id) // action personnelle
		{
		  	global $langs;
			$langs->load('googlecontactsync@googlecontactsync');
			
			define('INC_FROM_DOLIBARR',true);
			dol_include_once('/googlecontactsync/config.php');
			dol_include_once('/googlecontactsync/class/gcs.class.php');
				  	
			$PDOdb=new TPDOdb;
			$token = TGCSToken::getTokenFor($PDOdb, $object->id, 'user');
		  
		  	global $langs,$conf,$user,$db;
		  
			$user_card_url = (DOL_VERSION < 3.6) ? '/user/fiche.php' : '/user/card.php';

		  	if(empty($object->email)) {
		  		$button = $langs->trans('SetYourEmailToGetToken');
		  	}
			else if(empty($token)) {
			    	$button = '<a href="'.dol_buildpath('/googlecontactsync/php-google-contacts-v3-api/authorise-application.php',2).'?fk_user='.$object->id.'">'.$langs->trans('GetYourToken').'</a>';
			}
			else{
				$button = '<a href="'.dol_buildpath('/googlecontactsync/php-google-contacts-v3-api/authorise-application.php',2).'?fk_user='.$object->id.'">'.$langs->trans('UserHasToken').'</a>'.img_info('Token : '.$token->token.' - Refresh : '.$token->refresh_token);
				$button .=' <a href="'.dol_buildpath($user_card_url,1).'?id='.$object->id.'&action=removeMyToken">'.$langs->trans('Remove').'</a>'.img_info($langs->trans('RemoveToken'));
				$button .=' <a href="'.dol_buildpath($user_card_url,1).'?id='.$object->id.'&action=testTokenGoogle">'.$langs->trans('Test').'</a>'.img_info($langs->trans('TestToken'));
			}
		  
		  
		  	echo '
		  		<tr><td>'.$langs->trans('TokenForUser').'</td><td>'.$button.'</td></tr>
		  	';
		  	
		  	
		  
		}
		

	}
	
	function doActions($parameters, &$object, &$action, $hookmanager) {
		
		
		if (in_array('usercard', explode(':', $parameters['context']))
				&& ($action == 'removeMyToken' || $action=='testTokenGoogle'))
		{
			global $langs,$user;
			$langs->load('googlecontactsync@googlecontactsync');
			
			define('INC_FROM_DOLIBARR',true);
			dol_include_once('/googlecontactsync/config.php');
			dol_include_once('/googlecontactsync/class/gcs.class.php');
			
			if($action=='testTokenGoogle') {
				dol_include_once('/googlecontactsync/lib/googlecontactsync.lib.php');
				$TContact  = _getAllContact();
				if(!empty($TContact[0]->id)) {
					setEventMessage($langs->trans('TokenSeemsOK'));
				}
				else{
					setEventMessage($langs->trans('TokenSeemsKO'),'warning');
				}
			}
			else {
				$PDOdb=new TPDOdb;
				$token = TGCSToken::getTokenFor($PDOdb, $user->id, 'user');
				$token->delete($PDOdb);
				
			}
			
		}
		else if ((in_array('contactcard', explode(':', $parameters['context']))
				|| in_array('thirdpartycard', explode(':', $parameters['context'])))
				&& $action == 'syncToPhone')
		{
			global $langs,$user;
			$langs->load('googlecontactsync@googlecontactsync');
		
			define('INC_FROM_DOLIBARR',true);
			dol_include_once('/googlecontactsync/config.php');
			dol_include_once('/googlecontactsync/class/gcs.class.php');
		
			$PDOdb=new TPDOdb;
			$token = TGCSToken::getTokenFor($PDOdb, $user->id, 'user');
		
			global $langs,$conf,$user,$db;
		
			if(!empty($token)) {
				
				$fk_object = $object->id;
				if(empty($fk_object))$fk_object=GETPOST('id');
				if(empty($fk_object))$fk_object=GETPOST('socid');
				
				TGCSToken::setSync($PDOdb,$fk_object, $object->element, $user->id);
				
			}
		
		}
	
	}
	
	function addMoreActionsButtons($parameters, &$object, &$action, $hookmanager) {
		if (in_array('contactcard', explode(':', $parameters['context'])) 
				|| in_array('thirdpartycard', explode(':', $parameters['context'])))
		{
			global $langs,$user;
			$langs->load('googlecontactsync@googlecontactsync');
		
			define('INC_FROM_DOLIBARR',true);
			dol_include_once('/googlecontactsync/config.php');
			dol_include_once('/googlecontactsync/class/gcs.class.php');
		
			$PDOdb=new TPDOdb;
			$token = TGCSToken::getTokenFor($PDOdb, $user->id, 'user');
		
			global $langs,$conf,$user,$db;
		
			if(!empty($token)) {
				if($object->element == 'contact' && !empty($conf->global->GCS_GOOGLE_SYNC_CONTACT)) {
					echo '<a class="butAction" href="'.dol_buildpath('/contact/card.php',1).'?id='.$object->id.'&action=syncToPhone">'.$langs->trans('SyncCardToPhone').'</a>';
				}
				else if($object->element == 'societe' && !empty($conf->global->GCS_GOOGLE_SYNC_THIRDPARTY)) {
					echo '<a class="butAction" href="'.dol_buildpath('/societe/soc.php',1).'?socid='.$object->id.'&action=syncToPhone">'.$langs->trans('SyncCardToPhone').'</a>';
				}
				
			}
		
		}
	}
}
