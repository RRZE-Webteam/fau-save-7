<?php
/*
  Plugin Name: FAU Save 7
  Plugin URI: https://github.com/RRZE-Webteam/fau-save-7
  Version: 3.2
  Description: Speichert und verwaltet Formulareingaben aus Contact Form 7, CSV-Export
  Author: RRZE-Webteam
  Author URI: http://blogs.fau.de/webworking/
  Network:
 */

/*
  This program is free software; you can redistribute it and/or
  modify it under the terms of the GNU General Public License
  as published by the Free Software Foundation; either version 2
  of the License, or (at your option) any later version.

  This program is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.

  You should have received a copy of the GNU General Public License
  along with this program; if not, write to the Free Software
  Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 */

load_plugin_textdomain('fau-save-7', false, dirname(plugin_basename(__FILE__)) . '/languages');

add_action('plugins_loaded', array('FAU_Save_7', 'instance'));

register_activation_hook(__FILE__, array('FAU_Save_7', 'activation'));
register_deactivation_hook(__FILE__, array('FAU_Save_7', 'deactivation'));

//Contact Form 7 : CF7-JavaScript deaktivieren (Konflikt mit Theme-JS)
add_filter('wpcf7_load_js', '__return_false');


/*
 * FAU_Save_7-Klasse
 */

class FAU_Save_7 {
    /*
     * Name der Variable unter der die Einstellungen des Plugins gespeichert werden.
     * string
     */

    const option_name = 'fs7';

    /*
     * Name der Text-Domain.
     * string
     */
    const textdomain = 'fau-save-7';

    /*
     * Minimal erforderliche PHP-Version.
     * string
     */
    const php_version = '5.3';

    /*
     * Minimal erforderliche WordPress-Version.
     * string
     */
    const wp_version = '4.1';

    /*
     * Optionen des Pluginis
     * object
     */

    protected static $options;

    /*
     * "Screen ID" der Einstellungsseite
     * string
     */
    protected $admin_settings_page;

    /*
     * Bezieht sich auf eine einzige Instanz dieser Klasse.
     * mixed
     */
    protected static $instance = null;

    /*
     * Enthält alle vorhandenen CF7-Formulare
     * array
     */
    protected static $cf7Forms;

    /*
     * Erstellt und gibt eine Instanz der Klasse zurück.
     * Es stellt sicher, dass von der Klasse genau ein Objekt existiert (Singleton Pattern).
     * @return object
     */

    public static function instance() {

        if (null == self::$instance) {
            self::$instance = new self;
        }

        return self::$instance;
    }

    /*
     * Initialisiert das Plugin, indem die Lokalisierung, Hooks und Verwaltungsfunktionen festgesetzt werden.
     * @return void
     */

    private function __construct() {

        // Enthaltene Optionen.
        self::$options = self::get_options();

        /* -- START META-BOXES (Optional) -- */
        // Das CMB-Framework wird eingebunden und initialisiert.
        // (Auf Basis von https://github.com/WebDevStudios/Custom-Metaboxes-and-Fields-for-WordPress)
        //include_once(plugin_dir_path(__FILE__) . 'includes/cmb-meta-boxes.php');
        /* -- ENDE META-BOXES -- */

        /* -- START Optionsseite (Optional) -- */
        add_action('admin_menu', array($this, 'admin_settings_page'));
        add_action('admin_init', array($this, 'admin_settings'));
        /* -- ENDE Optionsseite -- */

        // Ab hier können weitere Hooks angelegt werden.
        //add_action('wpcf7_mail_sent', array(__CLASS__, 'save_file'));
        add_action('wpcf7_before_send_mail', array(__CLASS__, 'wpcf7_save_data'));

        add_filter('plugin_action_links_' . plugin_basename(__FILE__), array(__CLASS__, 'add_action_links'));
        add_action('admin_menu', array(__CLASS__, 'register_fs7_submenu_page'), 11);

        add_action('admin_init', array(__CLASS__, 'fs7_template_redirect'));

        $args = array('post_type' => 'wpcf7_contact_form', 'numberposts' => -1);
        self::$cf7Forms = get_posts($args);
    }

    /*
     * Wird durchgeführt wenn das Plugin aktiviert wird.
     * @return void
     */

    public static function activation() {
        // Überprüft die minimal erforderliche PHP- u. WP-Version.
        self::version_compare();

        // Ab hier können die Funktionen/Methoden hinzugefügt werden,
        // die bei der Aktivierung des Plugins aufgerufen werden müssen.
        // Bspw. wp_schedule_event, flush_rewrite_rules, etc.
    }

    /*
     * Wird durchgeführt wenn das Plugin deaktiviert wird
     * @return void
     */

    public static function deactivation() {
        // Hier können die Funktionen/Methoden hinzugefügt werden, die
        // bei der Deaktivierung des Plugins aufgerufen werden müssen.
        // Bspw. wp_clear_scheduled_hook
    }

    /*
     * Überprüft die minimal erforderliche PHP- u. WP-Version.
     * @return void
     */

    public static function version_compare() {
        $error = '';

        if (version_compare(PHP_VERSION, self::php_version, '<')) {
            $error = sprintf(__('Ihre PHP-Version %s ist veraltet. Bitte aktualisieren Sie mindestens auf die PHP-Version %s.', 'fau-save-7'), PHP_VERSION, self::php_version);
        }

        if (version_compare($GLOBALS['wp_version'], self::wp_version, '<')) {
            $error = sprintf(__('Ihre Wordpress-Version %s ist veraltet. Bitte aktualisieren Sie mindestens auf die Wordpress-Version %s.', 'fau-save-7'), $GLOBALS['wp_version'], self::wp_version);
        }

        if (is_plugin_active('contact-form-7/wp-contact-form-7.php') == false) {
            $error = sprintf(__('Dieses Plugin erfordert das Plugin %sContact Form 7%s. Installieren Sie zuerst Contact Form 7 und aktivieren Sie danach Form Save 7.', 'fau-save-7'), '<a href="https://wordpress.org/plugins/contact-form-7/" target="_blank">', '</a>');
            //$error = sprintf(__('This plugin requires %sContact Form 7%s plugin. Please install and activate Contact Form 7 first.', 'fau-save-7'),'<a href="https://wordpress.org/plugins/contact-form-7/" target="_blank">','</a>');
        }

        // Wenn die Überprüfung fehlschlägt, dann wird das Plugin automatisch deaktiviert.
        if (!empty($error)) {
            deactivate_plugins(plugin_basename(__FILE__), false, true);
            wp_die($error);
        }
    }

    /**
     * Display settings link on the plugins page (beside the activate/deactivate links)
     */
    public static function add_action_links($links) {
        $mylinks = array(
            '<a href="' . admin_url('options-general.php?page=options-fs7') . '">' . __('Einstellungen', 'fau-save-7') . '</a>',
        );
        return array_merge($links, $mylinks);
    }

    /*
     * Standard Einstellungen werden definiert
     * @return array
     */

    private static function default_options() {
        $options = array();
        foreach ((array) self::$cf7Forms as $cf7Form) {
            $id = $cf7Form->ID;
            $options['fs7_aktiv_' . $id] = 0;
        }
        return $options;
    }

    /*
     * Gibt die Einstellungen zurück.
     * @return object
     */

    private static function get_options() {
        $defaults = self::default_options();

        $options = (array) get_option(self::option_name);
        $options = wp_parse_args($options, $defaults);
        $options = array_intersect_key($options, $defaults);

        return $options;
    }

    /*
     * Füge eine Optionsseite in das Menü "Einstellungen" hinzu.
     * @return void
     */

    public function admin_settings_page() {
        $this->admin_settings_page = add_options_page(
                __('FAU Save 7', 'fau-save-7'), __('FAU Save 7', 'fau-save-7'), 'manage_options', 'options-fs7', array($this, 'settings_page'));
        //add_action('load-' . $this->admin_settings_page, array($this, 'admin_help_menu'));
    }

    /*
     * Die Ausgabe der Optionsseite.
     * @return void
     */

    public function settings_page() {
        ?>
        <div class="wrap">
            <h2><?php echo __('Einstellungen &rsaquo; FAU Save 7', 'fau-save-7'); ?></h2>

            <?php
            // Check if Contact Form 7 is installed
            if (is_plugin_active('contact-form-7/wp-contact-form-7.php') == false) {
                $error = sprintf(__('Dieses Plugin erfordert das Plugin %sContact Form 7%s. Installieren Sie zuerst Contact Form 7 und aktivieren Sie danach Form Save 7.', 'fau-save-7'), '<a href="https://wordpress.org/plugins/contact-form-7/" target="_blank">', '</a>');
                if (!empty($error)) {
                    echo '<div class="error"><p>' . $error . '</p><p>'
                    . '&rarr; <a href="plugins.php">' . __('Zur Plugin-Seite', 'fau-save-7') . '</a></p></div>';
                }
                return;
            }
            ?>

            <p><?php _e('Legen Sie hier fest, für welche Ihrer Kontaktformulare die Einträge gespeichert werden sollen.', 'fau-save-7'); ?></p>

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

    /*
     * Legt die Einstellungen der Optionsseite fest.
     * @return void
     */

    public function admin_settings() {
        register_setting(
                'fs7_options', // Option group
                self::option_name, // Option name
                array(__CLASS__, 'options_validate') // Sanitize
        );
        add_settings_section(
                'fs7_form', // ID
                __('Einträge speichern für folgende Formulare:', 'fau-save-7'), // Title
                '__return_false', // Callback
                'fs7_options' // Page
        );
        foreach (self::$cf7Forms as $cf7Form) {
            $id = $cf7Form->ID;
            $title = $cf7Form->post_title;
            add_settings_field(
                    'fs7_aktiv_' . $id, // ID
                    $title . '<br />(ID: ' . $id . ')', // Title
                    array(__CLASS__, 'fs7_aktiv_callback'), // Callback
                    'fs7_options', // Page
                    'fs7_form', // Section
                    array(
                'name' => 'fs7_aktiv_' . $id)
            );
        }
    }

    /*
     * Validiert die Eingabe der Optionsseite.
     * @param array $input
     * @return array
     */

    public function options_validate($input) {
        $new_input = array();
        foreach (self::$cf7Forms as $cf7Form) {
            $id = $cf7Form->ID;
            if (isset($input['fs7_aktiv_' . $id])) {
                $new_input['fs7_aktiv_' . $id] = ( $input['fs7_aktiv_' . $id] == 1 ? 1 : 0 );
            }
        }
        return $new_input;
    }

    /*
     * Felder der Optionsseite
     * @return void
     */

    // Checkbox "aktiv"
    public static function fs7_aktiv_callback($args) {
        $options = self::get_options();
        $name = esc_attr($args['name']);
        ?>
        <input name="<?php printf('%s[' . $name . ']', self::option_name); ?>" type='checkbox' value='1' <?php if (array_key_exists($name, $options)) {
            checked($options[$name], 1, true);
        } ?> />
        <?php
    }

    /*
     *  Daten beim Absenden des Formulars in der Options-Tabelle speichern
     */

    public static function wpcf7_save_data() {

        $options = self::get_options();

        $submission = WPCF7_Submission::get_instance();
        if (!$submission) {
            return;
        }

        $form_fields = $submission->get_posted_data();
        $form_id = $form_fields['_wpcf7'];

        // Dateien speichern

        $trick_17 = print_r($submission, TRUE);
        $trick_18 = strstr($trick_17, '[uploaded_files:WPCF7_Submission:private] => Array');
        $trick_19 = strstr($trick_18, ' )', true) . ' )';
        $trick_20 = strstr($trick_19, '(');
        $uploaded_files = explode('[', $trick_20);
        unset($uploaded_files[0]);

        foreach ($uploaded_files as $key => $array_string) {
            $uploaded_files[$key] = explode('] => ', $array_string);
            $new_key = explode('] => ', $array_string)['0'];
            $new_value = explode('] => ', $array_string)['1'];
            $new_value = str_replace(')', '', $new_value);
            $new_value = trim($new_value);
            $uploaded_files[$new_key] = $new_value;
            unset($uploaded_files[$key]);
        }

        $rand = intval(mt_rand(1, 9) . mt_rand(0, 9) . mt_rand(0, 9) . mt_rand(0, 9) . mt_rand(0, 9) . mt_rand(0, 9));
        ;

        $uploaddir = $_SERVER['DOCUMENT_ROOT'] . '/wp-content/uploads/fs7/' . $rand . '/';

        if (!file_exists($uploaddir)) {
            mkdir($uploaddir, 0755, true);
        }
        if (!file_exists($uploaddir . '.htaccess')) {
            $f = fopen($uploaddir . ".htaccess", "a+") or die("Error: Can't open file");
            $content_string = "RewriteEngine On\n"
                    . "Order allow,deny\n"
                    . "Allow from all\n"
                    . "RewriteBase /\n"
                    . "RewriteCond %{REQUEST_FILENAME} ^.*(pdf|m4a|jpg|gif|jpeg|doc|docx|png)$\n"
                    . "RewriteCond %{HTTP_COOKIE} !^.*wordpress_logged_in.*$ [NC]\n"
                    . "RewriteRule . - [R=403,L]\n";
            fwrite($f, $content_string);
            fclose($f);
        }

        foreach ($uploaded_files as $key => $file) {

            // breakdown parts of uploaded file, to get basename
            $path = pathinfo($file);

            // directory of the new file
            $newfile = $uploaddir . $path['basename'];

            // check if a file with the same name exists in the directory
            $actual_name = $path['filename'];
            $original_name = $actual_name;
            $extension = $path['extension'];
            $i = 1;
            while (file_exists($newfile)) {
                $actual_name = (string) $original_name . '_' . $i;
                $newfile = $uploaddir . $actual_name . "." . $extension;
                $i++;
            }

            // make a copy of file to new directory
            copy($file, $newfile);

            // Name und Pfad als Option speichern
            add_option(self::option_name . '_' . $form_id . '_' . $rand . '_' . 'file-' . $key, $newfile, '', 'yes');
        }


        // Felder speichern (außer Datei, s.o.)

        $form_fields['date'] = current_time('Y-m-d H:i');
        $form_fields = str_replace(array("\r\n", "\r", "\n"), " ", $form_fields);
        //$form_fields = str_replace("\"", "'", $form_fields);

        foreach ($form_fields as $k => $v) {
            if (is_array($v)) {
                $form_fields[$k] = implode(' | ', $v);
            }
            if (substr($k, 0) != '_wpcf7' && !array_key_exists($k, $uploaded_files)) {

                if(is_array($v)) {
                    $input = implode(' | ', $v);
                    add_option(self::option_name . '_' . $form_id . '_' . $rand . '_' . $k, $input, '', 'yes');
                } else {
                    add_option(self::option_name . '_' . $form_id . '_' . $rand . '_' . $k, $v, '', 'yes');
                }
            }
        }


        // If you want to skip mailing the data, you can do it...
        //$wpcf7_data->skip_mail = true;

        $all_options = wp_load_alloptions();
        $my_options = array();
        foreach ($all_options as $name => $value) {
            if (stristr($name, 'fs7')) {
                $my_options[$name] = $value;
            }
        }
    }

    /*
     * Für jedes speichernde Formular eine Unterseite im Menü "Formular"
     * (wird vom Plugin Contact Form 7 angelegt) einfügen
     */

    public static function register_fs7_submenu_page() {
        $fs7_options = self::get_options();

        foreach (self::$cf7Forms as $cf7Form) {
            $id = $cf7Form->ID;
            if (isset($fs7_options['fs7_aktiv_' . $id]) && $fs7_options['fs7_aktiv_' . $id] == 1) {
                add_submenu_page(
                        'wpcf7', // $parent_slug
                        __('Formulardaten', 'fau-save-7') . ' ' . $id, // $page_title
                        __('Formulardaten', 'fau-save-7') . ' ' . $id, // $menu_title
                        'manage_options', // $capability
                        'fs7_data_' . $id, // $menu_slug
                        array(__CLASS__, 'fs7_submenu_page_callback') // $function
                );
            }
        }
    }

    public static function fs7_submenu_page_callback() {
        $all_options = wp_load_alloptions();
        $screen = get_current_screen();
        $base = $screen->base;
        $base_parts = explode('_', $base);
        $form_id = end($base_parts);
        $my_options = array();

        foreach ($all_options as $name => $value) {
            if (stristr($name, self::option_name . '_' . $form_id)) {
                $keys = explode('_', $name);
                // wpcf7-key rausfiltern, Inhalte in Array packen:
                // Alle_Submissions[Eintrag][Feldname]=Inhalt
                if ($keys[3] != '') {
                    $my_options[$keys[2]][$keys[3]] = $value;
                }
            }
        }

        // Eintrag/Einträge löschen, wenn delete_all oder delete_XYZ im $_POST
        if (!empty($_POST) && !isset($_POST['download_csv'])) {

            foreach ($_POST as $key => $value) {
                if (strpos($key, 'delete_') === 0) {
                    $delete = explode('_', $key)[1];
                }
            }
            if (isset($delete) && $delete == 'all') {
                foreach ($all_options as $key => $value) {
                    if (stristr($key, self::option_name . '_' . $form_id)) {
                        $delete_options[] = $key;
                    }
                }
            } elseif (isset($delete)) {
                foreach ($all_options as $key => $value) {
                    if (stristr($key, self::option_name . '_' . $form_id . '_' . $delete)) {
                        $delete_options[] = $key;
                    }
                }
            }

            foreach ($delete_options as $option) {
                // Datei(en) löschen
                $file = $all_options[$option];
                $directory = pathinfo($file)['dirname'];

                if (file_exists($file)) {
                    unlink($file);
                }
                if (file_exists($directory . '/.htaccess')) {
                    unlink($directory . '/.htaccess');
                }
                if (is_dir($directory) && (count(scandir($directory)) == 2)) {
                    rmdir($directory);
                }
                // Options löschen
                delete_option($option);
            }
        }

        // wpcf7-key rausfiltern, Inhalte in Array packen:
        // Alle_Submissions[Eintrag][Feldname]=Inhalt
        $all_options = wp_load_alloptions();
        $my_options = array();
        foreach ($all_options as $name => $value) {
            if (stristr($name, self::option_name . '_' . $form_id)) {
                $keys = explode('_', $name);
                if ($keys[3] != '') {
                    $my_options[$keys[2]][$keys[3]] = $value;
                }
            }
        }

        foreach (self::$cf7Forms as $cf7Form) {
            if ($cf7Form->ID == $form_id) {
                $form_title = $cf7Form->post_title;
            }
        }
        echo '<div class="wrap">';
        echo '<h2>' . __('Formulardaten', 'fau-save-7') . ' ' . $form_id . '</h2>';
        echo '<p>' . __('Formular-ID:', 'fau-save-7') . ' <b>' . $form_id . '</b><br/>';
        echo __('Formular-Titel', 'fau-save-7') . ': <b>' . $form_title . '</b></p>';

        // Hinweis, wenn noch keine Daten existieren
        if (empty($my_options)) {
            echo '<div id="message" class="error notice below-h2"><p>';
            _e('Für dieses Formular liegen keine Einträge vor.', 'fau-save-7');
            echo '</p></div>';
            return;
        }

        // feststellen, welche Felder es gibt (falls unterschiedliche Felder in den einzelnen Einträgen)
        $entry_keys = array();
        foreach ($my_options as $entry) {
            foreach (array_keys($entry) as $value) {
                if (!in_array($value, $entry_keys)) {
                    $entry_keys[] = $value;
                }
            }
        }

        // alle Einträge auf die gleiche Struktur bringen
        $new_options = self::adjust_keys($my_options, $entry_keys);

        // Keys für CSV- und Tabellen-Header
        reset($new_options);
        $first_key = key($new_options);

        // Header-Zeile hinzufügen
        $header_for_csv = array();
        foreach ($new_options[$first_key] as $key => $value) {
            $header_for_csv[$key] = $key;
        }
        $new_options_with_header = $new_options;
        array_unshift($new_options_with_header, $header_for_csv);

        $json = json_encode($new_options_with_header, JSON_HEX_APOS);

        // CSV-Download
        echo '<form method="post" action="admin.php?page=fs7_data_' . $form_id . '">';
        echo "<input type='hidden' name='data' value='" . $json . "'>";
        echo '<input type="submit" value="' . __('Daten als CSV-Datei herunterladen', 'fau-save-7') . '" class="button" id="download_csv" name="download_csv" style="margin-bottom: 1em;">';

        // Datentabelle mit Lösch-Buttons
        echo '<table id="fs7_formdata_' . $form_id . '" class="wp-list-table widefat striped"><thead><tr>';
        foreach ($new_options[$first_key] as $key => $value) {
            echo '<th>' . $key . '</th>';
        }
        echo '<th>&nbsp;</th>';
        echo '</tr></thead><tbody>';
        foreach ($new_options as $entry_id => $entry) {
            echo '<tr>';
            foreach ($entry as $key => $value) {
                if (substr($key, 0, 4) == 'file') {
                    $path = pathinfo($value);
                    $approot = substr($value, strlen($_SERVER['DOCUMENT_ROOT']));
                    if (file_exists($value)) {
                        echo '<td><a href="' . $approot . '">' . $path['basename'] . '<a></td>';
                    } else {
                        echo '<td>' . $path['basename'] . '</td>';
                    }
                } else {
                    print '<td>' . $value . '</td>';
                }
            }
            echo '<td><input type="submit" value="X" title="' . __('Diesen Eintrag löschen', 'fau-save-7') . '" class="button button-primary" id="delete_' . $entry_id . '" name="delete_' . $entry_id . '"  onClick="return confirm(\'' . __('Sind Sie sicher, dass Sie diesen Eintrag löschen möchten?', 'fau-save-7') . '\')"></td>';
            echo '</tr>';
        }
        echo '</tbody>';
        echo '</table>';
        echo '<p class="submit"><input type="submit" value="' . __('Alle Einträge löschen', 'fau-save-7') . '" class="button button-primary alignright" id="delete_all" name="delete_all" onClick="return confirm(\'' . __('Sind Sie sicher, dass Sie alle Einträge löschen möchten?', 'fau-save-7') . '\')"></p>';

        echo '</form>';

        echo '</div>';
    }

    /*
     * CSV-Download
     */

    public static function fs7_template_redirect() {

        if (isset($_POST['download_csv'])) {
            $data = $_POST['data'];
            $data = str_replace('\\"', '"', $data);
            $data = str_replace('\\\\', '\\', $data);

            $results = json_decode($data, true);

            if (empty($results)) {
                $args = array(
                    'back_link' => true
                );
                wp_die('CSV-Datei konnte nicht erstellt werden.', 'CSV-Fehler', $args);
                return;
            }



            $fp = fopen("php://output", "w");
            $file = 'fs7_export';
            $filename = $file . "_" . date("Y-m-d_H-i", current_time('timestamp'));
            header("Content-type: text/csv");
            header('Content-Disposition: attachment; filename=' . $filename . ".csv");
            /* header("Content-disposition: csv" . date("Y-m-d") . ".csv");
              header("Content-disposition: filename=".$filename.".csv"); */
            header("Pragma: no-cache");
            header("Expires: 0");
            header("Content-Transfer-Encoding: UTF-8");

            foreach ($results as $result) {
                foreach ($result as $key => $value) {
                    $result[$key] = str_replace("\\\"", "'", $value);
                    // bei Dateien nur Dateinamen statt komplettem Pfad verwenden
                    if (file_exists($value)) {
                        $result[$key] = pathinfo($value)['basename'];
                    }
                }
                fputcsv($fp, $result, ';', '"');
            }

            fclose($fp);
            exit;
        }
    }

    /*
     * Helferfunktionen
     */

    // Keys konsolidieren (falls nachträglich Felder ins Formulat hinzugefügt wurden)

    private static function adjust_keys($entries, $keys) {
        foreach ($keys as $key) {
            $key_array[$key] = '';
        }
        foreach ($entries as $key => $entry) {
            $new_entries[$key] = array_merge($key_array, $entry);
        }
        // date ans Ende setzen
        foreach ($new_entries as &$new_entry) {
            self::move_to_end($new_entry, 'date');
        }
        return $new_entries;
    }

    // Array-Element mit einem bestimmten key ans Ende/Anfang des Arrays setzen

    private static function move_to_end(&$array, $key) {
        $tmp = $array[$key];
        unset($array[$key]);
        $array[$key] = $tmp;
    }

    private static function move_to_top(&$array, $key) {
        $tmp = array($key => $array[$key]);
        unset($array[$key]);
        $array = $tmp + $array;
    }

}
