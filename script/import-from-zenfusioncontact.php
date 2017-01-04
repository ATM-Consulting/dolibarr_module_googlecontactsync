<?php

	require '../config.php';

	$PDOdb = new TPDOdb;

	$Tab = $PDOdb->ExecuteAsArray("SELECT * FROM ".MAIN_DB_PREFIX."zenfusion_contacts_records");

//	var_dump($Tab);
	define('INC_FROM_DOLIBARR',true);
	dol_include_once('/googlecontactsync/config.php');
	dol_include_once('/googlecontactsync/class/gcs.class.php');
				  	
	$PDOdb=new TPDOdb;

	foreach($Tab as &$row) {
		$fk_object = $row->id_record_dolibarr;
		$type_object = 'societe';
		$fk_user = $row->id_dolibarr_user;

//		TGCSToken::setSync($PDOdb, $object->id, $object->element, $user->id);
			$token = new TGCSToken;
			$token->loadByObject($PDOdb, $fk_object, $type_object, $fk_user);
			$token->fk_object = $fk_object;
			$token->type_object = $type_object;
			$token->fk_user = $fk_user;
			$token->to_sync = 0;
			$token->token = $row->id_record_google;

			$token->save($PDOdb); 			
	}


	$Tab = $PDOdb->ExecuteAsArray("SELECT * FROM ".MAIN_DB_PREFIX."zenfusion_socpeople_records");

	 foreach($Tab as &$row) {
                $fk_object = $row->id_record_dolibarr;
                $type_object = 'contact';
                $fk_user = $row->id_dolibarr_user;

                        $token = new TGCSToken;
                        $token->loadByObject($PDOdb, $fk_object, $type_object, $fk_user);
                        $token->fk_object = $fk_object;
                        $token->type_object = $type_object;
                        $token->fk_user = $fk_user;
                        $token->to_sync = 0;
                        $token->token = $row->id_record_google;

                        $token->save($PDOdb); 
        }

