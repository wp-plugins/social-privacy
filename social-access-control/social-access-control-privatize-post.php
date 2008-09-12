<?php

session_start();
/*
Plugin Name: Social Access Control Privatize/Unprivatized Posts
Plugin URI: http://multinc.com
Description: This is an advanced feature of Social Access Control to privatize all protected posts.
Version: 1.1
Author: Justin at Multinc
Author URI: http://multinc.com
*/

load_plugin_textdomain('social-access-control-privatize-post', $path='wp-content/plugins/social-access-control');

// --------------------------------------------------------------------

class social_access_control_privatize_post {

	function get_all_posts() {
		global $wpdb;
		
		$sql = "SELECT * FROM $wpdb->posts WHERE post_type='post'";
		$posts  = $wpdb->get_results($sql);
		return $posts;
	}
	
	function lock_post_status() {
		$posts = social_access_control_privatize_post::get_all_posts();
		foreach ($posts as $key => $post) {
			if ($post->post_status=='publish' && is_callable(array('social_access_control','post_is_public')) && !social_access_control::post_is_public($post->ID)) {
				if (get_post_meta($post->ID, "_previous_status", true))
					delete_post_meta($post->ID, "_previous_status");
					
				// first backup				
				add_post_meta($post->ID, "_previous_status", $post->post_status, true);
				// then set to private
				$upd_post = array();
				$upd_post["ID"] = $post->ID;
				$upd_post["post_status"] = 'private';
				wp_update_post($upd_post);
			}
		}
		update_option("Social_Access_Control_post_counter",count($posts));
	}
	
	function restore_post_status() {
		$posts = social_access_control_privatize_post::get_all_posts();

		foreach ($posts as $key => $post) {
			$previous_status = get_post_meta($post->ID, "_previous_status", true);
			if ($previous_status) {
				$upd_post = array();
				$upd_post["ID"] = $post->ID;
				$upd_post["post_status"] = $previous_status;
				wp_update_post($upd_post);
				delete_post_meta($post->ID, "_previous_status");
			}
		}
	}

	function menu() {
	
		social_access_control_privatize_post::handle_action();
		
		echo "<div class=\"wrap\">";
		echo "<form method=\"post\" action=\"\">\r\n";
		echo "<h2>" . __('Privatize/Unprivatize Protected Posts', 'social-access-control-privatize-post') . "</h2>\r\n";
		echo "<p>Status: <b>".get_option("Social_Access_Control_privatize_status")."</b></p>\r\n";
		echo "<p><input type=\"radio\" name=\"action\" value=\"lock\"";
		echo " />" . __(' Privatize protected posts.', 'social-access-control-privatize-post') . "</p>\r\n";
		echo "<p><input type=\"radio\" name=\"action\" value=\"unlock\"";
		echo " />" . __(' Unprivatize protected posts.', 'social-access-control-privatize-post') . "</p>\r\n";

		echo("<p class=\"submit\" align=\"left\"><input type=\"submit\" id=\"save\" name=\"submit\" value=\"" . __('Do', 'social-access-control-privatize-post') . "\" /></p>");
		echo("</form></div>\r\n");
	}
	
	function handle_action() {
		if ($_POST["action"]) {
			if ($_POST["action"]=='lock') {
				if (is_callable(array('social_access_control','post_is_public'))) {
					social_access_control_privatize_post::lock_post_status();
					$str_action = "All protected posts have been privatized";
					update_option("Social_Access_Control_privatize_status",'Privatized');
				} else {
					$str_action = "Social_Access_Control was not completely activated. Please fix this before privatizing the posts.";
				}
			}
			else {
				social_access_control_privatize_post::restore_post_status();
				$str_action = "All protected posts have been unprivatized";
				update_option("Social_Access_Control_privatize_status",'Unprivatized');
			}
			echo("<div id=\"message\" class=\"updated fade\"><p><strong>" . __("$str_action", 'social-access-control-privatize-post') . "</strong></p></div>\n");
		}
		
		if ((int)get_option("Social_Access_Control_post_counter")!=count(social_access_control_privatize_post::get_all_posts()))
			update_option("Social_Access_Control_privatize_status",'Partially privatized');
	}
	
	function admin_menu() {
		add_management_page(__('Privatize Posts', 'social-access-control-privatize-post'), __("Privatize Posts", 'social-access-control-privatize-post'), 9, __FILE__, array('social_access_control_privatize_post', 'menu'));
	}
	
}

if (!get_option("Social_Access_Control_privatize_status"))
	update_option("Social_Access_Control_privatize_status",'Unprivatized');
if (!get_option("Social_Access_Control_post_counter"))
	update_option("Social_Access_Control_post_counter",count(social_access_control_privatize_post::get_all_posts()));
	
add_action('admin_menu', array('social_access_control_privatize_post', 'admin_menu'), 10001);

?>
