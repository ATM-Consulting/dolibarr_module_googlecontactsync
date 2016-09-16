<?php

class TGCSToken extends TObjetStd {

    function __construct() {
    	global $langs;
		
        $this->set_table(MAIN_DB_PREFIX.'gcs_token');
		
		$this->add_champs('fk_object',array('type'=>'integer', 'index'=>true));
        $this->add_champs('token,type_object', array('type'=>'string','index'=>true));
		
        $this->_init_vars();

        $this->start();
		
	}

	function loadByObject(&$PDOdb, $fk_object, $type_object) {
		
		$PDOdb->Execute("SELECT rowid FROM ".MAIN_DB_PREFIX."gcs_token WHERE fk_object=".(int)$fk_object." AND type_object='".$type_object."'");
		if($obj = $PDOdb->Get_line()){
			
			return $this->load($PDOdb, $obj->rowid);
		}
		
		return false;
	}

	static function getTokenFor(&$PDOdb, $fk_object, $type_object) {
	
		$gcs = new TGCSToken;
		if($gcs->loadByObject($PDOdb, $fk_object, $type_object)) {
			
			return $gcs->token;
			
		}
	
		return false;
	}

}