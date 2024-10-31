<?php

//#################
// Stop direct call
if(preg_match('#' . basename(__FILE__) . '#', $_SERVER['PHP_SELF'])) { die('Oh tonight, you killed me with your smile. So beautiful and wild, so beautiful..'); }
//#################  



?>
<div class="wrap">
<h2><? _e('Paranoid911 Options','paranoid911'); ?></h2>
				
<form method="post">

<?php wp_nonce_field('update-options'); ?>	
  
<table class="form-table"> 
<tr valign="top"> 
<th scope="row">
 <? _e('Keep an eye on','paranoid911'); ?>
</th> 
<td>
 <input id="check1" type="checkbox" name="paranoid911_check_database" <?php echo get_option('paranoid911_check_database') ? "checked":""; ?> />
 <label for="check1"><? _e('Database','paranoid911'); ?></label><br /> 
 <input id="check2" type="checkbox" name="paranoid911_check_filesystem" <?php echo get_option('paranoid911_check_filesystem') ? "checked":""; ?> />
 <label for="check2"><? _e('Filesystem','paranoid911'); ?></label><br /> 
</td>
</tr> 
<tr valign="top"> 
<th scope="row">
 <? _e('File checking method','paranoid911'); ?>
 
</th> 
<td>
 <input id="radio1" type="radio" name="paranoid911_file_method" value="1" <?php echo (get_option('paranoid911_file_method')==1) ? "checked":""; ?>/>
 <label for="radio1"><? _e('filemtime (faster, less secure)','paranoid911'); ?></label><br /> 
 <input id="radio2" type="radio" name="paranoid911_file_method" value="2" <?php echo (get_option('paranoid911_file_method')==2) ? "checked":""; ?>/>
 <label for="radio2"><? _e('md5_file (slower, more secure)','paranoid911'); ?></label><br /> 
</td>
</tr> 
<tr valign="top"> 
<th scope="row">
 <? _e('Run once in','paranoid911'); ?>
 
</th> 
<td>
 <input id="interval" name="paranoid911_check_interval" value="<?php echo (int)get_option('paranoid911_check_interval'); ?>" style="width: 50px;"/> <? _e('hours','paranoid911'); ?>
</td>
</tr> 
</table>

<input type="hidden" name="action" value="update" />
<input type="hidden" name="page_options" value="paranoid911_check_filesystem,paranoid911_check_database,paranoid911_check_interval" />



<p class="submit">
<input type="submit" name="Submit" value="<?php _e('Save Changes') ?>" />
</p>
</form>



<h5> <? _e('WordPress plugin by','paranoid911'); ?> <a href="http://www.jeka911.com/">Jeka911</a></h5>

</div>
	