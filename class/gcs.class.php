<?php

class TGCSToken extends TObjetStd {

    function __construct() {
    	global $langs;
		
        $this->set_table(MAIN_DB_PREFIX.'gcs_token');
		
		$this->add_champs('fk_object,fk_user,to_sync',array('type'=>'integer', 'index'=>true));
        $this->add_champs('token,type_object', array('type'=>'string','index'=>true));
		
        $this->_init_vars();

        $this->start();
		
		$this->to_sync = 1;
		
	}

	function getObject() {
		
		global $conf,$db,$user,$langs;
		
		$object = new \stdClass;
		
		if($this->type_object == 'company') {
			
			dol_include_once('/societe/class/societe.class.php');	
			
			$object = new \Societe($db);
			
			$object->fetch($this->fk_object);
			if(empty($object->name)) $object->name = $object->nom;
		}
		
		return $object;
	}

	function sync(&$PDOdb) {
		
		$object = $this->getObject();		
		
		require_once __DIR__.'/../php-google-contacts-v3-api/vendor/autoload.php';
		
		$_SESSION['GCS_fk_user'] = $this->fk_user; // TODO i'm shiting in the rain ! AA 
		
		if($this->token) {
			$contact = rapidweb\googlecontacts\factories\ContactFactory::getBySelfURL($this->token);	
		}
		else{
			
			$contact = rapidweb\googlecontacts\factories\ContactFactory::create($object->name, $object->tel, $object->email);
			$this->token = $contact->selfURL;
			var_dump($contact);
			$this->save($PDOdb);
		}
		/*
		$contact->name = 'Test';
		$contact->phoneNumber = '07812363789';
		$contact->email = 'test@example.com';
		
		$contactAfterUpdate = ContactFactory::submitUpdates($contact);
		*/
		return false;
	}

	function loadByObject(&$PDOdb, $fk_object, $type_object, $fk_user = 0) {
		
		$sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."gcs_token WHERE fk_object=".(int)$fk_object." AND type_object='".$type_object."'";
		
		if(!empty($fk_user)) $sql.=" AND fk_user = ".$fk_user;
		
		$PDOdb->Execute($sql);
		if($obj = $PDOdb->Get_line()){
			
			return $this->load($PDOdb, $obj->rowid);
		}
		
		return false;
	}

	static function getTokenToSync(&$PDOdb, $fk_user = 0, $nb = 5) {
		
		$sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."gcs_token WHERE to_sync = 1 ";
		if($fk_user>0)$sql.=" AND fk_user=".$fk_user;
		
		$sql.=" LIMIT ".$nb;
		$Tab = $PDOdb->ExecuteAsArray($sql);
		
		$TToken = array();
		foreach($Tab as $row) {
			
			$t=new TGCSToken;
			$t->load($PDOdb, $row->rowid);
			
			$TToken[] = $t;
			
		}
		
		
		return $TToken;
	}

	static function getTokenFor(&$PDOdb, $fk_object, $type_object) {
	
		$gcs = new TGCSToken;
		if($gcs->loadByObject($PDOdb, $fk_object, $type_object)) {
			
			return $gcs->token;
			
		}
	
		return false;
	}

}