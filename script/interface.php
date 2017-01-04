<?php
//	use rapidweb\googlecontacts\factories\ContactFactory;

	require '../config.php';
	dol_include_once('/googlecontactsync/class/gcs.class.php');

	$put = GETPOST('put');
	$get = GETPOST('get');
	
	$_SESSION['GCS_fk_user'] = $user->id;
	
switch ($put) {
	case 'sync':
		
		__out(_sync(),'json');
			
		break;
	
	case 'setGroup':
		
		__out(_setGroup(GETPOST('name')),'json');
		
		break;
	
}

switch($get) {
	case 'all-contact':
		__out(_getAllContact(),'json');
		break;
}

function _getAllContact() {
	global $user;

	require_once __DIR__.'/../php-google-contacts-v3-api/vendor/autoload.php';

	$_SESSION['GCS_fk_user'] = $user->id;

	return  \rapidweb\googlecontacts\factories\ContactFactory::getAll();

}

function _setGroup($name) {
	global $user;
	
	$PDOdb=new \TPDOdb;
	
	require_once __DIR__.'/../php-google-contacts-v3-api/vendor/autoload.php';
	
	return \TGCSToken::setGroup($PDOdb,$user->id,$name);
	
}

function _sync() {
	global $user,$fk_user_gcs;
	
	$PDOdb=new \TPDOdb;
	
	$TToken = \TGCSToken::getTokenToSync($PDOdb,$user->id);
	
	$TSync=array();
	foreach($TToken as &$token) {
		
		$res = $token->sync($PDOdb);
		$token->to_sync = 0;
		if($res === false) {
			$token->to_sync = 1;
			$token->save($PDOdb);
		}
		else{
			$token->save($PDOdb);
			$TSync[] = $token;
		}
	}
	
	
	
	return $TSync;
}
