<?php
//	use rapidweb\googlecontacts\factories\ContactFactory;

	require '../config.php';
	dol_include_once('/googlecontactsync/class/gcs.class.php');

	$put = GETPOST('put');
	$get = GETPOST('get');
	
switch ($put) {
	case 'sync':
		
		__out(_sync(),'json');
			
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

function _sync() {
	global $user,$fk_user_gcs;
	
	$PDOdb=new TPDOdb;
	
	$TToken = \TGCSToken::getTokenToSync($PDOdb,$user->id);
	foreach($TToken as &$token) {
		$token->to_sync = 0;
		//$token->save($PDOdb);
	}
	
	foreach($TToken as &$token) {
		if(!$token->sync($PDOdb)) {
			$token->to_sync = 1;
			$token->save($PDOdb);
		}
		else{
			$TSync[] = $token;
		}
	}
	
	$TSync=array();	
	
	return $TSync;
}
