<?php

namespace rapidweb\googlecontacts\factories;

use rapidweb\googlecontacts\helpers\GoogleHelper;
use rapidweb\googlecontacts\objects\Contact;

abstract class ContactFactory
{
    public static function getAll()
    {
        $client = GoogleHelper::getClient();

        $req = new \Google_Http_Request('https://www.google.com/m8/feeds/contacts/default/full?max-results=10000&updated-min=2007-03-16T00:00:00');

        $val = $client->getAuth()->authenticatedRequest($req);

        $response = $val->getResponseBody();

        $xmlContacts = simplexml_load_string($response);
        $xmlContacts->registerXPathNamespace('gd', 'http://schemas.google.com/g/2005');

        $contactsArray = array();

        foreach ($xmlContacts->entry as $xmlContactsEntry) {
            $contactDetails = array();

            $contactDetails['id'] = (string) $xmlContactsEntry->id;
            $contactDetails['name'] = (string) $xmlContactsEntry->title;

            foreach ($xmlContactsEntry->children() as $key => $value) {
                $attributes = $value->attributes();

                if ($key == 'link') {
                    if ($attributes['rel'] == 'edit') {
                        $contactDetails['editURL'] = (string) $attributes['href'];
                    } elseif ($attributes['rel'] == 'self') {
                        $contactDetails['selfURL'] = (string) $attributes['href'];
                    }
                }
            }

            $contactGDNodes = $xmlContactsEntry->children('http://schemas.google.com/g/2005');
            foreach ($contactGDNodes as $key => $value) {
                switch ($key) {
                    case 'organization':
                        $contactDetails[$key]['orgName'] = (string) $value->orgName;
                        $contactDetails[$key]['orgTitle'] = (string) $value->orgTitle;
                        break;
                    case 'email':
                        $attributes = $value->attributes();
                        $emailadress = (string) $attributes['address'];
                        $emailtype = substr(strstr($attributes['rel'], '#'), 1);
                        $contactDetails[$key][$emailtype] = $emailadress;
                        break;
                    case 'phoneNumber':
                        $attributes = $value->attributes();
                        $uri = (string) $attributes['uri'];
                        $type = substr(strstr($attributes['rel'], '#'), 1);
                        $e164 = substr(strstr($uri, ':'), 1);
                        $contactDetails[$key][$type] = $e164;
                        break;
                    default:
                        $contactDetails[$key] = (string) $value;
                        break;
                }
            }

            $contactsArray[] = new Contact($contactDetails);
        }

        return $contactsArray;
    }
    
    public static function getAllByURL($selfURL, $full = false)
    {
    	$client = GoogleHelper::getClient();
    
    	$req = new \Google_Http_Request($selfURL);
    
    	$val = $client->getAuth()->authenticatedRequest($req);

	//var_dump($val);   

    	$response = $val->getResponseBody();
    	//pre(htmlentities($response), true); 
    	$xml =  simplexml_load_string($response);
    	if($full) return $xml;
    	//echo pre($xml,true);
    	//echo pre(htmlentities($xml->asXML()),true);
    	
    	$Tab=array();
    	foreach ($xml->entry as $xmlEntry) {
    		
    		$Tab[] = $xmlEntry;
    		
    	}
    	
    	return $Tab;
    }
    
    
    public static function getBySelfURL($selfURL)
    {
        $client = GoogleHelper::getClient();

        $req = new \Google_Http_Request($selfURL);
        $req->setRequestHeaders(array(
			'GData-Version' => '3.0'
		));

        $val = $client->getAuth()->authenticatedRequest($req);

        $response = $val->getResponseBody();
//var_dump($selfURL,$client->getAuth(),$val);
//echo $response;exit;
        $xmlContact = simplexml_load_string($response);

		if($xmlContact === false) {
			echo "Wrong call : ".$selfURL."<br />";
			return false;
		}
/*echo $selfURL;
	pre($xmlContact,true);exit;
	*/
        $xmlContact->registerXPathNamespace('gd', 'http://schemas.google.com/g/2005');

        $xmlContactsEntry = $xmlContact;

        $contactDetails = array();

        $contactDetails['id'] = (string) $xmlContactsEntry->id;
        $contactDetails['name'] = (string) $xmlContactsEntry->title;

        foreach ($xmlContactsEntry->children() as $key => $value) {
            $attributes = $value->attributes();

            if ($key == 'link') {
                if ($attributes['rel'] == 'edit') {
                    $contactDetails['editURL'] = (string) $attributes['href'];
                } elseif ($attributes['rel'] == 'self') {
                    $contactDetails['selfURL'] = (string) $attributes['href'];
                }
            }
        }

        $contactGDNodes = $xmlContactsEntry->children('http://schemas.google.com/g/2005');

        foreach ($contactGDNodes as $key => $value) {
            $attributes = $value->attributes();

            if($key == 'phoneNumber') {
            	if(empty($contactDetails['phoneNumber']))$contactDetails['phoneNumber']=array();
            	$contactDetails['phoneNumber'][] = array(
            			'value'=>(string)$value
            			,'label'=>(string) $attributes['label'][0]
            			,'rel'=>(string) $attributes['rel']
            	) ;
            }
            
            else if ($key == 'email') {
                $contactDetails[$key] = (string) $attributes['address'];
            } else {
                $contactDetails[$key] = (string) $value;
            }
        }
//pre($contactDetails,true);exit;
        return new Contact($contactDetails);
    }

    public static function submitUpdates(&$userUsedToSync, Contact $updatedContact)
    {
		global $langs;
		$langs->load('companies');
		$langs->load('googlecontactsync@googlecontactsync');

        $client = GoogleHelper::getClient();

        $req = new \Google_Http_Request($updatedContact->selfURL);
        $req->setRequestHeaders(array(
			'GData-Version' => '3.0'
		));

        $val = $client->getAuth()->authenticatedRequest($req);

        $response = $val->getResponseBody();
        //pre(htmlentities($response), true);

        $xmlContact = simplexml_load_string($response);

        $xmlContact->registerXPathNamespace('gd', 'http://schemas.google.com/g/2005');
        $xmlContact->registerXPathNamespace('gContact', 'http://schemas.google.com/contact/2008');
        
        $xmlContactsEntry = $xmlContact;

        $xmlContactsEntry->title = $updatedContact->name;

        $contactGDNodes = $xmlContactsEntry->children('http://schemas.google.com/g/2005');
		$contactGContactNodes = $xmlContactsEntry->children('http://schemas.google.com/contact/2008');


	// Edition des noeuds du namespace XML gd

		unset($contactGDNodes->name);
		if (!empty($updatedContact->name))
		{
			$o = $xmlContactsEntry->addChild('name', null, 'http://schemas.google.com/g/2005');
			$o->addChild('fullName', $updatedContact->name, 'http://schemas.google.com/g/2005');
		}

       	unset($contactGDNodes->email);
        if(!empty($updatedContact->email)) {
        	$o = $xmlContactsEntry->addChild('email',null,'http://schemas.google.com/g/2005');
        	$o->addAttribute('address',$updatedContact->email);
        	$o->addAttribute('rel',"http://schemas.google.com/g/2005#work");
        }
	
     	unset($contactGDNodes->phoneNumber);
        foreach($updatedContact->phoneNumbers as $type=>$number) {
        	
        	if ($type == 'work') $rel = 'http://schemas.google.com/g/2005#work';
			else if ($type == 'mobile') $rel = 'http://schemas.google.com/g/2005#work_mobile';
			else if ($type == 'perso') $rel = 'http://schemas.google.com/g/2005#mobile';
			else if ($type == 'home') $rel = 'http://schemas.google.com/g/2005#home';
			else if ($type == 'other') $rel = 'http://schemas.google.com/g/2005#other';
			else if ($type == 'fax') $rel = 'http://schemas.google.com/g/2005#fax';
			else continue;
        	
        	$o = $xmlContactsEntry->addChild('phoneNumber',$number,'http://schemas.google.com/g/2005');
        	$o->addAttribute('rel', $rel);
        	 
        }

       	unset($contactGDNodes->structuredPostalAddress);
        if(!empty($updatedContact->postalAddress)) {
        	$o = $xmlContactsEntry->addChild('structuredPostalAddress', null,'http://schemas.google.com/g/2005');
        	$o->addAttribute('rel', 'http://schemas.google.com/g/2005#work');
        	$o->addAttribute('primary', 'true');
			$o->addChild('formattedAddress', $updatedContact->postalAddress, 'http://schemas.google.com/g/2005');
        }

        /*
         * <gd:structuredPostalAddress mailClass='http://schemas.google.com/g/2005#letters' label='John at Google'>
  <gd:street>1600 Amphitheatre Parkway</gd:street>
  <gd:city>Mountain View</gd:city>
  <gd:region>CA</gd:region>
  <gd:postcode>94043</gd:postcode>
</gd:structuredPostalAddress>
gd:country?
         * 
         */

		unset($contactGDNodes->organization);
		if(! empty($updatedContact->organization) || ! empty($updatedContact->organization_title)) {
			$o = $xmlContactsEntry->addChild('organization', null, 'http://schemas.google.com/g/2005');
			$o->addAttribute('rel', 'http://schemas.google.com/g/2005#work');

			if(! empty($updatedContact->organization)) {
				$o->addChild('orgName', $updatedContact->organization, 'http://schemas.google.com/g/2005');
			}

			if(! empty($updatedContact->organization_title)) {
				$o->addChild('orgTitle', $updatedContact->organization_title, 'http://schemas.google.com/g/2005');
			}
		}

	// Edition des noeuds du namespace XML gContact

        unset($contactGContactNodes->website);
		if (! empty($updatedContact->website))
		{
			$o = $xmlContactsEntry->addChild('website', null, 'http://schemas.google.com/contact/2008');
			$o->addAttribute('href', $updatedContact->website);
			$o->addAttribute('label', $langs->trans('DolibarrURL'));
		}
        
       	unset($contactGContactNodes->groupMembershipInfo);
        if(!empty($updatedContact->groupMembershipInfo)) {
        	foreach($updatedContact->groupMembershipInfo as $group) {
				$o = $xmlContactsEntry->addChild('groupMembershipInfo',null,'http://schemas.google.com/contact/2008');
				$o->addAttribute('deleted', 'false');
				$o->addAttribute('href', $group);
			}
        }

		unset($contactGContactNodes->userDefinedField);
		if(!empty($updatedContact->code_client) || !empty($updatedContact->code_fournisseur)) {

			if(!empty($updatedContact->code_client)) {
				$o = $xmlContactsEntry->addChild('userDefinedField', null, 'http://schemas.google.com/contact/2008');
				$o->addAttribute('key', $langs->trans('CustomerCode'));
				$o->addAttribute('value', $updatedContact->code_client);
			}

			if(!empty($updatedContact->code_fournisseur)) {
				$o = $xmlContactsEntry->addChild('userDefinedField', null, 'http://schemas.google.com/contact/2008');
				$o->addAttribute('key', $langs->trans('SupplierCode'));
				$o->addAttribute('value', $updatedContact->code_fournisseur);
			}
		}

		unset($xmlContactsEntry->updated);
		$xmlContactsEntry->addChild('updated', date('c'));

        $updatedXML = $xmlContactsEntry->asXML();
     	//pre(htmlentities($updatedXML),true);
// TODO remplacer "default" par l'adresse mail $userUsedToSync->email si nécessaire
        $req = new \Google_Http_Request('https://www.google.com/m8/feeds/contacts/'.urldecode($userUsedToSync->email).'/full/'.basename($updatedContact->id));
        $req->setRequestHeaders(array(
			'Content-Type' => 'application/atom+xml; charset=UTF-8; type=entry'
			,'GData-Version' => '3.0'
			,'If-Match' => '*'
		));
        $req->setRequestMethod('PUT');
        $req->setPostBody($updatedXML);


        $val = $client->getAuth()->authenticatedRequest($req);

        $response = $val->getResponseBody();
      	//pre(htmlentities($response),true); exit;
        $xmlContact = simplexml_load_string($response);
        $xmlContact->registerXPathNamespace('gd', 'http://schemas.google.com/g/2005');

        $xmlContactsEntry = $xmlContact;

        $contactDetails = array();

        $contactDetails['id'] = (string) $xmlContactsEntry->id;
        $contactDetails['name'] = (string) $xmlContactsEntry->title;

        foreach ($xmlContactsEntry->children() as $key => $value) {
            $attributes = $value->attributes();

            if ($key == 'link') {
                if ($attributes['rel'] == 'edit') {
                    $contactDetails['editURL'] = (string) $attributes['href'];
                } elseif ($attributes['rel'] == 'self') {
                    $contactDetails['selfURL'] = (string) $attributes['href'];
                }
            }
        }

        $contactGDNodes = $xmlContactsEntry->children('http://schemas.google.com/g/2005');
        
        foreach ($contactGDNodes as $key => $value) {
            $attributes = $value->attributes();

            if ($key == 'email') {
                $contactDetails[$key] = (string) $attributes['address'];
            } else {
                $contactDetails[$key] = (string) $value;
            }
        }

        return new Contact($contactDetails);
    }

    public static function create($userUsedToSync, $name)
    {
    	$doc = new \DOMDocument();
        $doc->formatOutput = true;
        $entry = $doc->createElement('atom:entry');
        $entry->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:atom', 'http://www.w3.org/2005/Atom');
        $entry->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:gd', 'http://schemas.google.com/g/2005');
        $entry->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:gd', 'http://schemas.google.com/g/2005');
        $doc->appendChild($entry);

        $title = $doc->createElement('title', $name);
        $entry->appendChild($title);

        $xmlToSend = $doc->saveXML();
        $client = GoogleHelper::getClient();

        $req = new \Google_Http_Request('https://www.google.com/m8/feeds/contacts/'.urlencode($userUsedToSync->email).'/full');
        $req->setRequestHeaders(array('content-type' => 'application/atom+xml; charset=UTF-8; type=feed'));
        $req->setRequestMethod('POST');
        $req->setPostBody($xmlToSend);

        $val = $client->getAuth()->authenticatedRequest($req);

        $response = $val->getResponseBody();

        $xmlContact = simplexml_load_string($response);

        if(!empty($xmlContact->error->internalReason)) {
        	
        	return $xmlContact->error;
        	
        }
        
	if(empty($xmlContact)) {
		return 'php-google-contacts-v3-api/factories/ContactFactory.php L389 Error in $val->getResponseBody()';
	}

        $xmlContact->registerXPathNamespace('gd', 'http://schemas.google.com/g/2005');

        $xmlContactsEntry = $xmlContact;

        $contactDetails = array();

        $contactDetails['id'] = (string) $xmlContactsEntry->id;
        $contactDetails['name'] = (string) $xmlContactsEntry->title;

        foreach ($xmlContactsEntry->children() as $key => $value) {
            $attributes = $value->attributes();

            if ($key == 'link') {
                if ($attributes['rel'] == 'edit') {
                    $contactDetails['editURL'] = (string) $attributes['href'];
                } elseif ($attributes['rel'] == 'self') {
                    $contactDetails['selfURL'] = (string) $attributes['href'];
                }
            }
        }

        $contactGDNodes = $xmlContactsEntry->children('http://schemas.google.com/g/2005');

        foreach ($contactGDNodes as $key => $value) {
            $attributes = $value->attributes();

            if ($key == 'email') {
                $contactDetails[$key] = (string) $attributes['address'];
            } else {
                $contactDetails[$key] = (string) $value;
            }
        }

        return new Contact($contactDetails);
    }

	public static function deleteAllGroups($url) {

		$TGroups = self::getAllByURL($url.'?max-results=100');
/*
		echo '<pre>';
		var_dump($TGroups);
		echo '</pre>';
*/
		if(! empty( $TGroups)) {
    			$doc = new \DOMDocument();
			$doc->formatOutput = true;
        		$feed = $doc->createElement('atom:feed');
        		$feed->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:atom', 'http://www.w3.org/2005/Atom');
			$feed->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:gd', 'http://schemas.google.com/g/2005');
			$feed->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:batch', 'http://schemas.google.com/gdata/batch');
			$doc->appendChild($feed);

			foreach($TGroups AS $group) {
				$entry = $doc->createElement('atom:entry');
				$entry->setAttribute('gd:etag', '*');
				$feed->appendChild($entry);
				$batchID = $doc->createElement('batch:id', 'delete');
				$entry->appendChild($batchID);
				$batchOperation = $doc->createElement('batch:operation', null);
				$batchOperation->setAttribute('type', 'delete');
				$entry->appendChild($batchOperation);

				$entryID = $doc->createElement('id', $group->id);
				$entry->appendChild($entryID);
			}

			$xmlToSend = $doc->saveXML();

			// echo '<pre>'.htmlspecialchars($xmlToSend).'</pre>';
        		$client = GoogleHelper::getClient();

        		$req = new \Google_Http_Request('https://www.google.com/m8/feeds/groups/default/full/batch');
        		$req->setRequestHeaders(array('content-type' => 'application/atom+xml; charset=UTF-8; type=feed'));
        		$req->setRequestMethod('POST');
        		$req->setPostBody($xmlToSend);

        		$val = $client->getAuth()->authenticatedRequest($req);

			// echo '<pre>'.htmlentities($val->getResponseBody()).'</pre>'; exit;
		}
	}
}
