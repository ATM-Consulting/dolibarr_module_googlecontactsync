<?php

	require '../config.php';


	$fk_user = GETPOST('fk_user');

	if (!empty($fk_user))
	{
		$u=new User($db);
		$u->fetch($fk_user);
		if($u->id <=0 ) exit('fk_user : fetch fail');
		echo $u->getNomUrl(1);
	}
	else
	{
		echo "Pas de fk_user, c'est donc pour tous les users qui on un token";
	}
	

	dol_include_once('/googlecontactsync/class/gcs.class.php');
				  	
	$PDOdb=new TPDOdb;

	$Tab = $PDOdb->ExecuteAsArray("
		SELECT fk_object, type_object FROM ".MAIN_DB_PREFIX."gcs_token WHERE to_sync = 0 AND type_object != 'user'
	");
//	var_dump("
//		SELECT fk_object, type_object FROM ".MAIN_DB_PREFIX."gcs_token WHERE to_sync = 0 AND type_object != 'user'
//	");
	
echo count($Tab);
//	var_dump($Tab);

	foreach($Tab as &$row) {
		$fk_object = $row->fk_object;
		$type_object = $row->type_object;

		if ($type_object == 'societe' && empty($conf->global->GCS_GOOGLE_SYNC_THIRDPARTY)) continue;
		if ($type_object == 'contact' && (empty($conf->global->GCS_GOOGLE_SYNC_CONTACT) || !empty($conf->global->GCS_GOOGLE_SYNC_ALL_CONTACT_FROM_SOCIETE))) continue;
		if ($type_object == 'user_object' && empty($conf->global->GCS_GOOGLE_SYNC_USER)) continue;
		
		// S'occupe de passer l'attribut "to_sync" à 1 => puis la tâche cron fera la synchro
		if (!empty($fk_user)) TGCSToken::setSync($PDOdb, $fk_object, $type_object, $u->id);
		else TGCSToken::setSyncAll($PDOdb, $fk_object, $type_object);
	}

