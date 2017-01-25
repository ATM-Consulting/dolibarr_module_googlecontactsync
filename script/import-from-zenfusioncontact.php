<?php

	require '../config.php';

	set_time_limit(0);

	define('INC_FROM_DOLIBARR',true);
	dol_include_once('/googlecontactsync/config.php');
	dol_include_once('/googlecontactsync/class/gcs.class.php');
	
	$PDOdb = new TPDOdb;
	/*
	 * {"access_token":"ya29.Ci90Ayl0nViP8PGO3LeTyFFBWTS_4S15cmRx7HEFxr3jNN5zer364ZpidaVjAnGuqg","token_type":"Bearer","expires_in":3600,"id_token":"eyJhbGciOiJSUzI1NiIsImtpZCI6IjU3NzNkMzQxZTE0MDdmYzlmZTYzNmJjYjQ4YWU4M2IyMjA1ZWQ1YzUifQ.eyJpc3MiOiJhY2NvdW50cy5nb29nbGUuY29tIiwiYXRfaGFzaCI6Ik1jbklWWnRoT2VJclI4czN2ZGIyRlEiLCJhdWQiOiIyMTQyNTYwNTE0MjEtOTByYW1hdmRrZGJ2YmZvNXY3cHM0Z2Jtb2wzazduNHIuYXBwcy5nb29nbGV1c2VyY29udGVudC5jb20iLCJzdWIiOiIxMTcyNTMzOTcwNzc4NjgyNjY3ODkiLCJlbWFpbF92ZXJpZmllZCI6dHJ1ZSwiYXpwIjoiMjE0MjU2MDUxNDIxLTkwcmFtYXZka2RidmJmbzV2N3BzNGdibW9sM2s3bjRyLmFwcHMuZ29vZ2xldXNlcmNvbnRlbnQuY29tIiwiaGQiOiJxdWltcGVyLWV2ZW5lbWVudHMuZnIiLCJlbWFpbCI6ImRhdmlkLnB1Z2V0QHF1aW1wZXItZXZlbmVtZW50cy5mciIsImlhdCI6MTQ3NTc2NjcxNCwiZXhwIjoxNDc1NzcwMzE0fQ.68kJNwJXGtLS3TcG-c1keUvmecZK5R_KgTyePILEn-dX1UrxOkZ_hmewsTxXpK9Bqv6xxnuiU7IktS_BIPzTrhUp8UHr2QuEEN_bkUZQpuDunuK64zXa8c2kQgHm19JGdaaaCgWLHIASUv6F-C-a3cKH5uY750uk8npBYokQ-RH7Ak6o1ThiJ7MASRqzOu-HTyGUzie2_gd1UEAEhbS__z-PAPY7VJ053WSlntUSD_U3IekCH6Sl0tK_s2zzO7HvJbYcJ0hD5IAINxljGsiVBilIBnhp2W0jEYi1DFTuYXgnOQdPQIOIl7-srQy-VjMinU2bplyrl5gsliv27IjsJQ","refresh_token":"1/12pp3uVAopMt9T2c1VHVcToQXXJhtB0nrURUZVg_7mw","created":1475766714}
	 * 
	 */
	$Tab = $PDOdb->ExecuteAsArray("SELECT * FROM ".MAIN_DB_PREFIX."zenfusion_oauth WHERE token IS NOT NULL");
	foreach($Tab as &$row) {
		$fk_object = $row->rowid;
		$type_object = 'user';
		$fk_user = $user->id;
	
		$tok = json_decode($row->token);
		
		//		TGCSToken::setSync($PDOdb, $object->id, $object->element, $user->id);
		$token = new TGCSToken;
		$token->loadByObject($PDOdb, $fk_object, $type_object, $fk_user);
		$token->fk_object = $fk_object;
		$token->type_object = $type_object;
		$token->fk_user = $fk_user;
		$token->to_sync = 0;
		$token->token =$tok->access_token;
		$token->refresh_token =$tok->refresh_token;
		
		$token->save($PDOdb);
	}
	
	$Tab = $PDOdb->ExecuteAsArray("SELECT * FROM ".MAIN_DB_PREFIX."zenfusion_contacts_records");
//$Tab=array();
//	var_dump($Tab);
	
				  	
	foreach($Tab as &$row) {
		$fk_object = $row->id_record_dolibarr;
		$type_object = 'societe';
		$fk_user = $row->id_dolibarr_user;

//		TGCSToken::setSync($PDOdb, $object->id, $object->element, $user->id);
			$token = new TGCSToken;
			
			if(!empty($row->id_record_google) &&  !$token->loadByObject($PDOdb, $fk_object, $type_object, $fk_user)) {
			$token->fk_object = $fk_object;
			$token->type_object = $type_object;
			$token->fk_user = $fk_user;
			$token->to_sync = 0;
			$token->token =strtr( $row->id_record_google, array('http://'=>'https://', '/base/'=>'/full/'));

			$token->save($PDOdb); 			
			}
	}


	$Tab = $PDOdb->ExecuteAsArray("SELECT * FROM ".MAIN_DB_PREFIX."zenfusion_socpeople_records");

	 foreach($Tab as &$row) {
                $fk_object = $row->id_record_dolibarr;
                $type_object = 'contact';
                $fk_user = $row->id_dolibarr_user;

                        $token = new TGCSToken;
                        if(!empty($row->id_record_google) &&  !$token->loadByObject($PDOdb, $fk_object, $type_object, $fk_user)) {
                        $token->fk_object = $fk_object;
                        $token->type_object = $type_object;
                        $token->fk_user = $fk_user;
                        $token->to_sync = 0;
			$token->token =strtr( $row->id_record_google, array('http://'=>'https://', '/base/'=>'/full/'));

                        $token->save($PDOdb); 
			}
        }

