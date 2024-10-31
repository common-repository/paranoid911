<?
/*
Plugin Name: Paranoid911
Plugin URI: http://www.jeka911.com/examples/paranoid911/
Description: Checks wordress installation (database and files) for changes and emails admin if any changes took place. This plugin will not save you from hackers. It will, however, notify you of possible attacks allowing you to fix things before your visitors find hidden iframes or your blog loses its rank in google.
Author: Jeka911
Version: 0.0.3

Author URI: http://jeka911.com/
*/
/*  
	Copyright 2008  Evgeniy Kiselyov (aka Jeka911)  (email : me@jeka911.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/



//#################
// Stop direct call
if(preg_match('#' . basename(__FILE__) . '#', $_SERVER['PHP_SELF'])) { die('Oh tonight, you killed me with your smile. So beautiful and wild, so beautiful..'); }
//#################  

define("PARANOID911FOLDER", plugin_basename( dirname(__FILE__)) );
load_plugin_textdomain("paranoid911", "/wp-content/plugins/paranoid911/");
///globals

$paranoid911_start_time = time();
$paranoid911_cur_item = 0;
$paranoid911_start_item = 0;

$paranoid911_default_stages = array(
                                    array('files','/',array('sitemap.xml','sitemap.xml.gz')),
                                    array('files','/wp-content/',false),
                                    array('files','/wp-content/themes/',false),
                                    array('skipfiles','/wp-content/cache/'),
                                    array('database','%prefix%options',array("option_name NOT LIKE 'rss_%'","option_name NOT IN ('stats_cache','category_children','sm_status','cron','doing_cron','akismet_spam_count','update_core','update_plugins','paranoid911_checking_stage','paranoid911_checking_item','paranoid911_first_run','kjgrc_cache')"),false),
                                    array('database','%prefix%posts',false,array("comment_count")),
                                    array('database','%prefix%users',false,false),
                                    array('database','%prefix%usermeta',array("meta_key != 'wp_autosave_draft_ids'"),false),
                                    array('database','%prefix%links',false,false)
                                   );

                                   
//get stages                                   
function paranoid911_get_stages()
{
	global $wpdb;
	global $paranoid911_default_stages;
	$paranoid911_stages = $paranoid911_default_stages;
	///////@todo: get stages from database (and administration for this)
	$stages = array();
	foreach ($paranoid911_stages as $s)
	{
		
	 if ($s[0] == "files")
	 if (get_option('paranoid911_check_filesystem'))
     {
	   //get excluded subdirs	
	   $exclude = array();	
	   foreach ($paranoid911_stages as $tmp_s)
	    if (($tmp_s[0] == "files" || $tmp_s[0] == "skipfiles") && strpos($tmp_s[1],$s[1]) === 0)
	     if (substr($tmp_s[1],strlen($s[1]),-1)) 
	      $exclude[] = substr($tmp_s[1],strlen($s[1]),-1);

	   if (isset($s[2]) && is_array($s[2])) 
	    $exclude = array_merge($exclude, $s[2]);  
	     
	   $exclude = array_unique($exclude);
	     
	   $stages[] = array('files',$s[1],implode(",",$exclude));	
	 }
	  
	 if ($s[0] == "database")
	  if (get_option('paranoid911_check_database'))
	   $stages[] = array('database', $s[1], $s[2], $s[3]);
	 
	  
	}
	
	return $stages;
}
                                   
//plugin activation
function paranoid911_install()
{
	global $wpdb , $wp_roles, $wp_version;
	
	// Check for capability
	if ( !current_user_can('activate_plugins') ) 
	 return;

	// upgrade function changed in WordPress 2.3	
	if (version_compare($wp_version, '2.3', '>='))		
		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
	else
		require_once(ABSPATH . 'wp-admin/upgrade-functions.php');
	
	// add charset & collate like wp core
	$charset_collate = '';

	if ( version_compare(mysql_get_server_info(), '4.1.0', '>=') ) {
		if ( ! empty($wpdb->charset) )
			$charset_collate = "DEFAULT CHARACTER SET $wpdb->charset";
		if ( ! empty($wpdb->collate) )
			$charset_collate .= " COLLATE $wpdb->collate";
	}

	$table_name = $wpdb->prefix . 'paranoid911_hashes';
	if($wpdb->get_var("show tables like '$table_name'") != $table_name) {
      
		$sql = "CREATE TABLE " . $table_name . " (
		`id` BIGINT(20) NOT NULL AUTO_INCREMENT ,
		`type` varchar(8) NOT NULL ,
		`value` VARCHAR(255) NOT NULL ,
		`key` VARCHAR(255) NOT NULL,
		`info` VARCHAR(255) NULL,
		PRIMARY KEY `id` (`id`),
        KEY `type` (`type`),
        KEY `key` (`key`)
		) $charset_collate;";
	
      dbDelta($sql);
    }
    
	$table_name = $wpdb->prefix . 'paranoid911_events';
	if($wpdb->get_var("show tables like '$table_name'") != $table_name) {
      
		$sql = "CREATE TABLE " . $table_name . " (
                `id` BIGINT(20) NOT NULL auto_increment,
                `type` varchar(8) NOT NULL,
                `info` varchar(255) NOT NULL,
                `time` int(11) NOT NULL,
                PRIMARY KEY  (`id`),
                KEY `type` (`type`,`time`)
		        ) $charset_collate;";
	
      dbDelta($sql);
    }    

    if(!get_option('paranoid911_check_filesystem'))
      add_option('paranoid911_check_filesystem', '1');
    if(!get_option('paranoid911_check_database'))
      add_option('paranoid911_check_database', '1');
    if(!get_option('paranoid911_check_interval'))
      add_option('paranoid911_check_interval', '1');    

    if(!get_option('paranoid911_file_method'))
      add_option('paranoid911_file_method', 1);        

    if(!get_option('paranoid911_checking_stage'))
      add_option('paranoid911_checking_stage', 0);  
       else    
        update_option('paranoid911_checking_stage', 0); 
        
    if(!get_option('paranoid911_checking_item'))
      add_option('paranoid911_checking_item', 0);  
       else    
        update_option('paranoid911_checking_item', 0);    

    if(!get_option('paranoid911_first_run'))
      add_option('paranoid911_first_run', 1);  

    
    update_option('paranoid911_first_run', 1);    
        
    paranoid911_cleanup();    
    paranoid911_reschedule();  
}

//cleanup paranoid tables
function paranoid911_cleanup()
{
	global $wpdb;
	$table_name = $wpdb->prefix . 'paranoid911_events';
	$wpdb->query("DELETE FROM ".$table_name);

	$table_name = $wpdb->prefix . 'paranoid911_hashes';
	$wpdb->query("DELETE FROM ".$table_name);	
}

//plugin deactivation
function paranoid911_deactivate()
{
	wp_clear_scheduled_hook('paranoid911_cron_event');
}

//register activate/deactivate hooks
register_activation_hook(PARANOID911FOLDER.'/paranoid911.php','paranoid911_install');
register_deactivation_hook(PARANOID911FOLDER.'/paranoid911.php','paranoid911_deactivate');

//add options page
function paranoid911_add_pages()
{
	add_options_page(__('Paranoid911 Options','paranoid911'), __('Paranoid911','paranoid911'), 10, __FILE__, 'paranoid911_options_page');  
}

//options page function
function paranoid911_options_page()
{
	if ( !current_user_can('edit_plugins') ) 
	 return;
	 
	if (isset($_POST['action']) && $_POST['action']=="update") 
	{
		//update options	
		$paranoid911_check_database = (!isset($_POST['paranoid911_check_database'])? '0': '1');
		$paranoid911_check_filesystem = (!isset($_POST['paranoid911_check_filesystem'])? '0': '1');
		$paranoid911_check_interval = (!isset($_POST['paranoid911_check_interval'])? 3: (int)$_POST['paranoid911_check_interval']);
		$paranoid911_file_method = (!isset($_POST['paranoid911_file_method'])? 2: (int)$_POST['paranoid911_file_method']);
		
		
		if ($paranoid911_check_interval < 1) $paranoid911_check_interval = 1;
		
		update_option('paranoid911_check_database', $paranoid911_check_database);
		update_option('paranoid911_check_filesystem', $paranoid911_check_filesystem);
		update_option('paranoid911_check_interval', $paranoid911_check_interval);
		update_option('paranoid911_file_method', $paranoid911_file_method); 
		update_option('paranoid911_checking_item', 0);  
		update_option('paranoid911_checking_stage', 0); 
        update_option('paranoid911_first_run', 1); 
		
        paranoid911_cleanup();
		paranoid911_reschedule();
		
		$msg_status = __('Paranoid911 options saved.','paranoid911');
		echo ('<div id="message" class="updated fade"><p>' . $msg_status . '</p></div>');		
	}
	
	$paranoid911_check_database =( get_option('paranoid911_check_database')=='1' ) ? "checked":"";
	$paranoid911_check_filesystem =( get_option('paranoid911_check_filesystem')=='1' ) ? "checked":"";
	$paranoid911_check_interval = (int)get_option('paranoid911_check_interval');
	$paranoid911_admin_email = get_option('paranoid911_admin_email');
	include("option_page.php");
}


add_action('admin_menu', 'paranoid911_add_pages');
//register hook for cron functions
add_action('paranoid911_cron_event','paranoid911_cron');


//checking everything
function paranoid911_cron()
{
    paranoid911_reschedule();

	global $wpdb;
	$paranoid911_stages = paranoid911_get_stages();
	$stage = $paranoid911_stages[get_option('paranoid911_checking_stage')];

	$recent_event_id = 0;
	$event_table_name = $wpdb->prefix . 'paranoid911_events';
	$query = "SELECT * FROM ".$event_table_name." ORDER BY id DESC LIMIT 1;";
    $results = $wpdb->get_results($query);	
	if ($results)
     $recent_event_id = $results[0]->id;
	
	$finished = true;
	if ($stage)
    {   	
     	if ($stage[0] == "files")
    	 $finished = paranoid911_check_folders($stage[1], $stage[2]);
     	if ($stage[0] == "database")
    	 $finished = paranoid911_check_database($stage[1], $stage[2], $stage[3]);    	    	
    }

    
    if ($finished)
    if (get_option('paranoid911_checking_stage') < (count($paranoid911_stages)-1))
     update_option('paranoid911_checking_stage', get_option('paranoid911_checking_stage')+1);
      else
      {
       if (get_option('paranoid911_first_run')==1)
       {
        if (get_settings('admin_email'))
        {
        	 $text = "<h3>".__('Results of first scan','paranoid911')."</h3>";
        	 
        	 $count_of_files = $wpdb->get_var("SELECT COUNT(*) FROM ".$event_table_name." WHERE `type` = 'file';");
        	 $count_of_rows =  $wpdb->get_var("SELECT COUNT(*) FROM ".$event_table_name." WHERE `type` = 'database';");
        	 
        	 $text.=__('Files: ','paranoid911').$count_of_files."<br>\n";
        	 $text.=__('Database rows: ','paranoid911').$count_of_rows."<br>\n";
        	 
        	 $text.="<hr>".__('Generated by Paranoid911','paranoid911')."<br><a href=\"http://jeka911.com\">Jeka911</a>";
             
        	 $headers = "MIME-Version: 1.0\n" .
        	 "From: " . get_settings('admin_email') . "\n" . 
             "Content-Type: text/html; charset=\"" . get_settings('blog_charset') . "\"\n";
        	 
        	 mail(get_settings('admin_email'), get_option("blogname")." - ".__("Results of first scan",'paranoid911'), $text, $headers);
        }
        update_option('paranoid911_first_run', 0);
        update_option('paranoid911_checking_stage', 0);
        return;
       }
       update_option('paranoid911_checking_stage', 0);
      }
      
    if (get_option('paranoid911_first_run')==0)
    {
    	
	$query = "SELECT * FROM ".$event_table_name." WHERE id > '".(int)$recent_event_id."';";
    $results = $wpdb->get_results($query);	
	if ($results)
	{
        ///////Send mail
        if (get_settings('admin_email'))
        { 
        	$text = "<h3>".__('Changes:','paranoid911')."</h3>";
        	
        	foreach ($results as $r)
        	 $text.= $r->info."<br>\n";
        	
        	$text.="<hr>".__('Generated by Paranoid911','paranoid911')."<br><a href=\"http://jeka911.com\">Jeka911</a>";
        	
            $headers = "MIME-Version: 1.0\n" .
            "From: " . get_settings('admin_email') . "\n" . 
            "Content-Type: text/html; charset=\"" . get_settings('blog_charset') . "\"\n";
        	 
        	 
        	mail(get_settings('admin_email'), get_option("blogname")." - ".__("Notice: Paranoid got something for you",'paranoid911'), $text, $headers);
        }
	}
	
    }
       
}

//reschedule paranoid911
function paranoid911_reschedule()
{
	$paranoid911_check_interval = (int)get_option('paranoid911_check_interval');
	$stages = paranoid911_get_stages();
	
	
	if ($paranoid911_check_interval < 1) 
	 $paranoid911_check_interval = 1;

	$next_run = time() + $paranoid911_check_interval*60*60/count($stages); 
	
	if(get_option('paranoid911_first_run') == 1)
	 $next_run = time();
	 
	wp_clear_scheduled_hook('paranoid911_cron_event');
	wp_schedule_single_event($next_run, 'paranoid911_cron_event');
}

//check folders
function paranoid911_check_folders($path, $excludes, $r = false)
{
	global $paranoid911_start_time;
	global $paranoid911_cur_item;
	global $paranoid911_start_item;
    
	if ($r == false)
	{
		//not recursive
		$paranoid911_start_time = time();
		$paranoid911_cur_item = 0;
		$paranoid911_start_item = get_option('paranoid911_checking_item');
	}
	
	$excludes = explode(",",$excludes);
	$d = dir(ABSPATH.$path);
    while (false !== ($entry = $d->read()))
    {
     if ($entry!="." && $entry!=".." && is_dir(ABSPATH.$path.$entry))
      if (!in_array($entry,$excludes))
      {
      	if (!paranoid911_check_folders($path.$entry."/", "", true))
      	 return false;
      }
     if (is_file(ABSPATH.$path.$entry))
     if (!in_array($entry,$excludes))
     if ($paranoid911_start_time > (time() - 15))
     {
     	$paranoid911_cur_item++;
     	if ($paranoid911_cur_item > $paranoid911_start_item)
     	{
     	 if (get_option('paranoid911_file_method') == 1)	
     	  $hash = md5($path.htmlentities($entry).filemtime(ABSPATH.$path.$entry)).$path.htmlentities($entry).filemtime(ABSPATH.$path.$entry);    	
     	   elseif (get_option('paranoid911_file_method') == 2)
     	    $hash = md5_file(ABSPATH.$path.$entry).$path.htmlentities($entry);    	
     	    
     	 $key = md5($path.htmlentities($entry)).$path.htmlentities($entry);
     	 $desc = __("File: ",'paranoid911').$path.htmlentities($entry);
     	 
     	 echo $paranoid911_cur_item." s".$paranoid911_start_item." ".$desc."<br>";
     	 
     	 paranoid911_check("file",$desc,$key,$hash);
     	}
     } else {
     	update_option('paranoid911_checking_item', $paranoid911_cur_item);
     	return false;
     }
    } 
	$d->close();
	
	if ($r == false)
	{
		//not recursive
		update_option('paranoid911_checking_item', 0);
		return true;
	}	
	
	return true;
}


//check database
function paranoid911_check_database($table_name, $awhere, $excluded_fields)
{
	global $wpdb;
	global $paranoid911_start_time;
	global $paranoid911_cur_item;
	global $paranoid911_start_item;
    
	$table_name = str_replace("%prefix%", $wpdb->prefix, $table_name);
		
	$paranoid911_start_time = time();
	$paranoid911_start_item = get_option('paranoid911_checking_item');	
	$paranoid911_cur_item = $paranoid911_start_item;	
	
	$where = "";
	if ($awhere)
	 $where = "WHERE ".implode(" AND ",$awhere);
	
	while ($paranoid911_start_time > (time() - 15))
	{
	
	$query = "SELECT * FROM ".$table_name." ".$where." LIMIT ".(int)$paranoid911_cur_item.", 50;";

    $results = $wpdb->get_results($query, ARRAY_N);
    
    if (count($results)==0)
    {
 	 update_option('paranoid911_checking_item', 0);
	 return true;	   	
    }
    
    //get id of primary_key;
    $key_name_key = 0;
    $keys = $wpdb->get_col_info('primary_key');
    for ($i = 0; $i<count($keys)-1; $i++)
     if ($keys[$i]==1)
      $key_name_key = $i;
      
    //excluded fields  
    $excluded_fields_ids = array();
    if ($excluded_fields)
    {
     	$keys = $wpdb->get_col_info('name');

     	for ($i = 0; $i<count($keys); $i++)
     	 if (in_array($keys[$i],$excluded_fields))
     	  $excluded_fields_ids[] = $i;
    }
    
	foreach ($results as $r)
	{
	 $paranoid911_cur_item++;
     
	 foreach ($excluded_fields_ids as $e_id)
	  $r[$e_id] = "";
	 
	  
     $hash = $table_name.md5(implode(",",$r));
     $key = "db-".$table_name."-".$r[$key_name_key];
     $desc = __("Table: ",'paranoid911').$table_name.__("; Row with primary key = ",'paranoid911').$r[$key_name_key];

     
     paranoid911_check("database",$desc,$key,$hash);
	}
	 
	}
	
 	 update_option('paranoid911_checking_item', $paranoid911_cur_item);
	 return false;	
}


//compare with stored values
function paranoid911_check($type, $desc, $key, $hash)
{
	global $wpdb;
	$table_name = $wpdb->prefix . 'paranoid911_hashes';
	$event_table_name = $wpdb->prefix . 'paranoid911_events';

	$key = substr($key,0,255);
	$hash = substr($hash,0,255);
	$desc = substr($desc,0,255);	
	

	
	$row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE `key` = %s",$key));
		

	if (!$row)
	{
		
		$desc = __("New: ",'paranoid911').$desc;
		$wpdb->query($wpdb->prepare("INSERT INTO $event_table_name (`type`, `time`, `info`) VALUES (%s, %d, %s)",$type,time(),$desc));
		//NEW ITEM!
	    $wpdb->query($wpdb->prepare("INSERT INTO $table_name (`type`, `value`, `key`, `info`) VALUES (%s, %s, %s, %s)",$type,$hash,$key,$desc));
	} else {
		if ($row->value != $hash)
		{
		  $desc = __("Updated: ",'paranoid911').$desc;	
		  $wpdb->query($wpdb->prepare("INSERT INTO $event_table_name (`type`, `time`, `info`) VALUES (%s, %d, %s)",$type,time(),$desc));
		  //ITEM IS UPDATED!	
		  $wpdb->query($wpdb->prepare("UPDATE $table_name SET `value` = %s WHERE `key` = %s",$hash,$key));	
		}
	}
}


?>
