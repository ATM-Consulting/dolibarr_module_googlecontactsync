<?php

class TGCSToken extends TObjetStd {

    function __construct() {
    	global $langs;
		
        $this->set_table(MAIN_DB_PREFIX.'gcs_token');
		
        $this->add_champs('token,type_object,fk_object', array('type'=>'string','index'=>true));
		
        $this->_init_vars();

        $this->start();
		
	}

}