<?php

class TGCSToken extends TObjetStd {

    function __construct() {
    	global $langs;
		
        $this->set_table(MAIN_DB_PREFIX.'gcs_token');
		
		$this->add_champs('fk_object,fk_user,to_sync',array('type'=>'integer', 'index'=>true));
        $this->add_champs('token,refresh_token,type_object', array('type'=>'string','index'=>true));
		
        $this->_init_vars();

        $this->start();
		
		$this->to_sync = 1;
		
	}

	function getObject() {
		
		global $conf,$db,$user,$langs;
		
		$object = false;

		if($this->type_object == 'company' || $this->type_object == 'societe') {
				
			dol_include_once('/societe/class/societe.class.php');
				
			$object = new \Societe($db);
				
			$object->fetch($this->fk_object);
			if(empty($object->name)) $object->name = $object->nom;
		}
		

		else if($this->type_object == 'contact') {
				
			dol_include_once('/contact/class/contact.class.php');
				
			$object = new \Contact($db);
				
			$object->fetch($this->fk_object);
			$object->name = $object->getFullName($langs);
			$object->email = $object->mail;
			$object->phone = empty($object->phone_pro) ? $object->phone_perso : $object->phone_pro;
			$object->fetch_thirdparty();
			$object->organization = $object->thirdparty->name;
			
		}
		
		
		if(is_object($object)) {
			foreach($object as $k=>&$v) {
				if(is_null($v)) $v = '';
			}
				
		}
//var_dump($object);
		return $object;
	}

	function sync(&$PDOdb) {
		
		$object = $this->getObject();	
		
		if(empty($object)) return false;
		require_once __DIR__.'/../php-google-contacts-v3-api/vendor/autoload.php';
		
		$_SESSION['GCS_fk_user'] = $this->fk_user; // TODO i'm shiting in the rain ! AA 
		
		$object->phone = self::normalize($object->phone);
		$object->email = self::normalize($object->email);
		
		if($this->token) {
			$contact = rapidweb\googlecontacts\factories\ContactFactory::getBySelfURL($this->token);	
		}
		else{
			$contact = rapidweb\googlecontacts\factories\ContactFactory::create($object->name,$object->phone, $object->email);
			$this->token = $contact->selfURL;
			$this->save($PDOdb);
		}
		
		$contact->name = $object->name; 
		$contact->phoneNumber = $object->phone;
		$contact->email = $object->email;
		$contact->postalAddress = $object->address;
		if(!empty($object->organization)) {
			$contact->organization = $object->organization ;
			$contact->organization_title = self::normalize($object->poste);
		}
		
		$contactAfterUpdate = rapidweb\googlecontacts\factories\ContactFactory::submitUpdates($contact);
		
		if(!empty($contactAfterUpdate->id)) return $contactAfterUpdate;
		else return false;
	}

	static function normalize($value) {
		
		if(empty($value)) $value = 'inconnu';
		return $value;
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
			
			return $gcs;
			
		}
	
		return false;
	}

	static function setSync(&$PDOdb, $fk_object, $type_object, $fk_user) {
			
		if(empty($fk_object) || empty($type_object) ) return false;
			
			$token = new TGCSToken;
			$token->loadByObject($PDOdb, $fk_object, $type_object, $fk_user);
			$token->fk_object = $fk_object;
			$token->type_object = $type_object;
			$token->fk_user = $fk_user;
			$token->to_sync = 1;
			$token->save($PDOdb); 
			
			global $langs;
			
			setEventMessage($langs->trans('SyncObjectInitiated'));
			
	}
	
}
