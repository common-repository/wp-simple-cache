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
	//error_reporting(E_ALL);
	
	// Include external codes	
	include_once(dirname(__FILE__).'/def.php');
	include_once(dirname(__FILE__).'/lib.php');
	include_once(dirname(__FILE__).'/options.php');
	
	// Load WPSC settings
	$options = wpsc_get_options();
	
	// Check for cache clean
	wpsc_check_for_cache_cleaning();
		
	// if WPSC is enabled load from cache
	if ( $options['enabled'] && wpsc_is_cachable() )
	{		
		// Include extra PHP script
		if ($include = $options['include_php'])
			include($include);
				
		$cache_file = wpsc_get_cache_filename();
		
		if ( wpsc_is_valid_cache( $cache_file ) ) 
		{							
			// Send compressed data
			if ( $options['compress'] )
			{					
				$content = file_get_contents( $cache_file );
				if ( $options['perf_footer'] )
				{
					$content = wpsc_uncompress( $content );
					$content = wpsc_inject_footer( $content, wpsc_get_performance_footer() );
					$content = wpsc_compress( $content );
				}
								
				if ($http_compress = wpsc_browser_gz_compatibility())
				{				
					header( 'Content-Encoding: gzip' );
					header( 'Content-Length: ' . strlen($content) );								
					if ($http_compress == C_METHOD_2)
						$content = "\x1f\x8b\x08\x00\x00\x00\x00\x00" . $content; 
				}
				else
				$content = wpsc_uncompress( $content );
			}
			
			// Send uncompressed data
			else 
			{
				$content = file_get_contents( $cache_file );
				if ($options['perf_footer'])
					$content = wpsc_inject_footer( $content, wpsc_get_performance_footer() );				
			}
						
			# Debugging code
			if (DEBUG_MODE)
			wpsc_add_to_file(dirname(__FILE__).'/cache/url.txt', time().' - LodCache: '.wpsc_get_url()."\n");				
			
			die($content);
		}
	}

	// Manage output buffer of PHP
	ob_start('wpsc_ob_callback');
?>