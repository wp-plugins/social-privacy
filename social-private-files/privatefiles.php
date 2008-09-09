<?php
/*
Plugin Name: Private Files for Social Access Control/Access Expiration
Plugin URI: http://multinc.com
Description: The 3rd-party Private Files plugin tightens the security of private posts by preventing clever users from obtaining unauthorized access to contained images and other attachments. Private Files was enhanced to respect the access permissions of the Social Access Control plugin.
Author: Justin at Multinc
Version: 1.0
Author URI: http://multinc.com
*/

/*
This is developed basing on the original Private Files version 0.34 by James Low
*/

/*
*** NOTE by Justin at Multinc
- Added some code to collaborate with Social Access Control and Access Expiration
- Some changes to the origin was made because it didn't work at my Windows box.
*/

add_action('admin_menu', 'private_menu');
add_filter('404_template', 'private_filter');
//Not used right now
//if('wp-login.php' != $pagenow && 'wp-register.php' != $pagenow) add_action('template_redirect', 'private_redirect');

//private_protect_uploads();
//private_unprotect_uploads();

function private_menu() {
	add_management_page(__('Social Private Files'),
			__('Social Private Files'),
			10, basename(__FILE__), 'private_form');		
}

function private_all($level) {
	return ($level < 0 || $level == '');
}
function private_subscriber($level) {
	return $level == 0;
}
function private_contributor($level) {
	return $level == 1;
}
function private_author($level) {
	return ($level >= 2 && $level <= 4);
}
function private_editor($level) {
	return ($level >= 5 && $level <= 7);
}
function private_admin($level) {
	return ($level >= 8 && $level <= 10);
}

function private_option($level, $name, $select) {
	echo "<option value=\"$level\" " . ($select ? "selected" : "") . ">$name</option>";
}

function private_form() {
	echo '<div class="wrap">';
	echo '<h2>Private Files</h2>';
	$submit = $_POST["submit"];
	if ($submit != '') {
		$level = $_POST["level"];
		update_option("private_files_level", $level);
		//remove because it doesn't do anything
		//and doesn't work with php4
		//try {
			if ($_POST["protect"] != '') {
				private_protect_uploads();
			} elseif ($_POST["unprotect"] != '') {
				private_unprotect_uploads();
			} elseif ($_POST["reprotect"] != '') {
				private_unprotect_uploads();
				private_protect_uploads();
			}
			echo '<div class="updated">Updated.</div>';
		//} catch (Exception $e) {
		//	echo '<div class="error">$e</div>';
		//}
	}
	
	$protected = file_exists(private_upload_htaccess());
	echo '<b>Status - ';
	if ($protected) {
		echo '<font color="green">Protected</font>';
	} else {
		echo '<font color="red">Unprotected</font>';
	}
	echo '</b>';
	$level = get_option("private_files_level");
?>
	<table border="0"><tr><td>
	<form method="post" action="">
	<input type="hidden" name="submit" value="true" />
	User at least: <select name="level">
<?php
		private_option(-1, "All", private_all($level));
		private_option(0, "Subscriber", private_subscriber($level));
		private_option(1, "Contributor", private_contributor($level));
		private_option(2, "Author", private_author($level));
		private_option(5, "Editor", private_editor($level));
		private_option(10, "Admin", private_admin($level));
?>
	</select>
	<br /><input type="submit" name="protect" value="Protect" />

	<input type="submit" name="unprotect" value="Unprotect" />

	<input type="submit" name="reprotect" value="Reprotect" />
	</form>
	</td></tr></table>
	<b>How this plugin works</b>
	<br />1) It requires mod_rewrite/php running in apache, probably on unix/linux, although windows may work.
	<br />2) It requires wordpress to be handling all url requests via a .htaccess in your blog root, and for your uploads to be a subdirectory of the your blog root and you're not using the default permalinks (ie. not http://www.myblog.com/?p=123) For example goto Settings->Permalinks and choose "Day and name"
	<br />3) An <b>additional</b> .htaccess file is placed in your uploads directory  with the following content:
	<blockquote>
	<?php echo str_replace("\n",'<br />',private_htacess()); ?>
	</blockquote>
	<br />4) All requests for files within your upload are direct to a file that doesn't exist
	<br />5) Wordpress handles this as a 404 error
	<br />6) This plugin has a hook which intercepts the 404, and returns the file if the user is logged in.
	<br />7) If you want to force user login please try <a href="http://blog.taragana.com/index.php/archive/angsumans-authenticated-wordpress-plugin-password-protection-for-your-wordpress-blog/">Angsuman's Authenticated WordPress Plugin</a> or <a href="http://jameslow.com/2007/12/02/allow-categories/">Allow Categories</a> to permission your blog.
	<br />8) There's a small chance that the protection detection might be wrong, if so reprotect your files.
	<br />9) If you want to stop using the plugin, unprotect it, or delete the .htaccess file with your uploads directory.
	</div>
<?php
}

function private_upload_path() {
	$path = get_option('upload_path');
	$home = (preg_replace('/\//','\/', private_root()));
	return preg_replace("/$home/","",$path);
}

function private_upload_fullpath() {
	// modified by Justin at Multinc
	// just to make sure I don't duplicate the trailing slash
	return trailingslashit(private_root()).private_upload_path();
/*	Original 0.34 code	
	return private_root() . '/' . private_upload_path();
*/
}

function private_upload_htaccess() {
	return private_upload_fullpath() . '/.htaccess';
}

function private_wordpress_site() {
	$wordpress = get_option('home');
	$start = strpos($wordpress,'/',strpos($wordpress,'/',strpos($wordpress,'/')+1)+1);
	return ($start == '' ? '' : substr($wordpress,$start+1));
}

function private_wordpress_path() {
	$wordpress = get_option('siteurl');
	$start = strpos($wordpress,'/',strpos($wordpress,'/',strpos($wordpress,'/')+1)+1);
	return ($start == '' ? '' : substr($wordpress,$start+1));
}

function private_add_wordpress_path() {
	$wordpress = private_wordpress_path();
	// modified by Justin at Multinc
	// when WP is not located at document root (eg. http://example.com/wp/) both private_wordpress_path & private_wordpress_site return 'wp', so should return '/wp' instead of just '' as the orgin does
	return ($wordpress == '' ? '' : ('/' . $wordpress));
/*  Original 0.34 code
	return ($wordpress == private_wordpress_site() ? '' : ('/' . $wordpress));
*/
}

function private_root() {
	// modified by Justin at Multinc
	// I got many exceptions with the original code
	// I replaced with this solution, the idea should be similar.
	// Also converted Windows path, that uses back-slash (\), to slash (/)
	return preg_replace("/\\\\/","/",ABSPATH);
/*  Original 0.34 code
	$wordpress = private_wordpress_site();
	$cwd = getcwd();
	$pos = strpos($cwd,'/wp-admin');
	if ($pos) {
		return substr($cwd,0,$pos);
	} else {
		$wordpressfull = $cwd;
		return ($wordpress == '' ? $wordpressfull : substr($wordpressfull,0,strpos($wordpressfull,$wordpress)));
	}
*/
}

function private_protect_uploads() {
	$upload = private_upload_fullpath();

	if(!file_exists($upload)) {
		mkdir($upload, 0755);
	}
	$htaccess = private_upload_htaccess();
	if(!file_exists($htaccess)) {
		$file = fopen($htaccess, 'w');
		fwrite($file, private_htacess());
	}
}

function private_htacess() {
	/* Should output something like:
		RewriteEngine On
		RewriteBase /wordpress/wp-content/uploads
		RewriteRule . /wordpress/afilethatshouldnotexist.txt [L]
		Options -Indexes
	*/
	return "RewriteEngine On\n" .
		'RewriteBase ' . private_add_wordpress_path() . '/' . private_upload_path() . "\n" .
		'RewriteRule . ' . private_add_wordpress_path() . '/afilethatshouldnotexist.txt' . "\n" .
		'Options -Indexes';
}

function private_unprotect_uploads() {
	$htaccess = private_upload_htaccess();
	if(file_exists($htaccess)) {
		unlink($htaccess);	
	}
}

//Not used right now
function private_redirect() {
	global $userdata;
	get_currentuserinfo();
	if (!$userdata->user_login) {
		header("HTTP/1.1 302 Moved Temporarily");
		header('Location: ' . get_settings('siteurl') . '/wp-login.php?redirect_to=' . urlencode($_SERVER['REQUEST_URI']));
      		header("Status: 302 Moved Temporarily");
		exit();
	}
}

// Added by Justin at Multinc
// to get attachment that the link belongs to
function get_attachment_from_link($link) {
	global $wpdb;

	// image links may have some extra info (eg. width, height), so get rid of them
	$path =  preg_replace("/-\\d+x\\d+/", "", $link);
	
	$query = "SELECT * FROM {$wpdb->posts} WHERE guid like '%$path'";
	$attachment = $wpdb->get_row($query);
	return $attachment;
}

// Added by Justin at Multinc
// to get the post id that the attachment belongs to
function get_post_from_attachment($attachment) {
	global $wpdb;
	
	$query = "SELECT post_parent FROM {$wpdb->posts} WHERE (post_status = 'attachment' || post_type = 'attachment') AND ID = $attachment->ID";
	$post_id = $wpdb->get_var($query);
	
	return $post_id;
}

function private_filter($type) {
	global $userdata;
	get_currentuserinfo();
	if ($userdata->user_login) {
		$level = get_option("private_files_level");
		if ($userdata->user_level >= $level) {
			$url = $_SERVER['REQUEST_URI'];
			$upload = private_upload_path();
			$pos = strpos($url,$upload);

			if ($pos != '') {
				//TODO: Make sure we don't allow ../ in urls to allow retrieving of other files outside of uploads directory
				$root = private_root();
				// modified by Justin at Multinc
				// fix the problem when WP is not at the document root (eg. /wp/)
				if (private_wordpress_path()!='' && strpos($url, private_wordpress_path())==1)
					$file_path = preg_replace('/'.private_wordpress_path().'\//', '', $url, 1); //remove the WP path if it's already in the $url
				$filename = trailingslashit($root).substr($file_path,1);

				if (file_exists($filename)) {
					// added by Justin at Multinc
					// this is to check Access Expiration and Social Access Control permission
					$attachment = get_attachment_from_link($url);
					if ($attachment)
						$post_id = get_post_from_attachment($attachment);

					if (is_callable(array('social_access_control', 'post_should_be_hidden_to_user'))) {
						$user = new WP_User($userdata->ID);
						// added by Justin at Multinc
						// we let the users of unique_url_authentication pass through if it's enabled
						if (is_callable(array('unique_url_authentication','get_user_id_from_authentication'))) {
							$personalized_url_user = unique_url_authentication::get_user_id_from_authentication();
							if ($user->ID==0 && $personalized_url_user!=0)
								$user = new WP_User($personalized_url_user);
						}

						if (social_access_control::post_should_be_hidden_to_user($post_id, $user))
							return;
					}
					if (class_exists("access_expiration")) {
						global $access_expiration;
						$post_view = $access_expiration->get_post_view($post_id, (int)$userdata->ID);
						// if it expired and not a power user, reject the request
						if ($post_view && $access_expiration->is_post_view_expired($post_view) && !user_can_edit_post($userdata->ID, $post_id))
							return;
					}
					// orgin continues...
					private_file($filename);
					exit;
				} else {
					//file doesn't exist, do nothing, let normal 404 handling continue
				}
			} else {
				//file not in protected dir, let normal 404 handling continue
			}
		} else {
			//user no permission, do nothing, let normal 404 handling continue
		}
	} else {
		//user not logged in, do nothing, let normal 404 handling continue
	}
}

function private_extension($filename) {
	$start = strrpos($filename,'/');
	if ($start == '') {
		//no / found in file name, not in a folder
		$start = 0;
	}
	$justfile = substr($filename,$start);
	$pos = strrpos($justfile,'.');
	return ($pos != '' ? substr($justfile, $pos+1) : '');
}

function private_minetype($filename) {
	global $mimes;
	if (!isset($mimes)) {
		$mimes = private_mimetypes();
	}
	$ext = private_extension($filename);
	$ftype = $mimes[$ext];
	return (isset($ftype) ? $ftype : 'text/plain');
}


function private_file($filename) {
	//This section of code is modified from evDbFiles (http://virtima.pl/evdbfiles)
	$file_time = filemtime($filename);

	$send_304 = false;
	if (php_sapi_name() == 'apache') {
        	// if our web server is apache
		// we get check HTTP
        	// If-Modified-Since header
		// and do not send image
		// if there is a cached version
		$ar = apache_request_headers();
        	if (isset($ar['If-Modified-Since']) && // If-Modified-Since should exists
			($ar['If-Modified-Since'] != '') && // not empty
			(strtotime($ar['If-Modified-Since']) >= $file_time)) { // and grater than file_time
			$send_304 = true;
		}                         
	}

	if ($send_304) {
		// Sending 304 response to browser
		// "Browser, your cached version of image is OK
		// we're not sending anything new to you"
		header('Last-Modified: '.gmdate('D, d M Y H:i:s', $file_time).' GMT', true, 304);
	} else {
		$data = file_get_contents($filename);

		// outputing Last-Modified header
		header('Last-Modified: '.gmdate('D, d M Y H:i:s', $file_time).' GMT', true, 200);

		// Set expiration time +1 year
		// We do not have any photo re-uploading
		// so, browser may cache this photo for quite a long time
		header('Expires: '.gmdate('D, d M Y H:i:s',  $file_time + 86400*365).' GMT', true, 200);
		
		// outputing HTTP headers
		header('Content-Length: '.strlen($data));

		//Not all php setups support this, eg. dreamhost
		//$finfo = finfo_open(FILEINFO_MIME);
		//$ftype = finfo_file($finfo, $filename);
		//finfo_close($finfo);
		//$ftype = mime_content_type($filename);

		$ftype = private_minetype($filename);
		header("Content-type: " . $ftype);

		/*
		//TODO: Figure out if we need to do anything with the below
		$isImage = strpos($ftype,'image/') != '';
		if (!$isImage){
			header('Content-Disposition: attachment; filename="'.$_SERVER['REQUEST_URI'].'"');
			header('Content-Transfer-Encoding: binary');
		}
		*/
    		echo $data;
	}
}

function private_mimetypes() {
	return array ('ez' => 'application/andrew-inset',								
'atom' => 'application/atom+xml',								
'jar' => 'application/java-archive',								
'hqx' => 'application/mac-binhex40',								
'cpt' => 'application/mac-compactpro',								
'mathml' => 'application/mathml+xml',								
'doc' => 'application/msword',								
'bin' => 'application/octet-stream',	'dms' => 'application/octet-stream',	'lha' => 'application/octet-stream',	'lzh' => 'application/octet-stream',	'exe' => 'application/octet-stream',	'class' => 'application/octet-stream',	'so' => 'application/octet-stream',	'dll' => 'application/octet-stream',	'dmg' => 'application/octet-stream',
'oda' => 'application/oda',								
'ogg' => 'application/ogg',								
'pdf' => 'application/pdf',								
'ai' => 'application/postscript',	'eps' => 'application/postscript',	'ps' => 'application/postscript',						
'rar' => 'application/rar',								
'rdf' => 'application/rdf+xml',								
'rtf' => 'application/rtf',								
'smi' => 'application/smil',	'smil' => 'application/smil',							
'gram' => 'application/srgs',								
'grxml' => 'application/srgs+xml',								
'mif' => 'application/vnd.mif',								
'xul' => 'application/vnd.mozilla.xul+xml',								
'xls' => 'application/vnd.ms-excel',								
'ppt' => 'application/vnd.ms-powerpoint',								
'rm' => 'application/vnd.rn-realmedia',								
'wbxml' => 'application/vnd.wap.wbxml',								
'wmlc' => 'application/vnd.wap.wmlc',								
'wmlsc' => 'application/vnd.wap.wmlscriptc',								
'vxml' => 'application/voicexml+xml',								
'bcpio' => 'application/x-bcpio',								
'vcd' => 'application/x-cdlink',								
'pgn' => 'application/x-chess-pgn',								
'cpio' => 'application/x-cpio',								
'csh' => 'application/x-csh',								
'dcr' => 'application/x-director',	'dir' => 'application/x-director',	'dxr' => 'application/x-director',						
'dvi' => 'application/x-dvi',								
'spl' => 'application/x-futuresplash',								
'gtar' => 'application/x-gtar',								
'gz' => 'application/x-gzip',								
'hdf' => 'application/x-hdf',								
'phps' => 'application/x-httpd-php-source',								
'php4' => 'application/x-httpd-php4',	'php3' => 'application/x-httpd-php4',	'php' => 'application/x-httpd-php4',	'phtml' => 'application/x-httpd-php4',					
'jnlp' => 'application/x-java-jnlp-file',								
'jardiff' => 'application/x-java-archive-diff',								
'js' => 'application/x-javascript',								
'skp' => 'application/x-koan',	'skd' => 'application/x-koan',	'skt' => 'application/x-koan',	'skm' => 'application/x-koan',					
'latex' => 'application/x-latex',								
'wmd' => 'application/x-ms-wmd',								
'wmz' => 'application/x-ms-wmz',								
'nc' => 'application/x-netcdf',	'cdf' => 'application/x-netcdf',							
'sh' => 'application/x-sh',								
'shar' => 'application/x-shar',								
'swf' => 'application/x-shockwave-flash',								
'sit' => 'application/x-stuffit',								
'sv4cpio' => 'application/x-sv4cpio',								
'sv4crc' => 'application/x-sv4crc',								
'tar' => 'application/x-tar',								
'tcl' => 'application/x-tcl',								
'tex' => 'application/x-tex',								
'texinfo' => 'application/x-texinfo',	'texi' => 'application/x-texinfo',							
't' => 'application/x-troff',	'tr' => 'application/x-troff',	'roff' => 'application/x-troff',						
'man' => 'application/x-troff-man',								
'me' => 'application/x-troff-me',								
'ms' => 'application/x-troff-ms',								
'ustar' => 'application/x-ustar',								
'src' => 'application/x-wais-source',								
'wmlc' => 'application/x-wap.wmlc',								
'wmlsc' => 'application/x-wap.wmlscriptc',								
'xhtml' => 'application/xhtml+xml',	'xht' => 'application/xhtml+xml',							
'xslt' => 'application/xslt+xml',								
'xml' => 'application/xml',	'xsl' => 'application/xml',							
'dtd' => 'application/xml-dtd',								
'zip' => 'application/zip',								
'kml' => 'application/vnd.google-earth.kml+xml',								
'kmz' => 'application/vnd.google-earth.kmz',								
'au' => 'audio/basic',	'snd' => 'audio/basic',							
'mid' => 'audio/midi',	'midi' => 'audio/midi',	'kar' => 'audio/midi',						
'mpga' => 'audio/mpeg',	'mp2' => 'audio/mpeg',	'mp3' => 'audio/mpeg',						
'aif' => 'audio/x-aiff',	'aiff' => 'audio/x-aiff',	'aifc' => 'audio/x-aiff',						
'm3u' => 'audio/x-mpegurl',								
'wax' => 'audio/x-ms-wax',								
'wma' => 'audio/x-ms-wma',								
'ram' => 'audio/x-pn-realaudio',	'ra' => 'audio/x-pn-realaudio',							
'wav' => 'audio/x-wav',								
'pdb' => 'chemical/x-pdb',								
'xyz' => 'chemical/x-xyz',								
'bmp' => 'image/bmp',								
'cgm' => 'image/cgm',								
'gif' => 'image/gif',								
'ief' => 'image/ief',								
'jpeg' => 'image/jpeg',	'jpg' => 'image/jpeg',	'jpe' => 'image/jpeg',						
'png' => 'image/png',								
'svg' => 'image/svg+xml',								
'tiff' => 'image/tiff',	'tif' => 'image/tiff',							
'djvu' => 'image/vnd.djvu',	'djv' => 'image/vnd.djvu',							
'wbmp' => 'image/vnd.wap.wbmp',								
'ras' => 'image/x-cmu-raster',								
'ico' => 'image/x-icon',								
'pnm' => 'image/x-portable-anymap',								
'pbm' => 'image/x-portable-bitmap',								
'pgm' => 'image/x-portable-graymap',								
'ppm' => 'image/x-portable-pixmap',								
'qtif' => 'image/x-quicktime',	'qti' => 'image/x-quicktime',							
'rgb' => 'image/x-rgb',								
'xbm' => 'image/x-xbitmap',								
'xpm' => 'image/x-xpixmap',								
'xwd' => 'image/x-xwindowdump',								
'igs' => 'model/iges',	'iges' => 'model/iges',							
'msh' => 'model/mesh',	'mesh' => 'model/mesh',	'silo' => 'model/mesh',						
'wrl' => 'model/vrml',	'vrml' => 'model/vrml',							
'ics' => 'text/calendar',	'ifb' => 'text/calendar',							
'css' => 'text/css',								
'html' => 'text/html',	'htm' => 'text/html',	'shtml' => 'text/html',	'shtm' => 'text/html',					
'asc' => 'text/plain',	'txt' => 'text/plain',							
'rtx' => 'text/richtext',								
'rtf' => 'text/rtf',								
'sgml' => 'text/sgml',	'sgm' => 'text/sgml',							
'tsv' => 'text/tab-separated-values',								
'jad' => 'text/vnd.sun.j2me.app-descriptor',								
'wml' => 'text/vnd.wap.wml',								
'wmls' => 'text/vnd.wap.wmlscript',								
'hdml' => 'text/x-hdml',								
'etx' => 'text/x-setext',								
'3gp' => 'video/3gpp',								
'mp4' => 'video/mp4',	'mpg4' => 'video/mp4',	'm4v' => 'video/mp4',						
'mpeg' => 'video/mpeg',	'mpg' => 'video/mpeg',	'mpe' => 'video/mpeg',						
'qt' => 'video/quicktime',	'mov' => 'video/quicktime',							
'mxu' => 'video/vnd.mpegurl',	'm4u' => 'video/vnd.mpegurl',							
'asf' => 'video/x-ms-asf',	'asx' => 'video/x-ms-asf',							
'wvx' => 'video/x-ms-wvx',								
'wm' => 'video/x-ms-wm',								
'wmx' => 'video/x-ms-wmx',								
'avi' => 'video/x-msvideo',								
'movie' => 'video/x-sgi-movie',								
'ice' => 'x-conference/x-cooltalk');
}

?>