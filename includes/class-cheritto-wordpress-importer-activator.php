<?php

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      1.0.0
 * @package    Cheritto_Wordpress_Importer
 * @subpackage Cheritto_Wordpress_Importer/includes
 * @author     Flavio Iulita <fiulita@gmail.com>
 */
class Cheritto_Wordpress_Importer_Activator
{
    public function activate($plugin_prefix) {

        if (!$plugin_prefix)
        {
            wp_die(__("Plugin prefix cannot be null - something's wrong in configuration values."));
        }
        
        global $wpdb;

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        
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

        $sql = "CREATE TABLE IF NOT EXISTS " . $posts_table . " LIKE " . $wpdb->prefix . "posts";
        $wpdb->query($sql);

        $sql = "CREATE TABLE IF NOT EXISTS " . $postmeta_table . " LIKE " . $wpdb->prefix . "postmeta";
        $wpdb->query($sql);

        $sql = "CREATE TABLE IF NOT EXISTS " . $terms_table . " LIKE " . $wpdb->prefix . "terms";
        $wpdb->query($sql);

        $sql = "CREATE TABLE IF NOT EXISTS " . $termmeta_table . " LIKE " . $wpdb->prefix . "termmeta";
        $wpdb->query($sql);

        $sql = "CREATE TABLE IF NOT EXISTS " . $term_taxonomy_table . " LIKE " . $wpdb->prefix . "term_taxonomy";
        $wpdb->query($sql);

        $sql = "CREATE TABLE IF NOT EXISTS " . $term_relationships_table . " LIKE " . $wpdb->prefix . "term_relationships";
        $wpdb->query($sql);

        $sql = "CREATE TABLE IF NOT EXISTS " . $comments_table . " LIKE " . $wpdb->prefix . "comments";
        $wpdb->query($sql);

        $sql = "CREATE TABLE IF NOT EXISTS " . $commentmeta_table . " LIKE " . $wpdb->prefix . "commentmeta";
        $wpdb->query($sql);

        $sql = "CREATE TABLE IF NOT EXISTS " . $users_table . " LIKE " . $wpdb->prefix . "users";
        $wpdb->query($sql);

        $sql = "CREATE TABLE IF NOT EXISTS " . $usermeta_table . " LIKE " . $wpdb->prefix . "usermeta";
        $wpdb->query($sql);

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS " . $wpdb->prefix . $plugin_prefix . "attachments  (
        ID bigint NOT NULL,
        attachment_url varchar(500) NOT NULL,
        upload_path varchar(20) NULL,
        mime_type varchar(100) NULL,
        downloaded_at datetime NULL,
        thumbnailed_at datetime NULL,
        error varchar(255) NULL,
        PRIMARY KEY  (ID)
        ) $charset_collate;";

        dbDelta( $sql );

        $sql = "CREATE TABLE IF NOT EXISTS " . $wpdb->prefix . $plugin_prefix . "keepalives  (
            name varchar(50) NOT NULL,
            last_seen_at datetime NULL,
            PRIMARY KEY  (name)
            ) $charset_collate;";

        dbDelta( $sql );
        
        $data = [ 'name' => 'job' ];
        $exists = $wpdb->get_var("SELECT count(1) FROM " . $wpdb->prefix . $plugin_prefix . "keepalives WHERE name='job'");
        if (!$exists) $wpdb->insert($wpdb->prefix . $plugin_prefix . "keepalives",$data);

        $data = [ 'name' => 'attachment_queue' ];
        $exists = $wpdb->get_var("SELECT count(1) FROM " . $wpdb->prefix . $plugin_prefix . "keepalives WHERE name='attachment_queue'");
        if (!$exists) $wpdb->insert($wpdb->prefix . $plugin_prefix . "keepalives",$data);

        $data = [ 'name' => 'thumbnails_queue' ];
        $exists = $wpdb->get_var("SELECT count(1) FROM " . $wpdb->prefix . $plugin_prefix . "keepalives WHERE name='thumbnails_queue'");
        if (!$exists) $wpdb->insert($wpdb->prefix . $plugin_prefix . "keepalives",$data);

        $sql = "CREATE TABLE IF NOT EXISTS " . $wpdb->prefix . $plugin_prefix . "temp_terms  (
            slug varchar(200) NOT NULL,
            term_id bigint(20) UNSIGNED NOT NULL,
            PRIMARY KEY  (term_id),
            KEY " . $wpdb->prefix . $plugin_prefix . "temp_terms_slug_idx (slug)
            ) $charset_collate;";

        dbDelta( $sql );
        
        $sql = "CREATE TABLE IF NOT EXISTS " . $wpdb->prefix . $plugin_prefix . "temp_terms_tax  (
            term_id bigint(20) UNSIGNED NOT NULL,
            slug varchar(200) NOT NULL,
            taxonomy varchar(32) NOT NULL,
            PRIMARY KEY  (term_id,taxonomy),
            KEY " . $wpdb->prefix . $plugin_prefix . "temp_terms_tax_slug_idx (slug)
            ) $charset_collate;";

        dbDelta( $sql );

        $sql = "CREATE TABLE IF NOT EXISTS " . $wpdb->prefix . $plugin_prefix . "temp_terms_wp  (
            slug varchar(200) NOT NULL,
            term_id bigint(20) UNSIGNED NOT NULL,
            PRIMARY KEY  (term_id),
            KEY " . $wpdb->prefix . $plugin_prefix . "temp_terms_wp_slug_idx (slug)
            ) $charset_collate;";

        dbDelta( $sql );

        $sql = "CREATE TABLE IF NOT EXISTS " . $wpdb->prefix . $plugin_prefix . "old_and_new_term_id  (
            old_term_id bigint(20) UNSIGNED NOT NULL,
            new_term_id bigint(20) UNSIGNED NOT NULL,
            PRIMARY KEY  (old_term_id),
            KEY " . $wpdb->prefix . $plugin_prefix . "new_term_id_idx (new_term_id)
            ) $charset_collate;";

        dbDelta( $sql );

        $sql = "CREATE TABLE IF NOT EXISTS " . $wpdb->prefix . $plugin_prefix . "old_and_new_term_taxonomy_id  (
            old_term_taxonomy_id bigint(20) UNSIGNED NOT NULL,
            new_term_taxonomy_id bigint(20) UNSIGNED NOT NULL,
            PRIMARY KEY  (old_term_taxonomy_id),
            KEY " . $wpdb->prefix . $plugin_prefix . "new_term_taxonomy_id_idx (new_term_taxonomy_id)
            ) $charset_collate;";

        dbDelta( $sql );

        $sql = "CREATE TABLE IF NOT EXISTS " . $wpdb->prefix . $plugin_prefix . "old_and_new_term_taxonomy_id_rjoin  (
            old_term_taxonomy_id bigint(20) UNSIGNED NOT NULL,
            new_term_taxonomy_id bigint(20) UNSIGNED NULL,
            PRIMARY KEY  (old_term_taxonomy_id),
            KEY " . $wpdb->prefix . $plugin_prefix . "new_term_taxonomy_id_rjoin_idx (new_term_taxonomy_id)
            ) $charset_collate;";

        dbDelta( $sql );

        return true;
    }
}

