<?php
	require '../config.php';

?>
$(document).ready(function() {

	$.ajax({
		url:'<?php echo dol_buildpath('/googlecontactsync/script/interface.php?put=sync',1) ?>'
		,dataType:'json'	
	}).done(function(data) {
		
	});
	
});
