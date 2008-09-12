<?php

/*
Plugin Name: Access Expiration
Plugin URI: http://multinc.com
Description: The Access Expiration plugin is another brand-new plugin that adds another level of access control to posts
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

load_plugin_textdomain('access-expiration', PLUGINDIR . '/' . dirname(plugin_basename(__FILE__)));

define('ACCESS_EXPIRATION_VER','1.0');

$access_expiration = new access_expiration();
$access_expiration->init();

class access_expiration {
	// Implementation of post expiration start from here
	
	function filter_content($text) {
		global $post, $current_user;
		$user_id = $current_user->ID;
		
		// if there is no expiration setting for this post
		if (!$this->post_has_expiration_setting($post->ID))
			return $text;

		//TODO: enable this for production
		//if this is a power user, no expiration applied
		if (user_can_edit_post($user_id, $post->ID))
			return $text;

		if (is_callable(array('unique_url_authentication','get_user_id_from_authentication'))) {
			$personalized_url_user = unique_url_authentication::get_user_id_from_authentication();
			// when anonymous but have personalized URL, get user from it
			if ($user_id==0 && $personalized_url_user!=0)
				$user_id = $personalized_url_user;
		}

		$post_view = $this->update_or_create_post_view($post->ID, $user_id);
		$post_view_id = $post_view->ID;

		// expire the post if it's expired
		$this->do_post_view_expire($post_view);
		$request_url = $_SERVER['REQUEST_URI'];
		if ($this->is_post_view_expired($post_view)==true) {
			$t = "Your permission to view this post has expired.  You may request renewed access from the author.";
			$t .= "<br /><a href='#' onclick=\"request_new_ticket($post_view_id, '$request_url');\">Request renewed access</a>.";
			$text=$t;
		}
		
		return $text;
	}

	function do_access_request_after_login() {
		global $current_user;
		
		if ($current_user->ID==0)
			return;
			
		$request_url = $_SERVER['REQUEST_URI'];
			
		if (isset($_GET["renewal"]) && isset($_GET["post_view_id"]))
		{
			?>
			<script type="text/javascript">
			//<![CDATA[
				request_new_ticket(<?php echo $_GET["post_view_id"] ?>, '<?php echo $request_url ?>');
			//]]>
			</script>
			<?php
		}
	}
	
	function add_post_view_table() {
		global $wpdb;
		
		$sql = "CREATE TABLE ".$wpdb->prefix."post_views (
			ID int(11) NOT NULL auto_increment,
			post_id int(11) NOT NULL,
			user_id int(11) NOT NULL,
			viewed_on varchar(20),
			counter int(11) default 1,
			status varchar(20) default 'active',
			PRIMARY KEY (id) )";
		$wpdb->query($sql);
	}
	
	//get view history of a user on a post
	function get_post_view($postid, $userid) {
		global $wpdb;
		
		$sql = "SELECT * FROM ".$wpdb->prefix."post_views WHERE post_id=$postid AND user_id=$userid";
		$result = $wpdb->get_row($sql);
	
		return $result;
	}
	
	function get_post_view_from_id($post_view_id) {
		global $wpdb;
		
		$sql = "SELECT * FROM ".$wpdb->prefix."post_views WHERE ID=$post_view_id";
		$result = $wpdb->get_row($sql);
	
		return $result;
	}
	
	//update view history of a user on a post
	//if there is no data before, create new
	function update_or_create_post_view($postid, $userid) {
		global $wpdb;
		
		$timestamp = date('Y-m-d G:i:s');
		
		$pw = $this->get_post_view($postid, $userid);
		
		// if first time view, create new row
		if (!$pw) {
			$sql = "INSERT INTO ".$wpdb->prefix."post_views (post_id, user_id, viewed_on) VALUES ('$postid','$userid','$timestamp')";
			$wpdb->query($sql);
			$_SESSION["user_id_$userid_post_id_$postid"] ='viewed';
		}
		// otherwise, update counter when needed
		else {
			if (!isset($_SESSION["user_id_$userid_post_id_$postid"])) {
				// update viewed_on when it's empty
				// happen when this post_view is renewed
				$viewed_on = "";
				if (empty($pw->viewed_on))
					$viewed_on = " ,viewed_on='$timestamp'";
				$sql = "UPDATE ".$wpdb->prefix."post_views set counter=counter+1".$viewed_on." WHERE ID=".$pw->ID;
				$wpdb->query($sql);
				$_SESSION["user_id_$userid_post_id_$postid"] ='viewed';
			}
		}
		
		$pw = $this->get_post_view($postid, $userid);
		
		return $pw;
	}
	
	function post_has_expiration_setting($postid) {
		if (!get_option("post_expiration_".$post_view_obj->post_id."_override")) {
			// get the list of expiration counters and days from different cats
			$post_categories = wp_get_post_cats(1, $postid);
			foreach ($post_categories as $catid) {
				if (get_option("cat_expiration_".$catid."_on_counter") || get_option("cat_expiration_".$catid."_on_time")) 
					return true;
			}
		}
		// check for individual post expiration setting (setting in post-new/post-edit screen
		if (get_option("post_expiration_".$postid."_on_counter") || get_option("post_expiration_".$postid."_on_time"))
			return true;
		return false;
	}
	
	function do_post_view_expire(&$post_view_obj) {
		global $wpdb;
	
		if (!$post_view_obj)
			die('Post view object is NULL');
		
		$target_counters = array();
		$target_times = array();

		// check for the post expiration overwriting setting
		if (!get_option("post_expiration_".$post_view_obj->post_id."_override")) {
			$post_categories = wp_get_post_cats(1, $post_view_obj->post_id);
			foreach ($post_categories as $catid) {
				// get the list of expiration counters and days from different cats
				if (get_option("cat_expiration_".$catid."_on_counter")) 
					$target_counters[] = (int)(get_option("cat_expiration_".$catid."_on_counter_number"));
				if (get_option("cat_expiration_".$catid."_on_time")) 
					$target_times[] = (((int)get_option("cat_expiration_".$catid."_on_time_in_day"))*24) + (((int)get_option("cat_expiration_".$catid."_on_time_in_hour")));
			}
		}

		// here I check for individual post expiration setting (setting in post-new/post-edit screen
		if (get_option("post_expiration_".$post_view_obj->post_id."_on_counter"))
			$target_counters[] = (int)get_option("post_expiration_".$post_view_obj->post_id."_on_counter_number");
		if (get_option("post_expiration_".$post_view_obj->post_id."_on_time"))
			$target_times[] = (((int)get_option("post_expiration_".$post_view_obj->post_id."_on_time_in_day"))*24) + (((int)get_option("post_expiration_".$post_view_obj->post_id."_on_time_in_hour")));

		$expired = false;
				
		if (count($target_counters)!=0) {
			// sort get the smallest counters
			if ((int)$post_view_obj->counter >= min($target_counters))
				$expired = true;
		}
		
		if (count($target_times)!=0) {
			// sort get the shortest days in length
			$start_time = strtotime($post_view_obj->viewed_on);
			$elapsed_time = $start_time + (min($target_times)*60*60);
			if ($elapsed_time <= time())
				$expired = true;
		}
		
		$update = false;
		$status = '';
		// this check is to make sure if it's already expired, we don't update the db again
		if ($expired==true && $post_view_obj->status!='expired') {
			$update = true;
			$status = 'expired';
		}
		// this check is in case we change the expiration type (eg. from counter to time) then it expired for this type but not for other types
		// then we have to reset the status
		// Besides, also apply when we extend the expiration period (eg. increase the counter or increase the time)
		if ($expired==false && $post_view_obj->status=='expired') {
			$update = true;
			$status = 'active';
		}
	
		if ($update) {
			$sql = "UPDATE ".$wpdb->prefix."post_views set status='$status' WHERE ID=".$post_view_obj->ID;
			$post_view_obj->status = $status;
			$wpdb->query($sql);
		}
	}
	
	
	function is_post_view_expired($post_view_obj) {
		if (!$post_view_obj)
			die('Post view object is NULL');
	
		return (($post_view_obj && $post_view_obj->status=='expired') ? true:false);
	}
	
	function reset_all_user_record_on_cat($catid) {
		global $wpdb;
		$posts = get_posts("category=$catid");
		$post_ids = array();
		foreach ($posts as $post) {
			$post_ids[] = $post->ID;
		}
		If (count($post_ids)!=0) {
			$sql = "UPDATE ".$wpdb->prefix."post_views set counter=0, viewed_on=NULL, status='active' WHERE post_id IN (".implode(",",$post_ids).")";
			$wpdb->query($sql);
		}
	}
	
	function reset_all_user_record_on_post($postid) {
		global $wpdb;
		$sql = "UPDATE ".$wpdb->prefix."post_views set counter=0, viewed_on=NULL, status='active' WHERE post_id=".$postid;
		$wpdb->query($sql);
	}

	function access_expiration_menu() {
		global $wpdb;
		
		access_expiration::handle_action();

		$all_cats = get_categories(array('hide_empty' => false));
		
		echo "<div class=\"wrap\">";
		echo "<form method=\"post\" action=\"\">\r\n";
		echo "<h2>" . __('Renewal Settings', 'access-expiration') . "</h2>\r\n";
		echo "<p><input type=\"checkbox\" name=\"cat_expiration_email_notification\" value=\"yes\"";
			if (get_option("cat_expiration_email_notification"))
			 echo " checked=\"checked\"";
		echo " />" . __(' Notify author of pending access requests and notify requester of approvals by email.', 'access-expiration') . "</p>\r\n";

		echo "<h2>" . __('Category Settings', 'access-expiration') . "</h2>\r\n";
		foreach ($all_cats as $cat) {
			$catid = $cat->term_id;
			echo("<p>Category: <b>".$cat->name."</b><br />\r\n");
			echo("<input type=\"checkbox\" name=\"cat_expiration_".$catid."_type"."[]"."\" value=\"counter\"");
			if (get_option("cat_expiration_".$catid."_on_counter"))
					echo(" checked=\"checked\" ");
			echo(" />".__(' Posts belonging to this category will expire after ', 'access-expiration'));
			echo('<input type="text" name="cat_expiration_'.$catid.'_on_counter_number" size=5 value="'.get_option("cat_expiration_".$catid."_on_counter_number")."\" style='text-align: right;'> ". __(' view(s) ', 'access-expiration'));
			echo("<br />\r\n");
			echo("<input type=\"checkbox\" name=\"cat_expiration_".$catid."_type"."[]"."\" value=\"time\"");
			if (get_option("cat_expiration_".$catid."_on_time"))
					echo(" checked=\"checked\" ");
			echo(" />".__(' Posts belonging to this category will expire after ', 'access-expiration'));
			echo('<input type="text" name="cat_expiration_'.$catid.'_on_time_in_day" size=5 value="'.get_option("cat_expiration_".$catid."_on_time_in_day")."\" style='text-align: right;'> ". __(' day(s) ', 'access-expiration'));
			echo('<input type="text" name="cat_expiration_'.$catid.'_on_time_in_hour" size=5 value="'.get_option("cat_expiration_".$catid."_on_time_in_hour")."\" style='text-align: right;'> ". __(' hour(s) ', 'access-expiration'));
			echo("<br />\r\n");
			echo("<hr style=\"border: 0; color: #DDDDDD; background-color: #DDDDDD; height: 1px;\"/>\r\n");
			echo("<input type=\"checkbox\" name=\"cat_expiration_".$catid."_reset\" value=\"yes\" style='font-size: x-small'");
			echo(" />".__(' Clicking the "Update" button will do a one-time reset of all user records (counter and timer) for this category', 'access-expiration'));
			echo("</p>\r\n");
			echo("<hr />\r\n");
		}
		echo("<p class=\"submit\" align=\"left\"><input type=\"submit\" id=\"save\" name=\"submit\" value=\"" . __('Update', 'access-expiration') . "\" /></p>");
		echo("</form></div>\r\n");
	}
	
	function handle_action() {
		global $_POST;

		if (strpos($_POST['submit'], __('Update', 'access-expiration')) !== false) {
			// General option setting
			update_option("cat_expiration_opt",$_POST['cat_expiration_opt']);

			// email notification
			if (isset($_POST["cat_expiration_email_notification"]))
				update_option("cat_expiration_email_notification",true);
			else
				update_option("cat_expiration_email_notification",false);
			
			// category setttings
			$all_cats = get_categories(array('hide_empty' => false));
			
			foreach ($all_cats as $cat) {
				$catid = $cat->term_id;
				if (isset($_POST["cat_expiration_".$catid."_type"])) {
					$expiration_types = $_POST["cat_expiration_".$catid."_type"];
					$target_day = intval($_POST['cat_expiration_'.$catid.'_on_time_in_day']);
					$target_hour = intval($_POST['cat_expiration_'.$catid.'_on_time_in_hour']);
					$target_counter = intval($_POST['cat_expiration_'.$catid.'_on_counter_number']);
					
					$target_day = ($target_day>=0) ? $target_day:$this->DEFAULT_TARGET_TIME_IN_DAY;
					$target_hour = ($target_hour>=0) ? $target_hour:$this->DEFAULT_TARGET_TIME_IN_HOUR;
					$target_counter = ($target_counter>0) ? $target_counter:$this->DEFAULT_TARGET_COUNTER;
					
					if ($target_day==0 && $target_hour==0) {
						$target_day = $this->DEFAULT_TARGET_TIME_IN_DAY;
						$target_hour = $this->DEFAULT_TARGET_TIME_IN_HOUR;
					}

					if (in_array('counter', $expiration_types)) {
						update_option("cat_expiration_".$catid."_on_counter",true);
						update_option('cat_expiration_'.$catid.'_on_counter_number', $target_counter);
					}
					else
						update_option("cat_expiration_".$catid."_on_counter",false);
						
					if (in_array('time', $expiration_types)) {
						update_option("cat_expiration_".$catid."_on_time",true);
						update_option('cat_expiration_'.$catid.'_on_time_in_day', $target_day);
						update_option('cat_expiration_'.$catid.'_on_time_in_hour', $target_hour);
					}
					else
						update_option("cat_expiration_".$catid."_on_time",false);
						
					// reset user record
					if (isset($_POST["cat_expiration_".$catid."_reset"]))
						$this->reset_all_user_record_on_cat($catid);
					
				} else {
					update_option("cat_expiration_".$catid."_on_counter",false);
					update_option("cat_expiration_".$catid."_on_time",false);
				}
			}
			
			echo("<div id=\"message\" class=\"updated fade\"><p><strong>" . __('Expiration setting updated.', 'access-expiration') . "</strong></p></div>\n");
		}
	}
	
	// Implementation of renewal
	
	function add_post_access_request_table() {
		global $wpdb;
		
		$sql = "CREATE TABLE ".$wpdb->prefix."post_access_requests (
			ID int(11) NOT NULL auto_increment,
			post_view_id int(11) NOT NULL,
			requested_on TIMESTAMP NOT NULL,
			counter int(11) default 1,
			status varchar(20) default 'requesting',
			PRIMARY KEY (id) )";
		$wpdb->query($sql);
	}
	
	function get_post_access_request($post_view_id) {
		global $wpdb;
		
		$sql = "SELECT * FROM ".$wpdb->prefix."post_access_requests WHERE post_view_id=$post_view_id";
		$result = $wpdb->get_row($sql);
	
		return $result;
	}
	
	function update_or_create_post_access_request($post_view_id) {
		global $wpdb;
		
		$timestamp = date('Y-m-d G:i:s');
		
		$par = $this->get_post_access_request($post_view_id);
		
		// if first time request, create new row
		if (!$par) {
			$sql = "INSERT INTO ".$wpdb->prefix."post_access_requests (post_view_id, requested_on) VALUES ('$post_view_id','$timestamp')";
			$wpdb->query($sql);
		}
		// otherwise, update counter
		else {
			$sql = "UPDATE ".$wpdb->prefix."post_access_requests set counter=counter+1, status='requesting' WHERE ID=".$par->ID;
			$wpdb->query($sql);
		}
		
		$par = $this->get_post_access_request($post_view_id);
		
		return $par;
	}
	
	// this is called by AJAX
	// to create a new request
	function request_post_access($post_view_id, $request_url) {
		global $current_user;

		// TODO: enable this condition in production		
		// if anonymous but use personalize URL, force them to login
		if ($current_user->ID==0 && strpos($request_url, 'au=')) {
			if (strpos($request_url, '?'))
				$redirect = "&renewal=1&post_view_id=".$post_view_id;
			else
				$redirect = "?renewal=1&post_view_id=".$post_view_id;
				
			$home = get_option("home");

			echo("window.location = '".trailingslashit($home)."wp-login.php?redirect_to=".urlencode($request_url.$redirect)."'");
			return;
		}
		
		// if the logged in user is not the user of post_view, don't let them request
		$post_view = $this->get_post_view_from_id($post_view_id);
		if ($current_user->ID!=$post_view->user_id) {
			echo("alert('You are not allowed to request access for this post');");
			return;
		}
		
		$par = $this->get_post_access_request($post_view_id);
		// only send mail when this is the first request, this is to avoid "SPAM request"
		if (!$par || ($par && $par->status=='resolved'))
			if (get_option("cat_expiration_email_notification"))
				$this->post_access_request_mail($post_view_id);
		
		$par = $this->update_or_create_post_access_request($post_view_id);
		if ($par && $par->status=='requesting')
			echo("alert('Your access request has been sent.');");
		else
			echo("alert('There was an error and your request access was not sent.  Please try again later.');");
	}
	
	// send email to author for request
	function post_access_request_mail($post_view_id) {
		$post_view = $this->get_post_view_from_id($post_view_id);
		
		$post = get_post($post_view->post_id);
		$author = get_userdata($post->post_author);
		$author_email = $author->user_email;

		$viewer = get_userdata($post_view->user_id);
		// In case the request from anonymouos users (happend when the post is a public post)
		$viewer_user_login = ($post_view->user_id!=0) ? ($viewer->user_login) : ("Anonymous User");
		$viewer_email = ($post_view->user_id!=0) ? $viewer->user_email : $author_email;

		$from = "From: \"".$viewer_user_login."\" <$viewer_email>";
		$reply_to = "Reply-To: \"".$viewer_user_login."\" <$viewer_email>";
		$subject 		 = sprintf( __('Access request on your post'));
		$message_headers = "MIME-Version: 1.0\n". "$from\n"."Content-Type: text/plain; charset=\"" .get_option('blog_charset')."\"\n";
		$message_headers .= $reply_to;
		
		$notify_message .= $viewer_user_login." has sent you a request for new ticket on\r\n";
		$notify_message  = $post->post_title."\r\n";
	
		@wp_mail($author_email, $subject, $notify_message, $message_headers);
	}
	
	// send email to 'requester' to notify renewal
	function renewed_notification_mail($post_view_id) {
		$post_view = $this->get_post_view_from_id($post_view_id);

		// if anonymous user, we don't send notification 
		if ($post_view->user_id==0)
			return;

		$viewer = get_userdata($post_view->user_id);
		$viewer_email = $viewer->user_email;
		
		$post = get_post($post_view->post_id);
		$author = get_userdata($post->post_author);
		$author_email = $author->user_email;
		
		$from = "From: \"".$author->user_login."\" <$author_email>";
		$reply_to = "Reply-To: \"".$author->user_login."\" <$author_email>";
		$subject 		 = sprintf( __('Request accepted'));
		$message_headers = "MIME-Version: 1.0\n". "$from\n"."Content-Type: text/plain; charset=\"" .get_option('blog_charset')."\"\n";
		$message_headers .= $reply_to;
		
		$notify_message .= "Your permission on viewing the post\r\n";
		$notify_message  = $post->post_title."\r\n";
		$notify_message .= "has been renewed\r\n";
	
		@wp_mail($viewer_email, $subject, $notify_message, $message_headers);
	}
	
	// do the renew
	function rewnew_post_access($post_view_id) {
		global $wpdb;
		
		//$timestamp = date('Y-m-d G:i:s');
		
		$sql = "UPDATE ".$wpdb->prefix."post_views set counter=0, viewed_on=NULL, status='active' WHERE ID=".$post_view_id;
		$wpdb->query($sql);
		
		$sql = "UPDATE ".$wpdb->prefix."post_access_requests set status='resolved' WHERE post_view_id=".$post_view_id;
		$wpdb->query($sql);
		
		if (get_option("cat_expiration_email_notification"))
			$this->renewed_notification_mail($post_view_id);
	}
	
	// get requests of specific author
	function get_requests_for_author($author_id) {
		global $wpdb;
		$sql = "SELECT * FROM ".$wpdb->prefix."post_access_requests WHERE status <> 'resolved'";
		$results = $wpdb->get_results($sql);
		
		foreach ($results as $row => $value) {
			$post_view = $this->get_post_view_from_id($value->post_view_id);
			$post = get_post($post_view->post_id);
			$autherid = $post->post_author;
			if ((int)$autherid!=(int)$author_id)
				unset($results[$row]);
		}
		return $results;
	}
	
	// display the request list in author profile
	function display_request_list() {
		global $wpdb, $current_user;
	
		$all_requests = $this->get_requests_for_author($current_user->ID);
	
		if 	(!$all_requests) {
			echo("There is no request");
			return;
		}
		
		echo("<table width=\"100%\" cellspacing=\"2\" cellpadding=\"5\" class=\"editform\">\r\n");
		echo("<tr valign=\"top\">\r\n");
		echo("<th width=\"5%\" align=\"left\"></th>\r\n");
		echo("<th width=\"50%\" align=\"left\">Post title</th>\r\n");
		echo("<th width=\"15%\" align=\"left\">Requested by</th>\r\n");
		echo("<th width=\"25%\" align=\"left\">Requested on</th>\r\n");
		echo("<th width=\"5%\" align=\"left\">Counter</th>\r\n");
		echo("</tr>\r\n");
		foreach ($all_requests as $request) {
			$post_view = $this->get_post_view_from_id($request->post_view_id);
			$post = get_post($post_view->post_id);
			$viewer = get_userdata($post_view->user_id);
	
			echo("<tr valign=\"top\">\r\n");
			echo("<td>\r\n");
			echo("<input type=\"checkbox\" name=\"requests[]\" value=\"" . $request->post_view_id . "\"");
			echo(" checked=\"checked\" ");
			echo(" />\r\n ");
			echo("</td>\r\n");
			echo("<td>".$post->post_title."</td>\r\n");
			echo("<td>".$viewer->user_login."</td>\r\n");
			echo("<td>".$request->requested_on."</td>\r\n");
			echo("<td>".$request->counter."</td>\r\n");
			echo("</tr>\r\n");
		}
		echo("</table>\r\n");
	}
	
	// the renewal menu
	function renewal_menu() {
		global $user_ID, $wp_version, $wpmu_version;
	
		// was anything POSTed?
		if (isset($_POST['requests'])) {
			$requests = $_POST['requests'];
			foreach ($requests as $request) {
				$this->rewnew_post_access((int)$request);
			}
			echo("<div id=\"message\" class=\"updated fade\"><p><strong>" . __('Permission updated.', 'subscribe2') . "</strong></p></div>\n");
		}
		// show our form
		echo("<div class=\"wrap\">");
		echo("<h2>" . __('Renewal requests', 'category-access') . "</h2>\r\n");
		echo("<form method=\"post\" action=\"\">");
	
		$this->display_request_list();
		echo("<p class=\"submit\"><input type=\"submit\" name=\"submit\" value=\"" . __("Renew", 'category-access') . " &raquo;\" /></p>");
		echo("</form></div>\r\n");
	
		include(ABSPATH . 'wp-admin/admin-footer.php');
	}
	
	function renew_post_access_js_header()
	{
		// use JavaScript SACK library for Ajax
		wp_print_scripts( array( 'sack' ));
	
		// Define JavaScript function
		?>
		<script type="text/javascript">
		//<![CDATA[
		function request_new_ticket(post_view_id, request_url)
		{
			// function body defined below
			var mysack = new sack("<?php bloginfo( 'wpurl' ); ?>/wp-content/plugins/access-expiration/post_access_renewal.php");
		
			mysack.execute = 1;
			mysack.method = 'POST';
			mysack.setVar( "post_view_id", post_view_id);
			mysack.setVar( "request_url", request_url);
			mysack.onError = function() { alert('Ajax error in sending' )};
			mysack.runAJAX();
		
			return true;
		
		} // end of JavaScript function
		//]]>
		</script>
		<?php
	}
	
	// END OF renewal implementation

	function admin_menu() {
		global $current_user;
		$requests = $this->get_requests_for_author($current_user->ID);
		
		add_menu_page(__('Access Requests', 'category-access'), __("Access Requests <span id='awaiting-mod' class='count-".count($requests)."'><span class='comment-count'>".count($requests)."</span></span>", 'category-access'), 2, __FILE__, array(&$this, 'renewal_menu'));
		
		add_options_page(__('Access Expiration', 'access-expiration'), __('Access Expiration', 'access-expiration'), 9, __FILE__, array(&$this, 'access_expiration_menu'));
		

	}

	function db_setup() {
		$this->add_post_view_table();
		$this->add_post_access_request_table();
	}
	
	function edit_post() {
		global $post;
		
		echo '<div id="expirationdiv" class="postbox if-js-closed">';
		echo '<h3>Expiration</h3>';
		echo '<div class="inside">';

		echo("<input type=\"checkbox\" name=\"post_expiration_type"."[]"."\" value=\"counter\"");
		if (get_option("post_expiration_".$post->ID."_on_counter"))
				echo(" checked=\"checked\" ");
		echo(" />".__(' Post will expire after ', 'access-expiration'));
		echo('<input type="text" name="post_expiration_on_counter_number" size=5 value="');
		echo(($post->ID!=0) ? get_option("post_expiration_".$post->ID."_on_counter_number"):$this->DEFAULT_TARGET_COUNTER);
		echo("\" style='text-align: right;'>". __(' view(s) ', 'access-expiration'));
		echo("<br />\r\n");
		echo("<input type=\"checkbox\" name=\"post_expiration_type"."[]"."\" value=\"time\"");
		if (($post->ID!=0) && get_option("post_expiration_".$post->ID."_on_time"))
			echo(" checked=\"checked\" ");
		echo(" />".__(' Post will expire after ', 'access-expiration'));
		echo('<input type="text" name="post_expiration_on_time_in_day" size=5 value="');
		echo(($post->ID!=0) ? get_option("post_expiration_".$post->ID."_on_time_in_day"):$this->DEFAULT_TARGET_TIME_IN_DAY);
		echo("\" style='text-align: right;'> ". __(' day(s) ', 'access-expiration'));
		echo('<input type="text" name="post_expiration_on_time_in_hour" size=5 value="');
		echo(($post->ID!=0) ? get_option("post_expiration_".$post->ID."_on_time_in_hour"):$this->DEFAULT_TARGET_TIME_IN_HOUR);
		echo("\" style='text-align: right;'> ". __(' hour(s) ', 'access-expiration'));
		echo("<br />\r\n");
		echo("<input type=\"checkbox\" name=\"post_expiration_type"."[]"."\" value=\"override\"");
		if (($post->ID!=0) && get_option("post_expiration_".$post->ID."_override"))
			echo(" checked=\"checked\" ");
		echo(" />".__(' Override the Category Expiration Setting', 'access-expiration'));
		echo("<br />\r\n");
		echo("<hr style=\"border: 0; color: #DDDDDD; background-color: #DDDDDD; height: 1px;\"/>\r\n");
		echo("<input type=\"checkbox\" name=\"post_expiration_type"."[]"."\" value=\"reset\"");
		echo(" />".__(' Do a one-time reset of all user records (counter and timer) for this post', 'access-expiration'));
		echo "</div>\r\n";
		echo "</div>\r\n";

	}
	
	function save_post($postid) {
		if (isset($_POST["post_expiration_type"])) {
			$target_counter = intval($_POST["post_expiration_on_counter_number"]);
			$target_day = intval($_POST["post_expiration_on_time_in_day"]);
			$target_hour = intval($_POST["post_expiration_on_time_in_hour"]);

			$target_day = ($target_day>=0) ? $target_day:$this->DEFAULT_TARGET_TIME_IN_DAY;
			$target_hour = ($target_hour>=0) ? $target_hour:$this->DEFAULT_TARGET_TIME_IN_HOUR;
			
			if ($target_day==0 && $target_hour==0) {
				$target_day = $this->DEFAULT_TARGET_TIME_IN_DAY;
				$target_hour = $this->DEFAULT_TARGET_TIME_IN_HOUR;
			}
			
			$target_counter = ($target_counter>0) ? $target_counter:$this->DEFAULT_TARGET_COUNTER;

			if (in_array('counter', $_POST["post_expiration_type"])) {
				update_option("post_expiration_".$postid."_on_counter", true);
				update_option("post_expiration_".$postid."_on_counter_number", $target_counter);
			} else
				update_option("post_expiration_".$postid."_on_counter", false);

			if (in_array('time', $_POST["post_expiration_type"])) {
				update_option("post_expiration_".$postid."_on_time",true);
				update_option("post_expiration_".$postid."_on_time_in_day", $target_day);
				update_option("post_expiration_".$postid."_on_time_in_hour", $target_hour);
			} else
				update_option("post_expiration_".$postid."_on_time",false);
				
			if (in_array('override', $_POST["post_expiration_type"])) {
				update_option("post_expiration_".$postid."_override",true);
			} else
				update_option("post_expiration_".$postid."_override",false);
				
			// reset user records
			if (in_array('reset', $_POST["post_expiration_type"]))
				$this->reset_all_user_record_on_post($postid);
				
		} else {
			update_option("post_expiration_".$postid."_on_counter", false);
			update_option("post_expiration_".$postid."_on_time",false);
			update_option("post_expiration_".$postid."_override",false);
		}
	}
	
	function hide_text($text) {
		global $current_user, $post;
		$post_view = $this->get_post_view($post->ID, $current_user->ID);
		
		if ($post_view && $this->is_post_view_expired($post_view))
			$text = "";

		return $text;
	}
	
	function init() {
		// set defaul values
		$this->DEFAULT_TARGET_COUNTER = 1;
		$this->DEFAULT_TARGET_TIME_IN_DAY = 1;
		$this->DEFAULT_TARGET_TIME_IN_HOUR = 0;
		
		$option = get_option("cat_expiration_email_notification");
		if (empty($option))
			update_option("cat_expiration_email_notification", true);
		
		register_activation_hook(__FILE__,array(&$this,'db_setup'));
				
		// I used 9999 to make these executation is done before Social Access Control (if exists) whose priority number is 10000
		// reason: Social Access Control should be the last script to grant access
		add_action('wp_head', array(&$this,'renew_post_access_js_header'), 9999);
		add_action('admin_menu', array(&$this, 'admin_menu'), 9999);
		add_filter('the_content', array(&$this,'filter_content'), 9999);
		add_action('edit_form_advanced', array(&$this,'edit_post'), 9999);
		add_action('wp_insert_post', array(&$this,'save_post'), 9999);
		add_filter('comment_text', array(&$this,'hide_text'), 9999);
		add_filter('comment_author', array(&$this,'hide_text'), 9999);
		add_filter('comment_email', array(&$this,'hide_text'), 9999);
		add_filter('comment_excerpt', array(&$this,'hide_text'), 9999);
		add_filter('comment_url', array(&$this,'hide_text'), 9999);
		add_filter('the_excerpt', array(&$this,'hide_text'), 9999);
		add_action('wp_footer', array(&$this,'do_access_request_after_login'), 9999);
	}
}

?>
