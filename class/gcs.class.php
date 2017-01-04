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
			$object->phone = $object->phone_pro;
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
		global $conf;
		
		$object = $this->getObject();	
		
		if(empty($object)) return false;
		require_once __DIR__.'/../php-google-contacts-v3-api/vendor/autoload.php';
		
		$_SESSION['GCS_fk_user'] = $this->fk_user; // TODO i'm shiting in the rain ! AA 
		
		$TPhone = array();
		if(!empty($object->phone)) $TPhone[] =$object->phone;
		if(!empty($object->phone_perso)) $TPhone[] =$object->phone_perso; //TODO add other phone to card
		if(!empty($object->phone_mobile)) $TPhone[] =$object->phone_mobile;
		
		$object->phone = self::normalize($TPhone[0]);
		$object->email = self::normalize($object->email);
		if($this->token) {
			$contact = rapidweb\googlecontacts\factories\ContactFactory::getBySelfURL($this->token);	
//		var_dump($contact,$this);exit;
		}
		
		if(empty($contact->id)) {
			$contact = rapidweb\googlecontacts\factories\ContactFactory::create($object->name,$object->phone, $object->email);
			$this->token = $contact->selfURL;
			$this->save($PDOdb);
		}
		
		$contact->name = $object->name;
		
		$contact->phoneNumber = $object->phone;
		$contact->email = $object->email;
		$contact->postalAddress = $object->address.', '.$object->zip.' '.$object->town;
		if(!empty($object->organization)) {
			$contact->organization = $object->organization ;
			$contact->organization_title = self::normalize($object->poste);
		}
		
		if(!empty($conf->global->GCS_GOOGLE_GROUP_NAME)) {
			
			$group = self::setGroup($PDOdb, $this->fk_user,$conf->global->GCS_GOOGLE_GROUP_NAME);
			
			$contact->groupMembershipInfo = $group->id; 
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

	static function setGroup(&$PDOdb, $fk_user, $name) {
		global $TCacheGroupSync,$db;
		
		$user = new User($db);
		$user->fetch($fk_user);
		
		if(empty($TCacheGroupSync[$fk_user]) || isset($_REQUEST['force'])) {
			$TCacheGroupSync[$fk_user]=array();
			$TGroup = rapidweb\googlecontacts\factories\ContactFactory::getAllByURL('https://www.google.com/m8/feeds/groups/'.urlencode($user->email).'/full');
			foreach($TGroup as $g) {
				
				$TCacheGroupSync[$fk_user][(string) $g->title] =(string) basename($g->id);
				
			}
		}
		
		if(isset($TCacheGroupSync[$fk_user][$name])) {
			$group = rapidweb\googlecontacts\factories\ContactFactory::getAllByURL('https://www.google.com/m8/feeds/groups/'.urlencode($user->email).'/full/'.$TCacheGroupSync[$fk_user][$name],true);
			return $group;
		}
		else{
		
			$doc = new \DOMDocument();
			$doc->formatOutput = true;
			$entry = $doc->createElement('atom:entry');
			$entry->setAttribute('xmlns:atom', 'http://www.w3.org/2005/Atom');
			$entry->setAttribute('xmlns:gd', 'http://schemas.google.com/g/2005');
			$entry->setAttribute('xmlns:gContact', 'http://schemas.google.com/contact/2008');
			$doc->appendChild($entry);
			
			$o = $doc->createElement('atom:title', $name);
			$o->setAttribute('type', 'text');
			$entry->appendChild($o);
			
			$o = $doc->createElement('atom:content', $name);
			$o->setAttribute('type', 'text');
			
			$entry->appendChild($o);
			
			$o = $doc->createElement('gd:extendedProperty');
			$o->setAttribute('name', 'more info about the group');
			
			$entry->appendChild($o);
			
			$o2 = $doc->createElement('info','created by Dolibarr Module GCS');
			$o->appendChild($o2);
			
			$o = $doc->createElement('atom:category');
			$o->setAttribute('scheme', 'http://schemas.google.com/g/2005#kind');
			$o->setAttribute('term', 'http://schemas.google.com/contact/2008#group');
			$entry->appendChild($o);
			
			$xmlToSend = $doc->saveXML();
		
		
			$client = rapidweb\googlecontacts\helpers\GoogleHelper::getClient();
			
			$req = new \Google_Http_Request('https://www.google.com/m8/feeds/groups/'.$user->email.'/full');
			$req->setRequestHeaders(array('content-type' => 'application/atom+xml; charset=UTF-8; type=feed'));
			$req->setRequestMethod('POST');
			$req->setPostBody($xmlToSend);
			
			$val = $client->getAuth()->authenticatedRequest($req);
			
			$response = simplexml_load_string( $val->getResponseBody() );
				
			if(!empty($response->id)) {
				
				$TCacheGroupSync[$fk_user][(string) $response->title] =(string) basename($response->id);
				return self::setGroup($PDOdb, $fk_user, $name);
			}
			
			return false;
			
		}
		
		return false;	
	}
	
}
