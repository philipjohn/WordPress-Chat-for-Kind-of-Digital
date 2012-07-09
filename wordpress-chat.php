<?php
/*
 Plugin Name: WordPress Chat for Kind of Digital
 Plugin URI: http://premium.wpmudev.org/project/wordpress-chat-plugin
 Description: Provides you with a fully featured chat area either in a
post, page or bottom corner of your site - once activated configure <a
href="options-general.php?page=chat">here</a> and drop into a post or
page by clicking on the new chat icon in your post/page editor.
 Author: S H Mohanjith (Incsub)
 WDP ID: 159
 Version: 1.0.10
 Author URI: http://premium.wpmudev.org
 Text Domain: chat
*/

/**
 * @global	object	$chat	Convenient access to the chat object
 */
global $chat;

/**
 * Chat object (PHP4 compatible)
 * 
 * Allow your readers to chat with you
 * 
 * @since 1.0.1
 * @author S H Mohanjith <moha@mohanjith.net>
 */
class Chat {
	/**
	 * @todo Update version number for new releases
	 * 
	 * @var		string	$chat_current_version	Current version
	 */
	var $chat_current_version = '1.0.9';
	
	/**
	 * @var		string	$translation_domain	Translation domain
	 */
	var $translation_domain = 'chat';
	
	/**
	 * @var		array	$auth_type_map		Authentication methods map
	 */
	var $auth_type_map = array(1 => 'current_user', 2 => 'network_user', 3 => 'facebook', 4 => 'twitter', 5 => 'public_user');
	
	/**
	 * @var		array	$fonts_list		List of fonts
	 */
	var $fonts_list = array(
		"Arial" => "Arial, Helvetica, sans-serif",
		"Arial Black" => "'Arial Black', Gadget, sans-serif",
		"Bookman Old Style" => "'Bookman Old Style', serif",
		"Comic Sans MS" => "'Comic Sans MS', cursive",
		"Courier" => "Courier, monospace",
		"Courier New" => "'Courier New', Courier, monospace",
		"Garamond" => "Garamond, serif",
		"Georgia" => "Georgia, serif",
		"Impact" => "Impact, Charcoal, sans-serif",
		"Lucida Console" => "'Lucida Console', Monaco, monospace",
		"Lucida Sans Unicode" => "'Lucida Sans Unicode', 'Lucida Grande', sans-serif",
		"MS Sans Serif" => "'MS Sans Serif', Geneva, sans-serif",
		"MS Serif" => "'MS Serif', 'New York', sans-serif",
		"Palatino Linotype" => "'Palatino Linotype', 'Book Antiqua', Palatino, serif",
		"Symbol" => "Symbol, sans-serif",
		"Tahoma" => "Tahoma, Geneva, sans-serif",
		"Times New Roman" => "'Times New Roman', Times, serif",
		"Trebuchet MS" => "'Trebuchet MS', Helvetica, sans-serif",
		"Verdana" => "Verdana, Geneva, sans-serif",
		"Webdings" => "Webdings, sans-serif",
		"Wingdings" => "Wingdings, 'Zapf Dingbats', sans-serif"
	);
	
	/**
	 * @var		array	$_chat_options			Consolidated options
	 */
	var $_chat_options = array();
	
	/**
	 * Get the table name with prefixes
	 * 
	 * @global	object	$wpdb
	 * @param	string	$table	Table name
	 * @return	string			Table name complete with prefixes
	 */
	function tablename($table) {
		global $wpdb;
		// We use a single table for all chats accross the network
		return $wpdb->base_prefix.'chat_'.$table;
	}
	
	/**
	 * Initializing object
	 * 
	 * Plugin register actions, filters and hooks. 
	 */
	function Chat() {
	
		// Activation deactivation hooks
		
		register_activation_hook(__FILE__, array(&$this, 'install'));
		register_deactivation_hook(__FILE__, array(&$this, 'uninstall'));
	
		// Actions
		
		add_action('init', array(&$this, 'init'));
		add_action('wp_head', array(&$this, 'wp_head'), 1);
		add_action('wp_head', array(&$this, 'output_css'));
		add_action('wp_footer', array(&$this, 'wp_footer'), 1);
		add_action('get_footer', array(&$this, 'get_footer'));
		// add_action('admin_head', array($this, 'admin_head'));
		add_action('save_post', array(&$this, 'post_check'));
		add_action('edit_user_profile', array(&$this, 'profile'));
		add_action('show_user_profile', array(&$this, 'profile'));
		
		add_action('admin_print_styles-settings_page_chat', array(&$this, 'admin_styles'));
		add_action('admin_print_scripts-settings_page_chat', array(&$this, 'admin_scripts'));
		
		// Filters
		add_action('wp_ajax_chatProcess', array(&$this, 'process'));
		add_action('wp_ajax_nopriv_chatProcess', array(&$this, 'process'));
		
		// Only authenticated users (admin) can moderate, clear and archive
		add_action('wp_ajax_chatArchive', array(&$this, 'archive'));
		add_action('wp_ajax_chatClear', array(&$this, 'clear'));
		
		// TinyMCE options
		add_action('wp_ajax_chatTinymceOptions', array(&$this, 'tinymce_options'));
		
		add_filter('wp_redirect', array(&$this, 'profile_process'), 1, 1);
		add_filter('admin_menu', array(&$this, 'admin_menu'));
		
		// White list the options to make sure non super admin can save chat options 
		add_filter('whitelist_options', array(&$this, 'whitelist_options'));
		
		// Add shortcode
		add_shortcode('chat', array(&$this, 'process_shortcode'));
		add_shortcode('chat_logs', array(&$this, 'chat_logs_shortcode'));
		
		$this->_chat_options['default'] = get_option('chat_default', array(
			'sound'			=> 'enabled',
			'avatar'		=> 'enabled',
			'emoticons'		=> 'disabled',
			'date_show'		=> 'disabled',
			'time_show'		=> 'disabled',
			'width'			=> '',
			'height'		=> '',
			'background_color'	=> '#FFFFFF',
			'background_highlighted_color'	=> '#FFFFFF',
			'date_color'		=> '#6699CC',
			'name_color'		=> '#666666',
			'moderator_name_color'	=> '#6699CC',
			'special_color'		=> '#660000',
			'text_color'		=> '#000000',
			'code_color'		=> '#FFFFCC',
			'font'			=> '',
			'font_size'		=> '12',
			'log_creation'		=> 'disabled',
			'log_display'		=> 'disabled',
			'login_options'		=> array('current_user'),
			'moderator_roles'	=> array('administrator','editor','author')
			));
		
		$this->_chat_options['site'] = get_option('chat_site', array(
			'site'			=> 'disabled',
			'sound'			=> 'enabled',
			'avatar'		=> 'enabled',
			'emoticons'		=> 'disabled',
			'date_show'		=> 'disabled',
			'time_show'		=> 'disabled',
			'width'			=> '',
			'height'		=> '',
			'border_color'		=> '#4b96e2',
			'border_highlighted_color' => '#ff8400',
			'background_color'	=> '#FFFFFF',
			'date_color'		=> '#6699CC',
			'name_color'		=> '#666666',
			'moderator_name_color'	=> '#6699CC',
			'special_color'		=> '#660000',
			'text_color'		=> '#000000',
			'code_color'		=> '#FFFFCC',
			'font'			=> '',
			'font_size'		=> '12',
			'log_creation'		=> 'disabled',
			'log_display'		=> 'disabled',
			'login_options'		=> array('current_user'),
			'moderator_roles'	=> array('administrator','editor','author')));
	}
	
	/**
	 * Activation hook
	 * 
	 * Create tables if they don't exist and add plugin options
	 * 
	 * @see		http://codex.wordpress.org/Function_Reference/register_activation_hook
	 * 
	 * @global	object	$wpdb
	 */
	function install() {
		global $wpdb;
	
		/**
		 * WordPress database upgrade/creation functions
		 */
		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
			
		// Get the correct character collate
		if ( ! empty($wpdb->charset) )
			$charset_collate = "DEFAULT CHARACTER SET $wpdb->charset";
		if ( ! empty($wpdb->collate) )
			$charset_collate .= " COLLATE $wpdb->collate";
			
		if($wpdb->get_var("SHOW TABLES LIKE '". Chat::tablename('message') ."'") != Chat::tablename('message'))
		{
			// Setup chat message table
			$sql_main = "CREATE TABLE ".Chat::tablename('message')." (
							id BIGINT NOT NULL AUTO_INCREMENT,
							blog_id INT NOT NULL ,
							chat_id INT NOT NULL ,
							timestamp TIMESTAMP NOT NULL DEFAULT '0000-00-00 00:00:00' ,
							name VARCHAR( 255 ) CHARACTER SET utf8 NOT NULL ,
							avatar VARCHAR( 1024 ) CHARACTER SET utf8 NOT NULL ,
							message TEXT CHARACTER SET utf8 NOT NULL ,
							moderator ENUM( 'yes', 'no' ) NOT NULL DEFAULT 'no' ,
							approved ENUM( 'yes', 'no' ) NOT NULL DEFAULT 'no' ,
							archived ENUM( 'yes', 'no' ) NOT NULL DEFAULT 'no' ,
							PRIMARY KEY (`id`),
							KEY `blog_id` (`blog_id`),
							KEY `chat_id` (`chat_id`),
							KEY `timestamp` (`timestamp`),
							KEY `archived` (`archived`)
						);";
			dbDelta($sql_main);
		} else {
			$wpdb->query("ALTER TABLE ".Chat::tablename('message')." CHANGE name name VARCHAR( 255 ) CHARACTER SET utf8 NOT NULL;");
			$wpdb->query("ALTER TABLE ".Chat::tablename('message')." CHANGE avatar avatar VARCHAR( 1024 ) CHARACTER SET utf8 NOT NULL;");
			$wpdb->query("ALTER TABLE ".Chat::tablename('message')." CHANGE message message TEXT CHARACTER SET utf8 NOT NULL;");
			
			if ($wpdb->get_var("SHOW COLUMNS FROM ".Chat::tablename('message')." LIKE 'moderator'") != 'moderator') {
				$wpdb->query("ALTER TABLE ".Chat::tablename('message')." ADD moderator ENUM( 'yes', 'no' ) NOT NULL DEFAULT 'no' AFTER message;");
			}
		}
		
		// Setup the chat log table
		$sql_main = "CREATE TABLE ".Chat::tablename('log')." (
						id BIGINT NOT NULL AUTO_INCREMENT,
						blog_id INT NOT NULL ,
						chat_id INT NOT NULL ,
						start TIMESTAMP DEFAULT '0000-00-00 00:00:00' ,
						end TIMESTAMP DEFAULT '0000-00-00 00:00:00' ,
						created TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
						PRIMARY KEY (`id`),
						KEY `blog_id` (`blog_id`),
						KEY `chat_id` (`chat_id`)
					);";
		dbDelta($sql_main);
		
		// Default chat options
		$this->_chat_options['default'] = array(
			'sound'			=> 'disabled',
			'avatar'		=> 'enabled',
			'emoticons'		=> 'disabled',
			'date_show'		=> 'disabled',
			'time_show'		=> 'disabled',
			'width'			=> '',
			'height'		=> '',
			'background_color'	=> '#ffffff',
			'background_highlighted_color'	=> '#FFFFFF',
			'date_color'		=> '#6699CC',
			'name_color'		=> '#666666',
			'moderator_name_color'	=> '#6699CC',
			'special_color'		=> '#660000',
			'text_color'		=> '#000000',
			'code_color'		=> '#FFFFCC',
			'font'			=> '',
			'font_size'		=> '12',
			'log_creation'		=> 'disabled',
			'log_display'		=> 'disabled',
			'login_options'		=> array('current_user'),
			'moderator_roles'	=> array('administrator','editor','author'));
		
		// Site wide chat options
		$this->_chat_options['site'] = array(
			'site'			=> 'disabled',
			'sound'			=> 'enabled',
			'avatar'		=> 'enabled',
			'emoticons'		=> 'disabled',
			'date_show'		=> 'disabled',
			'time_show'		=> 'disabled',
			'width'			=> '',
			'height'		=> '',
			'border_color'		=> '#4b96e2',
			'border_highlighted_color' => '#ff8400',
			'background_color'	=> '#ffffff',
			'date_color'		=> '#6699CC',
			'name_color'		=> '#666666',
			'moderator_name_color'	=> '#6699CC',
			'special_color'		=> '#660000',
			'text_color'		=> '#000000',
			'code_color'		=> '#FFFFCC',
			'font'			=> '',
			'font_size'		=> '12',
			'log_creation'		=> 'disabled',
			'log_display'		=> 'disabled',
			'login_options'		=> array('current_user'),
			'moderator_roles'	=> array('administrator','editor','author'));
		
		add_option('chat_default', $this->_chat_options['default']);
		add_option('chat_site', $this->_chat_options['site'], null, 'no');
	}
	
	/**
	 * Deactivation hook
	 * 
	 * @see		http://codex.wordpress.org/Function_Reference/register_deactivation_hook
	 * 
	 * @global	object	$wpdb
	 */
	function uninstall() {
		global $wpdb;
		// Nothing to do
	}
	
	/**
	 * Get chat options
	 *
	 * @param string $key
	 * @param mixed $default
	 * @param string $type
	 * @return mixed
	 */
	function get_option($key, $default = null, $type = 'default') {
		if (isset($this->_chat_options[$type][$key])) {
			return $this->_chat_options[$type][$key];
		} else {
			return get_option($key, $default);
		}
		return $default;
	}
	
	/**
	 * Initialize the plugin
	 * 
	 * @see		http://codex.wordpress.org/Plugin_API/Action_Reference
	 * @see		http://adambrown.info/p/wp_hooks/hook/init
	 */
	function init() {
		global $chat_processed;
		
		$chat_processed = 0;
		
		if (preg_match('/mu\-plugin/', PLUGINDIR) > 0) {
			load_muplugin_textdomain($this->translation_domain, dirname(plugin_basename(__FILE__)).'/languages');
		} else {
			load_plugin_textdomain($this->translation_domain, false, dirname(plugin_basename(__FILE__)).'/languages');
		}
		
		wp_register_script('chat_soundmanager', plugins_url('wordpress-chat/js/soundmanager2-nodebug-jsmin.js'), array(), $this->chat_current_version, true);
		wp_register_script('jquery-cookie', plugins_url('wordpress-chat/js/jquery-cookie.js'), array('jquery'), true);
		wp_register_script('chat_js', plugins_url('wordpress-chat/js/chat.js'), array('jquery', 'jquery-cookie', 'chat_soundmanager'), $this->chat_current_version, true);
		
		if (is_admin()) {
			wp_register_script('farbtastic', plugins_url('wordpress-chat/js/farbtastic.js'), array('jquery'));
			wp_register_script('chat_admin_js', plugins_url('wordpress-chat/js/chat-admin.js'), array('jquery','jquery-cookie','jquery-ui-core','jquery-ui-tabs','farbtastic'), $this->chat_current_version, true);
			wp_register_style('chat_admin_css', plugins_url('wordpress-chat/css/wp_admin.css'));
		}
		
		if ((current_user_can('edit_posts') || current_user_can('edit_pages')) && get_user_option('rich_editing') == 'true') {
			add_filter("mce_external_plugins", array(&$this, "tinymce_add_plugin"));
			add_filter('mce_buttons', array(&$this,'tinymce_register_button'));
			add_filter('mce_external_languages', array(&$this,'tinymce_load_langs'));
		}
		
		if (in_array('facebook', $this->get_option('login_options', array('current_user'))) && (empty($_COOKIE['chat_stateless_user_type_104']))) {
			$this->authenticate_facebook();
		}
		// Need to stop any output until cookies are set
		ob_start();
	}
	
	/**
	 * Add the CSS (admin_head)
	 * 
	 * @see		http://codex.wordpress.org/Plugin_API/Action_Reference/admin_head-(plugin_page)
	 */
	function admin_styles() {
		wp_enqueue_style('chat_admin_css');
	}
	
	function admin_scripts() {
		wp_enqueue_script('jquery-cookie');
		wp_enqueue_script('farbtastic');
		wp_enqueue_script('chat_admin_js');
	}
	
	/**
	 * Add the admin menus
	 * 
	 * @see		http://codex.wordpress.org/Adding_Administration_Menus
	 */
	function admin_menu() {
		add_options_page(__('Chat Plugin Options', $this->translation_domain), __('Chat', $this->translation_domain), 'manage_options', 'chat', array(&$this, 'plugin_options'));
	}
	
	/**
	 * Is Twitter setup complete
	 * 
	 * @return	boolean			Is Twitter setup complete. true or false		
	 */
	function is_twitter_setup() {
		if ($this->get_option('twitter_api_key', '') != '') {
			return true;
		}
		return false;
	}
	
	/**
	 * Is Facebook setup complete
	 * 
	 * @todo	Validate the application ID and secret with Facebook
	 * 
	 * @return	boolean			Is Facebook setup complete. true or false		
	 */
	function is_facebook_setup() {
		if ($this->get_option('facebook_application_id', '') != '' && $this->get_option('facebook_application_secret', '') != '') {
			return true;
		}
		return false;
	}
	
	function get_footer() {
		global $chat_processed;
		
		if (!$chat_processed) {
			wp_deregister_script('jquery-cookie');
			wp_deregister_script('chat_soundmanager');
			wp_deregister_script('chat_js');
		}
	}
	
	/**
	 * TinyMCE dialog content
	 */
	function tinymce_options() {
		?>
		<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
		<html>
			<head>
				<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
				<script type="text/javascript" src="../wp-includes/js/tinymce/tiny_mce_popup.js?ver=327-1235"></script>
				<script type="text/javascript" src="../wp-includes/js/tinymce/utils/mctabs.js?ver=327-1235"></script>
				<script type="text/javascript" src="../wp-includes/js/tinymce/utils/validate.js?ver=327-1235"></script>
				
				<script type="text/javascript" src="../wp-includes/js/tinymce/utils/form_utils.js?ver=327-1235"></script>
				<script type="text/javascript" src="../wp-includes/js/tinymce/utils/editable_selects.js?ver=327-1235"></script>
				
				<script type="text/javascript" src="../wp-includes/js/jquery/jquery.js"></script>
				
				<script type="text/javascript">
					var default_options = {
						id: "<?php print $this->get_last_chat_id(); ?>",
						sound: "<?php print $this->get_option('sound', 'enabled'); ?>",
						avatar: "<?php print $this->get_option('avatar', 'enabled'); ?>",
						emoticons: "<?php print $this->get_option('emoticons', 'disabled'); ?>",
						date_show: "<?php print $this->get_option('date_show', 'disabled'); ?>",
						time_show: "<?php print $this->get_option('time_show', 'disabled'); ?>",
						width: "<?php print $this->get_option('width', ''); ?>",
						height: "<?php print $this->get_option('height', ''); ?>",
						background_color: "<?php print $this->get_option('background_color', '#ffffff'); ?>",
						background_highlighted_color: "<?php print $this->get_option('background_highlighted_color', '#FFE9AB'); ?>",
						date_color: "<?php print $this->get_option('date_color', '#6699CC'); ?>",
						name_color: "<?php print $this->get_option('name_color', '#666666'); ?>",
						moderator_name_color: "<?php print $this->get_option('moderator_name_color', '#6699CC'); ?>",
						text_color: "<?php print $this->get_option('text_color', '#000000'); ?>",
						font: "<?php print $this->get_option('font', ''); ?>",
						font_size: "<?php print $this->get_option('font_size', '12'); ?>",
						log_creation: "<?php print $this->get_option('log_creation', 'disabled'); ?>",
						log_display: "<?php print $this->get_option('log_display', 'disabled'); ?>",
						login_options: "<?php print join(',', $this->get_option('login_options', array('current_user'))); ?>",
						moderator_roles: "<?php print join(',', $this->get_option('moderator_roles', array('administrator','editor','author'))); ?>"
					};

					var current_options = {
						id: default_options.id+Math.floor(Math.random()*10),
						sound: "<?php print $this->get_option('sound', 'enabled'); ?>",
						avatar: "<?php print $this->get_option('avatar', 'enabled'); ?>",
						emoticons: "<?php print $this->get_option('emoticons', 'disabled'); ?>",
						date_show: "<?php print $this->get_option('date_show', 'disabled'); ?>",
						time_show: "<?php print $this->get_option('time_show', 'disabled'); ?>",
						width: "<?php print $this->get_option('width', ''); ?>",
						height: "<?php print $this->get_option('height', ''); ?>",
						background_color: "<?php print $this->get_option('background_color', '#ffffff'); ?>",
						background_highlighted_color: "<?php print $this->get_option('background_highlighted_color', '#FFE9AB'); ?>",
						date_color: "<?php print $this->get_option('date_color', '#6699CC'); ?>",
						name_color: "<?php print $this->get_option('name_color', '#666666'); ?>",
						moderator_name_color: "<?php print $this->get_option('moderator_name_color', '#6699CC'); ?>",
						text_color: "<?php print $this->get_option('text_color', '#000000'); ?>",
						font: "<?php print $this->get_option('font', ''); ?>",
						font_size: "<?php print $this->get_option('font_size', '12'); ?>",
						log_creation: "<?php print $this->get_option('log_creation', 'disabled'); ?>",
						log_display: "<?php print $this->get_option('log_display', 'disabled'); ?>",
						login_options: "<?php print join(',', $this->get_option('login_options', array('current_user'))); ?>",
						moderator_roles: "<?php print join(',', $this->get_option('moderator_roles', array('administrator','editor','author'))); ?>"
					};
					
					parts = tinyMCEPopup.editor.selection.getContent().replace(' ]', '').replace('[', '').split(' ');
					old_string = '';
					
					if (!(parts.length > 1 && parts[0] == 'chat')) {
						_tmp = tinyMCEPopup.editor.getContent().split('[chat ');
						if (_tmp.length > 1) {
							_tmp1 = _tmp[1].split(' ]');
							old_string = '[chat '+_tmp1[0]+' ]';
							parts = (old_string).replace(' ]', '').replace('[', '').split(' ');
						}
					}

					if (parts.length > 1 && parts[0] == 'chat') {
						current_options.id = parts[1].replace('id="', '').replace('"', '');

						for (i=2; i<parts.length; i++) {
							attr_parts = parts[i].split('=');
							if (attr_parts.length > 1) {
								current_options[attr_parts[0]] = attr_parts[1].replace('"', '').replace('"', '');
							}	
						}
					}

					var insertChat = function (ed) {
						output  ='[chat id="'+current_options.id+'" ';

						if (default_options.sound != jQuery.trim(jQuery('#chat_sound').val())) {
							output += 'sound="'+jQuery.trim(jQuery('#chat_sound').val())+'" ';
						}
						if (default_options.avatar != jQuery.trim(jQuery('#chat_avatar').val())) {
							output += 'avatar="'+jQuery.trim(jQuery('#chat_avatar').val())+'" ';
						}
						if (default_options.emoticons != jQuery.trim(jQuery('#chat_emoticons').val())) {
							output += 'emoticons="'+jQuery.trim(jQuery('#chat_emoticons').val())+'" ';
						}
						if (default_options.date_show != jQuery.trim(jQuery('#chat_date_show').val())) {
							output += 'date_show="'+jQuery.trim(jQuery('#chat_date_show').val())+'" ';
						}
						if (default_options.time_show != jQuery.trim(jQuery('#chat_time_show').val())) {
							output += 'time_show="'+jQuery.trim(jQuery('#chat_time_show').val())+'" ';
						}
						if (default_options.width != jQuery.trim(jQuery('#chat_width').val())) {
							output += 'width="'+jQuery.trim(jQuery('#chat_width').val())+'" ';
						}
						if (default_options.height != jQuery.trim(jQuery('#chat_height').val())) {
							output += 'height="'+jQuery.trim(jQuery('#chat_height').val())+'" ';
						}
						if (default_options.background_color != jQuery.trim(jQuery('#chat_background_color').val())) {
							output += 'background_color="'+jQuery.trim(jQuery('#chat_background_color').val())+'" ';
						}
						if (default_options.background_highlighted_color != jQuery.trim(jQuery('#chat_background_highlighted_color').val())) {
							output += 'background_highlighted_color="'+jQuery.trim(jQuery('#chat_background_highlighted_color').val())+'" ';
						}
						if (default_options.date_color != jQuery.trim(jQuery('#chat_date_color').val())) {
							output += 'date_color="'+jQuery.trim(jQuery('#chat_date_color').val())+'" ';
						}
						if (default_options.name_color != jQuery.trim(jQuery('#chat_name_color').val())) {
							output += 'name_color="'+jQuery.trim(jQuery('#chat_name_color').val())+'" ';
						}
						if (default_options.moderator_name_color != jQuery.trim(jQuery('#chat_moderator_name_color').val())) {
							output += 'moderator_name_color="'+jQuery.trim(jQuery('#chat_moderator_name_color').val())+'" ';
						}
						if (default_options.text_color != jQuery.trim(jQuery('#chat_text_color').val())) {
							output += 'text_color="'+jQuery.trim(jQuery('#chat_text_color').val())+'" ';
						}
						if (default_options.font != jQuery.trim(jQuery('#chat_font').val())) {
							output += 'font="'+jQuery.trim(jQuery('#chat_font').val())+'" ';
						}
						if (default_options.font_size != jQuery.trim(jQuery('#chat_font_size').val())) {
							output += 'font_size="'+jQuery.trim(jQuery('#chat_font_size').val())+'" ';
						}
						if (default_options.log_creation != jQuery.trim(jQuery('#chat_log_creation').val())) {
							output += 'log_creation="'+jQuery.trim(jQuery('#chat_log_creation').val())+'" ';
						}
						if (default_options.log_display != jQuery.trim(jQuery('#chat_log_display').val())) {
							output += 'log_display="'+jQuery.trim(jQuery('#chat_log_display').val())+'" ';
						}
						var chat_login_options_arr = [];
						jQuery('input[name=chat_login_options]:checked').each(function() {
							chat_login_options_arr.push(jQuery(this).val());
						});
						if (default_options.login_options != jQuery.trim(chat_login_options_arr.join(','))) {
							output += 'login_options="'+jQuery.trim(chat_login_options_arr.join(','))+'" ';
						}
						
						var chat_moderator_roles_arr = [];
						jQuery('input[name=chat_moderator_roles]:checked').each(function() {
							chat_moderator_roles_arr.push(jQuery(this).val());
						});
						if (default_options.moderator_roles != jQuery.trim(chat_moderator_roles_arr.join(','))) {
							output += 'moderator_roles="'+jQuery.trim(chat_moderator_roles_arr.join(','))+'" ';
						}
						
						output += ']';
						
						if (old_string == '') {
							tinyMCEPopup.execCommand('mceReplaceContent', false, output);
						} else {
							tinyMCEPopup.execCommand('mceSetContent', false, tinyMCEPopup.editor.getContent().replace(old_string, output));
						}

						// Return
						tinyMCEPopup.close();
					};
				</script>
				<style type="text/css">
				td.info {
					vertical-align: top;
					color: #777;
				}
				</style>

				<title>{#chat_dlg.title}</title>
			</head>
			<body style="display: none">
				<form onsubmit="insertChat();return false;" action="#">
					<div class="tabs">
						<ul>
							<li id="general_tab" class="current"><span><a href="javascript:mcTabs.displayTab('general_tab','general_panel');generatePreview();" onmousedown="return false;">{#chat_dlg.general}</a></span></li>
							<li id="appearance_tab"><span><a href="javascript:mcTabs.displayTab('appearance_tab','appearance_panel');" onmousedown="return false;">{#chat_dlg.appearance}</a></span></li>
							<li id="logs_tab"><span><a href="javascript:mcTabs.displayTab('logs_tab','logs_panel');" onmousedown="return false;">{#chat_dlg.logs}</a></span></li>
							<li id="authentication_tab"><span><a href="javascript:mcTabs.displayTab('authentication_tab','authentication_panel');" onmousedown="return false;">{#chat_dlg.authentication}</a></span></li>
						</ul>
					</div>
			
					<div class="panel_wrapper">
						<div id="general_panel" class="panel current">
							<fieldset>
								<legend>{#chat_dlg.general}</legend>
			
								<table border="0" cellpadding="4" cellspacing="0">
									<tr>
										<td><label for="chat_sound">{#chat_dlg.sound}</label></td>
										<td>
											<select id="chat_sound" name="chat_sound" >
												<option value="enabled" <?php print ($this->get_option('sound', 'enabled') == 'enabled')?'selected="selected"':''; ?>>{#chat_dlg.enabled}</option>
												<option value="disabled" <?php print ($this->get_option('sound', 'enabled') == 'disabled')?'selected="selected"':''; ?>>{#chat_dlg.disabled}</option>
											</select>
										</td>
										<td class="info"><?php _e("Play sound when a new message is received?", $this->translation_domain); ?></td>
									</tr>
									
									
									<tr>
										<td><label for="chat_avatar">{#chat_dlg.avatar}</label></td>
										<td>
											<select id="chat_avatar" name="chat_avatar" >
												<option value="enabled" <?php print ($this->get_option('avatar', 'enabled') == 'enabled')?'selected="selected"':''; ?>>{#chat_dlg.enabled}</option>
												<option value="disabled" <?php print ($this->get_option('avatar', 'enabled') == 'disabled')?'selected="selected"':''; ?>>{#chat_dlg.disabled}</option>
											</select>
										</td>
										<td class="info"><?php _e("Display the user's avatar with the message?", $this->translation_domain); ?></td>
									</tr>
									
									<tr>
										<td><label for="chat_emoticons">{#chat_dlg.emoticons}</label></td>
										<td>
											<select id="chat_emoticons" name="chat_emoticons" >
												<option value="enabled" <?php print ($this->get_option('emoticons', 'disabled') == 'enabled')?'selected="selected"':''; ?>>{#chat_dlg.enabled}</option>
												<option value="disabled" <?php print ($this->get_option('emoticons', 'disabled') == 'disabled')?'selected="selected"':''; ?>>{#chat_dlg.disabled}</option>
											</select>
										</td>
										<td class="info"><?php _e("Display emoticons bar?", $this->translation_domain); ?></td>
									</tr>
									
									<tr>
										<td><label for="chat_date_show">{#chat_dlg.show_date}</label></td>
										<td>
											<select id="chat_date_show" name="chat_date_show" >
												<option value="enabled" <?php print ($this->get_option('date_show', 'disabled') == 'enabled')?'selected="selected"':''; ?>>{#chat_dlg.enabled}</option>
												<option value="disabled" <?php print ($this->get_option('date_show', 'disabled') == 'disabled')?'selected="selected"':''; ?>>{#chat_dlg.disabled}</option>
											</select>
										</td>
										<td class="info"><?php _e("Display date the message was sent?", $this->translation_domain); ?></td>
									</tr>
									
									<tr>
										<td><label for="chat_time_show">{#chat_dlg.show_time}</label></td>
										<td>
											<select id="chat_time_show" name="chat_time_show" >
												<option value="enabled" <?php print ($this->get_option('time_show', 'disabled') == 'enabled')?'selected="selected"':''; ?>>{#chat_dlg.enabled}</option>
												<option value="disabled" <?php print ($this->get_option('time_show', 'disabled') == 'disabled')?'selected="selected"':''; ?>>{#chat_dlg.disabled}</option>
											</select>
										</td>
										<td class="info"><?php _e("Display the time  the message was sent?", $this->translation_domain); ?></td>
									</tr>
			
									<tr>
										<td><label for="chat_width">{#chat_dlg.dimensions}</label></td>
										<td>
											<input type="text" id="chat_width" name="chat_width" value="<?php print $this->get_option('width', ''); ?>" class="size" size="5" /> x
											<input type="text" id="chat_height" name="chat_height" value="<?php print $this->get_option('height', ''); ?>" class="size" size="5" />
										</td>
										<td class="info"><?php _e("Dimensions of the chat box", $this->translation_domain); ?></td>
									</tr>
								</table>
							</fieldset>
						</div>
							
						<div id="appearance_panel" class="panel">
							<fieldset>
								<legend>{#chat_dlg.colors}</legend>
			
								<table border="0" cellpadding="4" cellspacing="0">
									<tr>
										<td><label for="chat_background_color">{#chat_dlg.background}</label></td>
										<td>
											<input type="text" id="chat_background_color" name="chat_background_color" value="<?php print $this->get_option('background_color', '#ffffff'); ?>" class="color" size="7" />
											<div class="color" id="chat_background_color_panel"></div>
										</td>
										<td class="info"><?php _e("Chat box background color", $this->translation_domain); ?></td>
									</tr>
									
									<tr>
										<td><label for="chat_background_highlighted_color">{#chat_dlg.background_highlighted}</label></td>
										<td>
											<input type="text" id="chat_background_highlighted_color" name="chat_background_highlighted_color" value="<?php print $this->get_option('background_highlighted_color', '#ffe9ab'); ?>" class="color" size="7" />
											<div class="color" id="chat_background_highlighted_color_panel"></div>
										</td>
										<td class="info"><?php _e("Chat box background color when there is a new message", $this->translation_domain); ?></td>
									</tr>
										
									<tr>
										<td><label for="chat_date_color">{#chat_dlg.date}</label></td>
										<td>
											<input type="text" id="chat_date_color" name="chat_date_color" value="<?php print $this->get_option('date_color', '#6699CC'); ?>" class="color" size="7" />
											<div class="color" id="chat_date_color_panel"></div>
										</td>
										<td class="info"><?php _e("Date background color", $this->translation_domain); ?></td>
									</tr>
										
									<tr>
										<td><label for="chat_name_color">{#chat_dlg.name}</label></td>
										<td>
											<input type="text" id="chat_name_color" name="chat_name_color" value="<?php print $this->get_option('name_color', '#666666'); ?>" class="color" size="7" />
											<div class="color" id="chat_name_color_panel"></div>
										</td>
										<td class="info"><?php _e("Name background color", $this->translation_domain); ?></td>
									</tr>
									
									<tr>
										<td><label for="chat_moderator_name_color">{#chat_dlg.moderator_name}</label></td>
										<td>
											<input type="text" id="chat_moderator_name_color" name="chat_moderator_name_color" value="<?php print $this->get_option('moderator_name_color', '#6699CC'); ?>" class="color" size="7" />
											<div class="color" id="chat_moderator_name_color_panel"></div>
										</td>
										<td class="info"><?php _e("Moderator Name background color", $this->translation_domain); ?></td>
									</tr>
									
									<tr>
										<td><label for="chat_text_color">{#chat_dlg.text}</label></td>
										<td>
											<input type="text" id="chat_text_color" name="chat_text_color" value="<?php print $this->get_option('text_color', '#000000'); ?>" class="color" size="7" />
											<div class="color" id="chat_text_color_panel"></div>
										</td>
										<td class="info"><?php _e("Text color", $this->translation_domain); ?></td>
									</tr>
								</table>
							</fieldset>
							
							<fieldset>
								<legend>{#chat_dlg.fonts}</legend>
			
								<table border="0" cellpadding="4" cellspacing="0">
			
									<tr>
										<td><label for="chat_font">{#chat_dlg.font}</label></td>
										<td>
											<select id="chat_font" name="chat_font" class="font" >
											<?php foreach ($this->fonts_list as $font_name => $font) { ?>
												<option value="<?php print $font; ?>" <?php print ($this->get_option('font', '') == $font)?'selected="selected"':''; ?>" ><?php print $font_name; ?></option>
											<?php } ?>
											</select>
										</td>
										<td class="info"><?php _e("Chat box font", $this->translation_domain); ?></td>
									</tr>
									
									<tr>
										<td><label for="chat_font_size">{#chat_dlg.font_size}</label></td>
										<td>
											<select id="chat_font_size" name="chat_font_size" class="font_size" >
											<?php for ($font_size=8; $font_size<21; $font_size++) { ?>
												<option value="<?php print $font_size; ?>" <?php print ($this->get_option('font_size', '12') == $font_size)?'selected="selected"':''; ?> ><?php print $font_size; ?></option>
											<?php } ?>
											</select> px
										</td>
										<td class="info"><?php _e("Chat box font size", $this->translation_domain); ?></td>
									</tr>
								</table>
							</fieldset>
						</div>
						
						<div id="logs_panel" class="panel">
							<fieldset>
								<legend>{#chat_dlg.logs}</legend>
								
								<table border="0" cellpadding="4" cellspacing="0">
									<tr>
										<td><label for="chat_log_creation">{#chat_dlg.creation}</label></td>
										<td>
											<select id="chat_log_creation" name="chat_log_creation" >
												<option value="enabled" <?php print ($this->get_option('log_creation', 'disabled') == 'enabled')?'selected="selected"':''; ?>>{#chat_dlg.enabled}</option>
												<option value="disabled" <?php print ($this->get_option('log_creation', 'disabled') == 'disabled')?'selected="selected"':''; ?>>{#chat_dlg.disabled}</option>
											</select>
										</td>
										<td class="info"><?php _e("Log chat messages?", $this->translation_domain); ?></td>
									</tr>
									
									<tr>
										<td><label for="chat_log_display">{#chat_dlg.display}</label></td>
										<td>
											<select id="chat_log_display" name="chat_log_display" >
												<option value="enabled" <?php print ($this->get_option('log_display', 'disabled') == 'enabled')?'selected="selected"':''; ?>>{#chat_dlg.enabled}</option>
												<option value="disabled" <?php print ($this->get_option('log_display', 'disabled') == 'disabled')?'selected="selected"':''; ?>>{#chat_dlg.disabled}</option>
											</select>
										</td>
										<td class="info"><?php _e("Display chat logs?", $this->translation_domain); ?></td>
									</tr>
								</table>
							</fieldset>
						</div>
					
						<div id="authentication_panel" class="panel">
							<fieldset>
								<legend>{#chat_dlg.authentication}</legend>
								
								<table border="0" cellpadding="4" cellspacing="0">
									<tr>
										<td valign="top"><label for="chat_login_options">{#chat_dlg.login_options}</label></td>
										<td>
											<label><input type="checkbox" id="chat_login_options_current_user" name="chat_login_options" class="chat_login_options" value="current_user" <?php print (in_array('current_user', $this->get_option('login_options', array('current_user'))) > 0)?'checked="checked"':''; ?> /> <?php _e('Current user', $this->translation_domain); ?></label><br/>
											<?php if (is_multisite()) { ?>
											<label><input type="checkbox" id="chat_login_options_network_user" name="chat_login_options" class="chat_login_options" value="network_user" <?php print (in_array('network_user', $this->get_option('login_options', array('current_user'))) > 0)?'checked="checked"':''; ?> /> <?php _e('Network user', $this->translation_domain); ?></label><br/>
											<?php } ?>
											<label><input type="checkbox" id="chat_login_options_public_user" name="chat_login_options" class="chat_login_options" value="public_user" <?php print (in_array('public_user', $this->get_option('login_options', array('current_user'))) > 0)?'checked="checked"':''; ?> /> <?php _e('Public user', $this->translation_domain); ?></label><br/>
											<?php if ($this->is_twitter_setup()) { ?>
											<label><input type="checkbox" id="chat_login_options_twitter" name="chat_login_options" class="chat_login_options" value="twitter" <?php print (!$this->is_twitter_setup())?'disabled="disabled"':''; ?> <?php print (in_array('twitter', $this->get_option('login_options', array('current_user'))) > 0)?'checked="checked"':''; ?> /> <?php _e('Twitter', $this->translation_domain); ?></label><br/>
											<?php } ?>
											<?php if ($this->is_facebook_setup()) { ?>
											<label><input type="checkbox" id="chat_login_options_facebook" name="chat_login_options" class="chat_login_options" value="facebook" <?php print (!$this->is_facebook_setup())?'disabled="disabled"':''; ?> <?php print (in_array('facebook', $this->get_option('login_options', array('current_user'))) > 0)?'checked="checked"':''; ?> /> <?php _e('Facebook', $this->translation_domain); ?></label><br/>
											<?php } ?>
										</td>
										<td class="info"><?php _e("Authentication methods users can use", $this->translation_domain); ?></td>
									</tr>
									<tr>
										<td valign="top"><label for="chat_moderator_roles">{#chat_dlg.moderator_roles}</label></td>
										<td>
											<?php
											foreach (get_editable_roles() as $role => $details) {
												$name = translate_user_role($details['name'] );
											?>
											<label><input type="checkbox" id="chat_moderator_roles_<?php print $role; ?>" name="chat_moderator_roles" class="chat_moderator_roles" value="<?php print $role; ?>" <?php print (in_array($role, $this->get_option('moderator_roles', array('administrator','editor','author'))) > 0)?'checked="checked"':''; ?> /> <?php _e($name, $this->translation_domain); ?></label><br/>
											<?php 
											}
											?>
										</td>
										<td class="info"><?php _e("Select which roles are moderators", $this->translation_domain); ?></td>
									</tr>
								</table>
							</fieldset>
						</div>
					</div>
			
					<div class="mceActionPanel">
						<div style="float: left">
							<input type="button" id="cancel" name="cancel" value="{#cancel}" onclick="tinyMCEPopup.close();" />
						</div>
			
						<div style="float: right">
							<input type="submit" id="insert" name="insert" value="{#insert}" />
						</div>
					</div>
				</form>
				<script type="text/javascript">
					jQuery(window).load(function() {
						for (attr in current_options) {
							if (attr == "id") continue;
	
							if (current_options[attr].match(',')) {
								jQuery("#chat_"+attr).val(current_options[attr].split(','));
							} else {
								jQuery("#chat_"+attr).val(current_options[attr]);
							}
						}
					});
				</script>
			</body>
		</html>
		<?php
		exit(0);
	}
	
	function whitelist_options($options) {
		$added = array( 'chat' => array( 'chat_default', 'chat_site' ) );
		$options = add_option_whitelist( $added, $options );
		return $options;
	}
	
	/**
	 * Plugin options
	 */
	function plugin_options() {	
		?>
		<div class="wrap">
		<h2><?php _e('Chat Settings', $this->translation_domain); ?></h2>
		<form method="post" action="options.php">
			<?php settings_fields('chat'); ?>
		
			<div id="chat_tab_pane" class="chat_tab_pane">
				<ul>
					<li><a href="#chat_default_panel"><span><?php _e('In post chat options', $this->translation_domain); ?></span></a></li>
					<!--li><a href="#chat_site_panel"><span><?php _e('Bottom corner chat', $this->translation_domain); ?></span></a></li-->
					<li><a href="#chat_twitter_api_panel"><span><?php _e('Twitter API', $this->translation_domain); ?></span></a></li>
					<li><a href="#chat_facebook_api_panel"><span><?php _e('Facebook API', $this->translation_domain); ?></span></a></li>
					<li><a href="#chat_advanced_panel"><span><?php _e('Advanced', $this->translation_domain); ?></span></a></li>
				</ul>
				
				<div id="chat_default_panel" class="chat_panel current">
					<p class="info">Default options for in post chat boxes.</p>
					<fieldset>
						<legend><?php _e('General', $this->translation_domain); ?></legend>
		
						<table border="0" cellpadding="4" cellspacing="0">
							<!--tr>
								<td><label for="chat_sound"><?php _e('Sound', $this->translation_domain); ?></label></td>
								<td>
									<select id="chat_sound" name="chat_default[sound]" >
										<option value="enabled" <?php print ($this->get_option('sound', 'enabled') == 'enabled')?'selected="selected"':''; ?>><?php _e('Enabled', $this->translation_domain); ?></option>
										<option value="disabled" <?php print ($this->get_option('sound', 'enabled') == 'disabled')?'selected="selected"':''; ?>><?php _e('Disabled', $this->translation_domain); ?></option>
									</select>
								</td>
								<td class="info"><?php _e("Play sound when a new message is received?", $this->translation_domain); ?></td>
							</tr-->	
								
							<tr>
								<td><label for="chat_avatar"><?php _e('Avatar', $this->translation_domain); ?></label></td>
								<td>
									<select id="chat_avatar" name="chat_default[avatar]" >
										<option value="enabled" <?php print ($this->get_option('avatar', 'enabled') == 'enabled')?'selected="selected"':''; ?>><?php _e('Enabled', $this->translation_domain); ?></option>
										<option value="disabled" <?php print ($this->get_option('avatar', 'enabled') == 'disabled')?'selected="selected"':''; ?>><?php _e('Disabled', $this->translation_domain); ?></option>
									</select>
								</td>
								<td class="info"><?php _e("Display the user's avatar with the message?", $this->translation_domain); ?></td>
							</tr>
								
							<tr>
								<td><label for="chat_emoticons"><?php _e('Emoticons', $this->translation_domain); ?></label></td>
								<td>
									<select id="chat_emoticons" name="chat_default[emoticons]" >
										<option value="enabled" <?php print ($this->get_option('emoticons', 'disabled') == 'enabled')?'selected="selected"':''; ?>><?php _e('Enabled', $this->translation_domain); ?></option>
										<option value="disabled" <?php print ($this->get_option('emoticons', 'disabled') == 'disabled')?'selected="selected"':''; ?>><?php _e('Disabled', $this->translation_domain); ?></option>
									</select>
								</td>
								<td class="info"><?php _e("Display emoticons bar?", $this->translation_domain); ?></td>
							</tr>
							
							<tr>
								<td><label for="chat_date_show"><?php _e('Show date', $this->translation_domain); ?></label></td>
								<td>
									<select id="chat_date_show" name="chat_default[date_show]" >
										<option value="enabled" <?php print ($this->get_option('date_show', 'disabled') == 'enabled')?'selected="selected"':''; ?>><?php _e('Enabled', $this->translation_domain); ?></option>
										<option value="disabled" <?php print ($this->get_option('date_show', 'disabled') == 'disabled')?'selected="selected"':''; ?>><?php _e('Disabled', $this->translation_domain); ?></option>
									</select>
								</td>
								<td class="info"><?php _e("Display date the message was sent?", $this->translation_domain); ?></td>
							</tr>
								
							<tr>
								<td><label for="chat_time_show"><?php _e('Show time', $this->translation_domain); ?></label></td>
								<td>
									<select id="chat_time_show" name="chat_default[time_show]" >
										<option value="enabled" <?php print ($this->get_option('time_show', 'disabled') == 'enabled')?'selected="selected"':''; ?>><?php _e('Enabled', $this->translation_domain); ?></option>
										<option value="disabled" <?php print ($this->get_option('time_show', 'disabled') == 'disabled')?'selected="selected"':''; ?>><?php _e('Disabled', $this->translation_domain); ?></option>
									</select>
								</td>
								<td class="info"><?php _e("Display the time  the message was sent?", $this->translation_domain); ?></td>
							</tr>
		
							<tr>
								<td><label for="chat_width"><?php _e('Dimensions', $this->translation_domain); ?></label></td>
								<td>
									<input type="text" id="chat_width" name="chat_default[width]" value="<?php print $this->get_option('width', '100%'); ?>" class="size" size="5" /> x
									<input type="text" id="chat_height" name="chat_default[height]" value="<?php print $this->get_option('height', '425px'); ?>" class="size" size="5" />
								</td>
								<td class="info"><?php _e("Dimensions of the chat box", $this->translation_domain); ?></td>
							</tr>
						</table>
					</fieldset>
					
					<fieldset>
						<legend><?php _e('Colors', $this->translation_domain); ?></legend>
		
						<table border="0" cellpadding="4" cellspacing="0">
							<tr>
								<td><label for="chat_background_color"><?php _e('Background', $this->translation_domain); ?></label></td>
								<td>
									<input type="text" id="chat_background_color" name="chat_default[background_color]" value="<?php print $this->get_option('background_color', '#ffffff'); ?>" class="color" size="7" />
									<div class="color" id="chat_background_color_panel"></div>
								</td>
								<td class="info"><?php _e("Chat box background color", $this->translation_domain); ?></td>
							</tr>
							
							<tr>
								<td><label for="chat_background_highlighted_color"><?php _e('Highlighted Background', $this->translation_domain); ?></label></td>
								<td>
									<input type="text" id="chat_background_highlighted_color" name="chat_default[background_highlighted_color]" value="<?php print $this->get_option('background_highlighted_color', '#FFE9AB'); ?>" class="color" size="7" />
									<div class="color" id="chat_background_highlighted_color_panel"></div>
								</td>
								<td class="info"><?php _e("Chat box background color when there is a new message", $this->translation_domain); ?></td>
							</tr>
									
							<tr>
								<td><label for="chat_date_color"><?php _e('Date', $this->translation_domain); ?></label></td>
								<td>
									<input type="text" id="chat_date_color" name="chat_default[date_color]" value="<?php print $this->get_option('date_color', '#6699CC'); ?>" class="color" size="7" />
									<div class="color" id="chat_date_color_panel"></div>
								</td>
								<td class="info"><?php _e("Date and time background color", $this->translation_domain); ?></td>
							</tr>
							
							<tr>
								<td><label for="chat_name_color"><?php _e('Name', $this->translation_domain); ?></label></td>
								<td>
									<input type="text" id="chat_name_color" name="chat_default[name_color]" value="<?php print $this->get_option('name_color', '#666666'); ?>" class="color" size="7" />
									<div class="color" id="chat_name_color_panel"></div>
								</td>
								<td class="info"><?php _e("Name background color", $this->translation_domain); ?></td>
							</tr>
							
							<tr>
								<td><label for="chat_moderator_name_color"><?php _e('Moderator Name', $this->translation_domain); ?></label></td>
								<td>
									<input type="text" id="chat_moderator_name_color" name="chat_default[moderator_name_color]" value="<?php print $this->get_option('moderator_name_color', '#6699CC'); ?>" class="color" size="7" />
									<div class="color" id="chat_moderator_name_color_panel"></div>
								</td>
								<td class="info"><?php _e("Moderator Name background color", $this->translation_domain); ?></td>
							</tr>
							
							<tr>
								<td><label for="chat_text_color"><?php _e('Text', $this->translation_domain); ?></label></td>
								<td>
									<input type="text" id="chat_text_color" name="chat_default[text_color]" value="<?php print $this->get_option('text_color', '#000000'); ?>" class="color" size="7" />
									<div class="color" id="chat_text_color_panel"></div>
								</td>
								<td class="info"><?php _e("Text color", $this->translation_domain); ?></td>
							</tr>
						</table>
					</fieldset>
					
					<fieldset>
						<legend><?php _e('Fonts', $this->translation_domain); ?></legend>
		
						<table border="0" cellpadding="4" cellspacing="0">
							<tr>
								<td><label for="chat_font"><?php _e('Font', $this->translation_domain); ?></label></td>
								<td>
									<select id="chat_font" name="chat_default[font]" class="font" >
									<?php foreach ($this->fonts_list as $font_name => $font) { ?>
										<option value="<?php print $font; ?>" <?php print ($this->get_option('font', '') == $font)?'selected="selected"':''; ?>" ><?php print $font_name; ?></option>
									<?php } ?>
									</select>
								</td>
								<td class="info"><?php _e("Chat box font", $this->translation_domain); ?></td>
							</tr>
								
							<tr>
								<td><label for="chat_font_size"><?php _e('Font size', $this->translation_domain); ?></label></td>
								<td>
									<select id="chat_font_size" name="chat_default[font_size]" class="font_size" >
									<?php for ($font_size=8; $font_size<21; $font_size++) { ?>
										<option value="<?php print $font_size; ?>" <?php print ($this->get_option('font_size', '12') == $font_size)?'selected="selected"':''; ?>" ><?php print $font_size; ?></option>
									<?php } ?>
									</select> px
								</td>
								<td class="info"><?php _e("Chat box font size", $this->translation_domain); ?></td>
							</tr>
						</table>
					</fieldset>
					
					<fieldset>
						<legend><?php _e('Logs', $this->translation_domain); ?></legend>
							
						<table border="0" cellpadding="4" cellspacing="0">
							<tr>
								<td><label for="chat_log_creation"><?php _e('Creation', $this->translation_domain); ?></label></td>
								<td>
									<select id="chat_log_creation" name="chat_default[log_creation]" >
										<option value="enabled" <?php print ($this->get_option('log_creation', 'enabled') == 'enabled')?'selected="selected"':''; ?>><?php _e('Enabled', $this->translation_domain); ?></option>
										<option value="disabled" <?php print ($this->get_option('log_creation', 'enabled') == 'disabled')?'selected="selected"':''; ?>><?php _e('Disabled', $this->translation_domain); ?></option>
									</select>
								</td>
								<td class="info"><?php _e("Log chat messages?", $this->translation_domain); ?></td>
							</tr>
								
							<tr>
								<td><label for="chat_log_display"><?php _e('Display', $this->translation_domain); ?></label></td>
								<td>
									<select id="chat_log_display" name="chat_default[log_display]" >
										<option value="enabled" <?php print ($this->get_option('log_display', 'enabled') == 'enabled')?'selected="selected"':''; ?>><?php _e('Enabled', $this->translation_domain); ?></option>
										<option value="disabled" <?php print ($this->get_option('log_display', 'enabled') == 'disabled')?'selected="selected"':''; ?>><?php _e('Disabled', $this->translation_domain); ?></option>
									</select>
								</td>
								<td class="info"><?php _e("Display chat logs?", $this->translation_domain); ?></td>
							</tr>
						</table>
					</fieldset>
					
					<fieldset>
						<legend><?php _e('Authentication', $this->translation_domain); ?></legend>
							
						<table border="0" cellpadding="4" cellspacing="0">
							<tr>
								<td valign="top"><label for="chat_login_options"><?php _e('Login options', $this->translation_domain); ?></label></td>
								<td>
									<label><input type="checkbox" id="chat_login_options_current_user" name="chat_default[login_options][]" class="chat_login_options" value="current_user" <?php print (in_array('current_user', $this->get_option('login_options', array('current_user'))) > 0)?'checked="checked"':''; ?> /> <?php _e('Current user', $this->translation_domain); ?></label><br/>
									<?php if (is_multisite()) { ?>
									<label><input type="checkbox" id="chat_login_options_network_user" name="chat_default[login_options][]" class="chat_login_options" value="network_user" <?php print (in_array('network_user', $this->get_option('login_options', array('current_user'))) > 0)?'checked="checked"':''; ?> /> <?php _e('Network user', $this->translation_domain); ?></label><br/>
									<?php } ?>
									<label><input type="checkbox" id="chat_login_options_public_user" name="chat_default[login_options][]" class="chat_login_options" value="public_user" <?php print (in_array('public_user', $this->get_option('login_options', array('current_user'))) > 0)?'checked="checked"':''; ?> /> <?php _e('Public user', $this->translation_domain); ?></label><br/>
									<label><input type="checkbox" id="chat_login_options_twitter" name="chat_default[login_options][]" class="chat_login_options" value="twitter" <?php print (!$this->is_twitter_setup())?'disabled="disabled"':''; ?> <?php print (in_array('twitter', $this->get_option('login_options', array('current_user'))) > 0)?'checked="checked"':''; ?> /> <?php _e('Twitter', $this->translation_domain); ?></label><br/>
									<label><input type="checkbox" id="chat_login_options_facebook" name="chat_default[login_options][]" class="chat_login_options" value="facebook" <?php print (!$this->is_facebook_setup())?'disabled="disabled"':''; ?> <?php print (in_array('facebook', $this->get_option('login_options', array('current_user'))) > 0)?'checked="checked"':''; ?> /> <?php _e('Facebook', $this->translation_domain); ?></label><br/>
								</td>
								<td class="info"><?php _e("Authentication methods users can use", $this->translation_domain); ?></td>
							</tr>
							
							<tr>
								<td valign="top"><label for="chat_moderator_roles"><?php _e('Moderator roles', $this->translation_domain); ?></label></td>
								<td>
									<?php
									foreach (get_editable_roles() as $role => $details) {
										$name = translate_user_role($details['name'] );
									?>
									<label><input type="checkbox" id="chat_moderator_roles_<?php print $role; ?>" name="chat_default[moderator_roles][]" class="chat_moderator_roles" value="<?php print $role; ?>" <?php print (in_array($role, $this->get_option('moderator_roles', array('administrator','editor','author'))) > 0)?'checked="checked"':''; ?> /> <?php _e($name, $this->translation_domain); ?></label><br/>
									<?php 
									}
									?>
								</td>
								<td class="info"><?php _e("Select which roles are moderators", $this->translation_domain); ?></td>
							</tr>
						</table>
					</fieldset>
				</div>
				
				<!-- div id="chat_site_panel" class="chat_panel current">
					<p class="info">Options for the bottom corner chat</p>
					<fieldset>
						<legend><?php _e('Main', $this->translation_domain); ?></legend>
		
						<table border="0" cellpadding="4" cellspacing="0">
							<tr>
								<td><label for="chat_site_1"><?php _e('Show', $this->translation_domain); ?></label></td>
								<td>
									<select id="chat_site_1" name="chat_site[site]" >
										<option value="enabled" <?php print ($this->get_option('site', 'enabled', 'site') == 'enabled')?'selected="selected"':''; ?>><?php _e('Enabled', $this->translation_domain); ?></option>
										<option value="disabled" <?php print ($this->get_option('site', 'enabled', 'site') == 'disabled')?'selected="selected"':''; ?>><?php _e('Disabled', $this->translation_domain); ?></option>
									</select>
								</td>
								<td class="info"><?php _e("Display bottom corner chat?", $this->translation_domain); ?></td>
							</tr>
						</table>
					</fieldset>
					
					<fieldset>
						<legend><?php _e('General', $this->translation_domain); ?></legend>
		
						<table border="0" cellpadding="4" cellspacing="0">
							
							<tr>
								<td><label for="chat_sound_1"><?php _e('Sound', $this->translation_domain); ?></label></td>
								<td>
									<select id="chat_sound_1" name="chat_site[sound]" >
										<option value="enabled" <?php print ($this->get_option('sound', 'enabled', 'site') == 'enabled')?'selected="selected"':''; ?>><?php _e('Enabled', $this->translation_domain); ?></option>
										<option value="disabled" <?php print ($this->get_option('sound', 'enabled', 'site') == 'disabled')?'selected="selected"':''; ?>><?php _e('Disabled', $this->translation_domain); ?></option>
									</select>
								</td>
								<td class="info"><?php _e("Play sound when a new message is received?", $this->translation_domain); ?></td>
							</tr>	
								
							<tr>
								<td><label for="chat_avatar_1"><?php _e('Avatar', $this->translation_domain); ?></label></td>
								<td>
									<select id="chat_avatar_1" name="chat_site[avatar]" >
										<option value="enabled" <?php print ($this->get_option('avatar', 'enabled', 'site') == 'enabled')?'selected="selected"':''; ?>><?php _e('Enabled', $this->translation_domain); ?></option>
										<option value="disabled" <?php print ($this->get_option('avatar', 'enabled', 'site') == 'disabled')?'selected="selected"':''; ?>><?php _e('Disabled', $this->translation_domain); ?></option>
									</select>
								</td>
								<td class="info"><?php _e("Display the user's avatar with the message?", $this->translation_domain); ?></td>
							</tr>
								
							<tr>
								<td><label for="chat_emoticons_1"><?php _e('Emoticons', $this->translation_domain); ?></label></td>
								<td>
									<select id="chat_emoticons_1" name="chat_site[emoticons]" >
										<option value="enabled" <?php print ($this->get_option('emoticons', 'enabled', 'site') == 'enabled')?'selected="selected"':''; ?>><?php _e('Enabled', $this->translation_domain); ?></option>
										<option value="disabled" <?php print ($this->get_option('emoticons', 'enabled', 'site') == 'disabled')?'selected="selected"':''; ?>><?php _e('Disabled', $this->translation_domain); ?></option>
									</select>
								</td>
								<td class="info"><?php _e("Display emoticons bar?", $this->translation_domain); ?></td>
							</tr>
							
							<tr>
								<td><label for="chat_date_show_1"><?php _e('Show date', $this->translation_domain); ?></label></td>
								<td>
									<select id="chat_date_show_1" name="chat_site[date_show]" >
										<option value="enabled" <?php print ($this->get_option('date_show', 'enabled', 'site') == 'enabled')?'selected="selected"':''; ?>><?php _e('Enabled', $this->translation_domain); ?></option>
										<option value="disabled" <?php print ($this->get_option('date_show', 'enabled', 'site') == 'disabled')?'selected="selected"':''; ?>><?php _e('Disabled', $this->translation_domain); ?></option>
									</select>
								</td>
								<td class="info"><?php _e("Display date the message was sent?", $this->translation_domain); ?></td>
							</tr>
								
							<tr>
								<td><label for="chat_time_show_1"><?php _e('Show time', $this->translation_domain); ?></label></td>
								<td>
									<select id="chat_time_show_1" name="chat_site[time_show]" >
										<option value="enabled" <?php print ($this->get_option('time_show', 'enabled', 'site') == 'enabled')?'selected="selected"':''; ?>><?php _e('Enabled', $this->translation_domain); ?></option>
										<option value="disabled" <?php print ($this->get_option('time_show', 'enabled', 'site') == 'disabled')?'selected="selected"':''; ?>><?php _e('Disabled', $this->translation_domain); ?></option>
									</select>
								</td>
								<td class="info"><?php _e("Display the time  the message was sent?", $this->translation_domain); ?></td>
							</tr>
		
							<tr>
								<td><label for="chat_width_1"><?php _e('Dimensions', $this->translation_domain); ?></label></td>
								<td>
									<input type="text" id="chat_width_1" name="chat_site[width]" value="<?php print $this->get_option('width', '', 'site'); ?>" class="size" size="5" /> x
									<input type="text" id="chat_height_1" name="chat_site[height]" value="<?php print $this->get_option('height', '', 'site'); ?>" class="size" size="5" />
								</td>
								<td class="info"><?php _e("Dimensions of the chat box", $this->translation_domain); ?></td>
							</tr>
						</table>
					</fieldset>
					
					<fieldset>
						<legend><?php _e('Colors', $this->translation_domain); ?></legend>
		
						<table border="0" cellpadding="4" cellspacing="0">
							<tr>
								<td><label for="chat_border_color_1"><?php _e('Border', $this->translation_domain); ?></label></td>
								<td>
									<input type="text" id=chat_border_color_1 name="chat_site[border_color]" value="<?php print $this->get_option('border_color', '#4b96e2', 'site'); ?>" class="color" size="7" />
									<div class="color" id="chat_border_color_1_panel"></div>
								</td>
								<td class="info"><?php _e("Chat box border color", $this->translation_domain); ?></td>
							</tr>
							
							<tr>
								<td><label for="chat_border_highlighted_color_1"><?php _e('Highlighted Border', $this->translation_domain); ?></label></td>
								<td>
									<input type="text" id=chat_border_highlighted_color_1 name="chat_site[border_highlighted_color]" value="<?php print $this->get_option('border_highlighted_color', '#ff8400', 'site'); ?>" class="color" size="7" />
									<div class="color" id="chat_border_highlighted_color_1_panel"></div>
								</td>
								<td class="info"><?php _e("Chat box border color when there is a new message", $this->translation_domain); ?></td>
							</tr>
							
							<tr>
								<td><label for="chat_background_color_1"><?php _e('Background', $this->translation_domain); ?></label></td>
								<td>
									<input type="text" id="chat_background_color_1" name="chat_site[background_color]" value="<?php print $this->get_option('background_color', '#ffffff', 'site'); ?>" class="color" size="7" />
									<div class="color" id="chat_background_color_1_panel"></div>
								</td>
								<td class="info"><?php _e("Chat box background color", $this->translation_domain); ?></td>
							</tr>
									
							<tr>
								<td><label for="chat_date_color"><?php _e('Date', $this->translation_domain); ?></label></td>
								<td>
									<input type="text" id="chat_date_color_1" name="chat_site[date_color]" value="<?php print $this->get_option('date_color', '#6699CC', 'site'); ?>" class="color" size="7" />
									<div class="color" id="chat_date_color_1_panel"></div>
								</td>
								<td class="info"><?php _e("Date and time background color", $this->translation_domain); ?></td>
							</tr>
							
							<tr>
								<td><label for="chat_name_color"><?php _e('Name', $this->translation_domain); ?></label></td>
								<td>
									<input type="text" id="chat_name_color_1" name="chat_site[name_color]" value="<?php print $this->get_option('name_color', '#666666', 'site'); ?>" class="color" size="7" />
									<div class="color" id="chat_name_color_1_panel"></div>
								</td>
								<td class="info"><?php _e("Name background color", $this->translation_domain); ?></td>
							</tr>
							
							<tr>
								<td><label for="chat_moderator_name_color"><?php _e('Moderator Name', $this->translation_domain); ?></label></td>
								<td>
									<input type="text" id="chat_moderator_name_color_1" name="chat_site[moderator_name_color]" value="<?php print $this->get_option('moderator_name_color', '#6699CC', 'site'); ?>" class="color" size="7" />
									<div class="color" id="chat_moderator_name_color_1_panel"></div>
								</td>
								<td class="info"><?php _e("Moderator Name background color", $this->translation_domain); ?></td>
							</tr>
							
							<tr>
								<td><label for="chat_text_color"><?php _e('Text', $this->translation_domain); ?></label></td>
								<td>
									<input type="text" id="chat_text_color_1" name="chat_site[text_color]" value="<?php print $this->get_option('text_color', '#000000', 'site'); ?>" class="color" size="7" />
									<div class="color" id="chat_text_color_1_panel"></div>
								</td>
								<td class="info"><?php _e("Text color", $this->translation_domain); ?></td>
							</tr>
						</table>
					</fieldset>
					
					<fieldset>
						<legend><?php _e('Fonts', $this->translation_domain); ?></legend>
		
						<table border="0" cellpadding="4" cellspacing="0">
							<tr>
								<td><label for="chat_font_1"><?php _e('Font', $this->translation_domain); ?></label></td>
								<td>
									<select id="chat_font_1" name="chat_site[font]" class="font" >
									<?php foreach ($this->fonts_list as $font_name => $font) { ?>
										<option value="<?php print $font; ?>" <?php print ($this->get_option('font', '', 'site') == $font)?'selected="selected"':''; ?>" ><?php print $font_name; ?></option>
									<?php } ?>
									</select>
								</td>
								<td class="info"><?php _e("Chat box font", $this->translation_domain); ?></td>
							</tr>
								
							<tr>
								<td><label for="chat_font_size_1"><?php _e('Font size', $this->translation_domain); ?></label></td>
								<td><select id="chat_font_size_1" name="chat_site[font_size]" class="font_size" >
									<?php for ($font_size=8; $font_size<21; $font_size++) { ?>
										<option value="<?php print $font_size; ?>" <?php print ($this->get_option('font_size', '12', 'site') == $font_size)?'selected="selected"':''; ?>" ><?php print $font_size; ?></option>
									<?php } ?>
									</select> px
								</td>
								<td class="info"><?php _e("Chat box font size", $this->translation_domain); ?></td>
							</tr>
						</table>
					</fieldset>
					
					<fieldset>
						<legend><?php _e('Authentication', $this->translation_domain); ?></legend>
							
						<table border="0" cellpadding="4" cellspacing="0">
							<tr>
								<td valign="top"><label for="chat_login_options_1"><?php _e('Login options', $this->translation_domain); ?></label></td>
								<td>
									<label><input type="checkbox" id="chat_login_options_1_current_user" name="chat_site[login_options][]" class="chat_login_options" value="current_user" <?php print (in_array('current_user', $this->get_option('login_options', array('current_user'), 'site')) > 0)?'checked="checked"':''; ?> /> <?php _e('Current user', $this->translation_domain); ?></label><br/>
									<?php if (is_multisite()) { ?>
									<label><input type="checkbox" id="chat_login_options_1_network_user" name="chat_site[login_options][]" class="chat_login_options" value="network_user" <?php print (in_array('network_user', $this->get_option('login_options', array('current_user'), 'site')) > 0)?'checked="checked"':''; ?> /> <?php _e('Network user', $this->translation_domain); ?></label><br/>
									<?php } ?>
									<label><input type="checkbox" id="chat_login_options_1_public_user" name="chat_site[login_options][]" class="chat_login_options" value="public_user" <?php print (in_array('public_user', $this->get_option('login_options', array('current_user'), 'site')) > 0)?'checked="checked"':''; ?> /> <?php _e('Public user', $this->translation_domain); ?></label><br/>
									<label><input type="checkbox" id="chat_login_options_1_twitter" name="chat_site[login_options][]" class="chat_login_options" value="twitter" <?php print (!$this->is_twitter_setup())?'disabled="disabled"':''; ?> <?php print (in_array('twitter', $this->get_option('login_options', array('current_user'), 'site')) > 0)?'checked="checked"':''; ?> /> <?php _e('Twitter', $this->translation_domain); ?></label><br/>
									<label><input type="checkbox" id="chat_login_options_1_facebook" name="chat_site[login_options][]" class="chat_login_options" value="facebook" <?php print (!$this->is_facebook_setup())?'disabled="disabled"':''; ?> <?php print (in_array('facebook', $this->get_option('login_options', array('current_user'), 'site')) > 0)?'checked="checked"':''; ?> /> <?php _e('Facebook', $this->translation_domain); ?></label><br/>
								</td>
								<td class="info"><?php _e("Authentication methods users can use", $this->translation_domain); ?></td>
							</tr>
							
							<tr>
								<td valign="top"><label for="chat_moderator_roles_1"><?php _e('Moderator roles', $this->translation_domain); ?></label></td>
								<td>
									<?php
									foreach (get_editable_roles() as $role => $details) {
										$name = translate_user_role($details['name'] );
									?>
									<label><input type="checkbox" id="chat_moderator_roles_1_<?php print $role; ?>" name="chat_site[moderator_roles][]" class="chat_moderator_roles" value="<?php print $role; ?>" <?php print (in_array($role, $this->get_option('moderator_roles', array('administrator','editor','author'), 'site')) > 0)?'checked="checked"':''; ?> /> <?php _e($name, $this->translation_domain); ?></label><br/>
									<?php 
									}
									?>
								</td>
								<td class="info"><?php _e("Select which roles are moderators", $this->translation_domain); ?></td>
							</tr>
						</table>
					</fieldset>
				</div-->
				
				<div id="chat_twitter_api_panel" class="chat_panel chat_auth_panel">
					<table border="0" cellpadding="4" cellspacing="0">
						<tr>
							<td><label for="chat_twitter_api_key"><?php _e('@Anywhere API key', $this->translation_domain); ?></label></td>
							<td>
								<input type="text" id="chat_twitter_api_key" name="chat_default[twitter_api_key]" value="<?php print $this->get_option('twitter_api_key', ''); ?>" class="" size="40" />
							</td>
							<td class="info">
								<ol>
									<li><?php print sprintf(__('Register this site as an application on Twitter\'s <a target="_blank" href="%s">app registration page</a>', $this->translation_domain), "http://dev.twitter.com/apps/new"); ?></li>
									<li><?php _e('If you\'re not logged in, you can use your Twitter username and password', $this->translation_domain); ?></li>
			  						<li><?php _e('Your Application\'s Name will be what shows up after "via" in your twitter stream', $this->translation_domain); ?></li>
									<li><?php _e('Application Type should be set on Browser', $this->translation_domain); ?></li>
			   						<li><?php _e('The callback URL should be', $this->translation_domain); ?> <b><?php print get_bloginfo('url'); ?></b></li>
			   						<li><?php _e('Once you have registered your site as an application, you will be provided with @Anywhere API key.', $this->translation_domain); ?></li>
									<li><?php _e('Copy and paste them to the fields on the left', $this->translation_domain); ?></li>
								</ol>
							</td>
						</tr>
					</table>
				</div>
				
				<div id="chat_facebook_api_panel" class="chat_panel chat_auth_panel">
					<table border="0" cellpadding="4" cellspacing="0">
						<tr>
							<td><label for="chat_facebook_application_id"><?php _e('Application id', $this->translation_domain); ?></label></td>
							<td>
								<input type="text" id="chat_facebook_application_id" name="chat_default[facebook_application_id]" value="<?php print $this->get_option('facebook_application_id', ''); ?>" class="" size="40" />
							</td>
							<td rowspan="2" class="info">
								<ol>
									<li><?php print sprintf(__('Register this site as an application on Facebook\'s <a target="_blank" href="%s">app registration page</a>', $this->translation_domain), 'http://www.facebook.com/developers/createapp.php'); ?></li>
									<li><?php _e('If you\'re not logged in, you can use your Facebook username and password', $this->translation_domain); ?></li>
			   						<li><?php _e('The site URL should be', $this->translation_domain); ?> <b><?php print get_bloginfo('url'); ?></b></li>
			   						<li><?php _e('Once you have registered your site as an application, you will be provided with a Application ID and a Application secret.', $this->translation_domain); ?></li>
									<li><?php _e('Copy and paste them to the fields on the left', $this->translation_domain); ?></li>
								</ol>
							</td>
						</tr>
								
						<tr>
							<td><label for="chat_facebook_application_secret"><?php _e('Application secret', $this->translation_domain); ?></label></td>
							<td>
								<input type="text" id="chat_facebook_application_secret" name="chat_default[facebook_application_secret]" value="<?php print $this->get_option('facebook_application_secret', ''); ?>" class="" size="40" />
							</td>
						</tr>
					</table>
				</div>
				
				<div id="chat_advanced_panel" class="chat_panel chat_advanced_panel">
					<table border="0" cellpadding="4" cellspacing="0">
						<tr>
							<td><label for="chat_interval"><?php _e('Interval', $this->translation_domain); ?></label></td>
							<td>
								<input type="text" id="chat_interval" name="chat_default[interval]" value="<?php print $this->get_option('interval', 1); ?>" class="" size="2" />
							</td>
							<td class="info">
								Refresh interval in seconds
							</td>
						</tr>
					</table>
				</div>
			</div>
	    
			<input type="hidden" name="page_options" value="chat_default,chat_site" />
			
			<p class="submit"><input type="submit" name="Submit"
				value="<?php _e('Save Changes', $this->translation_domain) ?>" /></p>
		</form>
		</div>
		<?php
	}
	
	/**
	 * Title filter
	 * 
	 * @see		http://codex.wordpress.org/Function_Reference/wp_head
	 * 
	 * @global	object	$current_user
	 * @global	object	$post
	 * @global	array	$chat_localized
	 * @param	string	$title
	 */
	function wp_head() {
		global $current_user, $post, $chat_localized;
	
		get_currentuserinfo();
				
		if ( !in_array('subscriber',$current_user->roles) ) {
			$vip = 'yes';
		} else {
			$vip = 'no';
		}
				
		$chat_sounds = get_user_meta($current_user->ID, 'chat_sounds', 'enabled');
		if (empty($chat_sounds)) {
			$chat_sounds = $this->get_option('sounds', "enabled");
		}
				
		if (!is_array($chat_localized)) {
			$chat_localized = array();
		}
				
		$chat_localized["url"] = site_url()."/wp-admin/admin-ajax.php";
		$chat_localized["plugin_url"] = plugins_url("wordpress-chat/");
		$chat_localized["facebook_text_sign_out"] = __('Sign out of Facebook', $this->translation_domain);
		$chat_localized["twitter_text_sign_out"] = __('Sign out of Twitter', $this->translation_domain);
		$chat_localized["please_wait"] = __('Please wait...', $this->translation_domain);
				
		$chat_localized["minimize"] = __('Minimize', $this->translation_domain);
		$chat_localized["minimize_button"] = plugins_url('wordpress-chat/images/16-square-blue-remove.png');
		$chat_localized["maximize"] = __('Maximize', $this->translation_domain);
		$chat_localized["maximize_button"] = plugins_url('wordpress-chat/images/16-square-green-add.png');
		
		$chat_localized["interval"] = $this->get_option('interval', 1);
		
		if ( is_user_logged_in() ) {
			$chat_localized['name'] = $current_user->display_name;
			$chat_localized['vip'] = $vip;
			$chat_localized['sounds'] = $chat_sounds;
			$chat_localized['post_id'] = $post->ID;
		} else {
			$chat_localized['name'] = "";
			$chat_localized['vip'] = false;
			$chat_localized['sounds'] = "enabled";
			$chat_localized['post_id'] = $post->ID;
		}
		
		if ($this->get_option('twitter_api_key') != '') {
			$chat_localized["twitter_active"] = true;
			wp_enqueue_script('twitter', 'http://platform.twitter.com/anywhere.js?id='.$this->get_option('twitter_api_key').'&v=1');
		} else {
			$chat_localized["twitter_active"] = false;
		}
		if ($this->get_option('facebook_application_id') != '') {
			$chat_localized["facebook_active"] = true;
			$chat_localized["facebook_app_id"] = $this->get_option('facebook_application_id');
			wp_enqueue_script('facebook', 'http://connect.facebook.net/en_US/all.js');
		} else {
			$chat_localized["facebook_active"] = false;
		}
		
		wp_enqueue_script('jquery');
		wp_enqueue_script('jquery-cookie');
		wp_enqueue_script('chat_js');
		
		if ($this->get_option('site', 'enabled', 'site') == 'enabled') {
			$atts = array(
				'id' => 1,
				'sound' => $this->get_option('sound', 'enabled', 'site'),
				'avatar' => $this->get_option('avatar', 'enabled', 'site'),
				'emoticons' => $this->get_option('emoticons', 'enabled', 'site'),
				'date_show' => $this->get_option('date_show', 'disabled', 'site'),
				'time_show' => $this->get_option('time_show', 'disabled', 'site'),
				'width' => $this->get_option('width', '', 'site'),
				'height' => $this->get_option('height', '', 'site'),
				'background_color' => $this->get_option('background_color', '#ffffff', 'site'),
				'date_color' => $this->get_option('date_color', '#6699CC', 'site'),
				'name_color' => $this->get_option('name_color', '#666666', 'site'),
				'moderator_name_color' => $this->get_option('moderator_name_color', '#6699CC', 'site'),
				'text_color' => $this->get_option('text_color', '#000000', 'site'),
				'font' => $this->get_option('font', '', 'site'),
				'font_size' => $this->get_option('font_size', '', 'site'),
				'log_creation' => $this->get_option('log_creation', 'disabled', 'site'),
				'log_display' => $this->get_option('log_display', 'disabled', 'site'),
				'login_options' => join(',', $this->get_option('login_options', array('current_user'), 'site')),
				'moderator_roles' => join(',', $this->get_option('moderator_roles', array('administrator','editor','author'))),
			);
			$this->process_shortcode($atts);
		}
	}
	
	/**
	 * Check the post for the short code and mark it
	 * 
	 * @deprecated	No longer relevant with site wide chat as well
	 */
	function post_check($post_ID) {
		$post = get_post($post_ID);
		if ( $post->post_content != str_replace('[chat', '', $post->post_content) ) {
			update_post_meta($post_ID, '_has_chat', 'yes');
		} else {
			delete_post_meta($post_ID, '_has_chat');
		}
	}
	
	/**
	 * Handle profile update
	 * 
	 * @see		http://codex.wordpress.org/Function_Reference/wp_redirect
	 * 
	 * @global	object	$current_user
	 * @param	string	$location
	 * @return	string	$location
	 */
	function profile_process($location) {
		global $current_user;
		if ( !empty( $_GET['user_id'] ) ) {
			$uid = $_GET['user_id'];
		} else {
			$uid = $current_user->ID;
		}
		if ( !empty( $_POST['chat_sounds'] ) ) {
			update_usermeta( $uid, 'chat_sounds', $_POST['chat_sounds'] );
		}
		return $location;
	}
	
	/**
	 * Add sound preferences to user profile
	 * 
	 * @global	object	$current_user
	 */
	function profile() {
		global $current_user;
		
		if (!empty( $_GET['user_id'])) {
			$uid = $_GET['user_id'];
		} else {
			$uid = $current_user->ID;
		}
		
		$chat_sounds = get_user_meta( $uid, 'chat_sounds' );
		?>
	    <h3><?php _e('Chat Settings', $this->translation_domain); ?></h3>
	    
	    <table class="form-table">
		    <tr>
		        <th><label for="chat_sounds"><?php _e('Chat sounds', $this->translation_domain); ?></label></th>
		        <td>
		            <select name="chat_sounds" id="chat_sounds">
		            	<option value="enabled"<?php if ( $chat_sounds == 'enabled' ) { echo ' selected="selected" '; } ?>><?php _e('Enabled', $this->translation_domain); ?></option>
		            	<option value="disabled"<?php if ( $chat_sounds == 'disabled' ) { echo ' selected="selected" '; } ?>><?php _e('Disabled', $this->translation_domain); ?></option>
		            </select>
		        </td>
		    </tr>
	    </table>
	    <?php
	}
	
	/**
	 * Output CSS
	 */
	function output_css() {
		echo '<link rel="stylesheet" href="' . plugins_url('wordpress-chat/css/style.css') . '" type="text/css" />';
	}
	
	/**
	 * Authenticate user with Facebook
	 * 
	 * We will also set the appropriate cookies
	 * 
	 * @uses	get_facebook_cookie()
	 * @return	boolean					Is the user authenticated. true or false
	 */
	function authenticate_facebook() {
		$cookie = $this->get_facebook_cookie();

		if ($cookie && isset($cookie['access_token'])) {
			$user = json_decode($this->file_get_contents('https://', 'graph.facebook.com', 443, '/me?access_token='.$cookie['access_token']));
			$existing = preg_split('/,/', $_COOKIE['chat_stateless_user_type_104']);
			$existing[] = 'facebook';
			
			setcookie('chat_stateless_user_type_104', join(',', $existing), time()+3600*24*7, '/');
			setcookie('chat_stateless_user_name_facebook', $user->name, time()+3600*24*7, '/');
			setcookie('chat_stateless_user_image_facebook', "http://graph.facebook.com/{$user->id}/picture", time()+3600*24*7, '/');
			
			$_COOKIE['chat_stateless_user_type_104'] = join(',', $existing); 
			$_COOKIE['chat_stateless_user_name_facebook'] = $user->name; 
			$_COOKIE['chat_stateless_user_image_facebook'] = "http://graph.facebook.com/{$user->id}/picture"; 
			return true;
		}
		return false;
	}
	
	/**
	 * Connect to the remote server and get the content
	 *
	 * @param	string	$scheme		Scheme
	 * @param	string	$host		Host
	 * @param	int	$port		Port
	 * @param	string	$filename	File name
	 *
	 * @return 	string			Response body
	 */
	function file_get_contents($scheme = '', $host, $port, $filename) {
		$response = wp_remote_get( "{$scheme}{$host}{$filename}" );
		if ( is_wp_error( $response ) || empty($response['body']) ) {
			if ($scheme == "https://") {
				$scheme = "ssl://";
			}
			$fp = fsockopen("{$scheme}{$host}", $port, $errno, $errstr, 30);
			if (!$fp) {
				trigger_error("$errstr ($errno)", E_USER_WARNING);
			} else {
				$out = "GET {$filename} HTTP/1.1\r\n";
				$out .= "Host: {$host}\r\n";
				$out .= "Connection: Close\r\n\r\n";
				fwrite($fp, $out);
				$content = "";
				$data = "";
				$content_found = false;
				while (!feof($fp)) {				
					$data .= fgets($fp, 128);
				}
				fclose($fp);
			}
			$data_parts = preg_split('/(\n\n|\r\r|\r\n\r\n)/', $data);
			$content = $data_parts[1];
		} else {
			$content = $response['body'];
		}
		
		return $content;
	}
	
	/**
	 * Validate and return the Facebook cookie payload
	 * 
	 * @see		http://developers.facebook.com/docs/guides/web#login
	 */
	function get_facebook_cookie() {
		$app_id = $this->get_option('facebook_application_id', '');
		$application_secret = $this->get_option('facebook_application_secret', '');
		
		$args = array();
		parse_str(trim($_COOKIE['fbs_' . $app_id], '\\"'), $args);
		ksort($args);
		$payload = '';
		
		foreach ($args as $key => $value) {
			if ($key != 'sig') {
				$payload .= $key . '=' . $value;
			}
		}
		
		if (md5($payload . $application_secret) != $args['sig']) {
			return null;
		}
		return $args;
	}
	
	/**
	 * Authenticate user
	 * 
	 * @global	object	$current_user
	 * @param	array	$options	Login options
	 * @return	int			How the user was authenticated or false (1,2,3,4,5)
	 */
	function authenticate($options = array()) {
		global $current_user;
		
		// current user
		if (is_user_logged_in() && current_user_can('read')) {
			return 1;
		}
		// Network user
		if (in_array('network_user', $options) && is_user_logged_in()) {
			return 2;
		}
		if (in_array('facebook', $options) && (preg_match('/facebook/', $_COOKIE['chat_stateless_user_type_104']) > 0 || $this->authenticate_facebook())) {
			return 3;
		}
		if (in_array('twitter', $options) && preg_match('/twitter/', $_COOKIE['chat_stateless_user_type_104']) > 0) {
			return 4;
		}
		if (in_array('public_user', $options) && preg_match('/public_user/', $_COOKIE['chat_stateless_user_type_104']) > 0) {
			return 5;
		}
		return false;
	}
	
	
	/**
	 * Get the user name
	 * 
	 * So many loggin options, this will decide the display name of the user
	 * 
	 * @global	object	$current_user
	 * @param	array	$options	Login options
	 * @return	string				User name or false
	 */
	function get_user_name($options = array()) {
		global $current_user;
		
		// current_user or network_user
		if ((is_user_logged_in() && current_user_can('read')) || (in_array('network_user', $options) && is_user_logged_in())) {
			return $current_user->display_name;
		}
		if (in_array('facebook', $options) && isset($_COOKIE['chat_stateless_user_type_104']) && (preg_match('/facebook/', $_COOKIE['chat_stateless_user_type_104']) > 0 || $this->authenticate_facebook())) {
			return $_COOKIE['chat_stateless_user_name_facebook'];
		}
		if (in_array('twitter', $options) && isset($_COOKIE['chat_stateless_user_type_104']) && preg_match('/twitter/', $_COOKIE['chat_stateless_user_type_104']) > 0) {
			return $_COOKIE['chat_stateless_user_name_twitter'];
		}
		if (in_array('public_user', $options) && isset($_COOKIE['chat_stateless_user_type_104']) && preg_match('/public_user/', $_COOKIE['chat_stateless_user_type_104']) > 0) {
			return $_COOKIE['chat_stateless_user_name_public_user'];
		}
		return false;
	}
	
	/**
	 * Do our magic in the footer and add the site wide chat
	 */
	function wp_footer() {
		global $chat_processed;
		
		$atts = array(
			'id' => 1,
			'sound' => $this->get_option('sound', 'enabled', 'site'),
			'avatar' => $this->get_option('avatar', 'enabled', 'site'),
			'emoticons' => $this->get_option('emoticons', 'enabled', 'site'),
			'date_show' => $this->get_option('date_show', 'disabled', 'site'),
			'time_show' => $this->get_option('time_show', 'disabled', 'site'),
			'width' => $this->get_option('width', '', 'site'),
			'height' => $this->get_option('height', '', 'site'),
			'background_color' => $this->get_option('background_color', '#ffffff', 'site'),
			'date_color' => $this->get_option('date_color', '#6699CC', 'site'),
			'name_color' => $this->get_option('name_color', '#666666', 'site'),
			'moderator_name_color' => $this->get_option('moderator_name_color', '#6699CC', 'site'),
			'text_color' => $this->get_option('text_color', '#000000', 'site'),
			'font' => $this->get_option('font', '', 'site'),
			'font_size' => $this->get_option('font_size', '', 'site'),
			'log_creation' => $this->get_option('log_creation', 'disabled', 'site'),
			'log_display' => $this->get_option('log_display', 'disabled', 'site'),
			'login_options' => join(',', $this->get_option('login_options', array('current_user'), 'site')),
			'moderator_roles' => join(',', $this->get_option('moderator_roles', array('administrator','editor','author'))),
		);
		
		if ($this->get_option('site', 'enabled', 'site') == 'enabled') {
			$width = $this->get_option('width', '', 'site');
			if (!empty($width)) {
				$width_str = 'width: '.$width;
				$width_style = '';
			} else {
				$width_style = ' free-width';
			}
			echo '<style type="text/css">#chat-block-site.new_msg { background-color: '.$this->get_option('border_highlighted_color', '#4b96e2', 'site').' !important; }</style>';
			echo '<div id="chat-block-site" class="chat-block-site closed'.$width_style.'" style="'.$width_str.'; background-color: '.$this->get_option('border_color', '#4b96e2', 'site').';">';
			echo '<div id="chat-block-header" class="chat-block-header"><span class="chat-title-text">'.__('Chat', $this->translation_domain).'</span><span class="chat-prompt-text">'.__('Click here to chat!', $this->translation_domain).'</span>';
			echo '<img src="'.plugins_url('wordpress-chat/images/16-square-green-add.png').'" alt="+" width="16" height="16" title="'.__('Maximize', $this->translation_domain).'" class="chat-toggle-button" id="chat-toggle-button" />';
			echo '</div>';
			echo '<div id="chat-block-inner" style="background: '.$this->get_option('background_color', '#ffffff', 'site').';">'.$this->process_shortcode($atts).'</div>';
			echo '</div>';
		}
		
		if ($chat_processed) {
			
		}
	}
	
	/**
	 * Process short code
	 * 
	 * @global	object	$post
	 * @global	array	$chat_localized	Localized strings and options
	 * @return	string					Content
	 */
	function process_shortcode($atts) {
		global $post, $chat_localized, $chat_processed;
		
		$a = shortcode_atts(array(
			'id' => 1,
			'sound' => $this->get_option('sound', 'enabled'),
			'avatar' => $this->get_option('avatar', 'enabled'),
			'emoticons' => $this->get_option('emoticons', 'enabled'),
			'date_show' => $this->get_option('date_show', 'disabled'),
			'time_show' => $this->get_option('time_show', 'disabled'),
			'width' => $this->get_option('width', '700px'),
			'height' => $this->get_option('height', '425px'),
			'background_color' => $this->get_option('background_color', '#ffffff'),
			'background_highlighted_color' => $this->get_option('background_highlighted_color', '#FFE9AB'),
			'date_color' => $this->get_option('date_color', '#6699CC'),
			'name_color' => $this->get_option('name_color', '#666666'),
			'moderator_name_color' => $this->get_option('moderator_name_color', '#6699CC'),
			'text_color' => $this->get_option('text_color', '#000000'),
			'font' => $this->get_option('font', ''),
			'font_size' => $this->get_option('font_size', ''),
			'log_creation' => $this->get_option('log_creation', 'disabled'),
			'log_display' => $this->get_option('log_display', 'disabled'),
			'login_options' => join(',', $this->get_option('login_options', array('current_user'))),
			'moderator_roles' => join(',', $this->get_option('moderator_roles', array('administrator','editor','author'))),
		), $atts);
		
		foreach ($a as $k=>$v) {
			$chat_localized[$k.'_'.$a['id']] = $v;
		}
		
		$content = '';
		
		$font_style = "";
	
		if (!empty($a['font'])) {
			$font_style .= 'font-family: '.$a['font'].';';
		}
		if (!empty($a['font_size'])) {
			$font_style .= 'font-size: '.$a['font_size'].'px;';
		}
		
		$a['font_style'] = $font_style;
		
		if ($post && $post->ID) {
			$a['permalink'] = get_permalink($post->ID);
		} else {
			$a['permalink'] = "";
		}
		
		$chat_url = $_SERVER['REQUEST_URI'];
		$chat_url = rtrim($chat_url, "/");
		$chat_url = substr($chat_url, -8);
		
		if (empty($a['permalink']) || preg_match('/\?/', $a['permalink']) > 0) {
			$a['url_separator'] = "&";
		} else {
			$a['url_separator'] = "?";
		}
		
		$a['smilies_list'] = array(':)', ':D', ':(', ':o', '8O', ':?', '8)', ':x', ':P', ':|', ';)', ':lol:', ':oops:', ':cry:', ':evil:', ':twisted:', ':roll:', ':!:', ':?:', ':idea:', ':arrow:', ':mrgreen:');
		$chat_localized['type_'.$a['id']] = $this->authenticate(preg_split('/,/', $a['login_options']));
		
		if ( $chat_localized['type_'.$a['id']] ) {
			$chat_localized['name_'.$a['id']] = $this->get_user_name(preg_split('/,/', $a['login_options']));
		}
    
		if (!$chat_localized['type_'.$a['id']] and $no_auth_template = locate_template('chat-inline-need-to-auth.php')) {
			require $no_auth_template;
		} else if ($chat_localized['type_'.$a['id']] and $auth_template = locate_template('chat-inline.php')) {
			require $auth_template;
		} else {
			$content .= $this->chat_box($a);
			$content .= $this->chat_wrap($a);
			$content .= $this->chat_area($a);
			
			if ( $chat_localized['type_'.$a['id']] ) {
				$content .= $this->chat_message_area($a);
			} else {
				if ($this->use_twitter_auth($a) or $this->use_facebook_auth($a) or $this->use_public_auth($a)) {
					if ($this->use_public_auth($a)) {
						$content .= '<div class="login-message">'.__('To get started just enter your email address and desired username', $this->translation_domain).': </div>';
						$content .= $this->chat_login_public($a, false);
					}
					if ($this->use_twitter_auth($a) or $this->use_facebook_auth($a)) { 
						$content .= '<div class="login-message">Log in using your: </div>';
						$content .= '<div class="chat-login-wrap">';
						
						$content .= $this->chat_login_twitter($a, false);
						$content .= $this->chat_login_facebook($a, false);
						
						$content .= '</div>';
					}
				} else {
					$content .= '<div class="login-message"><strong>' . __('You must be logged in to participate in chats', $this->translation_domain) . '</strong></div>';
				}
				
				$content .= $this->chat_dummy_message_area($a, false);
			}
			
			if ( $a['log_display'] == 'enabled' &&  $a['id'] != 1) {
				$dates = $this->get_archives($a['id']);
					
				if ( $dates && is_array($dates) ) {
					$content .= '<br />';
					$content .= '<div class="chat-note"><p><strong>' . __('Chat Logs', $this->translation_domain) . '</strong></p></div>';
					foreach ($dates as $date) {
						$date_content .= '<li><a class="chat-log-link" style="text-decoration: none;" href="' . $a['permalink'] . $a['url_separator'] . 'lid=' . $date->id . '">' . date_i18n(get_option('date_format').' '.get_option('time_format'), strtotime($date->start) + get_option('gmt_offset') * 3600, false) . ' - ' . date_i18n(get_option('date_format').' '.get_option('time_format'), strtotime($date->end) + get_option('gmt_offset') * 3600, false) . '</a>';
						if (isset($_GET['lid']) && $_GET['lid'] == $date->id) {
							$_POST['cid'] = $a['id'];
							$_POST['archived'] = 'yes';
							$_POST['function'] = 'update';
							$_POST['since'] = strtotime($date->start);
							$_POST['end'] = strtotime($date->end);
							$_POST['date_color'] = $a['date_color'];
							$_POST['name_color'] = $a['name_color'];
							$_POST['moderator_name_color'] = $a['moderator_name_color'];
							$_POST['text_color'] = $a['text_color'];
							$_POST['date_show'] = $a['date_show'];
							$_POST['time_show'] = $a['time_show'];
							$_POST['avatar'] = $a['avatar'];
							
							$date_content .= '<div class="chat-wrap avatar-'.$a['avatar'].'" style="background-color: '.$a['background_color'].'; '.$a['font_style'].'"><div class="chat-area" >';
							$date_content .= $this->process('yes');
							$date_content .= '</div></div>';
						}
						$date_content .= '</li>';
					}
					
					$content .= '<div id="chat-log-wrap-'.$a['id'].'" class="chat-log-wrap" style="background-color: '.$a['background_color'].'; '.$a['font_style'].'"><div id="chat-log-area-'.$a['id'].'" class="chat-log-area"><ul>' . $date_content . '</ul></div></div>';
				}
			}
			$content .= '<div class="chat-clear"></div></div>';
			$content .= '<style type="text/css">#chat-box-'.$a['id'].'.new_msg { background-color: '.$a['background_highlighted_color'].' !important; }</style>';
		}
		
		wp_localize_script('chat_js', 'chat_localized', $chat_localized);
		
		$chat_processed = 1;
		
		return $content;
	}
	
	/**
	 * Chat logs shortcode
	 * 
	 * Show a list of chat logs indepdently of a post or page
	 * 
	 * @since 1.0.10
	 * @author Philip John <phil@philipjohn.co.uk>
	 */
	function chat_logs_shortcode($atts){
		$a = shortcode_atts(array(
			'id' => 1,
			'sound' => $this->get_option('sound', 'enabled'),
			'avatar' => $this->get_option('avatar', 'enabled'),
			'emoticons' => $this->get_option('emoticons', 'enabled'),
			'date_show' => $this->get_option('date_show', 'disabled'),
			'time_show' => $this->get_option('time_show', 'disabled'),
			'width' => $this->get_option('width', '700px'),
			'height' => $this->get_option('height', '425px'),
			'background_color' => $this->get_option('background_color', '#ffffff'),
			'background_highlighted_color' => $this->get_option('background_highlighted_color', '#FFE9AB'),
			'date_color' => $this->get_option('date_color', '#6699CC'),
			'name_color' => $this->get_option('name_color', '#666666'),
			'moderator_name_color' => $this->get_option('moderator_name_color', '#6699CC'),
			'text_color' => $this->get_option('text_color', '#000000'),
			'font' => $this->get_option('font', ''),
			'font_size' => $this->get_option('font_size', ''),
			'log_creation' => $this->get_option('log_creation', 'disabled'),
			'log_display' => $this->get_option('log_display', 'disabled'),
			'login_options' => join(',', $this->get_option('login_options', array('current_user'))),
			'moderator_roles' => join(',', $this->get_option('moderator_roles', array('administrator','editor','author'))),
		), $atts);
		
		$dates = $this->get_archives(intval($a['id']));
		
		if ( $dates && is_array($dates) ) {
			$content .= '<div class="chat-note"><p><strong>' . __('Chat Logs', $this->translation_domain) . '</strong></p></div>';
			foreach ($dates as $date) {
				$date_content .= '<li><a class="chat-log-link" style="text-decoration: none;" href="?' . $a['permalink'] . $a['url_separator'] . 'lid=' . $date->id . '">' . date_i18n(get_option('date_format').' '.get_option('time_format'), strtotime($date->start) + get_option('gmt_offset') * 3600, false) . ' - ' . date_i18n(get_option('date_format').' '.get_option('time_format'), strtotime($date->end) + get_option('gmt_offset') * 3600, false) . '</a>';
					$_POST['cid'] = $a['id'];
					$_POST['archived'] = 'yes';
					$_POST['function'] = 'update';
					$_POST['since'] = strtotime($date->start);
					$_POST['end'] = strtotime($date->end);
					$_POST['date_color'] = $a['date_color'];
					$_POST['name_color'] = $a['name_color'];
					$_POST['moderator_name_color'] = $a['moderator_name_color'];
					$_POST['text_color'] = $a['text_color'];
					$_POST['date_show'] = $a['date_show'];
					$_POST['time_show'] = $a['time_show'];
					$_POST['avatar'] = $a['avatar'];
					
					$date_content .= '<div class="chat-wrap avatar-'.$a['avatar'].'" style="background-color: '.$a['background_color'].'; '.$a['font_style'].'"><div class="chat-area" >';
					$date_content .= $this->process('yes');
					$date_content .= '</div></div>';
				$date_content .= '</li>';
			}
			
			$content .= '<div id="chat-log-wrap-'.$a['id'].'" class="chat-log-wrap" style="background-color: '.$a['background_color'].'; '.$a['font_style'].'"><div id="chat-log-area-'.$a['id'].'" class="chat-log-area"><ul>' . $date_content . '</ul></div></div>';
		}
		
		wp_localize_script('chat_js', 'chat_localized', $chat_localized);
		
		return $content;
	}
	
	function chat_message_area($a, $echo = false) {
		$content = '';
		
		$content .= '<div class="chat-note"><p><strong>' . __('Message', $this->translation_domain) . '</strong></p></div>';
		$content .= '<form id="send-message-area">';
		
		$content .= '<input type="hidden" name="chat-post-id" id="chat-post-id-'.$a['id'].'" value="'.$a['id'].'" class="chat-post-id" />';
		
		$content .= '<div class="chat-tool-bar-wrap"><div class="chat-note">';
		
		if ($a['emoticons'] == 'enabled') {
			$content .= '<div id="chat-emoticons-list-'.$a['id'].'" class="chat-emoticons-list chat-tool-bar">';
			foreach ($a['smilies_list'] as $smilie) {
				$content .= convert_smilies($smilie);
			}
			$content .= '</div>';
		}
		
		$content .= '<div class="chat-clear"></div></div></div>';
		
		// $this->chat_send_form();
		
		$content .= '<div id="chat-send-wrap">';
		$content .= '<div class="chat-clear"></div>';
		$content .= '<div class="chat-send-wrap"><textarea id="chat-send-'.$a['id'].'" class="chat-send"></textarea></div>';
		$content .= '<div class="chat-note">' . __('"Enter" to send', $this->translation_domain) . '. ' . __('Place code in between [code] tags', $this->translation_domain) . '.</div>';
		if ( $this->authenticate(preg_split('/,/', $a['login_options'])) > 2 ) {
			$content .= '<div class="chat-note"><input type="button" value="'. __('Logout', $this->translation_domain) .'" name="chat-logout-submit" class="chat-logout-submit" id="chat-logout-submit-'.$a['id'].'" /></div>';
		}
		$content .= '</div>';
		$content .= '<div class="chat-tool-bar-wrap"><div class="chat-note">';
		
		// $this->chat_after_toolbar();
		if ($this->is_moderator(preg_split('/,/', $a['moderator_roles']))) {
			$content .= '<div id="chat-log-actions-'.$a['id'].'" class="chat-log-actions chat-tool-bar">';
			if ($a['log_creation'] == 'enabled' && $a['id'] != 1) {
				$content .= '<input type="button" value="'. __('Archive', $this->translation_domain) .'" name="chat-archive" class="chat-archive" id="chat-archive-'.$a['id'].'" />';
			}
			$content .= '<input type="button" value="'. __('Clear', $this->translation_domain) .'" name="chat-clear" class="chat-clear" id="chat-clear-'.$a['id'].'" />';
			$content .= '</div>';
		}
			
		$content .= '<div class="chat-clear"></div></div></div>';
		
		$content .= '</form>';
		if ($echo) {
			echo $content;
		}
		return $content;
	}
	
	function chat_box($a, $echo = false) {
		global $post;
		
		$content = '';
		
		if ($post) {
			$content = '<div id="chat-box-'.$a['id'].'" class="chat-box" style="width: '.$a['width'].' !important; background-color: '.$a['background_color'].'; '.$a['font_style'].'" >';
		} else {
			$content = '<div id="chat-box-'.$a['id'].'" class="chat-box" style="width: '.$a['width'].' !important; height: '.$a['height'].' !important; background-color: '.$a['background_color'].'; '.$a['font_style'].'" >';
		}
		
		if ($echo) {
			echo $content;
		}
		return $content;
	}
	
	function chat_wrap($a, $echo = false) {
		$content = '';
		
		$content .= '<div id="chat-wrap-'.$a['id'].'" class="chat-wrap avatar-'.$a['avatar'].'" >';
		
		if ($echo) {
			echo $content;
		}
		return $content;
	}
	
	function chat_area($a, $echo = false) {
		global $post;
		
		$content = '';
		
		if ($post) {
			$content .= '<div id="chat-area-'.$a['id'].'" class="chat-area" style="height: '.$a['height'].' !important;" ><div class="chat-scroll-height"></div></div></div>';
		} else {
			$content .= '<div id="chat-area-'.$a['id'].'" class="chat-area" ><div class="chat-scroll-height"></div></div></div>';
		}
		
		if ($echo) {
			echo $content;
		}
		return $content;
	}
	
	function use_public_auth($a) {
		return (preg_match('/public_user/', $a['login_options']) > 0);
	}
	function use_facebook_auth($a) {
		return (preg_match('/facebook/', $a['login_options']) > 0 && ($this->get_option('facebook_application_id') != ''));
	}
	
	
	function use_twitter_auth($a) {
		return (preg_match('/twitter/', $a['login_options']) > 0 && ($this->get_option('twitter_api_key') != ''));
	}
	
	function chat_dummy_message_area($a, $echo = true) {
		$content = '';
		if ($this->use_facebook_auth($a)) {
			$content .= '<form id="send-message-area">';
			$content .= '<input type="hidden" name="chat-post-id" id="chat-post-id-'.$a['id'].'" value="'.$a['id'].'" class="chat-post-id" />';
			$content .= '</form>';
		}
		if ($echo) {
			echo $content;
		}
		return $content;
	}
	
	function chat_login_public($a, $echo = true) {
		$content = '';
		$content .= '<form id="chat-login-'.$a['id'].'" class="chat-login">';
		$content .= '<div id="chat-login-wrap-'.$a['id'].'" class="chat-login-wrap">';
		$content .= '<label class="chat-login-name-label" for="chat-login-name-'.$a['id'].'">'.__('Name', $this->translation_domain) . '</label> <input id="chat-login-name-'.$a['id'].'" name="chat-login-name" class="chat-login-name" type="text" /> ';
		$content .= '<label class="chat-login-email-label" for="chat-login-email-'.$a['id'].'">' . __('E-mail', $this->translation_domain) . '</label> <input id="chat-login-email-'.$a['id'].'" name="chat-login-email" class="chat-login-email" type="text" /> ';
		$content .= '<input type="submit" value="'. __('Login', $this->translation_domain) .'" name="chat-login-submit" id="chat-login-submit-'.$a['id'].'" />';
		$content .= '</div>';
		$content .= '</form>';
		
		if ($echo) {
			echo $content;
		}
		return $content;
	}
	
	function chat_login_facebook($a, $echo = true) {
		$content = '';
		if ($this->use_facebook_auth($a)) {
			$content .= '<span id="chat-facebook-signin-btn-'.$a['id'].'" class="chat-auth-button chat-facebook-signin-btn"></span>';
		}
		if ($echo) {
			echo $content;
		}
		return $content;
	}
	
	function chat_login_twitter($a, $echo = true) {
		$content = '';
		if ($this->use_twitter_auth($a)) { 
			$content .= '<span id="chat-twitter-signin-btn-'.$a['id'].'" class="chat-auth-button chat-twitter-signin-btn"></span>';
		}
		if ($echo) {
			echo $content;
		}
		return $content;
	}
	
	/**
	 * @see		http://codex.wordpress.org/TinyMCE_Custom_Buttons
	 */
	function tinymce_register_button($buttons) {
		array_push($buttons, "separator", "chat");
		return $buttons;
	}
	
	/**
	 * @see		http://codex.wordpress.org/TinyMCE_Custom_Buttons
	 */
	function tinymce_load_langs($langs) {
		$langs["chat"] =  plugins_url('wordpress-chat/tinymce/langs/langs.php');
		return $langs;
	}
 
	/**
	 * @see		http://codex.wordpress.org/TinyMCE_Custom_Buttons
	 */
	function tinymce_add_plugin($plugin_array) {
		$plugin_array['chat'] = plugins_url('wordpress-chat/tinymce/editor_plugin.js');
		return $plugin_array;
	}
	
	/**
	 * Process chat requests
	 * 
	 * Mostly copied from process.php
	 * 
	 * @global	object	$current_user 
	 * @param	string	$return		Return? 'yes' or 'no'
	 * @return	string			If $return is yes will return the output else echo
	 */
	function process($return = 'no') {
		global $current_user, $blog_id, $wpdb;
		get_currentuserinfo();
		
		$function = $_POST['function'];
		
		if ( empty($function) ) {
			$function = $_GET['function'];
		}
		
		file_put_contents(trailingslashit(ABSPATH).'query.log', "POST: \r\n", FILE_APPEND);
		foreach ($_POST as $key => $value){
			file_put_contents(trailingslashit(ABSPATH).'query.log', "	$key: $value\r\n", FILE_APPEND);
		}
		file_put_contents(trailingslashit(ABSPATH).'query.log', "GET: \r\n", FILE_APPEND);
		foreach ($_GET as $key => $value){
			file_put_contents(trailingslashit(ABSPATH).'query.log', "	$key: $value\r\n", FILE_APPEND);
		}
		
		// Check if the current user is a moderator
		$moderator_roles = explode(',', $_POST['moderator_roles']);
		$moderator = $this->is_moderator($moderator_roles);
		
		$log = array();
	    
		switch($function) {
			case 'approve':
				$message_id = $_POST['message_id'];
				$update_query = "UPDATE  `".Chat::tablename('message')."` SET  `approved` =  'yes' WHERE `timestamp`='".date('Y-m-d H:i:s', $message_id)."' LIMIT 1";
				$wpdb->query($update_query);
				break;
			case 'delete':
				$message_id = $_POST['message_id'];
				$delete_query = "DELETE FROM `".Chat::tablename('message')."` WHERE `timestamp`='".date('Y-m-d H:i:s', $message_id)."' LIMIT 1";
				$wpdb->query($delete_query);
				break;
			case 'update':
				$chat_id = $_POST['cid'];
    			$since = $_POST['since'];
				$since_id = $_POST['since_id'];
    			$end = isset($_POST['end'])?$_POST['end']:0;
    			$archived = isset($_POST['archived'])?$_POST['archived']:'no';
				$name = isset($_POST['name'])?$_POST['name']:md5('wordpress-chat');
				$name = htmlentities(strip_tags($name));
				$approved = $this->is_approved($blog_id, $chat_id, $uname);
    			
				$rows = $this->get_messages($chat_id, $since, $end, $archived, $since_id, $moderator, array($approved, $name));

	    			if ($rows) {
		    			$text = array();
						$new_message = false;
		    			
		    			foreach ($rows as $row) {
		    				$message = stripslashes($row->message);
		    				$reg_exUrl = "/(http|https|ftp|ftps)\:\/\/[a-zA-Z0-9\-\.]+\.[a-zA-Z]{2,3}(\/\S*)?/";
		    				
		    				if(($message) != "\n" && ($message) != "<br />" && ($message) != "") {
								if(preg_match_all($reg_exUrl, $message, $urls) && isset($urls[0]) && count($urls[0]) > 0) {
									foreach ($urls[0] as $url) {
										$message = str_replace($url, '<a href="'.$url.'" target="_blank">'.$url.'</a>', $message);
									}
								}
		    				}
		    				
		    				$message = preg_replace(array('/\[code\]/','/\[\/code\]/'), array('<code style="background: '.$this->get_option('code_color', '#FFFFCC').'; padding: 4px 8px;">', '</code>'), $message);
		    				
							$code_start_count = preg_match_all('/<code/i', $message, $code_starts);
							
							$code_end_count = preg_match_all('/<\/code>/i', $message, $code_ends);
							
							if ($code_start_count > $code_end_count) {
								$code_diff = $code_start_count - $code_end_count;
								
								for ($i=0; $i<$code_diff; $i++) {
									$message .= '</code>';
								}
							} else {
								$code_diff = $code_end_count - $code_start_count;
								
								for ($i=0; $i<$code_diff; $i++) {
									$message = '<code>'.$message;
								}
							}
							
		    				$message = str_replace("\n", "<br />", $message);
		    				
		    				$prepend = "";
		    				if ($_POST['avatar'] == 'enabled') {
		    					if (preg_match('/@/', $row->avatar)) {
		    						$avatar = get_avatar($row->avatar, 50, null, $row->name);
		    					} else {
		    						$avatar = "<img alt='{$row->name}' src='{$row->avatar}' class='avatar photo' />";
		    					}
		    					$prepend .= "$avatar ";
		    				}
		    				
		    				if ($_POST['date_show'] == 'enabled') {
		    					$prepend .= ' <span class="date" style="background: '.$_POST['date_color'].';">'. date_i18n(get_option('date_format'), strtotime($row->timestamp) + get_option('gmt_offset') * 3600, false) . '</span>';
		    				}
		    				if ($_POST['time_show'] == 'enabled') {
		    					$prepend .= ' <span class="time" style="background: '.$_POST['date_color'].';">'. date_i18n(get_option('time_format'), strtotime($row->timestamp) + get_option('gmt_offset') * 3600, false) . '</span>';
		    				}
			    				
							if ($row->moderator == 'yes') {
								$name_color = $_POST['moderator_name_color'];
							} else {
								$name_color = $_POST['name_color'];
							}
							
							// Show moderation tools to moderators for unapproved messages
							if ($moderator && $row->approved == 'no'){
								$moderation = '<span class="moderation">
									<a class="approve_once" href="#" onClick="approveMsg(this)">Approve this comment</a>
								</span>';
							}
							// Show message to users whose messages are unapproved
							else if ($moderator !==1 && $row->approved == 'no'){
								$moderation = '<span class="moderation">
									Your comment is pending approval by a moderator.
								</span>';
							}
							
							// Allow moderators to delete messages
							if ($moderator){
								$delete = '<span class="delete"><a href="#" onClick="deleteMsg(this)">Delete this message</a></span>';
							}
						
		    				$prepend .= ' <span class="name" style="background: '.$name_color.';">'.stripslashes($row->name).'</span>';
		    				
		    				$text[$row->id] = " <div id='row-".strtotime($row->timestamp)."' class='row'>{$prepend}<span class='message' style='color: ".$_POST['text_color']."'>".convert_smilies($message)."</span>$moderation $delete<div class='chat-clear'></div></div>";
		    				$last_check = $row->timestamp;
		    				
							if ($name != $row->name) {
								$new_message = true;
							}
							
							unset($moderation); // stop putting moderation on all messages
		    			}
		    			
					$log['text'] = $text;
					$log['time'] = strtotime($last_check)+1;
					$log['new_message'] = $new_message;
	    			}
				break; 
			case 'send':
				$chat_id = $_POST['cid'];
				$name = strip_tags($_POST['name']);
				$avatar = (isset($_COOKIE['chat_stateless_user_image_'.$this->auth_type_map[$_POST['type']]]) && !empty($_COOKIE['chat_stateless_user_image_'.$this->auth_type_map[$_POST['type']]]))?$_COOKIE['chat_stateless_user_image_'.$this->auth_type_map[$_POST['type']]]:$current_user->user_email;
				$message = $_POST['message'];
				
				$smessage = base64_decode($message);
				
	   			$smessage = preg_replace(array('/<code>/','/<\/code>/'), array('[code]', '[/code]'), $smessage);
				
				$smessage = strip_tags($smessage);
				
				$approved = $this->is_approved($blog_id, $chat_id, trim(base64_decode($name)));
				
				$this->send_message($chat_id, $name, $avatar, base64_encode($smessage), $moderator, $approved);
				break;
		}
		
		if ($return == 'yes') {
			if (isset($log['text']) && is_array($log['text'])) {
				return "<p>".join("</p><p>", $log['text'])."</p>";
			} else {
				return "";
			}
		} else {
			echo json_encode($log);
			exit(0);
		}
	}
	
	/**
	 * Test whether user has had a message approved yet
	 * 
	 * @param	int	$blog_id	ID of the site we're on
	 * @param	int	$chat_id	ID of the specific chat
	 * @param	string	$name	Name used in chat
	 * @return	string	$approved	yes if approved no if not
	 */
	function is_approved($blog_id, $chat_id, $name){
		global $wpdb;
		$query = "SELECT * FROM `".Chat::tablename('message')."` WHERE blog_id = '$blog_id' AND chat_id = '$chat_id' AND name LIKE '$name' AND approved = 'yes';";
		$results = $wpdb->get_results($query, ARRAY_A);
		$num_rows = $wpdb->num_rows;
		$approved = ($num_rows === 0) ? 'no' : 'yes';
		return $approved;
	}
	
	/**
	 * Test whether logged in user is a moderator
	 *
	 * @param	Array	$moderator_roles Moderator roles
	 * @return	bool	$moderator	 True if moderator False if not
	 */
	function is_moderator($moderator_roles) {
		global $current_user;
		
		if ($current_user->ID) {
			foreach ($moderator_roles as $role) {
				if (in_array($role, $current_user->roles)) {
					return true;
				}
			}
		}
		return false;
	}
	
	/**
	 * Get message
	 * 
	 * @global	object	$wpdb
	 * @global	int		$blog_id
	 * @param	int		$chat_id	Chat ID
	 * @param	int		$since		Start Unix timestamp
	 * @param	int		$end		End Unix timestamp
	 * @param	string	$archived	Archived? 'yes' or 'no'
	 * 
	 * @todo include non-approved messages for admins
	 * @todo include non-approved messages for 'self'
	 */
	function get_messages($chat_id, $since = 0, $end = 0, $archived = 'no', $since_id = false, $moderator = false, $is_approved = array('yes')) {
		global $wpdb, $blog_id;
		
		$chat_id = $wpdb->escape($chat_id);
		$archived = $wpdb->escape($archived);
		$since_id = $wpdb->escape($since_id);
		
		if (empty($end)) {
			$end = time();
		}
		
		$start = date('Y-m-d H:i:s', $since);
		$end = date('Y-m-d H:i:s', $end);
		
		if ($since_id == false) {
			$since_id = 0;
		} else {
			$start = date('Y-m-d H:i:s', 0);
		}
		
		$approved = ($moderator) ? "" : " AND approved = 'yes'"; // show unapproved messages to moderators
		
		$query = "SELECT * FROM `".Chat::tablename('message')."` WHERE blog_id = '$blog_id' AND chat_id = '$chat_id'$approved AND archived = '$archived' AND timestamp BETWEEN '$start' AND '$end' ORDER BY timestamp ASC;";
		$results = $wpdb->get_results($query);
		
		// user has not yet been approved so show them their own messages too
		if ($is_approved[0] == 'no'){
			$aquery = "SELECT * FROM `".Chat::tablename('message')."` WHERE blog_id = '$blog_id' AND chat_id = '$chat_id' AND name = '$is_approved[1]' AND approved = 'no' AND archived = '$archived' AND timestamp BETWEEN '$start' AND '$end' ORDER BY timestamp ASC;";
			$aresults = $wpdb->get_results($aquery);
			if ($wpdb->num_rows >0) { $results = array_merge($results, $wpdb->get_results($aquery)); }
		}
		
		return $results;
	}
	
	/**
	 * Send the message
	 * 
	 * @global	object	$wpdb
	 * @global	int	$blog_id
	 * @param	int	$chat_id	Chat ID
	 * @param	string	$name		Name
	 * @param	string	$avatar		URL or e-mail
	 * @param	string	$message	Payload message
	 * @param	string	$moderator	Moderator
	 */
	function send_message($chat_id, $name, $avatar, $message, $moderator) {
		global $wpdb, $blog_id;
		
		$wpdb->real_escape = true;
		
		$time_stamp = date("Y-m-d H:i:s");
		
		$chat_id = $wpdb->_real_escape($chat_id);
		$name = $wpdb->_real_escape(trim(base64_decode($name)));
		$avatar = $wpdb->_real_escape(trim($avatar));
		$message = $wpdb->_real_escape(trim(base64_decode($message)));
		$moderator_str = 'no';
		$approved_str = 'no'; // If this message has been approved by a moderator
		
		// check for previously approved messages
		$approvedq = $wpdb->get_results("SELECT * FROM ".Chat::tablename('message')." WHERE `name` LIKE '".$name."' AND `approved` = 'yes' LIMIT 1", ARRAY_A);
		$approved = $wpdb->num_rows;
		
		if (empty($message)) {
			return false;
		}
		if ($moderator) {
			$moderator_str = 'yes';
			$approved_str = 'yes';
		}
		
		if ($approved === 1) { // this user has already been pre-approved
			$approved_str= 'yes';
		}
		
		//$message = $query;
		
		return $wpdb->query("INSERT INTO ".Chat::tablename('message')."
					(blog_id, chat_id, timestamp, name, avatar, message, archived, moderator, approved)
					VALUES ('$blog_id', '$chat_id', '$time_stamp', '$name', '$avatar', '$message', 'no', '$moderator_str', '$approved_str');");
	}
	
	/**
	 * Get the last chat id for the given blog
	 * 
	 * @global	object	$wpdb
	 * @global	int		$blog_id
	 */
	function get_last_chat_id() {
		global $wpdb, $blog_id;
		
		$last_id = $wpdb->get_var("SELECT chat_id FROM `".Chat::tablename('message')."` WHERE blog_id = '{$blog_id}' ORDER BY chat_id DESC LIMIT 1");
		
		if ($last_id) {
			return substr($last_id, 0, -1);
		}
		return 1;
	}
	
	/**
	 * Clear a chat log
	 * 
	 * @global	object	$wpdb
	 * @global	int		$blog_id
	 */
	function clear() {
		global $wpdb, $blog_id;
		
		$since = date('Y-m-d H:i:s', $_POST['since']);
		$chat_id = $wpdb->escape($_POST['cid']);
		$option_type = ($_POST['cid'] == 1)?'site':'default';
		
		if ($this->is_moderator($this->get_option('moderator_roles', array('administrator','editor','author'), $option_type))) {
			$wpdb->query("DELETE FROM `".Chat::tablename('message')."` WHERE blog_id = '{$blog_id}' AND chat_id = '{$chat_id}' AND timestamp <= '{$since}' AND archived = 'no';");
		}
		exit(0);
	}
	
	/**
	 * Archive a chat log
	 * 
	 * @global	object	$wpdb
	 * @global	int		$blog_id
	 */
	function archive() {
		global $wpdb, $blog_id;
		
		$since = date('Y-m-d H:i:s', $_POST['since']);
		$chat_id = $wpdb->escape($_POST['cid']);
		$created = date('Y-m-d H:i:s');
		$option_type = ($_POST['cid'] == 1)?'site':'default';
		
		if ($this->is_moderator($this->get_option('moderator_roles', array('administrator','editor','author'), $option_type))) {
			$start = $wpdb->get_var("SELECT timestamp FROM `".Chat::tablename('message')."` WHERE blog_id = '{$blog_id}' AND chat_id = '{$chat_id}' AND timestamp <= '{$since}' AND archived = 'no' ORDER BY timestamp ASC LIMIT 1;");
			$end = $wpdb->get_var("SELECT timestamp FROM `".Chat::tablename('message')."` WHERE blog_id = '{$blog_id}' AND chat_id = '{$chat_id}' AND timestamp <= '{$since}' AND archived = 'no' ORDER BY timestamp DESC LIMIT 1;");
			
			$sql = array();
			
			$sql[] = "SELECT timestamp FROM `".Chat::tablename('message')."` WHERE blog_id = '{$blog_id}' AND chat_id = '{$chat_id}' AND timestamp <= '{$since}' AND archived = 'no' ORDER BY timestamp DESC LIMIT 1;";
			$sql[] = "SELECT timestamp FROM `".Chat::tablename('message')."` WHERE blog_id = '{$blog_id}' AND chat_id = '{$chat_id}' AND timestamp <= '{$since}' AND archived = 'no' ORDER BY timestamp ASC LIMIT 1; ";
			$sql[] = "UPDATE `".Chat::tablename('message')."` set archived = 'yes' WHERE blog_id = '{$blog_id}' AND chat_id = '{$chat_id}' AND timestamp BETWEEN '{$start}' AND '{$end}' AND archived = 'no';";
			
			$wpdb->query("UPDATE `".Chat::tablename('message')."` set archived = 'yes' WHERE blog_id = '{$blog_id}' AND chat_id = '{$chat_id}' AND timestamp BETWEEN '{$start}' AND '{$end}' AND archived = 'no';");
			
			$wpdb->query("INSERT INTO ".Chat::tablename('log')."
						(blog_id, chat_id, start, end, created)
						VALUES ('$blog_id', '$chat_id', '$start', '$end', '$created');");
		}
					
		exit(0);
	}
	
	/**
	 * Get a list of archives for the given chat
	 * 
	 * @global	object	$wpdb
	 * @global	int		$blog_id
	 * @param	int		$chat_id	Chat ID
	 * @return	array				List of archives
	 */
	function get_archives($chat_id) {
		global $wpdb, $blog_id;
		
		$chat_id = $wpdb->escape($chat_id);
		
		return $wpdb->get_results(
			"SELECT * FROM `".Chat::tablename('log')."` WHERE blog_id = '$blog_id' AND chat_id = '$chat_id' ORDER BY created ASC;"
		);
	}
}

// Lets get things started
$chat = new Chat();
