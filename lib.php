<?php
/*  Copyright 2010 Ozgur Koca  (email : ozgur.koca@linux.org.tr)

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

	// Include external codes	
	include_once(dirname(__FILE__).'/def.php');	
	include_once(dirname(__FILE__).'/options.php');		
	
	// Called whenever the page generation is ended	
	function wpsc_ob_callback($ob_buffer)
	{		
		$options = wpsc_get_options();

		// Cache the output buffer (ob) of php
		if ( $options['enabled'] && wpsc_is_cachable( $ob_buffer ) )
		{	
			$cache_file = wpsc_get_cache_filename();
			
			// Remove performance footer (attaching from the wp_footer-filter)
			if ($options['perf_footer'])
				$ob_buffer = wpsc_remove_footer( $ob_buffer );
				
			$content = $ob_buffer;
						
			// Compress HTML
			if ($options['compress'])
				$content = wpsc_compress( $content );
			
			// Save cache
			file_put_contents( $cache_file, $content );
			
			# Debugging code
			if (DEBUG_MODE)
			wpsc_add_to_file(dirname(__FILE__).'/cache/url.txt', time().' - MakCache: '.wpsc_get_url()."\n");
			
			// If newly cached content
			if ($options['perf_footer'] && current_user_can('manage_options') )
				return wpsc_inject_footer( $ob_buffer, wpsc_get_performance_footer( true ) );	
		}		
		
		if ($options['perf_footer'] && current_user_can('manage_options'))
			return wpsc_inject_footer( $ob_buffer, wpsc_get_performance_footer( false ) );	
		else 
			return $ob_buffer;
	}
	
	function wpsc_delete_cache_trashed($post_id)
	{	
		wpsc_delete_cache_file($post_id);
	}
	
	function wpsc_handle_user_interactions()
	{
		add_action('publish_post', 	'wpsc_post_changed', 0);
		add_action('edit_post', 	'wpsc_post_changed', 0);
		add_action('delete_post', 	'wpsc_post_changed', 0);
		add_action('publish_phone', 'wpsc_post_changed', 0); //Runs just after a post is added via email.			
		add_action('trackback_post', 'wpsc_post_changed', 0);
		add_action('pingback_post', 'wpsc_post_changed', 0);
		add_action('comment_post', 	'wpsc_post_changed', 0);
		add_action('edit_comment', 	'wpsc_post_changed', 0);
		add_action('wp_set_comment_status', 'wpsc_post_changed', 0);
		add_action('delete_comment', 'wpsc_post_changed', 0);
		add_action('wp_cache_gc',	'wpsc_post_changed', 0);
		add_action('switch_theme', 	'wpsc_post_changed', 100); //**
		add_action('edit_user_profile_update', 'wpsc_post_changed', 100);
		add_action('trash_post', 'wpsc_delete_cache_trashed', 10);			
	}
	
	function wpsc_get_domain()
	{
		$ret = isset($_SERVER['HTTPS']) ? 'https://' : 'http://';
		$ret .= $_SERVER['HTTP_HOST'];
		return $ret;
	}
	
	function wpsc_get_url()
	{
		return wpsc_get_domain().$_SERVER['REQUEST_URI'];
	}
	
	function wpsc_get_cache_filename()
	{	
		// AUTH_KEY uniqly modifies file name for guessing attempts
		// otherwise someone on the server can modify the content of cache file.
		return dirname(__FILE__).'/cache/'.md5( AUTH_KEY . wpsc_get_url() ).'.dat';			
	}
	
	// Delete from disk
	function wpsc_delete_cache_file($delete_me)
	{
		# Delete with post_id = delete_me
		if (is_numeric($delete_me))
		{
			$permalink = get_permalink($delete_me);
			@unlink( dirname(__FILE__).'/cache/'.md5( AUTH_KEY . $permalink ).'.dat' );
			@unlink( dirname(__FILE__).'/cache/'.md5( AUTH_KEY . wpsc_get_domain() . '/').'.dat' ); //index.php
			return;
		}
		
		# Delete with permalink = delete_me
		
	}
	
	// Delete all category pages
	function wpsc_delete_category_pages()
	{
		$cats = get_categories();
		foreach($cats as $cat) {					
			$category_id = get_cat_ID($cat->name);
			$category_link = get_category_link( $category_id );
			wpsc_delete_cache_file($category_link);
		}
	}
	
	// Delete cached page on change
	function wpsc_post_changed($post_id)
	{		
		// Delete cache file from disk
		wpsc_delete_cache_file($post_id);
		
		// Delete all category pages
		wpsc_delete_category_pages();
		
		# Debugging code
		if (DEBUG_MODE)
		{
			$permalink = get_permalink($post_id);
			if (!empty($permalink))
			wpsc_add_to_file(dirname(__FILE__).'/cache/url.txt', time().' - DelCache: '.$permalink."\n");
			wpsc_add_to_file(dirname(__FILE__).'/cache/url.txt', time().' - DelCache: '.wpsc_get_domain()."/\n");			
		}
	}
	
	function wpsc_check_gz_funcs()
	{
		if (function_exists('gzencode') && function_exists('gzdecode'))
			return C_METHOD_1;
		
		if (function_exists('gzcompress') && function_exists('gzuncompress'))
			return C_METHOD_2;
		
		return 0;
	}
	
	function wpsc_compress($data)
	{
		global $options;
		if (function_exists('gzencode') && ($options['compress'] == C_METHOD_1) )
			return gzencode( $data, 4, FORCE_GZIP  );
		
		if (function_exists('gzcompress') && ($options['compress'] == C_METHOD_2) )
			return gzcompress( $data, 4 );
		
		return $data;
	}
	
	function wpsc_uncompress($data)
	{
		global $options;		
		if (function_exists('gzdecode') && ($options['compress'] == C_METHOD_1) )
			return gzdecode( $data );
		
		if (function_exists('gzuncompress') && ($options['compress'] == C_METHOD_2) )
			return gzuncompress( $data );
		
		return $data;
	}
	
	function wpsc_add_to_file($file_name, $text)
	{
		file_put_contents( $file_name, file_get_contents( $file_name ).$text);
	}
		
	function wpsc_activate()
	{
		if ( wpsc_is_config_writable() )
		{
			// Add WP_CACHE define to wp-config.php
			if ( wpsc_add_define(WPC_ENABLED, ABSPATH . 'wp-config.php') )
			{	
				if ( file_exists( ABSPATH.'wp-content/advanced-cache.php' ) 
					&& ( strpos(@file_get_contents( ABSPATH.'wp-content/advanced-cache.php' ), WPSC_SIGN) == false  ))
				{
					@unlink(ABSPATH.'wp-content/plugins/wp-simple-cache/advanced-cache-bck.php');
					
					//move existing 3rd party advanced-cache.php
					@rename( ABSPATH.'wp-content/advanced-cache.php', 
						ABSPATH.'wp-content/plugins/wp-simple-cache/advanced-cache-bck.php');
				}
				
				copy( ABSPATH.'wp-content/plugins/wp-simple-cache/advanced-cache.php',
					ABSPATH.'wp-content/advanced-cache.php');
			}
		}
	}
	
	function wpsc_deactivate()
	{
		if ( wpsc_is_config_writable() )
		{
			// Remove WP_CACHE define from wp-config.php
			wpsc_remove_define(WPC_ENABLED, ABSPATH . 'wp-config.php');
			
			@unlink(ABSPATH.'wp-content/advanced-cache.php');
			
			if (file_exists( ABSPATH.'wp-content/plugins/wp-simple-cache/advanced-cache-bck.php' ))			
				@rename( ABSPATH.'wp-content/plugins/wp-simple-cache/advanced-cache-bck.php',
					ABSPATH.'wp-content/advanced-cache.php');
			else
				@unlink( ABSPATH.'wp-content/advanced-cache.php' );
		}
	}
	
	// Uninstall WPSC plugin
	function wpsc_uninstall()
	{
		// Clean cache directory
		wpsc_clean_cache();
			
		// De activate WP cache system
		wpsc_deactivate();
	}
	
	// Install WPSC plugin
	function wpsc_install()
	{
		if ( is_admin() )
		{ 
			if ( wpsc_is_config_writable() && wpsc_is_cache_writable() )
			{
				if (!isset($options['cache_ttl'])) 
				{	
					// Default values
					wpsc_set_defaults();
				}
				wpsc_activate();
			}			
		} 
	}	
	
	function wpsc_set_defaults()
	{
		$options = array();			
		$options['cache_ttl'] = 8*60;
		$options['enabled'] = false;
		$options['clean_interval'] = 7*24*60;
		$options['last_cleaning'] = time();
		$options['compress'] = wpsc_check_gz_funcs();
		$options['perf_footer'] = false;
		wpsc_update_options($options);	
	}
	
	// Add menu item to wp manager for options
	function wpsc_add_options() 
	{
		add_options_page('WP Simple Cache Options', 'WP Simple Cache', 'manage_options', 'wpsc_options_id', 'wpsc_options');
	}

	function wpsc_notice_box( $notice )
	{		
		echo '<div class="error fade" style="background-color:red;color:white;"><p>' . $notice . '</p></div>';		
	}	
	
	function wpsc_is_cache_writable() 
	{			
		$wpsc_notice = '';
		
		if ( !is_dir( dirname(__FILE__).'/cache' ) )
		if ( !($dir = @mkdir( dirname(__FILE__).'/cache', 0777) ) ) 
			$wpsc_notice .= '<b>Warning:</b> WP Simple Cache plugin was not able to create the dir "cache" '
				.'in its installation dir("'.dirname(__FILE__).'"). '
				.'Create it by hand and make it writable or check permissions.<br />';

		if ( !is_writable( dirname(__FILE__) . '/cache' )) 			
			$wpsc_notice .= '<b>Warning:</b> WP Simple Cache plugin was not able to write cache directory "'
				.dirname(__FILE__).'/cache/". Please make the cache dir is writable.<br />';

		if (!empty($wpsc_notice))
		{
			wpsc_notice_box( $wpsc_notice );
			return false;
		}
		
		return true;
	}
	
	function wpsc_check_configuration()
	{
		$ret = True;
		
		echo file_get_contents( ABSPATH.'wp-content/advanced-cache.php' );
		
		# Check WP_CACHE define
		if (!defined('WP_CACHE'))
		{
			$ret = False;
			$message = sprintf("Warning: The plugin not functional because WP_CACHE not defined in <b>%s</b> file. You can add manually or first deactivate the plugin and activate again.",
				'/wp-config.php');
			wpsc_notice_box($message);
		}
		
		# Check advanced-cache.php
		if (!file_exists( ABSPATH.'wp-content/advanced-cache.php' ))
		{
			$ret = False;
			$message = sprintf("Warning: Plugin not funcitonal because the <b>%s</b> file not exists. You can manually copy <b>%s</b> to <b>%s</b> or deactivate the plugin and activate again.", 
				ABSPATH.'wp-content/advanced-cache.php',
				ABSPATH."wp-content/plugins/wp-simple-cache/advanced-cache.php",
				ABSPATH.'wp-content/advanced-cache.php');			
			wpsc_notice_box($message);
			
		} 
		else 
		{		
			if (strpos(file_get_contents( ABSPATH.'wp-content/advanced-cache.php' ), WPSC_SIGN) === false) 
			{
				$ret = False;
				$message = sprintf("Warning: Plugin not funcitonal because the <b>%s</b> file not valid. You can manually copy <b>%s</b> to <b>%s</b> or deactivate the plugin and activate again.", 
					ABSPATH.'wp-content/advanced-cache.php',
					ABSPATH."wp-content/plugins/wp-simple-cache/advanced-cache.php",
					ABSPATH.'wp-content/advanced-cache.php');				
				wpsc_notice_box($message);
			}
		}
			
		return $ret;
	}
	
	function wpsc_is_config_writable()
	{
		$wpsc_notice = '';
		
		if (!is_writable( ABSPATH . 'wp-config.php' ))
			$wpsc_notice .= '<b>Warning:</b> Wordpress config file (wp-config.php) is not writable by server.'
				.'Check its permissions.';
			
		if (!is_writable( ABSPATH . 'wp-content/' ))
			$wpsc_notice .= '<b>Warning:</b> Wordpress content directory (wp-content/) is not writable by server.'
			.'Check its permissions.';
		
		if (!empty($wpsc_notice))
		{
			wpsc_notice_box( $wpsc_notice );
			return false;
		}
		
		return true;		
	}
	
	function wpsc_get_performance_footer($newly_cached = false)
	{
		global $options;
		
		if ( $options['perf_footer'] ) 
		{			
			if (function_exists( 'get_num_queries' ))
				$queries = '<b>'.get_num_queries().'</b> Queries';
			else 
				$queries = '<b>No Query</b>';
		
			if (function_exists( 'timer_stop' ))
			$exec_time = '<b>'.timer_stop(0).'</b> sec.';
			
			$state = $newly_cached ? '<b><font color=green>Newly Cached</font></b>' : ($options['enabled'] ? '<b>Enabled</b>' : '<b>Disabled</b>');
			
			$ret = '';
			$ret .= "\n<div style='width:420px;position:fixed;margin:0;opacity:0.8;padding:1px 0;right:0;top:0;z-index:10001;background-color:#DDDDDD;background:-moz-linear-gradient(center bottom , #D7D7D7, #E4E4E4) repeat scroll 0 0 transparent;font-family:Verdana,Arial,Helvetica,sans-serif;font-size:12px;'>";
			$ret .= "\n	<div style='background-color:#DDDDDD;background:-moz-linear-gradient(center bottom , #D7D7D7, #E4E4E4) repeat scroll 0 0 transparent;float:left;text-align:center;width:120px;border-right:1px solid #AAAAAA;margin:0;padding:0;opacity:0.8;color:#404040;'>";
			$ret .= "\n		<a href='http://www.tankado.com/wp-simple-cache/' style='text-decoration:none;color:#404040;'>WP Simple Cache</a><br>";
			$ret .= "\n		$state";
			$ret .= "\n	</div>";
			$ret .= "\n	<div style='float:left;list-style:none outside none;opacity:0.8;border-right:1px solid #AAAAAA;margin-top:5px;padding:0 5px;'>";
			$ret .= "\n		<img src='/wp-content/plugins/wp-simple-cache/images/database.png' style='vertical-align:middle;'> $queries";
			$ret .= "\n	</div>";
			$ret .= "\n	<div style='float:left;list-style:none outside none;opacity:0.8;border-right:1px solid #AAAAAA;margin-top:5px;padding:0 5px;'>";
			$ret .= "\n		<img src='/wp-content/plugins/wp-simple-cache/images/time.png' style='vertical-align:middle;'> $exec_time";
			$ret .= "\n	</div>";
			$ret .= "\n	<div style='float:left;list-style:none outside none;opacity:0.8;margin-top:5px;padding:0 5px;'>";
			$ret .= "\n		<img src='/wp-content/plugins/wp-simple-cache/images/options.png' style='vertical-align:middle;'> <a href='/wp-admin/options-general.php?page=wpsc_options_id'>Options</a>";
			$ret .= "\n	</div>";
			$ret .= "\n</div>";
			
			$ret = FOOTER_START . $ret . FOOTER_END ;
			
			return $ret;
		}
	}
	
	function wpsc_remove_footer($content)
	{
		global $options;
		
		$ret = $content;		
		
		if ((strpos($content, FOOTER_START) !== false) and (strpos($content, FOOTER_END) !== false))
		{		
			$ret = substr($content, 0 , strpos($content, FOOTER_START))
				. substr($content, strpos($content, FOOTER_END) + strlen(FOOTER_END), strlen($content));
		}		
		return $ret;
	}
	
	function wpsc_inject_footer($content, $footer)
	{
		$ret = $content;
		
		if ((strpos($content, 'body>') !== false) or (strpos($content, 'BODY>') !== false))			
		{
			$body = strpos($content, 'body>');
			if ($body !== false)
				$body = strpos($content, 'BODY>');
			
			$ret = substr($content, 0, $body - 1) . $footer . substr($content, $body - 1, strlen($content));			
		}
		return $ret;
	}
	
	function wpsc_show_performance_footer()
	{
		if ( current_user_can('manage_options') )
		echo wpsc_get_performance_footer();
	}
	
	function wpsc_formatBytes($bytes, $precision = 2) 
	{
		$units = array('B', 'KB', 'MB', 'GB', 'TB');
		$bytes = max($bytes, 0);
		$pow = floor(($bytes ? log($bytes) : 0) / log(1024));
		$pow = min($pow, count($units) - 1);	  
		$bytes /= pow(1024, $pow);
	  
		return round($bytes, $precision) . ' ' . $units[$pow];
	}
	
	function wpsc_get_cache_status()
	{
		$count = 0;
		$size = 0;
		if ($handle = @opendir(dirname(__FILE__) . '/cache'))
		{
			while (false !== ($file = readdir($handle)))
			{
				if ($file != '.' && $file != '..' && $file != 'options.dat')
				{
					$count++;
					$size += filesize( dirname(__FILE__) . '/cache/' . $file );
				}
			}
			closedir($handle);
			return 'Cached files; Count: <b>' . $count . '</b>, Total size: <b>' . wpsc_formatBytes($size, 2).'</b>';
		}
		return 'Couldnt open dir: ' . dirname(__FILE__) . '/cache';
	}
	
	function wpsc_clean_cache() 
	{
		if ($handle = @opendir(dirname(__FILE__) . '/cache'))
			while (false !== ($file = readdir($handle)))
				if ($file != '.' && $file != '..' && $file != 'options.dat')
				@unlink(dirname(__FILE__) . '/cache/' . $file);
			
		closedir($handle);	
	}
	
	function wpsc_clean_expired_cache() 
	{
		global $options;
		
		if ($handle = @opendir(dirname(__FILE__) . '/cache'))
			while (false !== ($file = readdir($handle)))
				if ( ($file != '.' && $file != '..' && $file != 'options.dat')
					&& ((time() - filemtime($file)) >= ($options['cache_ttl'] * 60)) )
				@unlink(dirname(__FILE__) . '/cache/' . $file);
			
		closedir($handle);	
	}	
	
	function wpsc_check_for_cache_cleaning() 
	{
		global $options;
		
		if ( $options['clean_interval'] > 0 )
		if ((time() - $options['last_cleaning']) >= ($options['clean_interval'] * 60) )
		{
			wpsc_clean_expired_cache();
			$options['last_cleaning'] = time();
			wpsc_update_options($options);
		}
	}
	
	function wpsc_is_valid_cache($fname)
	{
		global $options;
		
		if (file_exists( $fname ))
		{
			if ((time() - filemtime($fname)) >= ($options['cache_ttl'] * 60))
			{
				@unlink( $fname );
				
				# Debug Code
				if (DEBUG_MODE)
				wpsc_add_to_file(dirname(__FILE__).'/cache/url.txt', time().' - DelCache: '.wpsc_get_url()."\n");				
				
				return false;
			} 			
			return true;
		}
		return false;
	}
	
	// Dont cache WP Manager pages
	function wpsc_is_cachable($ob_buffer = 'something')
	{
		if ( strlen( trim($ob_buffer) ) == 0 )
			return false;
		
		if ( (strpos($_SERVER['REQUEST_URI'], '/wp-content/') !== false) ||
			(strpos($_SERVER['REQUEST_URI'], '/wp-includes/') !== false) ||
			(strpos($_SERVER['REQUEST_URI'], '/wp-admin') !== false) ||
			(strpos($_SERVER['REQUEST_URI'], '/wp-login') !== false) ||
			(strpos($_SERVER['REQUEST_URI'], 'wp-cron.php') !== false) ||
			(strpos($_SERVER['REQUEST_URI'], 'wp-comments-post.php') !== false) )
		return false;
		
		return true;
	}
	
	function wpsc_add_define($new, $my_file)
	{
		if (!is_writable($my_file)) 
		{
			wpsc_notice_box( "Error: file $my_file is not writeable.<br />\n" );
			return false;
		}
		
		$found = false;
		$lines = file($my_file);
		foreach($lines as $line) 
		{
			if ( strpos($line, $new) !== false ) 
			{
				$found = true;
				break;
			}
		}
		
		if (!$found) 
		{
			$fd = fopen($my_file, 'w');
			$done = false;
			foreach($lines as $line) 
			{
				if ( $done || !(strpos(strtolower($line), 'define') !== false))
					fputs($fd, $line);
				else {
					fputs($fd, "$new");
					fputs($fd, $line);
					$done = true;
				}
			}
			fclose($fd);
			return true;
		}
		return false;
	}
	
	function wpsc_remove_define($new, $my_file)
	{
		if (!is_writable($my_file)) 
		{
			wpsc_notice_box( "Error: file $my_file is not writeable.<br />\n" );
			return false;
		}
		
		$found = false;
		$lines = file($my_file);
		foreach($lines as $line) 
		{
			if ( strpos($line, $new) !== false) 
			{
				$found = true;
				break;
			}
		}
		
		if ($found) 
		{
			$fd = fopen($my_file, 'w');
			$done = false;
			foreach($lines as $line) 
			{
				if ( strpos($line, $new) === false )
					fputs($fd, $line);
			}
			fclose($fd);
			return true;
		}
	}
	
	function wpsc_update_options($arr)
	{
		file_put_contents(ABSPATH.'wp-content/plugins/wp-simple-cache/cache/options.dat',
			 serialize( $arr ) );
	}
	
	function wpsc_get_options()
	{
		$ret = unserialize( file_get_contents(
			ABSPATH.'wp-content/plugins/wp-simple-cache/cache/options.dat'));
		
		if ( empty($ret) )
			wpsc_set_defaults();
		
		$ret = unserialize( file_get_contents(
			ABSPATH.'wp-content/plugins/wp-simple-cache/cache/options.dat'));
		
		return $ret;
	}	
	
	function wpsc_browser_gz_compatibility()
	{
		return strpos($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip') !== false;
	}
?>