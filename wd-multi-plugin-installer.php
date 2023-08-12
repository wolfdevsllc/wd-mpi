<?php
/**
 * Plugin Name: WD Multi Plugin Installer
 * Description: A plugin to install multiple plugins from ZIP files or direct URLs.
 * Version: 1.0
 * Author: WolfDevs
 * Author URI: https://wolfdevs.com
 * License: GPL2
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Add a new submenu page for our Multi Plugin Installer under "Plugins".
function mpi_add_admin_menu() {
    add_plugins_page(
        'Multi Plugin Installer',
        'Multi Plugin Installer',
        'manage_options',
        'multi-plugin-installer',
        'mpi_admin_menu_page'
    );
}
add_action('admin_menu', 'mpi_add_admin_menu');

// Display our admin menu page.
function mpi_admin_menu_page() {
    // Check if form was submitted.
    if (isset($_POST['mpi_submit'])) {
        $file_count = count($_FILES['mpi_files']['name']);
        for ($i = 0; $i < $file_count; $i++) {
            $file_data = [
                'name'     => $_FILES['mpi_files']['name'][$i],
                'type'     => $_FILES['mpi_files']['type'][$i],
                'tmp_name' => $_FILES['mpi_files']['tmp_name'][$i],
                'error'    => $_FILES['mpi_files']['error'][$i],
                'size'     => $_FILES['mpi_files']['size'][$i]
            ];
            $result = handle_plugin_install($file_data, $file_data['name']);
            echo '<div class="notice ' . ($result['success'] ? 'notice-success' : 'notice-error') . ' is-dismissible">';
            echo '<p>' . esc_html($result['message']) . '</p>';
            echo '</div>';
        }
    }

    // Display the form.
    ?>
    <div class="wrap">
        <h2>Multi Plugin Installer</h2>
        <form method="post" enctype="multipart/form-data">
            <table class="form-table">
                <tbody>
                    <tr>
                        <th scope="row"><label for="mpi_files">Upload Plugins (ZIP files)</label></th>
                        <td><input type="file" name="mpi_files[]" id="mpi_files" multiple></td>
                    </tr>
                </tbody>
            </table>
            <?php submit_button('Install Plugins', 'primary', 'mpi_submit'); ?>
        </form>
    </div>
    <?php
}

// Function to handle the actual plugin installation.
function handle_plugin_install($file) {
    if (empty($file['tmp_name'])) {
        return [
            'success' => false,
            'message' => 'No file provided for installation.'
        ];
    }

    if (!function_exists('wp_handle_upload')) {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
    }

    $uploaded = wp_handle_upload($file, ['test_form' => false, 'action' => 'wp_handle_upload']);

    if (isset($uploaded['error'])) {
        return [
            'success' => false,
            'message' => 'Upload error: ' . $uploaded['error']
        ];
    }

    if (!function_exists('install_plugin')) {
        require_once(ABSPATH . 'wp-admin/includes/class-wp-upgrader.php');
        require_once(ABSPATH . 'wp-admin/includes/class-wp-ajax-upgrader-skin.php');
    }

    if (!function_exists('get_plugins')) {
        require_once(ABSPATH . 'wp-admin/includes/plugin.php');
    }

    // List all plugins before installation
    $all_plugins_before = get_plugins();

    $upgrader = new Plugin_Upgrader(new WP_Ajax_Upgrader_Skin());
    $upgrader->install($uploaded['file']);

    // List all plugins after installation
    $all_plugins_after = get_plugins();

    $new_plugin_path = array_diff_key($all_plugins_after, $all_plugins_before);

    if (empty($new_plugin_path)) {
        return [
            'success' => false,
            'message' => 'Failed to detect newly installed plugin.'
        ];
    }

    // Get the key (path) for the newly installed plugin
    $new_plugin_path_key = key($new_plugin_path);

    $plugin_data = $all_plugins_after[$new_plugin_path_key];
    $plugin_name = isset($plugin_data['Name']) ? $plugin_data['Name'] : 'UNKNOWN PLUGIN';

    return [
        'success' => true,
        'message' => "Successfully installed the plugin: $plugin_name."
    ];
}
