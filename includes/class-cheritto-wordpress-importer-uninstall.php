<?php

/**
 * Fired during plugin uninstallation.
 *
 * This class defines all code necessary to run during the plugin's uninstallation.
 *
 * @since      1.0.0
 * @package    Cheritto_Wordpress_Importer
 * @subpackage Cheritto_Wordpress_Importer/includes
 * @author     Flavio Iulita <fiulita@gmail.com>
 */
class Cheritto_Wordpress_Importer_Uninstall
{
    public function uninstall($plugin_prefix) {

        if (!$plugin_prefix)
        {
            wp_die(__("Plugin prefix cannot be null - something's wrong in configuration values."));
        }

        global $wpdb;

        $current_job = get_option('cheritto-wordpress-importer-current-job');
        $jobs_dir = get_option('cheritto-wordpress-importer-current-job-path');

        if ($current_job != '' && $jobs_dir != '') {

            $jobPath = $jobs_dir . DIRECTORY_SEPARATOR . $current_job;
           
            if (file_exists( $jobPath )) {

                @unlink($jobPath . '_check.html' );
                @unlink($jobPath . '_check_summary.html' );
                
                @array_map('unlink', glob("$jobPath/*.*"));
                @rmdir($jobPath);
        
            }

        }
        
        $posts_table = $wpdb->prefix . $plugin_prefix . 'posts';
        $postmeta_table = $wpdb->prefix . $plugin_prefix . 'postmeta';
        $terms_table = $wpdb->prefix . $plugin_prefix . 'terms';
        $termmeta_table = $wpdb->prefix . $plugin_prefix . 'termmeta';
        $term_taxonomy_table = $wpdb->prefix . $plugin_prefix . 'term_taxonomy';
        $term_relationships_table = $wpdb->prefix . $plugin_prefix . 'term_relationships';
        $comments_table = $wpdb->prefix . $plugin_prefix . 'comments';
        $commentmeta_table = $wpdb->prefix . $plugin_prefix . 'commentmeta';
        $users_table = $wpdb->prefix . $plugin_prefix . 'users';
        $usermeta_table = $wpdb->prefix . $plugin_prefix . 'usermeta';

        $attachments_table = $wpdb->prefix . $plugin_prefix . 'attachments';
        $keepalives_table = $wpdb->prefix . $plugin_prefix . 'keepalives';

        $temp_terms_table = $wpdb->prefix . $plugin_prefix . "temp_terms";
        $temp_terms_tax_table= $wpdb->prefix . $plugin_prefix . "temp_terms_tax";
        $temp_terms_wp_table = $wpdb->prefix . $plugin_prefix . "temp_terms_wp";
        $old_and_new_term_id_table = $wpdb->prefix . $plugin_prefix . "old_and_new_term_id";
        $old_and_new_term_taxonomy_id_table = $wpdb->prefix . $plugin_prefix . "old_and_new_term_taxonomy_id";
        $old_and_new_term_taxonomy_id_rjoin_table = $wpdb->prefix . $plugin_prefix . "old_and_new_term_taxonomy_id_rjoin";

        $sql = "DROP TABLE IF EXISTS " . $posts_table;
        $wpdb->query($sql);

        $sql = "DROP TABLE IF EXISTS " . $postmeta_table;
        $wpdb->query($sql);

        $sql = "DROP TABLE IF EXISTS " . $terms_table;
        $wpdb->query($sql);
        
        $sql = "DROP TABLE IF EXISTS " . $termmeta_table;
        $wpdb->query($sql);
        
        $sql = "DROP TABLE IF EXISTS " . $term_taxonomy_table;
        $wpdb->query($sql);
        
        $sql = "DROP TABLE IF EXISTS " . $term_relationships_table;
        $wpdb->query($sql);
        
        $sql = "DROP TABLE IF EXISTS " . $comments_table;
        $wpdb->query($sql);

        $sql = "DROP TABLE IF EXISTS " . $commentmeta_table;
        $wpdb->query($sql);

        $sql = "DROP TABLE IF EXISTS " . $users_table;
        $wpdb->query($sql);

        $sql = "DROP TABLE IF EXISTS " . $usermeta_table;
        $wpdb->query($sql);

        $sql = "DROP TABLE IF EXISTS " . $attachments_table;
        $wpdb->query($sql);

        $sql = "DROP TABLE IF EXISTS " . $keepalives_table;
        $wpdb->query($sql);

        $sql = "DROP TABLE IF EXISTS " . $temp_terms_table;
        $wpdb->query($sql);

        $sql = "DROP TABLE IF EXISTS " . $temp_terms_tax_table;
        $wpdb->query($sql);

        $sql = "DROP TABLE IF EXISTS " . $temp_terms_wp_table;
        $wpdb->query($sql);

        $sql = "DROP TABLE IF EXISTS " . $old_and_new_term_id_table;
        $wpdb->query($sql);

        $sql = "DROP TABLE IF EXISTS " . $old_and_new_term_taxonomy_id_table;
        $wpdb->query($sql);

        $sql = "DROP TABLE IF EXISTS " . $old_and_new_term_taxonomy_id_rjoin_table;
        $wpdb->query($sql);

        delete_option( 'cheritto-wordpress-importer-current-job' );
        delete_option( 'cheritto-wordpress-importer-current-job-path' );
        delete_option( 'cheritto-wordpress-importer-current-job-stage' );
        delete_option( 'cheritto-wordpress-importer-attachment-queue-lock' );
        delete_option( 'cheritto-wordpress-importer-attachment-queue-stage' );
        delete_option( 'cheritto-wordpress-importer-thumbnails-queue-lock' );
        delete_option( 'cheritto-wordpress-importer-thumbnails-queue-stage' );
        
        return true;
    }
}