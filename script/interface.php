<?php
	use rapidweb\googlecontacts\factories\ContactFactory;
	require_once '../config.php';
	dol_include_once('/googlecontactsync/class/gcs.class.php');
	dol_include_once('/googlecontactsync/lib/googlecontactsync.lib.php');

	$conf->modules_parts['triggers']=array();// Ouh que c'est moche mais sinon le trigger de dropcloud passe foutre le merdier
	
	$put = GETPOST('put');
	$get = GETPOST('get');
	
	$_SESSION['GCS_fk_user'] = $user->id;
	
switch ($put) {
	case 'sync':
		
		__out(_sync(GETPOST('nb')),'json');
			
		break;
	
	case 'optimizedSync':
		
		__out(_optimizedSync(),'json');
			
		break;
	
	case 'setGroup':
		
		__out(_setGroup(GETPOST('name')),'json');
		
		break;
	case 'deleteAllGroups':
		require_once __DIR__.'/../php-google-contacts-v3-api/vendor/autoload.php';
		rapidweb\googlecontacts\factories\ContactFactory::deleteAllGroups('https://www.google.com/m8/feeds/groups/'.urlencode($user->email).'/full');
		

		break;	
}

switch($get) {
	case 'all-contact':
		__out(_getAllContact(),'json');
		break;
}


function _setGroup($name) {
	global $user;
	
	$PDOdb=new \TPDOdb;
	
	require_once __DIR__.'/../php-google-contacts-v3-api/vendor/autoload.php';
	
	return \TGCSToken::setGroup($user,$name);
	
}

function _sync($nb=5) {
	global $user,$fk_user_gcs;
	$PDOdb=new \TPDOdb;
	if(empty($nb))$nb = 5;
	$TToken = \TGCSToken::getTokenToSync($PDOdb,$user->id,$nb);

	$TSync=array();
	foreach($TToken as &$token) {
		$res = $token->sync($PDOdb);
		$token->to_sync = 0; 
		// Desactivé pour éviter appel infini
		/*if($res === false) {
			$token->to_sync = 1;
			$token->save($PDOdb);
		}
		else{*/
			$token->save($PDOdb);
			$TSync[] = $token;
		//}
	}
	
	return $TSync;
}

function _optimizedSync() {
	global $user,$fk_user_gcs;

	require_once __DIR__.'/../php-google-contacts-v3-api/vendor/autoload.php';
	
	$PDOdb=new \TPDOdb;
	
	$TToken = \TGCSToken::getTokenToSync($PDOdb,$user->id);

	$TSync = array();

	$apiBaseURL = 'https://www.google.com/m8/feeds/contacts/'.urlencode($user->email).'/thin';

	$contacts = rapidweb\googlecontacts\factories\ContactFactory::getAll();

	if(!empty($conf->global->GCS_GOOGLE_GROUP_NAME)) {
		$contactGroup = \TGCSToken::setGroup($user, $conf->global->GCS_GOOGLE_GROUP_NAME);
		// $contact->groupMembershipInfo = $contactGroup->id; 
	}

/*
	echo '<pre>';
	var_dump($contacts);
	echo '</pre>';

	echo '<pre>';
	var_dump($TToken);
	echo '</pre>';
*/

	if (! empty($contacts))
	{
		$contactKeys = array();
		foreach($contacts as $contact) {
			$contactID = $contact->id;
			// echo $contactID, "<br />";
			$contactKeys[] = substr($contactID, strrpos($contactID, '/') + 1);
		}
/*
		echo '<pre>';
		var_dump($contactKeys);
		echo '</pre>';
*/
		foreach($TToken as $token) {
/*
			echo '<pre>';
			var_dump($token->token);
			echo '</pre>';
*/
			$tok = $token->token;
			
			$index = array_search(substr($tok, strrpos($tok, '/') + 1), $contactKeys);

			if($index >= 0)
			{
				// echo "Trouvé contact ".$token->token."<br />";
				// $token->contact = new rapidweb\googlecontacts\objects\Contact($contacts[$index]);
				$token->contact = $contacts[$index];
			}

			if(!empty($conf->global->GCS_GOOGLE_GROUP_NAME)) {
				$token->contact->groupMembershipInfo = $contactGroup->id;
			}

			$res = $token->optimizedSync($PDOdb);
			$token->to_sync = 0; 
			// Desactivé pour éviter appel infini
			/*if($res === false) {
				$token->to_sync = 1;
				$token->save($PDOdb);
			}
			else{*/
				$token->save($PDOdb);
				$TSync[] = $token;
			//}
		}
	}

	return $TSync;
}
