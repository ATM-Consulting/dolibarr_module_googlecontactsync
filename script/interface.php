<?php
	use rapidweb\googlecontacts\factories\ContactFactory;

	require '../config.php';
	dol_include_once('/googlecontactsync/class/gcs.class.php');

	$put = GETPOST('put');
	
switch ($put) {
	case 'sync':
		
		__out(_sync());
			
		break;
	
	
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
