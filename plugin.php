<?php
/**
 Plugin Name: WP Simple Cache
 Plugin URI: http://www.tankado.com/wp-simple-cache/
 Version: 0.1.2
 Description: WP Simple Cache is a really simple and tiny cache system for Wordpress Blogs to improve performance.
 Author: Özgür Koca
 Author URI: http://www.tankado.com/
*/
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
	// Include WP codes
	include_once(ABSPATH . 'wp-includes/plugin.php');
	include_once(ABSPATH . 'wp-includes/functions.php');
	
	// Include external codes
	include_once(dirname(__FILE__).'/def.php');
	include_once(dirname(__FILE__).'/lib.php');
	include_once(dirname(__FILE__).'/options.php');
	
	// Load WPSC settings
	$options = wpsc_get_options();

	// Install plugin
	register_activation_hook(__FILE__, 'wpsc_install');
	
	// Install plugin
	register_deactivation_hook(__FILE__, 'wpsc_uninstall');	
	
	// Add options page
	add_action('admin_menu', 'wpsc_add_options');
	
	// Performance box
	add_filter('wp_footer', 'wpsc_show_performance_footer');	
	add_action('admin_footer', 'wpsc_show_performance_footer');
	
	// Post, comment etc. updates
	wpsc_handle_user_interactions();
?>