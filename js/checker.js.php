<?php
	require '../config.php';
	
	if(empty($conf->googlecontactsync->enabled) || !empty($conf->global->GSC_DISABLE_JS_SYNC)) exit;
	
?>
$(document).ready(function() {

	window.setTimeout(gcscksync,5000);
	
});

function gcscksync() {

	$.ajax({
             url:'<?php echo dol_buildpath('/googlecontactsync/script/interface.php?put=sync',1) ?>'
             ,dataType:'json'        
         }).done(function(data) {

         })
}
