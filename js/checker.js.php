<?php
	require '../config.php';
	
	if(empty($conf->googlecontactsync->enabled) || !empty($conf->global->GSC_DISABLE_JS_SYNC)) exit;
	
?>
$(document).ready(function() {
	console.log('Please, enable GSC_DISABLE_JS_SYNC and use cron job');
	window.setTimeout(gcscksync,5000);
	
});

function gcscksync() {

	$.ajax({
             url:'<?php echo dol_buildpath('/googlecontactsync/script/interface.php?put=sync',1) ?>'
             ,dataType:'json'        
         }).done(function(data) {

         })
}
