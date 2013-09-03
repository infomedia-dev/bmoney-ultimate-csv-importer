<?php
$log_format = '<div class="entry"><span class="timestamp" title="%1$s">[%1$s]</span> <span class="message">%2$s</span></div>'; ?>
<div class="wrap">

	<?php if($last_increment){ ?>
		<p class="last-increment">Last row imported #<?php echo $last_increment; ?></p>
	<?php } ?>
	
	
	<?php 
	$test = get_option(BMUCI_OPT_PREFIX.'import_log');

	if(is_array($import_log) && count($import_log)){ ?>
		<div class="log-wrap">
			<h3>Import Log</h3>
			<div class="import-log log">
				<?php foreach($import_log as $k => $v){
					echo sprintf($log_format, date('n/j/Y g:i', $k), $v);
				} ?>
			</div>
		</div>
	<?php } ?>
	
	
	<div class="clear"><!-- .clear --></div>
	
	<?php if(!$import_log){ ?>
		<p>No logs found.</p>
	<?php } ?>
</div>