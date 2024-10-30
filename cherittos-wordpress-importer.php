<?php

/**
 * Plugin Name: Cheritto's Importer
 * Description: Import posts, pages, comments, custom fields, categories, tags and more from a WordPress export file.
 * Version: 1.0.1
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Author: Flavio Iulita
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

$version = 'version:1.0.0';

global $cheritto_wordpress_importer_prefix;
$cheritto_wordpress_importer_prefix = 'cherittoimp_';

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define( 'CHERITTO_WORDPRESS_IMPORTER_VERSION', str_replace( 'version:', '', $version ) );

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-cheritto-admin-grid-activator.php
 */
function activate_cheritto_wordpress_importer() {
	global $cheritto_wordpress_importer_prefix;
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-cheritto-wordpress-importer-activator.php';
	$activator = new Cheritto_Wordpress_Importer_Activator;
	$activator->activate($cheritto_wordpress_importer_prefix);
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-cheritto-admin-grid-deactivator.php
 */
function deactivate_cheritto_wordpress_importer() {
	global $cheritto_wordpress_importer_prefix;
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-cheritto-wordpress-importer-deactivator.php';
	$deactivator = new Cheritto_Wordpress_Importer_Deactivator;
	$deactivator->deactivate(CHERITTO_WORDPRESS_IMPORTER_VERSION,$cheritto_wordpress_importer_prefix);
}

/**
 * The code that runs during plugin uninstall.
 * This action is documented in uninstall.php
 */
function uninstall_cheritto_wordpress_importer() {
	global $cheritto_wordpress_importer_prefix;
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-cheritto-wordpress-importer-uninstall.php';
	$uninstaller = new Cheritto_Wordpress_Importer_Uninstall;
	$uninstaller->uninstall($cheritto_wordpress_importer_prefix);
}

register_activation_hook( __FILE__, 'activate_cheritto_wordpress_importer' );
register_deactivation_hook( __FILE__, 'deactivate_cheritto_wordpress_importer' );
register_uninstall_hook( __FILE__, 'uninstall_cheritto_wordpress_importer' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-cheritto-wordpress-importer.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_cheritto_wordpress_importer($version,$prefix) {

	$plugin = new Cheritto_Wordpress_Importer($version,$prefix);
	$plugin->run();

}

run_cheritto_wordpress_importer($version,$cheritto_wordpress_importer_prefix);