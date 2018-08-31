<?php

if (!class_exists('TObjetStd'))
{
	define('INC_FROM_DOLIBARR', true);
	require_once dirname(__FILE__).'/../config.php';
}

class TGCSToken extends TObjetStd {

	static public $table = 'gcs_token';
	
    function __construct() {
    	global $conf;
		
        $this->set_table(MAIN_DB_PREFIX.self::$table);
		
		$this->add_champs('fk_object,fk_user,to_sync',array('type'=>'integer', 'index'=>true));
        $this->add_champs('token,refresh_token,type_object', array('type'=>'string','index'=>true));
		$this->add_champs('entity',array('type'=>'integer', 'index'=>true, 'default'=>1));
		
        $this->_init_vars();

        $this->start();
		
		$this->to_sync = 1;
		$this->entity = $conf->entity;
		
	}

	public function gcs_cronjob_nyancat($nb=50)
	{
		global $db;

		if ($nb > 0)
		{
			$PDOdb=new \TPDOdb;
			$TToken = \TGCSToken::getTokenToSync($PDOdb, 0, $nb, false);
			
			$user = new User($db);
			foreach($TToken as &$token) {
				if ($user->id != $token->fk_user)
				{
					$user = new User($db);
					$user->fetch($token->fk_user);
				}
				
				$res = $token->sync($PDOdb);
				if ($res === 0) {} // Do nothing
				else if ($res < 0)
				{
					$this->output.= 'FAIL Sync - (token id: '.$token->id.') User id ['.$token->fk_user.'] sync object ['.$token->type_object.'] with id ['.$token->fk_object.']'."\n";
				}
				else
				{
					$token->to_sync = 0;
					$token->save($PDOdb);
					$this->output.= '(token id: '.$token->id.') User id ['.$token->fk_user.'] sync object ['.$token->type_object.'] with id ['.$token->fk_object.']'."\n";
				}
			}
		}
		
		return 0;
	}
	
	public function getObject() {
		global $conf,$db,$user,$langs;
		
		$object = false;

		$TCateg = array();
		dol_include_once('/categories/class/categorie.class.php');
		$categObject = new \Categorie($db);

		if($this->type_object == 'company' || $this->type_object == 'societe') {
				
			dol_include_once('/societe/class/societe.class.php');
				
			$object = new \Societe($db);
				
			$object->fetch($this->fk_object);
			$object->dolibarrUrl = dol_buildpath('societe/soc.php?socid='.$this->fk_object, 2);

			if(empty($object->name)) $object->name = $object->nom;

			if(! empty($object->client)) {
				$TCategClient = $categObject->containing($this->fk_object, 'customer');
				if(is_array($TCategClient)) $TCateg = $TCategClient;
			}

			if(!empty ($object->fournisseur)) {
				$TCategFourn = $categObject->containing($this->fk_object, 'supplier');
				if(is_array($TCategFourn)) $TCateg = array_merge($TCateg, $TCategFourn);
			}

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
			$object->dolibarrUrl = dol_buildpath('contact/card.php?id='.$this->fk_object, 2);
			
			$TCategContact = $categObject->containing($this->fk_object, 'contact');
			if(is_array($TCategContact)) $TCateg = $TCategContact;
		}
		else if ($this->type_object == 'user_object')
		{
			dol_include_once('/user/class/user.class.php');
			$object = new \User($db);
			
			$object->fetch($this->fk_object);
			$object->name = $object->getFullName($langs);
//			$object->email = $object->email;
			$object->phone = $object->office_phone;
//			
			if (!empty($object->fk_soc))
			{
				$object->fetch_thirdparty();
				$object->organization = $object->thirdparty->name;
			}
			else
			{
				global $mysoc;
				$object->organization = $mysoc->nom;
			}
			
			$object->dolibarrUrl = dol_buildpath('/user/card.php?id='.$this->fk_object, 2);
		}
		
		
		if(is_object($object)) {

			$object->categories = array();
			foreach($TCateg as $categ) {
				$ways = $categ->print_all_ways();
				$fullLabel = '';
				foreach($ways as $way) {
					$fullLabel .= strip_tags($way);
				}
				$object->categories[] = $fullLabel;
			}

			foreach($object as $k=>&$v) {
				if(is_null($v)) $v = '';
			}
				
		}
//var_dump($object); exit;
		return $object;
	}

	public function sync(&$PDOdb) {
		global $db, $conf, $user, $userUsedToSync;

		$object = $this->getObject();	
//var_dump($object);
		if(empty($object)) return false;
		require_once __DIR__.'/../php-google-contacts-v3-api/vendor/autoload.php';
		
		$_SESSION['GCS_fk_user'] = $this->fk_user; // TODO i'm shiting in the rain ! AA 
		
		if (empty($userUsedToSync) || $userUsedToSync->id != $this->fk_user)
		{
			$userUsedToSync = new User($db);
			$userUsedToSync->fetch($this->fk_user);
		}
		
		if (empty($userUsedToSync->email)) return 0; // User sans adresse mail, on fait rien
		
		$TPhone = array();
		if(!empty($object->phone)) $TPhone['work'] =$object->phone;
		if(!empty($object->phone_perso)) $TPhone['perso'] =$object->phone_perso; 
		if(!empty($object->phone_mobile)) $TPhone['mobile'] =$object->phone_mobile;
		if(!empty($object->fax)) $TPhone['fax'] =$object->fax;
		
		$object->phone = self::normalize($TPhone['work']);
		$object->email = self::normalize($object->email);
		if($this->token) {
			$contact = rapidweb\googlecontacts\factories\ContactFactory::getBySelfURL($this->token);
		}
		
		if(empty($contact->id)) {
			$contact = rapidweb\googlecontacts\factories\ContactFactory::create($object->name,$object->phone, $object->email);
			$this->token = $contact->selfURL;
			$this->save($PDOdb);
		}
		
		$contact->name = $object->name;
		
		$contact->phoneNumbers = $TPhone;
		
		$contact->email = $object->email;

		$contact->website = $object->dolibarrUrl;

		if(! empty($object->code_client)) $contact->code_client = $object->code_client;
		if(! empty($object->code_fournisseur)) $contact->code_fournisseur = $object->code_fournisseur;

		if($object->address || $object->zip || $object->town) {
			$contact->postalAddress = trim($object->address);
			if($object->zip || $object->town) {
				if(!empty($contact->postalAddress))$contact->postalAddress.=', ';	
				$contact->postalAddress.=trim($object->zip.' '.$object->town);
			}
		}

		if(!empty($object->organization)) {
			$contact->organization = $object->organization ;
			$contact->organization_title = self::normalize($object->poste);
		}


		$contact->groupMembershipInfo = array(
			0 => 'http://www.google.com/m8/feeds/groups/'.urlencode($userUsedToSync->email).'/base/6' // Groupe 6 = liste globale des contacts
		);

		if(!empty($conf->global->GCS_GOOGLE_GROUP_NAME)) {
			$group = self::setGroup($PDOdb, $this->fk_user,$conf->global->GCS_GOOGLE_GROUP_NAME);
			if(! empty($group->id)) array_push($contact->groupMembershipInfo, $group->id->__toString()); 
		}

		if(!empty($object->categories)) {
			foreach($object->categories as $categ) {
				$group = self::setGroup($PDOdb, $this->fk_user, $categ);
				if(! empty($group->id)) array_push($contact->groupMembershipInfo, $group->id->__toString()); 
			}
		}

		$contactAfterUpdate = rapidweb\googlecontacts\factories\ContactFactory::submitUpdates($contact);

		if(!empty($contactAfterUpdate->id)) return $contactAfterUpdate;
		else return -1;
	}

	public function optimizedSync(&$PDOdb) {
		global $conf;
		
		$object = $this->getObject();	
		
		if(empty($object)) return false;
		require_once __DIR__.'/../php-google-contacts-v3-api/vendor/autoload.php';
		
		$_SESSION['GCS_fk_user'] = $this->fk_user; // TODO i'm shiting in the rain ! AA 
		
		$TPhone = array();
		if(!empty($object->phone)) $TPhone['work'] =$object->phone;
		if(!empty($object->phone_perso)) $TPhone['perso'] =$object->phone_perso; 
		if(!empty($object->phone_mobile)) $TPhone['mobile'] =$object->phone_mobile;
		if(!empty($object->fax)) $TPhone['fax'] =$object->fax;
		
		$object->phone = self::normalize($TPhone['work']);
		$object->email = self::normalize($object->email);
/*
		if($this->token) {
			$contact = rapidweb\googlecontacts\factories\ContactFactory::getBySelfURL($this->token);	
//		var_dump($contact,$this);exit;
		}
*/			
		if(empty($this->contact)) {
			$this->contact = rapidweb\googlecontacts\factories\ContactFactory::create($object->name,$object->phone, $object->email);
			$this->token = $this->contact->selfURL;
			$this->save($PDOdb);
		}

		$this->contact->name = $object->name;
		
		$this->contact->phoneNumber = $object->phone;
		
		$this->contact->phoneNumbers = $TPhone;
		
		$this->contact->email = $object->email;
		
		if($object->address || $object->zip || $object->town) {
			$this->contact->postalAddress = trim($object->address);
			if($object->zip || $object->town) {
				if(!empty($this->contact->postalAddress))$this->contact->postalAddress.=', ';	
				$this->contact->postalAddress.=trim($object->zip.' '.$object->town);
			}
		}
		if(!empty($object->organization)) {
			$this->contact->organization = $object->organization ;
			$this->contact->organization_title = self::normalize($object->poste);
		}

/*	
		if(!empty($conf->global->GCS_GOOGLE_GROUP_NAME)) {
			
			$group = self::setGroup($PDOdb, $this->fk_user,$conf->global->GCS_GOOGLE_GROUP_NAME);
			
			$contact->groupMembershipInfo = $group->id; 
		}

		echo '<pre>';
		var_dump($this->contact);
		echo '</pre>';
*/

		$contactAfterUpdate = rapidweb\googlecontacts\factories\ContactFactory::submitUpdates($this->contact);
		
		if(!empty($contactAfterUpdate->id)) return $contactAfterUpdate;
		else return false;
	}

	private static function normalize($value) {
		
		//if(empty($value)) $value = 'inconnu';
		return $value;
	}
	
	public function loadByObject(&$PDOdb, $fk_object, $type_object, $fk_user = 0) {
		global $conf;
		
		$sql = "SELECT rowid FROM ".MAIN_DB_PREFIX.self::$table." WHERE fk_object=".(int)$fk_object." AND type_object='".$type_object."'";
		$sql.= ' AND entity = '.$conf->entity;
		
		if(!empty($fk_user)) $sql.=" AND fk_user = ".$fk_user;
		
		$PDOdb->Execute($sql);
		if($obj = $PDOdb->Get_line()){
			
			return $this->load($PDOdb, $obj->rowid);
		}
		
		return false;
	}

	public static function getTokenToSync(&$PDOdb, $fk_user = 0, $nb = 5, $filter_entity=true) {
		global $conf;
		
		$sql = "SELECT rowid FROM ".MAIN_DB_PREFIX.self::$table." WHERE to_sync = 1 ";
		if ($filter_entity) $sql.= ' AND entity = '.$conf->entity;
		if($fk_user>0)$sql.=" AND fk_user=".$fk_user;
		$sql.= ' AND type_object != \'user\'';
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

	public static function getTokenFor(&$PDOdb, $fk_object, $type_object) {
	
		$gcs = new TGCSToken;
		if($gcs->loadByObject($PDOdb, $fk_object, $type_object)) {
			
			return $gcs;
			
		}
	
		return false;
	}
	
	/**
	 * Pour 1 type_object (societe, contact, user_object) avec son identidiant ($fk_object), met à jour la table
	 * pour que tous les user autorisés à sync l'item passe l'attribut "to_sync" à 1
	 * 
	 * @global type $conf
	 * @param TPDOdb $PDOdb
	 * @param type $fk_object
	 * @param type $type_object
	 * @return boolean
	 */
	public static function setSyncAll(TPDOdb &$PDOdb, $fk_object, $type_object) {
		global $conf;
		
		if(empty($fk_object) || empty($type_object) ) return false;
		
		$TUserToken = $PDOdb->ExecuteAsArray("SELECT fk_object FROM ".MAIN_DB_PREFIX.self::$table."
				WHERE type_object='user' AND refresh_token!='' AND entity = ".$conf->entity);
		
		foreach($TUserToken as &$u) {
			self::setSync($PDOdb, $fk_object, $type_object, $u->fk_object);
		}
	}
	
	public static function getUserToTest($fk_user)
	{
		global $TUserTmpGSC,$db;
		
		if (!empty($TUserTmpGSC[$fk_user])) return $TUserTmpGSC[$fk_user];
		
		$u = new User($db);
		if ($u->fetch($fk_user) > 0)
		{
			$u->getrights('societe');
			$TUserTmpGSC[$fk_user] = $u;
			
			return $u;
		}
		
		if (!empty($u->error)) dol_print_error($u->db, $u->error);
		
		return false; // User not found or SQL error
	}
	
	public static function allowedToSync($fk_object, $type_object, $fk_user)
	{
		global $db,$conf;
		
		if ($type_object == 'company' || $type_object == 'societe' || $type_object == 'contact')
		{
			$userToTest = self::getUserToTest($fk_user);
			// !Pas le droit (donc a le droit) "Etendre l'accès à tous les tiers..." || conf cachée définie permettant d'usurper cette nouvelle restriction (cad, conserver le comportement d'avant)
			if (!empty($userToTest->rights->societe->client->voir) || !empty($conf->global->GCS_GOOGLE_SYNC_CONTACT_ALL_USER_GET_REKT_RIGHTS))
			{
				if ($type_object == 'company' || $type_object == 'societe')
				{
					if (!class_exists('Societe')) require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
					$societe = new Societe($db);
					$societe->fetch($fk_object);
					$listsalesrepresentatives=$societe->getSalesRepresentatives($userToTest);
				}
				else if ($type_object == 'contact')
				{
					if (!class_exists('Contact')) require_once DOL_DOCUMENT_ROOT.'/contact/class/contact.class.php';
					$contact = new Contact($db);
					$contact->fetch($fk_object);
					$contact->fetch_thirdparty();
					if (empty($contact->thirdparty->id)) return false; // Contact sans Tiers
					$listsalesrepresentatives=$contact->thirdparty->getSalesRepresentatives($userToTest);
				}
				
				// Si la variable existe, alors il faut tester son contenu même si vide
				if (isset($listsalesrepresentatives))
				{
					foreach ($listsalesrepresentatives as $info)
					{
						if ($userToTest->id == $info['id']) return true;
					}
				}
			}
		}
		else if ($type_object == 'user_object')
		{
			// Tentative de synchro d'une fiche user avec lui même (pas vraiment de sens de ce synchro avec soit même)
			if ($fk_user == $fk_object) return false;
		}
		
		return false;
	}
	
	/**
	 * Méthode qui détermine si le $fk_user est autorisé à passer l'attribut "to_sync" à 1
	 * 
	 * @global type $conf
	 * @global type $langs
	 * @global boolean $google_sync_message_sync_ok
	 * @param type $PDOdb
	 * @param type $fk_object
	 * @param type $type_object
	 * @param type $fk_user
	 * @return boolean
	 */
	public static function setSync(&$PDOdb, $fk_object, $type_object, $fk_user) {
		global $conf;
		
		if (empty($fk_object) || empty($type_object) || !self::allowedToSync($fk_object, $type_object, $fk_user)) return false;
		
		$token = new TGCSToken;
		$token->loadByObject($PDOdb, $fk_object, $type_object, $fk_user);
		$token->fk_object = $fk_object;
		$token->type_object = $type_object;
		$token->fk_user = $fk_user;
		$token->to_sync = 1;
		$token->save($PDOdb);

		if ($type_object == 'societe' && !empty($conf->global->GCS_GOOGLE_SYNC_ALL_CONTACT_FROM_SOCIETE))
		{
			$TContactId = TRequeteCore::get_id_from_what_you_want($PDOdb, MAIN_DB_PREFIX.'socpeople', array('fk_soc'=>$fk_object,'statut'=>1));
			foreach ($TContactId as $fk_socpeople)
			{
				self::setSync($PDOdb, $fk_socpeople, 'contact', $fk_user);
			}
		}

		global $langs;

		$langs->load('googlecontactsync@googlecontactsync');

		global $google_sync_message_sync_ok;

		if(empty($google_sync_message_sync_ok)) setEventMessage($langs->trans('SyncObjectInitiated'));

		$google_sync_message_sync_ok = true;
	}

	public static function setGroup(&$PDOdb, $fk_user, $name) {
		global $TCacheGroupSync,$db;
		
		$user = new User($db);
		$user->fetch($fk_user);
		
		// Création d'un cache global des groupes s'il n'existe pas

		if(empty($TCacheGroupSync[$fk_user]) || isset($_REQUEST['force'])) {
			$TCacheGroupSync[$fk_user]=array();

			$url = 'https://www.google.com/m8/feeds/groups/'.urlencode($user->email).'/full?max-results=100';
			$TGroup = rapidweb\googlecontacts\factories\ContactFactory::getAllByURL($url);

			foreach($TGroup as $g) {
				
				$TCacheGroupSync[$fk_user][htmlspecialchars((string) $g->title)] =(string) basename($g->id);
				
			}
		}
	
		// Si le groupe est dans le cache, on le récupère

		if(isset($TCacheGroupSync[$fk_user][$name])) {
			$reqURL = 'https://www.google.com/m8/feeds/groups/'.urlencode($user->email).'/full/'.$TCacheGroupSync[$fk_user][$name];
			$group = rapidweb\googlecontacts\factories\ContactFactory::getAllByURL($reqURL, true);

			if(! empty($group->id)) return $group;

			return false;
		}

		// Sinon, création d'un nouveau groupe
		
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

			// MàJ cache

			$TCacheGroupSync[$fk_user][htmlspecialchars((string) $response->title)] =(string) basename($response->id);
			return $response;
		}
		
		return false;	
	}
	
}
