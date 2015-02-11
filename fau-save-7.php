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

load_plugin_textdomain('fau-save-7', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

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
	const textdomain = 'fau-save-7';
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
			'fs7_aktiv_1' => 0,
			'fs7_id_1' => '0000',
			'fs7_filename_1' => 'n72t27ks',
			'fs7_aktiv_2' => 0,
			'fs7_id_2' => '',
			'fs7_filename_2' => '',
			'fs7_aktiv_3' => 0,
			'fs7_id_3' => '',
			'fs7_filename_3' => ''
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

			<?php
			// Check if Contact Form 7 is installed
			if (is_plugin_active( 'contact-form-7/wp-contact-form-7.php' ) == false) {
				$error = sprintf(__('This plugin requires %sContact Form 7%s plugin. Please install and activate Contact Form 7 first.', self::textdomain),'<a href="https://wordpress.org/plugins/contact-form-7/" target="_blank">','</a>');
				if (!empty($error)) {
					echo '<div class="error"><p>' . $error . '</p><p>'
						. '&rarr; <a href="plugins.php">' . __('Go to Plugin Page', self::textdomain) . '</a></p></div>';
				}
				return;
			} ?>

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
		// Form Settings 1
		add_settings_section(
				'fs7_form1', // ID
				__('Form 1', self::textdomain), // Title
				'__return_false', // Callback
				'fs7_options' // Page
		);
		add_settings_field(
				'fs7_aktiv_1', // ID
				__('Save Form Data', self::textdomain), // Title
				array(__CLASS__, 'fs7_aktiv_callback'), // Callback
				'fs7_options', // Page
				'fs7_form1', // Section
				array(
					'name' => 'fs7_aktiv_1')
		);
		add_settings_field(
				'fs7_id_1',
				__('Form ID', self::textdomain),
				array(__CLASS__, 'fs7_id_callback'),
				'fs7_options',
				'fs7_form1',
				array(
					'name' => 'fs7_id_1')
		);
		add_settings_field(
				'fs7_filename_1',
				__('File Name', self::textdomain),
				array(__CLASS__, 'fs7_filename_callback'),
				'fs7_options',
				'fs7_form1',
				array(
					'name' => 'fs7_filename_1')
		);
		// Form Settings 2
		add_settings_section(
				'fs7_form_2', // ID
				__('Form 2', self::textdomain), // Title
				'__return_false', // Callback
				'fs7_options' // Page
		);
		add_settings_field(
				'fs7_aktiv_2', // ID
				__('Save Form Data', self::textdomain), // Title
				array(__CLASS__, 'fs7_aktiv_callback'), // Callback
				'fs7_options', // Page
				'fs7_form_2', // Section
				array(
					'name' => 'fs7_aktiv_2')
		);
		add_settings_field(
				'fs7_id_2',
				__('Form ID', self::textdomain),
				array(__CLASS__, 'fs7_id_callback'),
				'fs7_options',
				'fs7_form_2',
				array(
					'name' => 'fs7_id_2')
		);
		add_settings_field(
				'fs7_filename_2',
				__('File Name', self::textdomain),
				array(__CLASS__, 'fs7_filename_callback'),
				'fs7_options',
				'fs7_form_2',
				array(
					'name' => 'fs7_filename_2')
		);
		// Form Settings 3
		add_settings_section(
				'fs7_form_3', // ID
				__('Form 3', self::textdomain), // Title
				'__return_false', // Callback
				'fs7_options' // Page
		);
		add_settings_field(
				'fs7_aktiv_3', // ID
				__('Save Form Data', self::textdomain), // Title
				array(__CLASS__, 'fs7_aktiv_callback'), // Callback
				'fs7_options', // Page
				'fs7_form_3', //Section
				array(
					'name' => 'fs7_aktiv_3')
		);
		add_settings_field(
				'fs7_id_3',
				__('Form ID', self::textdomain),
				array(__CLASS__, 'fs7_id_callback'),
				'fs7_options',
				'fs7_form_3',
				array(
					'name' => 'fs7_id_3')
		);
		add_settings_field(
				'fs7_filename_3',
				__('File Name', self::textdomain),
				array(__CLASS__, 'fs7_filename_callback'),
				'fs7_options',
				'fs7_form_3',
				array(
					'name' => 'fs7_filename_3')
		);
	}

	/**
	 * Sanitize each setting field as needed
	 */
	public function sanitize($input) {
		$new_input = array();
		for ($i = 1; $i <= 3; $i++) {
			if (isset($input['fs7_aktiv_'.$i])) {
				$new_input['fs7_aktiv_'.$i] = ( $input['fs7_aktiv_'.$i] == 1 ? 1 : 0 );
			}
			if (isset($input['fs7_id_'.$i])) {
				$new_input['fs7_id_'.$i] = absint($input['fs7_id_'.$i]);
			}
			if (isset($input['fs7_filename_'.$i])) {
				$new_input['fs7_filename_'.$i] = sanitize_text_field($input['fs7_filename_'.$i]);
			}
		}
		return $new_input;
	}

	/**
	 * Get the settings option array and print its values
	 */
	// Checkbox "aktiv"
	public static function fs7_aktiv_callback($args) {
		$options = self::get_options();
		$name = esc_attr( $args['name'] );
		?>
		<input name="<?php printf('%s['.$name.']' , self::option_name); ?>" type='checkbox' value='1' <?php if (array_key_exists($name, $options)) { print checked($options[$name], 1, false); } ?> >
		<?php
		}

	// Textbox "Form ID"
	public static function fs7_id_callback($args) {
		$options = self::get_options();
		$name = esc_attr( $args['name'] );
		?>
		<input name="<?php printf('%s['.$name.']', self::option_name); ?>" type='text' value="<?php if (array_key_exists($name, $options)) { echo $options[$name]; } ?>" ><br />
		<span class="description"><?php _e('Please enter the Contact Form 7 form ID.', self::textdomain) ?></span>
		<?php
	}
	// Textbox "File name"
	public static function fs7_filename_callback($args) {
		$options = self::get_options();
		$name = esc_attr( $args['name'] );
		$uploads = wp_upload_dir();
		$uploads_dir = trailingslashit($uploads['baseurl']);

		?>
		<input name="<?php printf('%s['.$name.']', self::option_name); ?>" type='text' value="<?php if (array_key_exists($name, $options)) { echo $options[$name]; } ?>" >.csv<br />
		<span class="description">
			<?php
			_e('You can download the CSV file at: ', self::textdomain);
			if (isset($options[$name]) && strlen($options[$name]) > 2) {
				echo '<a href="' . $uploads_dir . $options[$name] . '.csv">';
				echo $uploads_dir . $options[$name] . '.csv';
				echo '</a>';
			} else {
				echo $uploads_dir . '[your_file_name].csv';
			}
	}

	/**
	 * Contact Form 7 : Save Form Data in CSV file
	 * Path: wp-content/uploads/$options['fs7_filename'].csv
	 */
	public static function wpcf7_write_csv($wpcf7_data) {

		require_once(ABSPATH . '/wp-admin/includes/file.php');
		global $wp_filesystem;
		WP_Filesystem();
		$submission = WPCF7_Submission::get_instance();
		if ($submission) {
			$form_fields = $submission->get_posted_data();
			$form_id = $form_fields['_wpcf7'];
		}

		$options = self::get_options();
		$opt_array = array();
		for ($i = 1; $i <= 3; $i++) {
			$opt_array[$options['fs7_id_'.$i]]['aktiv'] = $options['fs7_aktiv_'.$i];
			$opt_array[$options['fs7_id_'.$i]]['filename'] = $options['fs7_filename_'.$i];
		}

		if (array_key_exists($form_id, $opt_array)) {
			if ((isset($opt_array[$form_id]['aktiv']) && $opt_array[$form_id]['aktiv'] == 1 )
			&& (isset($opt_array[$form_id]['filename']))) {

				$uploads = wp_upload_dir();
				$uploads_dir = trailingslashit($uploads['basedir']);
				$file = $uploads_dir . '/' . $opt_array[$form_id]['filename'] . '.csv';
				$form_fields = str_replace(array("\r\n", "\r", "\n"), " ", $form_fields);
				$form_fields = str_replace("\"", "'", $form_fields);
				foreach ($form_fields as $k => $v) {
					if (is_array($v)) {
						$v_new = implode(' | ', $v);
						$form_fields[$k] = $v_new;
					}
				}
				$csv_fields = array_slice($form_fields, 5);
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
			}
		}
		// If you want to skip mailing the data, you can do it...
		// $wpcf7_data->skip_mail = false;
	}
}
