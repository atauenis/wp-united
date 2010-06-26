<?php
/** 
*
* WP-United Settings Panels
*
* @package WP-United
* @version $Id: v0.9.0RC3 2010/07/01 John Wells (Jhong) Exp $
* @copyright (c) 2006-2010 wp-united.com
* @license http://opensource.org/licenses/gpl-license.php GNU Public License  
* @author John Wells
*
*	The plugin settings panels.
* 
*/

/**
 * Add menu options for WP-United Settings panel
 */
function wpu_settings_menu() {  
	global $wpuUrl, $wpSettings;
	
	if (!current_user_can('manage_options'))  {
		return;
	}
	
	if (!function_exists('add_submenu_page')) {
		return;
	}	
	
	if(isset($_POST['wpusettings-transmit'])) {
		if(check_ajax_referer( 'wp-united-transmit')) {			
			wpu_transmit_settings();
			die();
		}
	}	

	wp_register_style('wpuSettingsStyles', $wpuUrl . 'theme/settings.css');
	wp_enqueue_style('wpuSettingsStyles'); 
		
	if(isset($_GET['page'])) {
		if( ($_GET['page'] == 'wp-united-settings') || ($_GET['page'] == 'wp-united-setup') ) {
			wp_deregister_script( 'jquery' );
			wp_deregister_script( 'jquery-ui-core' );				
			
			wp_enqueue_script('jquery', $wpuUrl . 'js/jquery-wpu-min.js', array(), false, true);
			wp_enqueue_script('jquery-ui', $wpuUrl . 'js/jqueryui-wpu-min.js', array(), false, true);
			wp_enqueue_script('filetree', $wpuUrl . 'js/filetree.js', array(), false, true);				
		}
	}	
		
	$top = add_menu_page('WP-United ', 'WP-United', 'manage_options', 'wp-united-setup', 'wpu_setup_menu', $wpuUrl . 'images/tiny.gif', 2 );
	add_submenu_page('wp-united-setup', 'WP-United Setup', 'Setup / Status', 'manage_options','wp-united-setup');
		
		
	$status = (isset($wpSettings['status'])) ? $wpSettings['status'] : 0;
	
	// only show other menu items if WP-United is set up
	if($status == 2) {
		add_submenu_page('wp-united-setup', 'WP-United Settings', 'Settings', 'manage_options','wp-united-settings', 'wpu_settings_page');

			if(isset($wpSettings['integrateLogin'])) {
				if($wpSettings['integrateLogin']) {
					add_submenu_page('wp-united-setup', 'WP-United User Mapping', 'User Mapping', 'manage_options','wpu-user-mapper', 'wpu_user_mapper');
				}
			}
		add_submenu_page('wp-united-setup', 'WP-United Advanced Options', 'Advanced Options', 'manage_options','wpu-advanced-options', 'wpu_advanced_options');
	}
	
}


/**
 * Decide whether to show the advanced options, or save them
 */
function wpu_advanced_options() {
	global $wpuUrl; 
	?>
		<div class="wrap" id="wp-united-advoptions">
		<?php screen_icon('options-general'); ?>
		<h2> <?php _e('WP-United Advanced Options<br />[UNDER CONSTRUCTION - USE SETTINGS PAGE FOR NOW]'); ?> </h2>
		<p><?php _e('Here you can set advanced options that control WP-United by editing the options.php file. You should not normally have to edit these.') ?></p>
		<p><?php _e('Note that these options could be overwritten during a WP-United upgrade.') ?></p>
	<?php
	if(isset($_POST['wpuadvanced-submit'])) {
		// process form
		if(check_admin_referer( 'wp-united-advanced')) {
			wpu_process_advanced_options();
		}
	} else {
			wpu_show_advanced_options();
	}
	?></div> <?php
}

function wpu_setup_menu() {
	global $wpuUrl, $wpuPath; 
	$settings = wpu_get_settings();
	
	?>
		<div class="wrap" id="wp-united-setup">
		<?php screen_icon('options-general'); ?>
		<h2> <?php _e('WP-United Setup / Status'); ?> </h2>
		<p><?php _e('WP-United needs to connect to phpBB in order to work. On this screen you can set up or disable the connection.') ?></p>
	
	
		<div id="wputransmit"><img src="<?php echo $wpuUrl ?>/images/wpuldg.gif" style="float: left;" />Contacting phpBB...</div>
	
	<?php
	
	$msg = '';
	if(isset($_GET['msg'])) {
		if($_GET['msg'] == 'fail') {
			$msg = (string)stripslashes($_GET['msgerr']);
		}
	}
				
	$status = (isset($settings['status'])) ? $settings['status'] : 0;
	
	$buttonDisplay = 'display: block;';
	
	switch($status) {
		case 2:
			$statusText = __('OK');
			$statusColour = "updated allok";
			$statusDesc =  __('WP-United is connected and working.');
			$buttonDisplay = 'display: none;';
			break;
		case 1:
			$statusText = __('Connected, but not ready or disabled due to errors');
			$statusColour = "updated highlight allok";
			$statusDesc = sprintf(__('WP-United is connected but your phpBB forum is either producing errors, or is not set up properly. You need to modify your board. %1$sClick here%2$s to download the modification package. You can apply it using %3$sAutoMod%4$s (recommended), or manually by reading the install.xml file and following %5$sthese instructions%6$s. When done, click &quot;Connect&quot; to try again.'), '<a href=\"#\">', '</a>', '<a href=\"http://www.phpbb.com/mods/automod/\">', '</a>', '<a href=\"http://www.phpbb.com/mods/installing/\">', '</a>') .  '<br /><br />' . __('You can\'t change any other settings until the problem is fixed.');
			break;
		case 0:
		default:
			$statusText = __('Not Connected');
			$statusColour = "error";
			$statusDesc = _('WP-United is not connected yet. Select your forum location below and then click &quot;Connect&quot;') . '<br /><br />' . __('You can\'t change any other settings until WP-United is connected.');
			$buttonDisplay = (isset($settings['phpbb_path'])) ? 'display: block;' : 'display: none;';
	}
	
	if(!is_writable($wpuPath . 'cache/')) {
		echo '<div id="cacheerr" class="error highlight"><p>ERROR: Your cache folder (' . $wpuPath . 'cache/) is not writable by the web server. You must make this folder writable for WP-United to work properly!</p></div>';
	}
	
			
	echo "<div id=\"wpustatus\" class=\"$statusColour\"><p><strong>" . sprintf(__('Current Status: %s'), $statusText) . '</strong>';
	if($status == 2) {
		echo '<button style="float: right;margin-bottom: 6px;" class="button-secondary" onclick="return wpu_manual_disable(\'wp-united-setup\');">Disable</button>';
	}
	echo "<br /><br />$statusDesc";
	if(!empty($msg)) {
		echo '<br /><br /><strong>' . __('The server returned the following information:') . "</strong><br />$msg";
	}
	echo '</p></div>';
		
	?>
	<h3><?php _e('phpBB Location') ?></h3>
	<form name="wpu-setup" id="wpusetup" method="post" onsubmit="return wpu_transmit('wp-united-setup', this.id);">
		<?php wp_nonce_field('wp-united-setup');  ?>
		
		<p><?php _e('WP-United needs to know where phpBB is installed on your server.'); ?> <span id="txtselpath">Find and select your phpBB's config.php below.</span><span id="txtchangepath" style="display: none;">Click &quot;Change Location&quot; to change the stored location.</span></p>
		<div id="phpbbpath">&nbsp;</div>
		<p>Path selected: <strong id="phpbbpathshow" style="color: red;"><?php echo "Not selected"; ?></strong> <a id="phpbbpathchooser" href="#" onclick="return wpuChangePath();" style="display: none;">Change Location &raquo;</a><a id="wpucancelchange" style="display: none;" href="#" onclick="return wpuCancelChange();">Cancel Change</a></p>
		<input id="wpupathfield" type="hidden" name="wpu-path" value="notset"></input>
	
		<p class="submit">
			<input type="submit" style="<?php echo $buttonDisplay; ?>"; class="button-primary" value="<?php  _e('Connect') ?>" name="wpusetup-submit" id="wpusetup-submit" />
		</p>
	</form>
		
	<script type="text/javascript">
	// <![CDATA[
		jQuery(document).ready(function($) { 
			$('#phpbbpath').fileTree({ 
				root: '/',
				script: '<?php echo $wpuUrl . 'js/filetree.php'; ?>',
				multiFolder: false,
				loadMessage: "Loading..."
			}, function(file) { 
				var parts = file.split('/');
				if ((parts.length) > 1) {
					file = parts.pop();
				}
				if(file=='config.php') {
					var pth = parts.join('/') + '/'; 
					$("#phpbbpathshow").html(pth).css('color', 'green');
					$("#wpupathfield").val(pth);
					$('#phpbbpath').hide('slide');
					$('#txtchangepath').show();
					$('#txtselpath').hide();
					$('#wpucancelchange').hide();
					$('#phpbbpathchooser').show('slide');
					$('#wpusetup-submit').show();
					window.scrollTo(0,0);
				}
			});
			
		<?php if(isset($settings['phpbb_path'])) { ?> 
				$('#phpbbpath').hide();
				$('#phpbbpathchooser').button();
				$('#phpbbpathchooser').show();
				$("#phpbbpathshow").html('<?php echo $settings['phpbb_path']; ?>').css('color', 'green');
				$("#wpupathfield").val('<?php echo $settings['phpbb_path']; ?>');
				$('#txtchangepath').show();
				$('#txtselpath').hide();
		<?php } ?>
						
		});
		
		function wpuChangePath() {
			$('#phpbbpath').show('slide');
			$('#phpbbpathchooser').hide('slide');
			$('#txtchangepath').hide();
			$('#txtselpath').show();
			$('#wpucancelchange').show();
			$('#wpucancelchange').button();
			$('#wpusetup-submit').show();
			return false;
		}
		
		function wpuCancelChange() {
			$('#phpbbpath').hide('slide');
			$('#phpbbpathchooser').show('slide');
			$('#txtchangepath').show();
			$('#txtselpath').hide();
			$('#wpucancelchange').hide();
			$('#wpusetup-submit').hide();			
			return false;
		}
		
		var transmitMessage;
		function wpu_transmit(type, formID) {
			$('#wpustatus').hide();
			window.scrollTo(0,0);
			$('#wputransmit').show();
			var formData;
			
			formData = $('#' + formID).serialize() +'&wpusettings-transmit=1&_ajax_nonce=<?php echo wp_create_nonce ('wp-united-transmit'); ?>';
			$.post('admin.php?page='+type, formData, function(response) {
				if(response=='OK') {
					// the settings were applied
					window.location = window.location.href + '&msg=success';
					return;
				}
				
				transmitMessage = response;
				// there was an uncatchable error, send a disable request
				if  (transmitMessage.indexOf('[ERROR]') == -1) {
					var disable = 'wpudisable=1&_ajax_nonce=<?php echo wp_create_nonce ('wp-united-disable'); ?>';
					$.post('admin.php?page='+type, disable, function(response) {
						// the connection has been disabled, redirect
						if(transmitMessage == '') {
							transmitMessage = '<?php _e('Blank page received: check your error log.'); ?>';
						}
						window.location = 'admin.php?page=wp-united-setup&msg=fail&msgerr=' + escape(transmitMessage);
					});
				} else {
					// we caught the error, redirect to setup page
					transmitMessage = transmitMessage.replace(/\[ERROR\]/g, '');
					window.location = 'admin.php?page=wp-united-setup&msg=fail &msgerr=' + escape(transmitMessage);
				}

			});
			return false;
		}
		
		function wpu_manual_disable(type) {
			
			var disable = 'wpudisableman=1&_ajax_nonce=<?php echo wp_create_nonce ('wp-united-disable'); ?>';
			$.post('admin.php?page='+type, disable, function(response) {
				// the connection has been disabled, redirect
				window.location = 'admin.php?page='+type;
			});
			
			return false;
			
		}

	// ]]>
	</script>

		
<?php

}

function wpu_user_mapper() {
	echo "USER MAPPER";
}
	
/**
 * The main WP-United settings panel
 */	
function wpu_settings_page() {	
	
	global $phpbbForum, $wpuUrl; 
	$settings = wpu_get_settings();
	
	?>
	
	<div class="wrap" id="wp-united-settings">
		<?php screen_icon('options-general'); ?>
		<h2> <?php _e('WP-United Settings'); ?> </h2>
	
			<div id="wputransmit"><img src="<?php echo $wpuUrl ?>/images/wpuldg.gif" style="float: left;" />Transmitting settings to phpBB...</div>
			
			<?php
				if(isset($_GET['msg'])) {
					if($_GET['msg'] == 'success') {
			?>
			<div id="wpustatus" class="updated"><p><?php _e('Settings applied successfully.'); ?></p></div>
			<?php
				} elseif($_GET['msg'] == 'fail') {
			?>
					<div id="wpustatus" class="error">
						<p><?php _e('An error occurred. The error details are below. Please check your settings or try disabling plugins.'); ?></p>
						<div style="margin-bottom: 8px;" id="wpuerrordets">
							<?php echo (string)stripslashes($_GET['msgerr']); ?>
						</div>
					</div>
			<?php
				}
			}
			?>
			
			<p><?php _e('WP-United is modular; You can enable or disable any of the four major features below: User Integration, Theme Integration, Behaviour Integration and User Blogs.') ?></p>
			<p><?php _e('Visit each of the tabs to select the settings, then hit Submit when done.') ?></p>
					
			<form name="wpu-settings" id="wpusettings" method="post" onsubmit="return wpu_transmit('wp-united-settings', this.id);">
				
				<div id="wputabs">
					<ul>
						<li><a href="#wputab-basic">Basic Settings</a></li>
						<li><a href="#wputab-user">User Integration</a></li>
						<li><a href="#wputab-theme">Theme Integration</a></li>
						<li><a href="#wputab-behav">Behaviour Integration</a></li>
					<!--	<li><a href="#wputab-blogs">User Blogs</a></li>-->
					</ul>

					<div id="wputab-basic">
						<h3>Path to phpBB3</h3>
						<p>WP-United needs to know where phpBB is installed on your server. You can change the location on the &quot;Setup / Status&quot; page.</p>
					
						<p>Path selected: <strong id="phpbbpathshow" style="color: red;"><?php echo "Not selected"; ?></strong> <a href="admin.php?page=wp-united-setup" id="phpbbpathchange">Change Location &raquo;</a></p>
						<input id="wpupathfield" type="hidden" name="wpu-path" value="notset"></input>
						<h3>Forum Page</h3>
						<p>Create a WordPress forum page? If you enable this option, WP-United will create a blank page in your WordPress installation, so that 'Forum' links appear in your blog. These links will automatically direct to your forum.</p>
						<input type="checkbox" id="wpuforumpage" name="wpuforumpage" <?php if(!empty($settings['useForumPage'])) { ?>checked="checked"<?php } ?> /><label for="wpuforumpage">Enable Forum Page</label>		
					</div>
					
					<div id="wputab-user">
						<h3>Integrate logins?</h3>
						<p>If you turn this option on, phpBB will create a WordPress account the first time each phpBB user <strong>with appropriate permissions</strong> visits the blog. If this WordPress install will be non-interactive (e.g., a blog by a single person, a portal page, or an information library with commenting disabled), you may want to turn this option off, as readers may not need accounts. You can also map existing WordPress users to phpBB users, using the mapping tool that will appear after you turn on this option.</p>
						<p>You <strong>must set</strong> the privileges for each user using the WP-United permissions under the phpBB3 Users' and Groups' permissions settings.</p>
						<input type="checkbox" id="wpuloginint" name="wpuloginint" <?php if(!empty($settings['integrateLogin'])) { ?>checked="checked"<?php } ?> /><label for="wpuloginint">Enable Login Integration?</label>		
						
						<div id="wpusettingsxpost" class="subsettings">
							<h4>Enable cross-posting?</h4>
							<p>If you enable this option, users will be able to elect to have their blog entry copied to a forum when writing a blog post. To set which forums the user can cross-post to, visit the phpBB forum permissions panel, and enable the &quot;can cross-post&quot; permission for the users/groups/forums combinations you need.</p>
							<input type="checkbox" id="wpuxpost" name="wpuxpost" <?php if(!empty($settings['xposting'])) { ?>checked="checked"<?php } ?> /><label for="wpuxpost">Enable Cross-Posting?</label>		
							
							
							<div id="wpusettingsxpostxtra" class="subsettings">
								<h4>Type of cross-posting?</h4>
								<p>Choose how the post should appear in phpBB. WP-United can post an excerpt, the full post, or give you an option to select when posting each post.</p>
								<input type="radio" name="rad_xpost_type" value="excerpt" id="wpuxpexc"  <?php if($settings['xposttype'] == 'excerpt') { ?>checked="checked"<?php } ?>  /><label for="wpuxpexc">Excerpt</label>
								<input type="radio" name="rad_xpost_type" value="fullpost" id="wpuxpfp" <?php if($settings['xposttype'] == 'fullpost') { ?>checked="checked"<?php } ?>  /><label for="wpuxpfp">Full Post</label>
								<input type="radio" name="rad_xpost_type" value="askme" id="wpuxpask" <?php if($settings['xposttype'] == 'askme') { ?>checked="checked"<?php } ?>  /><label for="wpuxpask">Ask Me</label>
								
								<h4>phpBB manages comments on crossed posts?</h4>
								<p>Choose this option to have WordPress comments replaced by forum replies for cross-posted blog posts. In addition, comments posted by integrated users via the WordPress comment form will be cross-posted as replies to the forum topic.</p>
								<input type="checkbox" name="wpuxpostcomments" id="wpuxpostcomments" <?php if(!empty($settings['xpostautolink'])) { ?>checked="checked"<?php } ?> /><label for="wpuxpostcomments">phpBB manages comments</label>		
								
								<h4>Force all blog posts to be cross-posted?</h4>
								<p>Setting this option will force all blog posts to be cross-posted to a specific forum. You can select the forum here. Note that users must have the &quot;can cross-post&quot; WP-United permission under phpBB Forum Permissions, or the cross-posting will not take place.</p>
								<select id="wpuxpostforce" name="wpuxpostforce">
									<option value="0" <?php if($wpSettings['xpostforce'] == 0) { echo ' selected="selected" '; } ?>>-- Disabled --</option>
									
									<?php
									if(defined('IN_PHPBB')) { 
										global $phpbbForum, $db;
										$phpbbForum->enter();
										$sql = 'SELECT forum_id, forum_name FROM ' . FORUMS_TABLE . ' WHERE ' .
											'forum_type = ' . FORUM_POST;
										if ($result = $db->sql_query($sql)) {
											while ( $row = $db->sql_fetchrow($result) ) {
												echo '<option value="' . $row['forum_id'] . '"';
												if($wpSettings['xpostforce'] == (int)$row['forum_id']) {
													 echo ' selected="selected" ';
												}
												echo '>' . $row['forum_name'] . '</option>';
											}
										}
									}
								?>								
									
								</select>
							</div>				
						</div>
					</div>		
					
					<div id="wputab-theme">
						<h3>Integrate themes?</h3>
						<p>WP-United can integrate your phpBB &amp; WordPress templates.</p>
						<input type="checkbox" id="wputplint" name="wputplint" <?php if($settings['showHdrFtr'] != 'NONE') { ?>checked="checked" <?php } ?> /><label for="wputplint">Enable Theme Integration</label>
						<div id="wpusettingstpl" class="subsettings">
							<h4>Integration Mode</h4>
							<p>Do you want WordPress to appear inside your phpBB template, or phpBB to appear inside your WordPress template?</p>
							
							<input type="radio" name="rad_tpl" value="rev" id="wputplrev"  <?php if($settings['showHdrFtr'] != 'FWD') { ?>checked="checked" <?php } ?> /><label for="wputplrev">phpBB inside WordPress</label>
							<input type="radio" name="rad_tpl" value="fwd" id="wputplfwd" <?php if($settings['showHdrFtr'] == 'FWD') { ?>checked="checked" <?php } ?>  /><label for="wputplfwd">WordPress inside phpBB</label>
							
						
							<h4>Automatic CSS Integration</h4>
							
							<p>WP-United can automatically fix CSS conflicts between your phpBB and WordPress templates. Set the slider to "maximum compatibility" to fix most problems. If you prefer to fix CSS conflicts by hand, or if the automatic changes cause problems, try reducing the level.</p>
							
							<div style="padding: 0 100px;">
								<p style="height: 11px;"><span style="float: left;">Off</span><span style="float: right;">Maximum Compatibility (Recommended)</span></p>
								<div id="wpucssmlvl"></div>
								<div id="cssmdesc"><p><strong>Current Level: <span id="cssmlvltitle">xxx</span></strong><br /></p><p id="cssmlvldesc">xxx</p></div>
							</div>
							<input type="hidden" id="wpucssmlvlfield" name="wpucssmlevel" value="notset"></input>
							<p><a id="wputpladvancedstgs" href="#" onclick="return tplAdv();"><span id="wutpladvshow">Show Advanced Settings &raquo;</span><span id="wutpladvhide" style="display: none;">&laquo; Hide Advanced Settings</span></a></p>
							
							<div id="wpusettingstpladv" class="subsettings">
								<h4>Advanced Settings</h4>
								<p><strong>Use full page?</strong>
									<a class="wpuwhatis" href="#" title="Do you want phpBB to simply appear inside your WordPress header and footer, or do you want it to show up in a fully featured WordPress page? Simple header and footer will work best for most WordPress themes – it is faster and less resource-intensive, but cannot display dynamic content on the forum page. However, if you want the WordPress sidebar to show up, or use other WordPress features on the integrated page, you could try 'full page'. This option could be a little slower.">What is this?</a>
								</p>
								<select id="wpuhdrftrspl" name="wpuhdrftrspl">
									
									<option value="0"<?php if($settings['wpSimpleHdr'] == 1) { echo ' selected="selected" '; } ?>>-- Simple Header &amp; Footer (recommended) --</option>
									<?php
										$files = scandir(TEMPLATEPATH);
										if(sizeof($files)) {
											foreach($files as $file) {
												// no stripos for ph4 compatibility
												if(strpos(strtolower($file), '.php') == (strlen($file) - 4)) {
													echo '<option value="' . $file . '"';
													if( ($settings['wpPageName'] == $file) && ($settings['wpSimpleHdr'] == 0) ) {
														echo ' selected="selected" ';
													}
													echo '>Full Page: ' . $file . '</option>';
												}
											}
										}
									?>
								</select>
								
								<p><strong>Padding around phpBB</strong>
									<a class="wpuwhatis" href="#" title="phpBB is inserted on the WordPress page inside a DIV. Here you can set the padding of that DIV. This is useful because otherwise the phpBB content may not line up properly on the page. The defaults here are good for most WordPress templates. If you would prefer set this yourself, just leave these boxes blank (not '0'), and style the 'phpbbforum' DIV in your stylesheet.">What is this?</a>
								</p>
									<table>
										<tr>
											<td>
												<label for="wpupadtop">Top:</label><br />
											</td>
											<td>
												<input type="text" maxlength="3" style="width: 30px;" id="wpupadtop" name="wpupadtop" value="6" />px<br />
											</td>
										</tr>
										<tr>
											<td>
												<label for="wpupadright">Right:</label><br />
											</td>
											<td>
												<input type="text" maxlength="3" style="width: 30px;" id="wpupadright" name="wpupadright" value="12" />px<br />
											</td>
										</tr>
										<tr>
											<td>
												<label for="wpupadbtm">Bottom:</label><br />
											</td>
											<td>
												<input type="text" maxlength="3" style="width: 30px;" id="wpupadbtm" name="wpupadbtm" value="6" />px<br />
											</td>
										</tr>
										<tr>
											<td>
												<label for="wpupadleft">Left:</label><br />
											</td>
											<td>
												<input type="text" maxlength="3" style="width: 30px;" id="wpupadleft" name="wpupadleft" value="12" />px<br />
											</td>
										</tr>
										</table>
									<p><a href="#" onclick="return false;">Reset to defaults</a></p>
									
									<p>
										<input type="checkbox" id="wpudtd" name="wpudtd" /> <label for="wpudtd"><Strong>Use Different Document Type Declaration?</Strong></label>
										<a class="wpuwhatis" href="#" title="The Document Type Declaration, or DTD, is provided at the top of all web pages to let the browser know what type of markup language is being used. phpBB3's prosilver uses an XHTML 1.0 Strict DTD by default. Most WordPress templates, however, use an XHTML 1 transitional  or XHTML 5 DTD. In most cases, this doesn't matter -- however, If you want to use WordPress' DTD on pages where WordPress is inside phpBB, then you can turn this option on. This should prevent browsers from going into quirks mode, and will ensure that even more WordPress templates display as designed.">What is this?</a>
									</p>
								</div>
						</div>
					</div>
					
					<div id="wputab-behav">
					
						<h3>Use phpBB Word Censor?</h3>
						<p>Turn this option on if you want WordPress posts to be passed through the phpBB word censor.</p>
						<input type="checkbox" id="wpucensor" name="wpucensor" /><label for="wpucensor">Enable word censoring in WordPress</label>
						
						<h3>Use phpBB smilies?</h3>
						<p>Turn this option on if you want to use phpBB smilies in WordPress comments and posts.</p>
						<input type="checkbox" id="wpucensor" name="wpusmilies" /><label for="wpusmilies">Enable phpBB smilies in WordPress</label>	
						
						<h3>Make Blogs Private?</h3>
						<p>If you turn this on, users will have to be logged in to VIEW blogs. This is not recommended for most set-ups, as WordPress will lose search engine visibility.</p>
						<input type="checkbox" id="wpucensor" name="wpusmilies" /><label for="wpusmilies">Make blogs private</label>							
						
					</div>
					
					<!--<div id="wputab-blogs">
						User Blogs - options being revamped
					</div>-->
				</div>
				
			<p class="submit">
				<input type="submit" class="button-primary" value="<?php _e('Submit') ?>" name="wpusettings-submit" />
			</p>
		</form>
		
		<div id="wpu-dialog" title="What is this?" style="display: none;">
			<p id="wpu-desc">&nbsp;</p>
		
		</div>
		
	</div>
		<script type="text/javascript">
		// <![CDATA[
			jQuery(document).ready(function($) { 

				$('#wputabs').tabs();	
				$('#phpbbpathchange').button();	
				$('#wputpladvancedstgs').button();	
				$('.wpuwhatis').button();	
				
	
			<?php if(isset($settings['phpbb_path'])) { ?>
					$("#phpbbpathshow").html('<?php echo $settings['phpbb_path']; ?>').css('color', 'green');
					$("#wpupathfield").val('<?php echo $settings['phpbb_path']; ?>');
										
			<?php } ?>
				
				$('.wpuwhatis').click(function() {
					$('#wpu-desc').text($(this).attr('title'));
					$("#wpu-dialog").dialog({
						modal: true,
						buttons: {
							Ok: function() {
								$(this).dialog('close');
							}
						}
					});
					return false;
				});
				

				if($('#wpuxpost').is(':checked')) $('#wpusettingsxpostxtra').show();
				if($('#wpuloginint').is(':checked')) $('#wpusettingsxpost').show();
				if($('#wputplint').is(':checked')) $('#wpusettingstpl').show();
				
				$('#wpuloginint').change(function() {
						$('#wpusettingsxpost').toggle("slide", "slow");
				});
				$('#wpuxpost').change(function() {
						$('#wpusettingsxpostxtra').toggle("slide", "slow");
				});
				
	

				<?php 
					$cssmVal = 0;
					if(!empty($settings['cssMagic'])){
						$cssmVal++;
					}
					if(!empty($settings['templateVoodoo'])){
						$cssmVal++;
					}
				?>
				
				setCSSMLevel(<?php echo $cssmVal; ?>);
				
				
				$('#wputplint').change(function() {
						$('#wpusettingstpl').toggle("slide", "slow");
						var slVal = ($(this).val()) ? 2 : 0;						
						setCSSMLevel(slVal);
						$("#wpucssmlvl").slider("value", slVal);
				});	
				
				$("#wpucssmlvl").slider({
					value: <?php echo $cssmVal; ?>,
					min: 0,
					max: 2,
					step: 1,
					change: function(event, ui) {
						setCSSMLevel(ui.value);
					}
				});
				
							
			});
			
			function setCSSMLevel(level) {
				var lvl, desc;
				if(level == 0) {
					lvl = "Off";
					desc = "All automatic CSS integration is disabled";
				} else if(level == 1) {
					lvl = "Medium";
					desc = "CSS Magic is enabled, Template Voodoo is disabled: <ul><li>Styles are reset to stop outer styles applying to the inner part of the page.</li><li>Inner CSS is made more specific so it does affect the outer portion of the page.</li><li>Some HTML IDs and class names may be duplicated.</li></ul>";
				} else if(level == 2) {
					lvl = "Full";
					desc = "CSS Magic and Template Voodoo are enabled:<ul><li>Styles are reset to stop outer styles applying to the inner part of the page.</li><li>Inner CSS is made more specific so it does affect the outer portion of the page.</li><li>HTML IDs and class names that are duplicated in the inner and outer parts of the page are fixed.</li></ul>";							
				}
				$("#wpucssmlvlfield").val(level);
				$("#cssmlvltitle").html(lvl);
				$("#cssmlvldesc").html(desc);
				$("#cssmdesc").effect("highlight");
			}
			
			function tplAdv() {
				$('#wpusettingstpladv').toggle('slide');
				$('#wutpladvshow').toggle()
				$('#wutpladvhide').toggle();
				return false;
			}
			
		var transmitMessage;
		function wpu_transmit(type, formID) {
			$('#wpustatus').hide();
			window.scrollTo(0,0);
			$('#wputransmit').show();
			var formData;
			
			formData = $('#' + formID).serialize() +'&wpusettings-transmit=1&_ajax_nonce=<?php echo wp_create_nonce ('wp-united-transmit'); ?>';
			$.post('admin.php?page='+type, formData, function(response) {
				if(response=='OK') {
					// the settings were applied
					window.location = window.location.href + '&msg=success';
					return;
				}
				
				transmitMessage = response;
				// there was an uncatchable error, send a disable request
				if  (transmitMessage.indexOf('[ERROR]') == -1) {
					var disable = 'wpudisable=1&_ajax_nonce=<?php echo wp_create_nonce ('wp-united-disable'); ?>';
					$.post('admin.php?page='+type, disable, function(response) {
						// the connection has been disabled, redirect
						if(transmitMessage == '') {
							transmitMessage = '<?php _e('Blank page received: check your error log.'); ?>';
						}
						window.location = 'admin.php?page=wp-united-setup&msg=fail&msgerr=' + escape(transmitMessage);
					});
				} else {
					// we caught the error, redirect to setup page
					transmitMessage = transmitMessage.replace(/\[ERROR\]/g, '');
					window.location = 'admin.php?page=wp-united-setup&msg=fail &msgerr=' + escape(transmitMessage);
				}

			});
			return false;
		}
		
		// ]]>
		</script>

<?php }




/**
 * Process settings
 */
function wpu_process_settings() {
	global $wpuUrl, $wpuPath, $wpSettings;
	
	$type = 'setup';
	if(isset($_GET['page'])) {
		if($_GET['page'] == 'wp-united-settings') {
			$type = 'settings';
		}
	}
	
	$data = array();
	
	/**
	 * First process path to phpBB
	 */
	if(!isset($_POST['wpu-path'])) {
		die('[ERROR] ERROR: You must specify a valid path for phpBB\'s config.php');
	}
	$wpuPhpbbPath = (string)$_POST['wpu-path'];
	$wpuPhpbbPath = str_replace('http:', '', $wpuPhpbbPath);
	$wpuPhpbbPath = add_trailing_slash($wpuPhpbbPath);
	if(!file_exists($wpuPath))  {
		die('[ERROR] ERROR:The path you selected for phpBB\'s config.php is not valid');
		return;
	}
	if(!file_exists($wpuPhpbbPath . 'config.php'))  {
		die('[ERROR] ERROR: phpBB\'s config.php could not be found at the location you chose');
		return;
	}
	if($type=='setup') {
		$data['phpbb_path'] = $wpuPhpbbPath;
	}
	
	$data = array_merge((array)$wpSettings, $data);

	if($type == 'settings') {
		/**
		 * Process 'use forum page'
		 */
		$data['useForumPage'] = isset($_POST['wpuforumpage']) ? 1 : 0;
		
		
		
		/** 
		 * Process login integration settings
		 */
		$data['integrateLogin'] = isset($_POST['wpuloginint']) ? 1 : 0;
		
		if($data['integrateLogin']) {
			$data['xposting'] = isset($_POST['wpuxpost']) ? 1 : 0;
			
			if($data['xposting'] ) { 
				
				$xpostType = (!isset($_POST['rad_xpost_type'])) ? 'excerpt' : $_POST['rad_xpost_type'];
				if($xpostType == 'askme') {
					$data['xposttype'] ='askme';
				} else if($xpostType == 'fullpost') {
					$data['xposttype'] ='fullpost';
				} else {
					$data['xposttype'] ='excerpt';
				}
				
				$data['xpostautolink'] =(isset($_POST['wpuxpostcomments'])) ? 1 : 0;
				$data['xpostforce'] =( isset($_POST['wpuxpostforce'])) ? (int) $_POST['wpuxpostforce'] : 0;
			} else {
				//cross-posting disabled, set to default
				$data = array_merge($data, array(
					'xposttype' 					=> 'excerpt',
					'wpuxpostcomments'	=> 0,
					'xpostforce' 				=> 0
				));
			}
		} else {
			// logins not integrated, set to default
			$data = array_merge($data, array(
				'xposting' 					=> 0,
				'xposttype' 					=> 'excerpt',
				'wpuxpostcomments'	=> 0,
				'xpostforce' 				=> 0
			));
		}
			
			
		/**
		 * Process 'theme integration' settings
		 */
		
		 $tplInt = isset($_POST['wputplint']) ? 1 : 0;

		if($tplInt) {
			$tplDir = isset($_POST['rad_tpl']) ? (string) $_POST['rad_tpl'] : 'fwd';
			
			if($tplDir == 'rev') {
				$data['showHdrFtr'] = 'REV';
			} else {
				$data['showHdrFtr'] = 'FWD';
			}
			
			$cssmLevel = isset($_POST['wpucssmlevel']) ? (int) $_POST['wpucssmlevel'] : 2;
			switch($cssmLevel) {
				case 0:
					$data['cssMagic'] = 0;
					$data['templateVoodoo'] = 0;
					break;
				case 1:
					$data['cssMagic'] = 1;
					$data['templateVoodoo'] = 0;
					break;
				default:
					$data['cssMagic'] = 1;
					$data['templateVoodoo'] = 1;	
			}
			
			$simpleHeader = (isset($_POST['wpuhdrftrspl'])) ?  $_POST['wpuhdrftrspl'] : 0;
			
			// set defaults
			$data['wpSimpleHdr'] = 1;
			$data['wpPageName'] = 'page.php';	

			if(!empty($simpleHeader)) {
				// we would check for existence of the file, but TEMPLATEPATH isn't initialised here yet.
				$data['wpSimpleHdr'] = 0;
				$data['wpPageName'] = $simpleHeader;
			} 
			
			$padT = isset($_POST['wpupadtop']) ? $_POST['wpupadtop'] : '';
			$padR = isset($_POST['wpupadright']) ? $_POST['wpupadright'] : '';
			$padB = isset($_POST['wpupadbtm']) ? $_POST['wpupadbtm'] : '';
			$padL = isset($_POST['wpupadleft']) ? $_POST['wpupadleft'] : '';

			if ( ($padT == '') && ($padR == '') && ($padB == '') && ($padL == '') ) {
				$data['phpbbPadding'] = 'NOT_SET';
			} else {
				$data['phpbbPadding'] = (int)$padT . '-' . (int)$padR . '-' . (int)$padB . '-' . (int)$padL;
			}
			
			$data['dtdSwitch'] =(isset($_POST['wpudtd'])) ? 1 : 0;
			
		} else {
			$data = array_merge($data, array(
				'showHdrFtr' 			=> 'NONE',
				'cssMagic' 				=> 0,
				'templateVoodoo' 	=> 0,
				'wpSimpleHdr' 		=> 1,
				'wppageName' 		=> 'page.php',
				'phpbbPadding' 		=>  '6-12-6-12',
				'dtdSwitch' 				=> 0
			));
		}
		
		/**
		 * Process 'behaviour' settings
		 */
		$data = array_merge($data, array(
			'phpbbCensor' 	=> (isset($_POST['phpbbCensor'])) ? 1 : 0,
			'phpbbSmilies' 	=> (isset($_POST['phpbbSmilies'])) ? 1 : 0,
			'mustLogin' 		=> (isset($_POST['mustLogin'])) ? 1 : 0
		));
		
	}

	$data = array_merge($data, array(
		'wpUri' => add_trailing_slash(get_option('home')),
		'wpPath' => ABSPATH,
		'wpPluginPath' => ABSPATH.'wp-content/plugins/' . plugin_basename('wp-united') . '/',
		'wpPluginUrl' => $wpuUrl,
		'enabled' => 'enabled',
		'status' => 2
	));


	update_option('wpu-settings', $data);
	
}


/**
 * Transmit settings to phpBB
 */
function wpu_transmit_settings() {
	global $phpbbForum;
	
	$settings = get_option('wpu-settings');
	if($phpbbForum->synchronise_settings($settings)) {
			die('OK');
	}
	
}

/**
 * Retrieve stored WP-United settings or set defaults
 */
function wpu_get_settings() {
	global $wpSettings;
	$settings = (array)get_option('wpu-settings');
	
	$defaults = array(
	'status' => 0,
	'wpUri' => '' ,
	'wpPath' => '', 
	'integrateLogin' => 0, 
	'showHdrFtr' => 'NONE',
	'wpSimpleHdr' => 1,
	'dtdSwitch' => 0,
	//'installLevel' => 0,
	'usersOwnBlogs' => 0,
	//'buttonsProfile' => 0,
	//'buttonsPost' => 0,
	//'allowStyleSwitch' => 0,
	//'useBlogHome' => 0,
	//'blogListHead' => $user->lang['WPWiz_BlogListHead_Default'],
	//'blogIntro' => $user->lang['WPWiz_blogIntro_Default'],
	'blogsPerPage' => 6,
	'blUseCSS' => 1,
	'phpbbCensor' => 1,
	//'wpuVersion' => $user->lang['WPU_Not_Installed'],
	'wpPageName' => 'page.php',
	'phpbbPadding' =>  '6-12-6-12',
	'mustLogin' => 0,
	//'upgradeRun' => 0,
	'xposting' => 0,
	'phpbbSmilies' => 0,
	'xpostautolink' => 0,
	'xpostforce' => -1,
	'xposttype' => 'EXCERPT',	
	'cssMagic' => 1,
	'templateVoodoo' => 1,
	//'pluginFixes' => 0,
	'useForumPage' => 1
	
);
	$settings = array_merge($defaults, $settings);
	
	if(isset($wpSettings)) {
		$settings = array_merge($settings, $wpSettings);
	}
	
	return $settings;
	
}

function wpu_panel_error($type, $text) {
	
	echo '<div id="message" class="error"><p>' . $text . '</p></div>';
	if($type=='settings') {
		wpu_settings_page();
	} else {
		wpu_show_setup_menu();
	}
	
}


function wpu_show_advanced_options() {	
	
	global $phpbbForum; 
	?>

		<form name="wpu-advoptions" id="wpuoptions" action="admin.php?page=wp-united-advanced" method="post">
		
		_EDITOR_HERE_
		
		<?php wp_nonce_field('wp-united-advanced'); ?>
		
		<p class="submit">
			<input type="submit" class="button-primary" value="<?php  _e('Save') ?>" name="wpuadvanced-submit" />
		</p>
		
		</form>
		
	<?php

}

function wpu_process_advanced_options() {	
	echo "SAVED";
	wpu_show_advanced_options();
}


add_action('admin_menu', 'wpu_settings_menu');

?>