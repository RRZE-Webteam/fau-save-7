<?php
/**
 * Plugin Name: FAU Save 7
 * Description: Erweiterung des WP-Formular-Plugins "Contact Form 7": Speichert Formulardaten in einer CSV-Datei
 * Version: 1.0
 * Author: Barbara Bothe
 * Author URI: http://blogs.fau.de/webworking/
 * License: GPLv2 or later
 */
/*
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 */

add_action('plugins_loaded', array('FAU_Save_7', 'instance'));

register_activation_hook(__FILE__, array('FAU_Save_7', 'activate'));
register_deactivation_hook(__FILE__, array('FAU_Save_7', 'deactivate'));

//Contact Form 7 : CF7-JavaScript deaktivieren (Konflikt mit Theme-JS)
add_filter('wpcf7_load_js', '__return_false');

class FAU_Save_7 {

	/**
	 * Get Started
	 */
	const version = '1.1';
	const option_name = '_fs7';
	const version_option_name = '_fs7_version';
	const textdomain = 'fs7';
	const php_version = '5.3'; // Minimal erforderliche PHP-Version
	const wp_version = '3.9.2'; // Minimal erforderliche WordPress-Version

	protected static $instance = null;
	private static $fs7_option_page = null;

	public static function instance() {

		if (null == self::$instance) {
			self::$instance = new self;
			self::$instance->init();
		}

		return self::$instance;
	}

	private function init() {
		load_plugin_textdomain(self::textdomain, false, sprintf('%s/languages/', dirname(plugin_basename(__FILE__))));
		add_action('admin_init', array(__CLASS__, 'admin_init'));
		add_action('admin_menu', array(__CLASS__, 'add_options_page'));
		add_action('wpcf7_before_send_mail', array(__CLASS__, 'wpcf7_write_csv'));
		add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), array(__CLASS__, 'add_action_links') );
	}

	/**
	 * Check PHP and WP Version and if Contact Form 7 is active
	 */
	public static function activate() {
		self::version_compare();
		update_option(self::version_option_name, self::version);
	}

	private static function version_compare() {
		$error = '';

		if (version_compare(PHP_VERSION, self::php_version, '<')) {
			$error = sprintf(__('Your PHP version %s is deprecated. Please update at least to version %s.', self::textdomain), PHP_VERSION, self::php_version);
		}

		if (version_compare($GLOBALS['wp_version'], self::wp_version, '<')) {
			$error = sprintf(__('Your Wordpresss version %s is deprecated. Please update at least to version %s.', self::textdomain), $GLOBALS['wp_version'], self::wp_version);
		}

		if (is_plugin_active( 'contact-form-7/wp-contact-form-7.php' ) == false) {
			$error = sprintf(__('This plugin requires %sContact Form 7%s plugin. Please install and activate Contact Form 7 first.', self::textdomain),'<a href="https://wordpress.org/plugins/contact-form-7/" target="_blank">','</a>');
		}

		if (!empty($error)) {
			deactivate_plugins(plugin_basename(__FILE__), false, true);
			wp_die(
				$error,
				__('Plugin Activation Error', self::textdomain),
				array(
					'response' => 500,
					'back_link' => TRUE
				)
			);
		}
	}

	public static function update_version() {
		if (get_option(self::version_option_name, null) != self::version)
			update_option(self::version_option_name, self::version);
	}

	/**
	 * Display settings link on the plugins page (beside the activate/deactivate links)
	 */
	public static function add_action_links ( $links ) {
		$mylinks = array(
			'<a href="' . admin_url( 'options-general.php?page=options-fs7' ) . '">' . __('Settings', self::textdomain) . '</a>',
		);
		return array_merge( $links, $mylinks );
	}

	/**
	 * Get Options
	 */
	private static function get_options() {
		$defaults = self::default_options();
		$options = (array) get_option(self::option_name);
		$options = wp_parse_args($options, $defaults);
		$options = array_intersect_key($options, $defaults);
		return $options;
	}

	/**
	 * Set default options
	 */
	private static function default_options() {
		$options = array(
			'fs7_aktiv' => 1,
			'fs7_id' => '0000',
			'fs7_filename' => 'emil'
		);
		return $options;
	}

	/**
	 * Add options page
	 */
	public static function add_options_page() {
		self::$fs7_option_page = add_options_page(
				'FAU Save 7', 'FAU Save 7', 'manage_options', 'options-fs7', array(__CLASS__, 'options_fs7')
		);
		//add_action('load-' . self::$fs7_option_page, array(__CLASS__, 'fs7_help_menu'));
	}

	/**
	 * Options page callback
	 */
	public static function options_fs7() {
		?>
		<div class="wrap">
		<?php screen_icon(); ?>
			<h2><?php echo __('Settings', self::textdomain) . ' &rsaquo; FAU Save 7'; ?></h2>

			<form method="post" action="options.php">
		<?php
		settings_fields('fs7_options');
		do_settings_sections('fs7_options');
		submit_button();
		?>
			</form>
		</div>
		<?php
		}

	/**
	 * Register and add settings
	 */
	public static function admin_init() {
		register_setting(
				'fs7_options', // Option group
				self::option_name, // Option name
				array(__CLASS__, 'sanitize') // Sanitize
		);
		add_settings_section(
				'fs7_section', // ID
				false, // Title
				'__return_false', // Callback
				'fs7_options' // Page
		);
		add_settings_field(
				'fs7_aktiv', // ID
				__('Save Form Data', self::textdomain), // Title
				array(__CLASS__, 'fs7_aktiv_callback'), // Callback
				'fs7_options', // Page
				'fs7_section' // Section
		);
		add_settings_field(
				'fs7_id', __('Form ID', self::textdomain), array(__CLASS__, 'fs7_id_callback'), 'fs7_options', 'fs7_section'
		);
		add_settings_field(
				'fs7_filename', __('File Name', self::textdomain), array(__CLASS__, 'fs7_filename_callback'), 'fs7_options', 'fs7_section'
		);
	}

	/**
	 * Sanitize each setting field as needed
	 */
	public function sanitize($input) {
		$new_input = array();
		if (isset($input['fs7_aktiv'])) {
			$new_input['fs7_aktiv'] = ( $input['fs7_aktiv'] == 1 ? 1 : 0 );
		}
		if (isset($input['fs7_id'])) {
			$new_input['fs7_id'] = absint($input['fs7_id']);
		}
		if (isset($input['fs7_filename'])) {
			$new_input['fs7_filename'] = sanitize_text_field($input['fs7_filename']);
		}
		return $new_input;
	}

	/**
	 * Get the settings option array and print its values
	 */
	// Checkbox "aktiv"
	public static function fs7_aktiv_callback() {
		$options = self::get_options();
		?>
		<input name="<?php printf('%s[fs7_aktiv]', self::option_name); ?>" type='checkbox' value='1' <?php print checked($options['fs7_aktiv'], 1, false); ?> >
		<?php
	}
	// Textbox "Form ID"
	public static function fs7_id_callback() {
		$options = self::get_options();
		?>
		<input name="<?php printf('%s[fs7_id]', self::option_name); ?>" type='text' value="<?php echo $options['fs7_id']; ?>" ><br />
		<span class="description"><?php _e('Please enter the Contact Form 7 form ID.', self::textdomain) ?></span>
		<?php
	}
	// Textbox "File name"
	public static function fs7_filename_callback() {
		$options = self::get_options();
		$uploads = wp_upload_dir();
		$uploads_dir = trailingslashit($uploads['baseurl']);

		?>
		<input name="<?php printf('%s[fs7_filename]', self::option_name); ?>" type='text' value="<?php echo $options['fs7_filename']; ?>" >.csv<br />
		<span class="description">
			<?php
			_e('You can download the CSV file at: ', self::textdomain);
			if (isset($options['fs7_filename']) && strlen($options['fs7_filename']) > 2) {
				echo '<a href="' . $uploads_dir . '/' . $options['fs7_filename'] . '.csv">';
				echo $uploads_dir . $options['fs7_filename'] . '.csv';
				echo '</a>';
			} else {
				echo $uploads_dir . '/' . '[your_file_name].csv';
			}
	}

	/**
	 * Contact Form 7 : Save Form Data in CSV file
	 * Path: wp-content/uploads/$options['fs7_filename'].csv
	 */
	public static function wpcf7_write_csv($wpcf7_data) {

		$options = self::get_options();

		if ((isset($options['fs7_aktiv']) && $options['fs7_aktiv'] == 1 )
			&& (isset($options['fs7_id']) && $wpcf7_data->id() == $options['fs7_id'])
			&& (isset($options['fs7_filename']))) {

			require_once(ABSPATH . '/wp-admin/includes/file.php');
			global $wp_filesystem;
			/*echo "<pre>";
			var_dump($options['fs7_id']);
			var_dump($wpcf7_data->id());
			echo "</pre>";*/
			WP_Filesystem();
			$submission = WPCF7_Submission::get_instance();
			if ($submission) {
				$form_fields = $submission->get_posted_data();
			}
			$uploads = wp_upload_dir();
			$uploads_dir = trailingslashit($uploads['basedir']);
			$file = $uploads_dir . '/' . $options['fs7_filename'] . '.csv';
			$form_fields = str_replace(array("\r\n", "\r", "\n"), " ", $form_fields);
			$form_fields = str_replace("\"", "'", $form_fields);
			$csv_fields = array_slice($form_fields, 4);
			$csv_line = "\"" . stripslashes(implode("\";\"", $csv_fields)) . "\"" . PHP_EOL;

			if (!file_exists($file)) {
				if (!$wp_filesystem->put_contents($file, $csv_line, FS_CHMOD_FILE)) {
					return new WP_Error('writing_error', 'Error when writing file'); //return error object
				}
			} else {
				$csv_old = $wp_filesystem->get_contents($file);
				$csv_new = $csv_old . $csv_line;
				if (!$wp_filesystem->put_contents($file, $csv_new, FS_CHMOD_FILE)) {
					return new WP_Error('writing_error', 'Error when writing file'); //return error object
				}
			}

			// If you want to skip mailing the data, you can do it...
			$wpcf7_data->skip_mail = false;
		}
	}
}
