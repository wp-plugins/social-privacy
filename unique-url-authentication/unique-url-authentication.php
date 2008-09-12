<?php
/*
Plugin Name: Unique URL Authentication
Plugin URI: http://multinc.com
Description: The Unique URL Authentication plugin is a new plugin that works with the Social Access Control plugin to add access control to syndicated feeds. Since popular web feed readers such as Google Reader do not support private feeds that require a login and a password, a common solution is to give each registered user their own unique and unguessable feed URL.  With this plugin installed, each user will have his or her own feed and will receive a listing of only the feed items that he or she has access to. Also, the links contained in each feed item and each email notification that point back to the blog website can also benefit from password-less authentication.  This allows sensitive posts to remain on the website rather than sent out, while giving authorized users single-click convenience.
Version: 1.1
Author: Justin at Multinc
Author URI: http://multinc.com
*/

/*  Copyright 2008  Justin at Multinc

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
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/


load_plugin_textdomain('unique-url-authentication', PLUGINDIR . '/' . dirname(plugin_basename(__FILE__)));
define('UNIQUE_URL_AUTHENTICATION_VER','1.0');

$unique_url_authentication = new unique_url_authentication();
$unique_url_authentication->init();

class unique_url_authentication {
	
	function generate_authentication_code($user, $password) {
		global $wpdb, $wp_version;
		
		srand ((double)microtime()*1000000);
		
		$current_user = get_userdatabylogin($user);
	
		$sql = 'SELECT authentication FROM '.$wpdb->prefix.'users WHERE ID='.$current_user->ID;
		$authentication = $wpdb->get_var($sql);
	
		$secret_key = '';
		if (strpos($wp_version, '2.6'))
			$secret_key = SECRET_KEY;
		elseif (strpos($wp_version, '2.5'))
			$secret_key = AUTH_KEY;
		else
			$secret_key = "secret";
		
		if ($authentication == NULL || $authentication=='') {
			$authentication = sha1($current_user->ID.date('Y-m-d G:i:s').$secret_key.rand(0,10000));
			$sql = 'UPDATE '.$wpdb->prefix.'users set authentication=\''.$authentication.'\' WHERE ID='.$current_user->ID;
			$wpdb->query($sql);
		}
	}
	
	function get_user_id_from_authentication() {
		global $wpdb, $post;
		
		$sql = 'SELECT ID FROM '.$wpdb->prefix.'users WHERE authentication=\''.get_query_var('au').'\'';
		$result = $wpdb->get_var($sql);
		if ($result==NULL || $result=='')
			return 0;

		// we don't want power user to get authenticated here, too dangerous
		$user = new WP_User($result);
		if ($user->has_cap('manage_categories'))
			return 0;
			
		// if in the loop, check for per post setting
		if ($post && unique_url_authentication::category_or_post_isset_no_auto_authentication($post->ID))
			return 0;

		return (int)$result;
	}
	
	function add_authentication_column() {
		global $wpdb;
		$sql = 'ALTER TABLE ' . $wpdb->prefix . 'users ADD authentication char(255) default \'\' NOT NULL';
		$wpdb->query($sql);
	}
	
	function del_authentication_column() {
		global $wpdb;
		$sql = 'ALTER TABLE ' . $wpdb->prefix . 'users DROP COLUMN authentication';
		$wpdb->query($sql);
	}
	
	//add authentication code to links
	function url_authentication_append_link($link, $normal_link = false) {
		global $current_user, $wpdb;
		global $wp_rewrite;
		$user_id = $current_user->ID;
		
		if ($user_id == 0) 	///anonymous user, don't add authentication code to link
			return $link;
		else //registered user, add authentication code to link
		{
			$sql = 'SELECT authentication FROM '.$wpdb->prefix.'users WHERE ID='.$user_id;
			$authentication = $wpdb->get_var($sql);
			if ($authentication==NULL || $authentication == '')
				return $link;
	
			// No need?			
			//set_query_var('authentication',$authentication);
			
			$query_string = strpos($link, '?');
			
			if ($query_string) {
				if ($normal_link==false) {
					return $link . "&amp;au=$authentication";
				} else {
					return $link . "&au=$authentication";
				}
			}
			// otherwise
			return trailingslashit($link)."?au=$authentication";
		}
	}
	
	//setup new query var for authentication code
	function append_query_var($public_query_vars) {
		$public_query_vars[] = 'au';
		return $public_query_vars;
	}
	
	//append to WP login text to notify users
	function modify_loginout_string($link) {
		if ( !is_user_logged_in() )
			$link = str_replace('</a>', '</a><div>Log in to get personalized feed URLs</div>', $link);
		return $link;
	}
	
	function menu() {
		global $wpdb;
		
		$this->handle_menu_post_action();

		$all_cats = get_categories(array('hide_empty' => false));
		
		echo "<div class=\"wrap\">";
		echo "<form method=\"post\" action=\"\">\r\n";
		echo "<h2>" . __('Category Settings', 'unique-url-authentication') . "</h2>\r\n";
		foreach ($all_cats as $cat) {
			$catid = $cat->term_id;
			echo("<p>Category: <b>".$cat->name."</b><br />\r\n");
			echo("<input type=\"checkbox\" name=\"personalized_url_".$catid."_no_auto_authentication\" value=\"yes\"");
			if (get_option("personalized_url_".$catid."_no_auto_authentication"))
					echo(" checked=\"checked\" ");
			echo(" />".__(' Disable auto authentication on this category', 'unique-url-authentication'));
			echo("</p>\r\n");
			echo("<hr />\r\n");
		}
		echo("<p class=\"submit\" align=\"left\"><input type=\"submit\" id=\"save\" name=\"submit\" value=\"" . __('Update', 'unique-url-authentication') . "\" /></p>");
		echo("</form></div>\r\n");
	}
	
	function handle_menu_post_action() {
		if (strpos($_POST['submit'], __('Update', 'unique-url-authentication')) !== false) {
			$all_cats = get_categories(array('hide_empty' => false));
			foreach ($all_cats as $cat) {
				$catid = $cat->term_id;
				if (isset($_POST["personalized_url_".$catid."_no_auto_authentication"]))
					update_option("personalized_url_".$catid."_no_auto_authentication",true);
				else
					update_option("personalized_url_".$catid."_no_auto_authentication",false);
			}
				
			echo("<div id=\"message\" class=\"updated fade\"><p><strong>" . __('Setting updated.', 'unique-url-authentication') . "</strong></p></div>\n");
		}
	}
	
	function add_admin_menu() {
		add_options_page(__('Unique URL Authentication', 'unique-url-authentication'), __('Unique URL Authentication', 'unique-url-authentication'), 9, __FILE__, array(&$this, 'menu'));
	}
	
	
	function edit_post() {
		global $post;
		echo '<div id="uniqueurlauthenticationdiv" class="postbox if-js-closed">';
		echo '<h3>Unique URL Authentication</h3>';
		echo '<div class="inside">';
		echo("<input type=\"checkbox\" name=\"disable_automatic_authentication\" value=\"yes\"");
		if (($post->ID!=0) && get_post_meta($post->ID, '_disable_automatic_authentication', true)=='true')
			echo(" checked=\"checked\" ");
		echo(" />".__(' Disable automatic authentication on this post', 'unique-url-authentication'));
		echo "</div>";
		echo "</div>";
	}
	
	function save_post($postid) {
		if (isset($_POST["disable_automatic_authentication"])) {
			add_post_meta($postid, '_disable_automatic_authentication', 'true', true);
		} else {
			delete_post_meta($postid, '_disable_automatic_authentication');
		}
	}
	
	function category_or_post_isset_no_auto_authentication($postid) {
		$no_auto = false;
		$post_categories = wp_get_post_cats(1, $postid);
		foreach ($post_categories as $catid) {
			if (get_option("personalized_url_".$catid."_no_auto_authentication")) 
			$no_auto = true;
		}
		if (get_post_meta($postid, '_disable_automatic_authentication', true)=='true')
			$no_auto = true;
		return $no_auto;
	}
	
	function db_setup() {
		$this->add_authentication_column();
	}
	
	function init() {
		register_activation_hook(__FILE__,array(&$this,'db_setup'));
		
		add_filter('query_vars', array(&$this,'append_query_var'));
		add_action('wp_authenticate',array(&$this,'generate_authentication_code'), 10, 2);
		add_filter('feed_link', array(&$this,'url_authentication_append_link'), 10, 1);
		add_filter('post_comments_feed_link', array(&$this,'url_authentication_append_link'), 10, 1);
		add_filter('category_feed_link', array(&$this,'url_authentication_append_link'), 10, 1);
		add_filter('loginout', array(&$this,'modify_loginout_string'), 10, 1);
		add_action('edit_form_advanced', array(&$this,'edit_post'));
		add_action('wp_insert_post', array(&$this,'save_post'));
		add_action('admin_menu', array(&$this, 'add_admin_menu'));
	}
	
}

?>
