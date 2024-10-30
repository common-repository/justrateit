<?php
/*
Plugin Name: JustRateIt
Plugin URI: http://wordpress.org/extend/plugins/justrateit/
Description: A nice and super simple way of adding ratings in your posts.
Version: 1.0
Author: Sebastian Österberg
Author URI: http://www.sebastianosterberg.se/justrateit
License: GPL2
*/

class justrateit {

	public static $last_error = "";

	public function __construct() {
		register_activation_hook(__FILE__, array($this, 'install'));

		add_shortcode('justrateit-button', array($this, 'shortcode_button'));
		add_shortcode('rateit-button', array($this, 'shortcode_button'));

		add_action('template_redirect', array($this, 'add_style_and_script'));
		add_action('wp_head', array($this, 'ajax_url'));
		add_action('wp_ajax_justrateit_ajax', array($this, 'ajax_callback'));
		add_action('wp_ajax_nopriv_justrateit_ajax', array($this, 'ajax_callback'));

		if (is_admin()) 
		{
			add_action('admin_menu', array($this, 'add_admin_menu'));
			add_action('admin_init', array($this, 'register_settings'));
		}
	}

	// ACTIONS, REGISTER, SHORTCUTS ETC.

	/**
	* Add style and script
	* Add style and script files used by this plugin.
	*/
	public function add_style_and_script() {
		wp_enqueue_script('justrateit', plugins_url('justrateit.js', __FILE__), array('jquery'), '1.0', true);
		wp_enqueue_style('justrateit', plugins_url('justrateit.css', __FILE__));
	}

	/**
	* Ajax callback
	* This is called via ajax from the javascript.
	*
	* @param $_POST["justrateit"] 	string 	action to preform. 	
	*/
	public function ajax_callback() {

		if (!isset($_POST["justrateit"]))
			exit(json_encode(array("error" => __("Missing 'justrateit'", "justrateit"))));

		$action = $_POST["justrateit"];
		switch ($action) {
			case 'get-button':
				if (!isset($_POST["identifier"]))
					exit(json_encode(array("error" => __("Missing identifier", "justrateit"))));

				exit($this->get_button_layout($_POST["identifier"]));
				break;

			case 'vote':
				if (!isset($_POST["identifier"]))
					exit(json_encode(array("error" => __("missing identifier", "justrateit"))));
				if (!isset($_POST["value"]))
					exit(json_encode(array("error" => __("missing value", "justrateit"))));
				if ($this->add_vote($_POST["identifier"], $_POST["value"]))
					exit(json_encode(array("success" => __("vote succeeded", "justrateit"))));
				
				exit(json_encode(array("error" => self::get_last_error())));
				break;

			default:
				exit(json_encode(array("error" => __("unknown action ", "justrateit").$action)));
				break;
		}
	}

	/**
	* Ajax url
	* prints a javascript variable with the ajax url.
	*/
	public function ajax_url() {
		?><script type="text/javascript">var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';</script><?php
	}

	/**
	* Register settings
	* Register settings for the settings page.
	*/
	public function register_settings() {
		register_setting("justrateit_settings_group", "max_per_user", "intval");
		register_setting("justrateit_settings_group", "max_per_ip", "intval");
		register_setting("justrateit_settings_group", "allow_anonymous_votes", "intval");
	}

	/**
	* Render settings
	* Render the settings page.
	*/
	public function render_settings() {
		include "justrateit_options.php";
	}

	/**
	* Add admin menu
	* Adds an admin menu item under the settings tab.
	*/
	public function add_admin_menu() {
		add_options_page("JustRateIt Settings", "JustRateIt", "manage_options", "justrateit_plugin_option_menu_slug", array($this, 'render_settings'));
	}

	/**
	* Shortcode button
	* Replplace shortcode with button in posts.
	*
	* @param $atts 			array 	arrat of attributes in tag.
	* 
	* @return the markup to replace the shortcode tag with.
	*/
	public function shortcode_button($atts) 
	{
		$identifier = "";
		if (isset($atts["id"]))
			$identifier = $atts["id"];
		
		$layout = "default";
		$html = "";

		if (isset($atts["layout"]))
			$layout = $atts["layout"];

		return $this->get_button_layout($identifier, $layout);
	}

	/**
	* Install
	* This runs when plugin is activated
	*/
	public function install() {	
		global $wpdb;
		$table_name = $wpdb->prefix . "justrateit";

		$sql = "CREATE TABLE $table_name (
			id int(11) NOT NULL AUTO_INCREMENT,
			identifier VARCHAR(255) NOT NULL,
			value INT NOT NULL DEFAULT 0,
			user_id INT NOT NULL DEFAULT 0,
			ip VARCHAR(45) NOT NULL,
			created DATETIME NOT NULL,
			PRIMARY KEY (id),
			INDEX identifier (identifier ASC)
		);";

		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		dbDelta($sql);
	}

	// ERRORS

	/**
	* Set last error
	* 
	* @param $error 		string 	latest error message
	*/
	public static function set_last_error($error) {
		self::$last_error = $error;
	}

	/**
	* Get last error
	*
	* @return string latest error message
	*/
	public static function get_last_error() {
		return self::$last_error;
	}

	// VOTES

	/**
	* Add vote
	* Adds a vote to the database
	*
	* @param $identifier 	string 	identifier for the voteing 
	* @param $value 		int		rate value (1-5)
	* 
	* @return bool True if success false if fail. Errors can be found in justrateit::get_last_error()
	*/
	public function add_vote($identifier, $value) {
		global $wpdb;

		$table_name = $wpdb->prefix . "justrateit";
		$current_user = wp_get_current_user();
		$ip = ip2long($_SERVER["REMOTE_ADDR"]);

		if (get_option("max_per_ip") > 0)
		{
			$count_per_ip = $this->get_vote_count($identifier, null, null, $ip);

			if ($count_per_ip >= get_option("max_per_ip"))
			{
				self::set_last_error(__("Max votes per ip reached", "justrateit"));
				return false;
			}
		}

		if (get_option("max_per_user") > 0 && $current_user->ID > 0)
		{
			$count_per_user = $this->get_vote_count($identifier, null, $current_user->ID);
			
			if ($count_per_user >= get_option("max_per_user"))
			{
				self::set_last_error(__("Max votes per user reached", "justrateit"));
				return false;
			}
		}

		if ($current_user->ID == 0 && get_option("allow_anonymous_votes") == 0)
		{
			self::set_last_error(__("Anonymous votes not allowed", "justrateit"));
			return false;
		}


		$res = $wpdb->insert($table_name, array(
			'identifier' => $identifier,
			'value' => $value,
			'user_id' => $current_user->ID,
			'ip' => $ip,
			'created' => date("Y-m-d H:i:s")
		));

		if ($res <= 0)
		{
			self::set_last_error(__("No results returned", "justrateit"));
			return false;
		}

		return true;
	}

	/**
	* Get vote count
	* Count all votes for a voting
	* @param $identifier 	string 	identifier for the voteing 
	* @param $value 		int		rate value (1-5)
	* @param $user_id 		int 	match votes from this user ID
	* @param $ip 			long 	match votes from this ip
	*
	* @return number of items found
	*/
	public function get_vote_count($identifier, $value=null, $user_id = 0, $ip = 0) {
		global $wpdb;

		$table_name = $wpdb->prefix . "justrateit";
		$sql = "SELECT count(0) FROM $table_name WHERE identifier = '".$wpdb->escape($identifier)."'";
		if ($value != null)
			$sql .= " AND value = '".$wpdb->escape($value)."'";
		if ($user_id > 0)
			$sql .= " AND user_id = '".$wpdb->escape($user_id)."'";
		if ($ip > 0)
			$sql .= " AND ip = '".$wpdb->escape($ip)."'";
		
		$res = $wpdb->get_col($sql);
		if (count($res) > 0)
			return $res[0];

		return 0;
	}

	/**
	* Get vote average
	* Calculate the avatage vote value for a voting
	* @param $identifier 	string 	identifier for the voteing 
	* 
	* @return votings avarage value.
	*/
	public function get_vote_avg($identifier) {
		global $wpdb;
		$table_name = $wpdb->prefix . "justrateit";
		$sql = "SELECT avg(value) FROM $table_name WHERE identifier = '".$wpdb->escape($identifier)."'";
		
		$res = $wpdb->get_col($sql);
		if (count($res) > 0)
			return $res[0];

		return null;
	}

	/**
	* Get button layout
	* Get the markup for the vote buttons
	* @param $identifier 	string 	identifier for the voteing 
	* @param $layout 		string 	select the layout
	* 
	* @return markup for the vote buttons
	*/
	public function get_button_layout($identifier, $layout=null)
	{
		switch ($layout) {
			case 'stars':
				break;
			
			default:
				$avg = $this->get_vote_avg($identifier);
				$html = "<span class=\"justrateit justrateit-avg-".round($avg)."\" id=\"justrateit-id-".$identifier."\">";
				for ($i=1; $i <= 5; $i++) 
				{
					$active = "";
					if ($i <= (int)$avg) 
						$active = "active";
					$html .= "<a href=\"#$i\" class=\"value-".$i." star ".$active."\">*</a>";
				}
				$html .= " <span class=\"count\">(".$this->get_vote_count($identifier).")</span></span>";
				break;
		}
		return $html;
	}
}

$justrateit = new justrateit();