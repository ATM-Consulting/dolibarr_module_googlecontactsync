<?php

	require '../config.php';


	$fk_user = GETPOST('fk_user');

	$u=new User($db);

	$u->fetch($fk_user);

	if($u->id <=0 ) exit('fk_user');

	echo $u->getNomUrl(1);

	dol_include_once('/googlecontactsync/class/gcs.class.php');
				  	
	$PDOdb=new TPDOdb;

	$Tab = $PDOdb->ExecuteAsArray("SELECT rowid FROM ".MAIN_DB_PREFIX."socpeople 
		WHERE 1 ");
echo count($Tab);
//	var_dump($Tab);

	foreach($Tab as &$row) {
		$fk_object = $row->rowid;
		$type_object = 'contact';

			$token = new TGCSToken;
			$token->loadByObject($PDOdb, $fk_object, $type_object, $fk_user);
			$token->fk_object = $fk_object;
			$token->type_object = $type_object;
			$token->fk_user = $fk_user;
			$token->to_sync = 1;

			$token->save($PDOdb); 			
	}

