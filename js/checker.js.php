<?php
	require '../config.php';
	
	if(empty($conf->googlecontactsync->enabled)) exit;
	
?>
$(document).ready(function() {

	$.ajax({
		url:'<?php echo dol_buildpath('/googlecontactsync/script/interface.php?put=sync',1) ?>'
		,dataType:'json'	
	}).done(function(data) {
		
	});
	
});
