<?php

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's deactivation.
 *
 * @since      1.0.0
 * @package    Cheritto_Wordpress_Importer
 * @subpackage Cheritto_Wordpress_Importer/includes
 * @author     Flavio Iulita <fiulita@gmail.com>
 */
class Cheritto_Wordpress_Importer_Deactivator
{
    public function deactivate($version,$plugin_prefix) {

        require_once plugin_dir_path(__FILE__) . "class-cheritto-wordpress-importer.php";

        $imp = new Cheritto_Wordpress_Importer($version,$plugin_prefix);

        // Reset tables and clean up files
        $imp->cancel_job();

        return true;
    }
}