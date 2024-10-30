<?php

/**
 * The core plugin class.
 *
 * @since      1.0.0
 * @package    Cheritto_Wordpress_Importer
 * @subpackage Cheritto_Wordpress_Importer/includes
 * @author     Flavio Iulita <fiulita@gmail.com>
 */

require_once plugin_dir_path(__FILE__) . "class-cheritto-wordpress-importer-parser.php";

class Cheritto_Wordpress_Importer {

    protected $version;
    protected $user;
    protected $plugin_prefix;

    public function __construct( $version, $plugin_prefix ) {

        $this->version = $version;
        $this->plugin_prefix = $plugin_prefix;
        
    }

    public function run() {

        add_action( 'admin_menu', [ $this, 'cheritto_wordpress_importer_page' ] );

        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_script' ] );

        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_styles' ] );

        add_action( 'admin_footer', [ $this, 'add_upload_js' ] ); 

        add_action( 'admin_init', [ $this, 'register_settings' ] );

        add_action( 'wp_ajax_cheritto_wordpress_importer_cancel_job', [ $this, 'cancel_job' ] );

        add_action( 'wp_ajax_cheritto_wordpress_importer_check_files', [ $this, 'check_files' ] );

        add_action( 'wp_ajax_cheritto_wordpress_importer_start_download_queue', [ $this, 'start_download_queue' ] );

        add_action( 'wp_ajax_cheritto_wordpress_importer_pause_download_queue', [ $this, 'pause_download_queue' ] );

        add_action( 'wp_ajax_cheritto_wordpress_importer_start_thumbnails_queue', [ $this, 'start_thumbnails_queue' ] );

        add_action( 'wp_ajax_cheritto_wordpress_importer_pause_thumbnails_queue', [ $this, 'pause_thumbnails_queue' ] );

        add_action( 'wp_ajax_cheritto_wordpress_importer_start_data_import', [ $this, 'start_data_import' ] );
        
    }

    public function cheritto_wordpress_importer_page() {
        $slug = add_menu_page(
            'Importer!',
            'Importer!',
            'import',
            plugin_dir_path(__FILE__) . 'import.php',
            null,
            'dashicons-database-import',
            7
        );
        
    }

    public function enqueue_styles($hook)
	{
        if ( 'cherittos-importer/includes/import.php' != $hook ) {
            return;
        }
		wp_enqueue_style( 'CHERITTO_WORDPRESS_IMPORTER', plugin_dir_url( __FILE__ ) . 'css/cheritto-wordpress-importer.css', array(), $this->version, 'all' );
	}

    public function enqueue_script($hook)
    {
        if ( 'cherittos-importer/includes/import.php' != $hook ) {
            return;
        }
        wp_enqueue_script( 'cherittos-importer', plugin_dir_url( __FILE__ ) . 'js/cheritto-wordpress-importer.js', array( 'jquery', 'wp-i18n', 'plupload' ), '1.0' );
    }

    public function register_settings()
    {
        register_setting( 'cheritto-wordpress-importer', 'cheritto-wordpress-importer-current-job', ['default' => ''] );
        register_setting( 'cheritto-wordpress-importer', 'cheritto-wordpress-importer-current-job-path', ['default' => ''] );
        register_setting( 'cheritto-wordpress-importer', 'cheritto-wordpress-importer-current-job-stage', ['default' => ''] );
        register_setting( 'cheritto-wordpress-importer', 'cheritto-wordpress-importer-attachment-queue-lock', ['default' => 'unlocked'] );
        register_setting( 'cheritto-wordpress-importer', 'cheritto-wordpress-importer-attachment-queue-stage', ['default' => ''] );
        register_setting( 'cheritto-wordpress-importer', 'cheritto-wordpress-importer-thumbnails-queue-lock', ['default' => 'unlocked'] );
        register_setting( 'cheritto-wordpress-importer', 'cheritto-wordpress-importer-thumbnails-queue-stage', ['default' => ''] );
    }

    public function add_upload_js()
    {
        $screen = get_current_screen();
        if ( 'cherittos-importer/includes/import' != $screen->id ) {
            return;
        }

        $post_max_size = $this->return_bytes( ini_get("post_max_size") );
        $upload_max_filesize = $this->return_bytes( ini_get("upload_max_filesize") );

        $max_size = min( [$post_max_size,$upload_max_filesize] );

        ?>
        <script>

            window.addEventListener("load", () => {
                var filelist = document.getElementById("filelist");
                
                var uploader = new plupload.Uploader({
                    runtimes: "html5",
                    browse_button: "pickfiles",
                    url: "<?php echo plugin_dir_url( __FILE__ ) . 'up.php'; ?>",
                    chunk_size: "<?php echo (int) $max_size; ?>",
                    filters: {
                    max_file_size: "1000mb",
                    mime_types: [{title: "Files", extensions: "xml"}]
                    },
                    init: {
                        PostInit: () => { filelist.innerHTML = "<div></div>"; },
                        FilesAdded: (up, files) => {
                            plupload.each(files, (file) => {
                                let row = document.createElement("div");
                                row.id = file.id;
                                row.innerHTML = `${file.name} (${plupload.formatSize(file.size)}) <strong></strong>`;
                                filelist.appendChild(row);
                            });
                            uploader.start();
                        },
                        UploadProgress: (up, file) => {
                            document.querySelector(`#${file.id} strong`).innerHTML = `${file.percent}%`;
                        },
                        FileUploaded: (up, file, response) => {
                            document.querySelector(`#${file.id} strong`).innerHTML = `<?php echo __('COMPLETE','cherittos-importer'); ?>`;
                        },
                        UploadComplete: (up,file) => {
                            location.reload();
                        },
                        Error: (up, err) => { console.error(err); }
                    }
                });
                uploader.init();

            });

        </script>
        <?php
    }

    public function cancel_job()
    {
        global $wpdb;

        $current_job = get_option('cheritto-wordpress-importer-current-job');
        $jobs_dir = get_option('cheritto-wordpress-importer-current-job-path');

        // No current job here? Something's off
        if (!$current_job) {
            $jobPath = false;
        } else {
            $jobPath = $jobs_dir . DIRECTORY_SEPARATOR . $current_job;
        }

        if( $jobPath != false ) {
            @unlink($jobPath . '_check.html' );
            @unlink($jobPath . '_check_summary.html' );
            
            array_map('unlink', glob("$jobPath/*.*"));
            rmdir($jobPath);
        }

        $wpdb->query("TRUNCATE TABLE " . $wpdb->prefix . $this->plugin_prefix . "posts");
        $wpdb->query("TRUNCATE TABLE " . $wpdb->prefix . $this->plugin_prefix . "postmeta");
        $wpdb->query("TRUNCATE TABLE " . $wpdb->prefix . $this->plugin_prefix . "comments");
        $wpdb->query("TRUNCATE TABLE " . $wpdb->prefix . $this->plugin_prefix . "commentmeta");
        $wpdb->query("TRUNCATE TABLE " . $wpdb->prefix . $this->plugin_prefix . "users");
        $wpdb->query("TRUNCATE TABLE " . $wpdb->prefix . $this->plugin_prefix . "usermeta");
        $wpdb->query("TRUNCATE TABLE " . $wpdb->prefix . $this->plugin_prefix . "terms");
        $wpdb->query("TRUNCATE TABLE " . $wpdb->prefix . $this->plugin_prefix . "termmeta");
        $wpdb->query("TRUNCATE TABLE " . $wpdb->prefix . $this->plugin_prefix . "term_relationships");
        $wpdb->query("TRUNCATE TABLE " . $wpdb->prefix . $this->plugin_prefix . "term_taxonomy");
        $wpdb->query("TRUNCATE TABLE " . $wpdb->prefix . $this->plugin_prefix . "attachments");

        $wpdb->query("TRUNCATE TABLE " . $wpdb->prefix . $this->plugin_prefix . "temp_terms");
        $wpdb->query("TRUNCATE TABLE " . $wpdb->prefix . $this->plugin_prefix . "temp_terms_tax");
        $wpdb->query("TRUNCATE TABLE " . $wpdb->prefix . $this->plugin_prefix . "temp_terms_wp");
        $wpdb->query("TRUNCATE TABLE " . $wpdb->prefix . $this->plugin_prefix . "old_and_new_term_id");
        $wpdb->query("TRUNCATE TABLE " . $wpdb->prefix . $this->plugin_prefix . "old_and_new_term_taxonomy_id");
        $wpdb->query("TRUNCATE TABLE " . $wpdb->prefix . $this->plugin_prefix . "old_and_new_term_taxonomy_id_rjoin");


        $wpdb->query("UPDATE " . $wpdb->prefix . $this->plugin_prefix . "keepalives SET last_seen_at=NULL");

        update_option('cheritto-wordpress-importer-current-job','');
        update_option('cheritto-wordpress-importer-current-job-stage','');
        update_option('cheritto-wordpress-importer-current-job-path','');
        update_option('cheritto-wordpress-importer-attachment-queue-lock','unlocked');
        update_option('cheritto-wordpress-importer-attachment-queue-stage','');
        update_option('cheritto-wordpress-importer-thumbnails-queue-lock','unlocked');
        update_option('cheritto-wordpress-importer-thumbnails-queue-stage','');

        if (wp_doing_ajax())
            echo __("Job has been successfully closed.",'cherittos-importer');
        
        return true;

    }

    public function check_files()
    {
        global $wpdb;

        update_option('cheritto-wordpress-importer-current-job-stage','check_files');

        // First keepalive
        $wpdb->query("UPDATE " . $wpdb->prefix . $this->plugin_prefix . "keepalives SET last_seen_at = NOW() WHERE name='job'");

        //$startMemory = memory_get_usage();
        set_time_limit(0);

        $start = microtime(true);

        $current_job = get_option('cheritto-wordpress-importer-current-job');
        $current_job_path = get_option('cheritto-wordpress-importer-current-job-path');
        $jobPath = $current_job_path . DIRECTORY_SEPARATOR . $current_job;

        $files = scandir($jobPath);
        $files = array_diff(scandir($jobPath), array('.', '..'));

        $file_output_path = $current_job_path . DIRECTORY_SEPARATOR . $current_job . "_check.html";

        $file_output = fopen($file_output_path, "w");
        
        foreach($files as $file)
        {
            try {

                $extension = pathinfo($file, PATHINFO_EXTENSION);

                if ("part" == $extension)
                {
                    fwrite($file_output,"<br/><h4 class='cheritto-wordpress-importer-file'>" . $file . " is incomplete! You may want to restart the job and make sure upload reaches 100%. Skipping.</h4></p>");
                    continue;
                }

                $parser = new Cheritto_Wordpress_Importer_Parser($jobPath . DIRECTORY_SEPARATOR . $file, $this->plugin_prefix);

                if ($parser->error_string!='') {

                    fwrite($file_output,"<br/><h4 class='cheritto-wordpress-importer-file'>" . $file . " is not valid XML or is unreadable.</h4><p>Error is: " . $parser->error_code . " " . $parser->error_string . "</p>");
                    continue;
                }

                fwrite($file_output,"<br/><h4 class='cheritto-wordpress-importer-file'>Currently checking " . $file . "...</h4>");

                $parser->parse(true);

                if ($parser->wxr_version=='') {
                    fwrite($file_output,"<br/><h4 class='cheritto-wordpress-importer-file'>" . $file . " is not valid Wordpress exported XML file or is unreadable.</h4>");
                    continue;
                }

                fwrite($file_output,"<table class='cheritto-wordpress-importer-report-table'><thead><tr>");

                fwrite($file_output,"<th>" . __("Authors") . "</th>");
                fwrite($file_output,"<th>" . __("Categories") . "</th>");
                fwrite($file_output,"<th>" . __("Tags") . "</th>");
                fwrite($file_output,"<th>" . __("Terms") . "</th>");
                fwrite($file_output,"<th>" . __("Comments") . "</th>");

                foreach($parser->post_types as $post_type => $count) 
                {
                    fwrite($file_output, "<th>" . $post_type . "</th>");
                }

                fwrite($file_output,"</tr></thead>");

                fwrite($file_output,"<tbody><tr>");

                fwrite($file_output,"<td>" . (int) $parser->total_authors . "</td>");
                fwrite($file_output,"<td>" . (int) $parser->total_categories . "</td>");
                fwrite($file_output,"<td>" . (int) $parser->total_tags . "</td>");
                fwrite($file_output,"<td>" . (int) $parser->total_terms . "</td>");
                fwrite($file_output,"<td>" . (int) $parser->total_comments . "</td>");

                foreach($parser->post_types as $post_type => $count) 
                {
                    fwrite($file_output, "<td>" . $count . "</td>");
                }

                fwrite($file_output,"</tr></tbody></table>");

                // Keepalive
                $wpdb->query("UPDATE " . $wpdb->prefix . $this->plugin_prefix . "keepalives SET last_seen_at = NOW() WHERE name='job'");


            } catch (\Exception $e) {
                fwrite($file_output,"<h4>There was an error processing $file: " . $e->getMessage() . "</h4>");
                continue;
            } 
        }

        fclose($file_output);

        $time_elapsed_secs = microtime(true) - $start;
        
        $post_types = $wpdb->get_results("SELECT post_type,COUNT(1) AS count FROM " . $wpdb->prefix . $this->plugin_prefix . "posts GROUP BY post_type");

        $new_authors_count = $wpdb->get_var("SELECT COUNT(1) FROM " . $wpdb->prefix . $this->plugin_prefix . "users");

        $file_summary_path = $current_job_path . DIRECTORY_SEPARATOR . $current_job . "_check_summary.html";

        $file_summary = fopen($file_summary_path, "w");

        fwrite($file_summary, "<table class='cheritto-wordpress-importer-report-table'><thead>");

        fwrite($file_summary,"<tr>");
        fwrite($file_summary, "<th>");
        fwrite($file_summary, "<strong>" . __('Entity','cherittos-importer') . "</strong>");
        fwrite($file_summary, "</th>");
        fwrite($file_summary, "<th>");
        fwrite($file_summary, "<strong>" . __('Count','cherittos-importer') . "</strong>");
        fwrite($file_summary, "</th>");
        fwrite($file_summary,"</tr>");

        fwrite($file_summary,"</thead><tbody>");

        foreach($post_types as $row)
        {
            fwrite($file_summary,"<tr>");
            fwrite($file_summary, "<td>");
            fwrite($file_summary, $row->post_type);
            fwrite($file_summary, "</td>");
            fwrite($file_summary, "<td>");
            fwrite($file_summary, $row->count);
            fwrite($file_summary, "</td>");
            fwrite($file_summary,"</tr>");
        }

        fwrite($file_summary,"<tr>");
        fwrite($file_summary, "<td>");
        fwrite($file_summary, __("authors"));
        fwrite($file_summary, "</td>");
        fwrite($file_summary, "<td>");
        fwrite($file_summary, $new_authors_count);
        fwrite($file_summary, "</td>");
        fwrite($file_summary,"</tr>");

        /*fwrite($file_summary,"<tr>");
        fwrite($file_summary, "<td>");
        fwrite($file_summary, "Time elapsed (seconds): ");
        fwrite($file_summary, "</td>");
        fwrite($file_summary, "<td>");
        fwrite($file_summary, $time_elapsed_secs );
        fwrite($file_summary, "</td>");
        fwrite($file_summary,"</tr>");

        fwrite($file_summary,"<tr>");
        fwrite($file_summary, "<td>");
        fwrite($file_summary, "Memory usage: ");
        fwrite($file_summary, "</td>");
        fwrite($file_summary, "<td>");
        fwrite($file_summary, ((memory_get_usage() - $startMemory) / 1000000) . ' megabytes');
        fwrite($file_summary, "</td>");
        fwrite($file_summary,"</tr>");*/

        fwrite($file_summary,"</tbody></table>");

        fclose($file_summary);

        // Last keepalive
        $wpdb->query("UPDATE " . $wpdb->prefix . $this->plugin_prefix . "keepalives SET last_seen_at = NOW() WHERE name='job'");

        update_option('cheritto-wordpress-importer-current-job-stage','files_checked');
    }

    protected function integrity_check()
    {
        // Deprecated and not in use.

        global $wpdb;

        global $wp_post_types;

        $checks = [];

        $checks['post_orphans'] = $wpdb->get_var( "SELECT COUNT(1) FROM " . $wpdb->prefix . $this->plugin_prefix . "posts p
                                    LEFT JOIN " . $wpdb->prefix . $this->plugin_prefix . "posts pp ON p.post_parent=pp.ID
                                    WHERE p.post_parent != 0 AND pp.ID IS NULL" );
                                    
        $checks['comments_orphans'] = $wpdb->get_var( "SELECT COUNT(1) FROM " . $wpdb->prefix . $this->plugin_prefix . "comments c
                                    LEFT JOIN " . $wpdb->prefix . $this->plugin_prefix . "posts p ON c.comment_post_ID=p.ID
                                    WHERE p.ID IS NULL" );

        $checks['postmeta_orphans'] = $wpdb->get_var( "SELECT COUNT(1) FROM " . $wpdb->prefix . $this->plugin_prefix . "postmeta pm
                                    LEFT JOIN " . $wpdb->prefix . $this->plugin_prefix . "posts p ON pm.post_id=p.ID
                                    WHERE p.ID IS NULL" );

        $checks['relationships_orphans'] = $wpdb->get_var( "SELECT COUNT(1) FROM " . $wpdb->prefix . $this->plugin_prefix . "term_relationships tr
                                    LEFT JOIN " . $wpdb->prefix . $this->plugin_prefix . "posts p ON tr.object_id=p.ID
                                    WHERE p.ID IS NULL" );

        $checks['posts_conflicting'] = $wpdb->get_var( "SELECT COUNT(1) FROM " . $wpdb->prefix . $this->plugin_prefix . "posts wcp
                                    JOIN wp_posts p ON wcp.ID=p.ID");
        
        $checks['terms_conflicting'] = $wpdb->get_var( "SELECT COUNT(1) FROM " . $wpdb->prefix . $this->plugin_prefix . "terms wct
                                    JOIN wp_terms t ON wct.term_id=t.term_id");

        $checks['slugs_conflicting'] = $wpdb->get_var( "SELECT COUNT(1) FROM " . $wpdb->prefix . $this->plugin_prefix . "terms wct
                                    JOIN wp_terms t ON wct.slug=t.slug");

        $registered_post_types = "'" . implode("','", array_keys($wp_post_types)) . "'";

        $checks['unregistered_post_types'] = $wpdb->get_var( "SELECT COUNT(1) FROM " . $wpdb->prefix . $this->plugin_prefix . "posts wcp
                                    WHERE post_type NOT IN (" . $registered_post_types . ")");

        return $checks;
    }

    public function start_download_queue()
    {
        global $wpdb;

        set_time_limit(0);

        update_option('cheritto-wordpress-importer-attachment-queue-stage','running');
        update_option('cheritto-wordpress-importer-attachment-queue-lock','unlocked');

        // First keepalive
        $wpdb->query("UPDATE " . $wpdb->prefix . $this->plugin_prefix . "keepalives SET last_seen_at = NOW() WHERE name='attachment_queue'");

        $batch_size = 10;
        $total_attachments = $wpdb->get_var("SELECT COUNT(1) FROM " . $wpdb->prefix . $this->plugin_prefix . "attachments WHERE downloaded_at IS NULL AND error IS NULL" );

        do {
            $rows = $wpdb->get_results("SELECT ID, attachment_url, upload_path FROM " . $wpdb->prefix . $this->plugin_prefix . "attachments WHERE downloaded_at IS NULL AND error IS NULL LIMIT " . $batch_size);

            foreach($rows as $row)
            {
                // Keepalive
                $wpdb->query("UPDATE " . $wpdb->prefix . $this->plugin_prefix . "keepalives SET last_seen_at = NOW() WHERE name='attachment_queue'");
                
                $file_name = basename( parse_url( $row->attachment_url, PHP_URL_PATH ) );
                
                if (!$file_name) $file_name = md5($row->attachment_url);

                $tmp_file_name = wp_tempnam( $file_name );
                if ( ! $tmp_file_name ) {
                    $wpdb->query(
                        $wpdb->prepare("UPDATE " . $wpdb->prefix . $this->plugin_prefix . "attachments SET error = %s WHERE ID = " . (int) $row->ID,"Could not create temporary file" )
                    );
                    continue;
                }

                // Fetch the remote URL and write it to the placeholder file.
                $remote_response = wp_safe_remote_get( $row->attachment_url, array(
                    'reject_unsafe_urls' => false,
                    'timeout'    => 10,
                    'stream'     => true,
                    'filename'   => $tmp_file_name,
                    'headers'    => array(
                        'Accept-Encoding' => 'identity',
                    ),
                ) );

                if ( is_wp_error( $remote_response ) ) {
                    @unlink( $tmp_file_name );
                    $wpdb->query(
                        $wpdb->prepare("UPDATE " . $wpdb->prefix . $this->plugin_prefix . "attachments SET error = %s WHERE ID = " . (int) $row->ID,$remote_response->get_error_message() )
                    );
                    continue;
                }

                $remote_response_code = (int) wp_remote_retrieve_response_code( $remote_response );

                // Make sure the fetch was successful.
                if ( 200 !== $remote_response_code ) {
                    @unlink( $tmp_file_name );
                    $wpdb->query(
                        $wpdb->prepare("UPDATE " . $wpdb->prefix . $this->plugin_prefix . "attachments SET error = %s WHERE ID = " . (int) $row->ID,get_status_header_desc( $remote_response_code ) )
                    );
                    continue;
                }

                $headers = wp_remote_retrieve_headers( $remote_response );

                // Request failed.
                if ( ! $headers ) {
                    @unlink( $tmp_file_name );
                    $wpdb->query(
                        $wpdb->prepare("UPDATE " . $wpdb->prefix . $this->plugin_prefix . "attachments SET error = %s WHERE ID = " . (int) $row->ID,"Timeout." )
                    );
                    continue;
                }

                $filesize = (int) filesize( $tmp_file_name );

                if ( 0 === $filesize ) {
                    echo $tmp_file_name;
                    //@unlink( $tmp_file_name );
                    $wpdb->query(
                        $wpdb->prepare("UPDATE " . $wpdb->prefix . $this->plugin_prefix . "attachments SET error = %s WHERE ID = " . (int) $row->ID,"Zero size file." )
                    );
                    continue;
                }

                if ( ! isset( $headers['content-encoding'] ) && isset( $headers['content-length'] ) && $filesize !== (int) $headers['content-length'] ) {
                    @unlink( $tmp_file_name );
                    $wpdb->query(
                        $wpdb->prepare("UPDATE " . $wpdb->prefix . $this->plugin_prefix . "attachments SET error = %s WHERE ID = " . (int) $row->ID,"Incorrect file size." )
                    );
                    continue;
                }

                // Set file extension if missing.
                $file_ext = pathinfo( $file_name, PATHINFO_EXTENSION );
                if ( ! $file_ext && ! empty( $headers['content-type'] ) ) {
                    $extension = self::get_file_extension_by_mime_type( $headers['content-type'] );
                    if ( $extension ) {
                        $file_name = "{$file_name}.{$extension}";
                    }
                }

                // Handle the upload like _wp_handle_upload() does.
                $wp_filetype     = wp_check_filetype_and_ext( $tmp_file_name, $file_name );
                $ext             = empty( $wp_filetype['ext'] ) ? '' : $wp_filetype['ext'];
                $type            = empty( $wp_filetype['type'] ) ? '' : $wp_filetype['type'];
                $proper_filename = empty( $wp_filetype['proper_filename'] ) ? '' : $wp_filetype['proper_filename'];

                // Check to see if wp_check_filetype_and_ext() determined the filename was incorrect.
                if ( $proper_filename ) {
                    $file_name = $proper_filename;
                }

                if ( ( ! $type || ! $ext ) && ! current_user_can( 'unfiltered_upload' ) ) {
                    return new WP_Error( 'import_file_error', __( 'Sorry, this file type is not permitted for security reasons.', 'cheritto-wordpress-importer' ) );
                }

                $uploads_folder = wp_upload_dir();
                if ( ! ( $uploads_folder && false === $uploads_folder['error'] ) ) {
                    $wpdb->query(
                        $wpdb->prepare("UPDATE " . $wpdb->prefix . $this->plugin_prefix . "attachments SET error = %s WHERE ID = " . (int) $row->ID,$uploads_folder['error'] )
                    );
                    continue;
                }

                if (!wp_mkdir_p( $uploads_folder['basedir'] . $row->upload_path ))
                {
                    $wpdb->query(
                        $wpdb->prepare("UPDATE " . $wpdb->prefix . $this->plugin_prefix . "attachments SET error = %s WHERE ID = " . (int) $row->ID,"Can't create destination folder." )
                    );
                    continue;
                }

                // Move the file to the uploads dir.
                $file_name     = wp_unique_filename( $uploads_folder['basedir'] . $row->upload_path, $file_name );
                $new_file      = $uploads_folder['basedir'] . $row->upload_path . DIRECTORY_SEPARATOR . $file_name;
                $move_new_file = copy( $tmp_file_name, $new_file );

                if ( ! $move_new_file ) {
                    @unlink( $tmp_file_name );
                    $wpdb->query(
                        $wpdb->prepare("UPDATE " . $wpdb->prefix . $this->plugin_prefix . "attachments SET error = %s WHERE ID = " . (int) $row->ID,"Can't move uploaded file." )
                    );
                    continue;
                }

                // Set correct file permissions.
                $stat  = stat( dirname( $new_file ) );
                $perms = $stat['mode'] & 0000666;
                chmod( $new_file, $perms );

                $wpdb->query(
                    $wpdb->prepare("UPDATE " . $wpdb->prefix . $this->plugin_prefix . "attachments SET downloaded_at = NOW(), mime_type = %s WHERE ID = " . (int) $row->ID,$headers['content-type'] )
                );

                $wpdb->query(
                    $wpdb->prepare("UPDATE " . $wpdb->prefix . $this->plugin_prefix . "posts SET post_mime_type = %s WHERE ID = " . (int) $row->ID,$headers['content-type'] )
                );

                $exists = $wpdb->get_var("SELECT COUNT(1) FROM " . $wpdb->prefix . $this->plugin_prefix . "postmeta WHERE post_id = " . (int) $row->ID . " AND meta_key = '_wp_attached_file' ");

                if ($exists)
                    $wpdb->query(
                        $wpdb->prepare("UPDATE " . $wpdb->prefix . $this->plugin_prefix . "postmeta SET meta_value = %s WHERE post_id = " . (int) $row->ID . " AND meta_key = '_wp_attached_file'", $row->upload_path . $file_name)
                    );
                else
                    $wpdb->query(
                        $wpdb->prepare("INSERT INTO " . $wpdb->prefix . $this->plugin_prefix . "postmeta (post_id,meta_key,meta_value) VALUES ( " . (int) $row->ID . ",'_wp_attached_file',%s)", $row->upload_path . $file_name)
                    );

                if (get_option('cheritto-wordpress-importer-current-job-stage') == 'import_ended') 
                {
                    $wpdb->query(
                        $wpdb->prepare("UPDATE " . $wpdb->posts . " SET post_mime_type = %s WHERE ID = " . (int) $row->ID,$headers['content-type'] )
                    );
                    
                    $exists = $wpdb->get_var("SELECT COUNT(1) FROM " . $wpdb->postmeta . " WHERE post_id = " . (int) $row->ID . " AND meta_key = '_wp_attached_file' ");

                    if ($exists)
                        $wpdb->query(
                            $wpdb->prepare("UPDATE " . $wpdb->postmeta . " SET meta_value = %s WHERE post_id = " . (int) $row->ID . " AND meta_key = '_wp_attached_file'", $row->upload_path . $file_name)
                        );
                    else
                        $wpdb->query(
                            $wpdb->prepare("INSERT INTO " . $wpdb->postmeta . " (post_id,meta_key,meta_value) VALUES ( " . (int) $row->ID . ",'_wp_attached_file',%s)", $row->upload_path . $file_name)
                        );

                }

                
            }

        } while ( count($rows) > 0 && $wpdb->get_var( "SELECT option_value FROM $wpdb->options WHERE option_name = 'cheritto-wordpress-importer-attachment-queue-lock'" ) !="locked" );

        // Reset keepalive
        $wpdb->query("UPDATE " . $wpdb->prefix . $this->plugin_prefix . "keepalives SET last_seen_at = NULL WHERE name='attachment_queue'");

        if ($wpdb->get_var( "SELECT option_value FROM $wpdb->options WHERE option_name = 'cheritto-wordpress-importer-attachment-queue-lock'" ) !="locked")
            update_option('cheritto-wordpress-importer-attachment-queue-stage','ended');

        return true;

    }

    public function start_thumbnails_queue()
    {
        global $wpdb;

        set_time_limit(0);

        update_option('cheritto-wordpress-importer-thumbnails-queue-stage','running');
        update_option('cheritto-wordpress-importer-thumbnails-queue-lock','unlocked');

        // First keepalive
        $wpdb->query("UPDATE " . $wpdb->prefix . $this->plugin_prefix . "keepalives SET last_seen_at = NOW() WHERE name='thumbnails_queue'");

        $batch_size = 10;
        
        do {
            $rows = $wpdb->get_results("SELECT ID, attachment_url, upload_path FROM " . $wpdb->prefix . $this->plugin_prefix . "attachments WHERE downloaded_at IS NOT NULL AND thumbnailed_at IS NULL AND error IS NULL LIMIT " . $batch_size);
        
            foreach($rows as $row)
            {
                // Keepalive
                $wpdb->query("UPDATE " . $wpdb->prefix . $this->plugin_prefix . "keepalives SET last_seen_at = NOW() WHERE name='thumbnails_queue'");

                $uploads_folder = wp_upload_dir();

                $file_path = $wpdb->get_var("SELECT meta_value FROM " . $wpdb->prefix . "postmeta WHERE post_id = " . (int) $row->ID . " AND meta_key = '_wp_attached_file'");

                wp_update_attachment_metadata( (int) $row->ID, wp_generate_attachment_metadata( (int) $row->ID, $uploads_folder['basedir'] . $file_path ) );

                $wpdb->query("UPDATE " . $wpdb->prefix . $this->plugin_prefix . "attachments SET thumbnailed_at = NOW() WHERE ID = " . (int) $row->ID);
            }


        } while ( count($rows) > 0 && $wpdb->get_var( "SELECT option_value FROM $wpdb->options WHERE option_name = 'cheritto-wordpress-importer-thumbnails-queue-lock'" ) !="locked" );
    
        // Reset keepalive
        $wpdb->query("UPDATE " . $wpdb->prefix . $this->plugin_prefix . "keepalives SET last_seen_at = NULL WHERE name='thumbnails_queue'");

        if ($wpdb->get_var( "SELECT option_value FROM $wpdb->options WHERE option_name = 'cheritto-wordpress-importer-thumbnails-queue-lock'" ) !="locked")
            update_option('cheritto-wordpress-importer-thumbnails-queue-stage','ended');
    }


    public function pause_download_queue()
    {
        update_option('cheritto-wordpress-importer-attachment-queue-lock','locked');
    }

    public function pause_thumbnails_queue()
    {
        update_option('cheritto-wordpress-importer-thumbnails-queue-lock','locked');
    }

    public function start_data_import()
    {
        global $wpdb;
        
        update_option('cheritto-wordpress-importer-current-job-stage','import_started');

        $duplicate_posts_strategy = (int) $_REQUEST['duplicate_posts_strategy'];

        // This avoids clashing post ids
        $this->_update_posts_ids();

        // This avoids clashing user ids
        $this->_update_users_ids();
        $this->_delete_duplicate_users_before_insert();
        
        // This avoids duplication of terms
        $this->_fix_terms_relationships();

        if (1 === $duplicate_posts_strategy ) {

            // We need an extra step
            // Strategy is "do not insert duplicate posts based on title, date and post type", so we delete them before insert
            $this->_delete_duplicate_posts_before_insert();

        }

        // Data is ready to be imported.

        // Insert data in wordpress tables
        $this->_insert_ignore_data();

        // Update term counts after insert
        $this->_fix_terms_counts();

        update_option('cheritto-wordpress-importer-current-job-stage','import_ended');

        return true;

    }

    protected function _delete_duplicate_posts_before_insert()
    {
        // We don't want to insert posts with same title, date and type of existing posts
        // so we delete these posts before insert

        global $wpdb;

        $delete_ids = $wpdb->get_results("SELECT cp.ID FROM " . $wpdb->prefix . $this->plugin_prefix . "posts cp
                        JOIN $wpdb->posts p ON cp.post_title=p.post_title AND cp.post_date=p.post_date AND cp.post_type=p.post_type 
                        GROUP BY cp.ID");

        foreach($delete_ids as $row)
        {
            $wpdb->query("DELETE FROM " . $wpdb->prefix . $this->plugin_prefix . "posts WHERE ID = ". (int) $row->ID);
            $wpdb->query("DELETE FROM " . $wpdb->prefix . $this->plugin_prefix . "postmeta WHERE post_id = ". (int) $row->ID);
            $wpdb->query("DELETE FROM " . $wpdb->prefix . $this->plugin_prefix . "comments WHERE comment_post_ID = ". (int) $row->ID);
            $wpdb->query("DELETE FROM " . $wpdb->prefix . $this->plugin_prefix . "attachments WHERE ID = ". (int) $row->ID);
            $wpdb->query("DELETE FROM " . $wpdb->prefix . $this->plugin_prefix . "term_relationships WHERE object_id = ". (int) $row->ID);
        }

        return true;
    }

    protected function _delete_duplicate_users_before_insert()
    {
        // We don't want to insert users with same email and user_login
        // so we delete these users before insert

        global $wpdb;

        $delete_ids = $wpdb->get_results("SELECT cu.ID FROM " . $wpdb->prefix . $this->plugin_prefix . "users cu
                        JOIN $wpdb->users u ON cu.user_email=u.user_email OR cu.user_login=u.user_login 
                        GROUP BY cu.ID");

        foreach($delete_ids as $row)
        {
            $wpdb->query("DELETE FROM " . $wpdb->prefix . $this->plugin_prefix . "users WHERE ID = ". (int) $row->ID);
            $wpdb->query("DELETE FROM " . $wpdb->prefix . $this->plugin_prefix . "usermeta WHERE user_id = ". (int) $row->ID);
        }

        return true;
    }

    protected function _update_posts_ids()
    {
        global $wpdb;

        $current_max_post_id = (int) $wpdb->get_var("SELECT MAX(ID) FROM $wpdb->posts");
        $current_importing_max_post_id = (int) $wpdb->get_var("SELECT MAX(ID) FROM " . $wpdb->prefix . $this->plugin_prefix . "posts");

        $safe_base_id = $current_max_post_id + $current_importing_max_post_id;

        $wpdb->query("UPDATE " . $wpdb->prefix . $this->plugin_prefix . "posts SET ID = (" . (int) $safe_base_id . " + ID), post_parent = (" . (int) $safe_base_id . " + post_parent)");
        $wpdb->query("UPDATE " . $wpdb->prefix . $this->plugin_prefix . "postmeta SET post_id = (" . (int) $safe_base_id . " + post_id)");
        $wpdb->query("UPDATE " . $wpdb->prefix . $this->plugin_prefix . "postmeta SET meta_value = (" . (int) $safe_base_id . " + meta_value) WHERE meta_key='_thumbnail_id'");
        $wpdb->query("UPDATE " . $wpdb->prefix . $this->plugin_prefix . "comments SET comment_post_ID = (" . (int) $safe_base_id . " + comment_post_ID)");
        $wpdb->query("UPDATE " . $wpdb->prefix . $this->plugin_prefix . "attachments SET ID = (" . (int) $safe_base_id . " + ID)");
        $wpdb->query("UPDATE " . $wpdb->prefix . $this->plugin_prefix . "term_relationships SET object_id = (" . (int) $safe_base_id . " + object_id)");

        $current_max_meta_id = (int) $wpdb->get_var("SELECT MAX(meta_id) FROM $wpdb->postmeta");
        $current_importing_max_meta_id = (int) $wpdb->get_var("SELECT MAX(meta_id) FROM " . $wpdb->prefix . $this->plugin_prefix . "postmeta");

        $safe_base_id = $current_max_meta_id + $current_importing_max_meta_id;

        $wpdb->query("UPDATE " . $wpdb->prefix . $this->plugin_prefix . "postmeta SET meta_id = (" . (int) $safe_base_id . " + meta_id)");

        $current_max_comment_id = (int) $wpdb->get_var("SELECT MAX(comment_ID) FROM $wpdb->comments");
        $current_importing_max_comment_id = (int) $wpdb->get_var("SELECT MAX(comment_ID) FROM " . $wpdb->prefix . $this->plugin_prefix . "comments");

        $safe_base_id = $current_max_comment_id + $current_importing_max_comment_id;

        $wpdb->query("UPDATE " . $wpdb->prefix . $this->plugin_prefix . "comments SET comment_ID = (" . (int) $safe_base_id . " + comment_ID)");

        return true;

    }

    protected function _update_users_ids()
    {
        global $wpdb;

        $current_max_user_id = (int) $wpdb->get_var("SELECT MAX(ID) FROM $wpdb->users");
        $current_importing_max_user_id = (int) $wpdb->get_var("SELECT MAX(ID) FROM " . $wpdb->prefix . $this->plugin_prefix . "users");

        $safe_base_id = $current_max_user_id + $current_importing_max_user_id;

        $wpdb->query("UPDATE " . $wpdb->prefix . $this->plugin_prefix . "users SET ID = (" . (int) $safe_base_id . " + ID)");
        $wpdb->query("UPDATE " . $wpdb->prefix . $this->plugin_prefix . "usermeta SET user_id = (" . (int) $safe_base_id . " + user_id)");
        $wpdb->query("UPDATE " . $wpdb->prefix . $this->plugin_prefix . "posts SET post_author = (" . (int) $safe_base_id . " + post_author)");

        $current_max_umeta_id = (int) $wpdb->get_var("SELECT MAX(umeta_id) FROM $wpdb->usermeta");
        $current_importing_umax_meta_id = (int) $wpdb->get_var("SELECT MAX(umeta_id) FROM " . $wpdb->prefix . $this->plugin_prefix . "usermeta");

        $safe_base_id = $current_max_umeta_id + $current_importing_umax_meta_id;

        $wpdb->query("UPDATE " . $wpdb->prefix . $this->plugin_prefix . "usermeta SET umeta_id = (" . (int) $safe_base_id . " + umeta_id)");

        // Already existing? Update to actual user id
        $wpdb->query("UPDATE " . $wpdb->prefix . $this->plugin_prefix . "usermeta cumeta
                        JOIN " . $wpdb->prefix . $this->plugin_prefix . "users cu ON cu.ID=cumeta.user_id
                        JOIN $wpdb->users u ON cu.user_email=u.user_email OR cu.user_login=u.user_login
                        SET cumeta.user_id=u.ID");

        $wpdb->query("UPDATE " . $wpdb->prefix . $this->plugin_prefix . "posts cp
                        JOIN " . $wpdb->prefix . $this->plugin_prefix . "users cu ON cu.ID=cp.post_author
                        JOIN $wpdb->users u ON cu.user_email=u.user_email OR cu.user_login=u.user_login
                        SET cp.post_author=u.ID");

        $wpdb->query("UPDATE " . $wpdb->prefix . $this->plugin_prefix . "users cu
                        JOIN $wpdb->users u ON cu.user_email=u.user_email OR cu.user_login=u.user_login
                        SET cu.ID=u.ID");
       
        return true;

    }

    protected function _update_terms_ids()
    {
        // Deprecated and not in use. It's ok for empty installations, but will duplicate terms for not empty ones.

        global $wpdb;

        $current_max_term_id = (int) $wpdb->get_var("SELECT MAX(term_id) FROM $wpdb->terms");
        $current_importing_max_term_id = (int) $wpdb->get_var("SELECT MAX(term_id) FROM " . $wpdb->prefix . $this->plugin_prefix . "terms");

        $safe_base_id = $current_max_term_id + $current_importing_max_term_id;

        $wpdb->query("UPDATE " . $wpdb->prefix . $this->plugin_prefix . "terms SET term_id = (" . (int) $safe_base_id . " + term_id)");
        $wpdb->query("UPDATE " . $wpdb->prefix . $this->plugin_prefix . "termmeta SET term_id = (" . (int) $safe_base_id . " + term_id)");
        $wpdb->query("UPDATE " . $wpdb->prefix . $this->plugin_prefix . "term_taxonomy SET term_id = (" . (int) $safe_base_id . " + term_id)");

        $current_max_term_taxonomy_id = (int) $wpdb->get_var("SELECT MAX(term_taxonomy_id) FROM $wpdb->term_taxonomy");
        $current_importing_max_term_taxonomy_id = (int) $wpdb->get_var("SELECT MAX(term_taxonomy_id) FROM " . $wpdb->prefix . $this->plugin_prefix . "term_taxonomy");

        $safe_base_id = $current_max_term_taxonomy_id + $current_importing_max_term_taxonomy_id;

        $wpdb->query("UPDATE " . $wpdb->prefix . $this->plugin_prefix . "term_taxonomy SET term_taxonomy_id = (" . (int) $safe_base_id . " + term_taxonomy_id)");
        $wpdb->query("UPDATE " . $wpdb->prefix . $this->plugin_prefix . "term_relationships SET term_taxonomy_id = (" . (int) $safe_base_id . " + term_taxonomy_id)");

        $current_max_meta_id = (int) $wpdb->get_var("SELECT MAX(meta_id) FROM $wpdb->termmeta");
        $current_importing_max_meta_id = (int) $wpdb->get_var("SELECT MAX(meta_id) FROM " . $wpdb->prefix . $this->plugin_prefix . "termmeta");

        $safe_base_id = $current_max_meta_id + $current_importing_max_meta_id;

        $wpdb->query("UPDATE " . $wpdb->prefix . $this->plugin_prefix . "termmeta SET meta_id = (" . (int) $safe_base_id . " + meta_id)");

        return true;

    }

    protected function _fix_terms_relationships()
    {
        # This set of queries checks for already existing terms and term / taxonomy associations and ensures we are not duplicating any of the terms during our data import
        # Basically, we update all import ids to the already existing ones so that, since we will do an "INSERT IGNORE", only new ones will be imported
        # and relationships needed for the posts we are importing will be already in place thanks to this id mapping. 

        global $wpdb;

        $wpdb->query("TRUNCATE TABLE " . $wpdb->prefix . $this->plugin_prefix . "temp_terms");

        $wpdb->query("INSERT INTO " . $wpdb->prefix . $this->plugin_prefix . "temp_terms 
                    SELECT wt.slug,min(wt.term_id) AS term_id FROM " . $wpdb->prefix . $this->plugin_prefix . "terms wt GROUP BY wt.slug");
        
        $wpdb->query("TRUNCATE TABLE " . $wpdb->prefix . $this->plugin_prefix . "temp_terms_tax");

        $wpdb->query("INSERT INTO " . $wpdb->prefix . $this->plugin_prefix . "temp_terms_tax 
                    SELECT MIN(" . $wpdb->prefix . $this->plugin_prefix . "terms.term_id) AS term_id,
                    " . $wpdb->prefix . $this->plugin_prefix . "terms.slug,
                    " . $wpdb->prefix . $this->plugin_prefix . "term_taxonomy.taxonomy 
                    FROM " . $wpdb->prefix . $this->plugin_prefix . "term_taxonomy
                    JOIN " . $wpdb->prefix . $this->plugin_prefix . "terms 
                        ON " . $wpdb->prefix . $this->plugin_prefix . "term_taxonomy.term_id=" . $wpdb->prefix . $this->plugin_prefix . "terms.term_id
                    GROUP BY " . $wpdb->prefix . $this->plugin_prefix . "terms.slug," . $wpdb->prefix . $this->plugin_prefix . "term_taxonomy.taxonomy");

        # Delete multiple taxonomy rows (in this plugin tables, not actual wordpress tables)
        $wpdb->query("DELETE FROM " . $wpdb->prefix . $this->plugin_prefix . "term_taxonomy WHERE term_id NOT IN (
                            SELECT term_id FROM " . $wpdb->prefix . $this->plugin_prefix . "temp_terms_tax)");

        # Update taxonomy using lowest term id, to ensure all is covered after the previous deletion
        $wpdb->query("UPDATE " . $wpdb->prefix . $this->plugin_prefix . "term_taxonomy
                    JOIN " . $wpdb->prefix . $this->plugin_prefix . "terms on " . $wpdb->prefix . $this->plugin_prefix . "term_taxonomy.term_id=" . $wpdb->prefix . $this->plugin_prefix . "terms.term_id
                    JOIN " . $wpdb->prefix . $this->plugin_prefix . "temp_terms ON " . $wpdb->prefix . $this->plugin_prefix . "temp_terms.slug=" . $wpdb->prefix . $this->plugin_prefix . "terms.slug
                    SET " . $wpdb->prefix . $this->plugin_prefix . "term_taxonomy.term_id=" . $wpdb->prefix . $this->plugin_prefix . "temp_terms.term_id");

        # Delete multiple terms rows (in this plugin tables, not actual wordpress tables)
        $wpdb->query("DELETE FROM " . $wpdb->prefix . $this->plugin_prefix . "terms WHERE term_id NOT IN (
                            SELECT term_id FROM " . $wpdb->prefix . $this->plugin_prefix . "temp_terms)");

        $wpdb->query("TRUNCATE TABLE " . $wpdb->prefix . $this->plugin_prefix . "temp_terms_wp");

        $wpdb->query("INSERT INTO " . $wpdb->prefix . $this->plugin_prefix . "temp_terms_wp 
                        SELECT t.slug,min(t.term_id) AS term_id FROM " . $wpdb->prefix . $this->plugin_prefix . "terms wt 
                        JOIN $wpdb->terms t ON wt.slug=t.slug");
        
        $wpdb->query("TRUNCATE TABLE " . $wpdb->prefix . $this->plugin_prefix . "old_and_new_term_id");

        $wpdb->query("INSERT INTO " . $wpdb->prefix . $this->plugin_prefix . "old_and_new_term_id
        SELECT " . $wpdb->prefix . $this->plugin_prefix . "terms.term_id AS old_term_id, " . $wpdb->prefix . $this->plugin_prefix . "temp_terms_wp.term_id AS new_term_id
        FROM " . $wpdb->prefix . $this->plugin_prefix . "terms 
        JOIN " . $wpdb->prefix . $this->plugin_prefix . "temp_terms_wp 
        ON " . $wpdb->prefix . $this->plugin_prefix . "terms.slug=" . $wpdb->prefix . $this->plugin_prefix . "temp_terms_wp.slug");

        # Avoid clashing ids during update - It is safe since we used min and group by at the end of the query we will have integrity
        # The next query removes AUTO_INCREMENT from the column definition, otherwise we won't be able to drop the primary key:
        $wpdb->query("ALTER TABLE " . $wpdb->prefix . $this->plugin_prefix . "terms MODIFY `term_id` bigint(20) UNSIGNED NOT NULL");
        $wpdb->query("ALTER TABLE " . $wpdb->prefix . $this->plugin_prefix . "terms DROP PRIMARY KEY");

        # Update plugins tables with existing term ids
        $wpdb->query("UPDATE " . $wpdb->prefix . $this->plugin_prefix . "terms
                        JOIN " . $wpdb->prefix . $this->plugin_prefix . "old_and_new_term_id 
                            ON " . $wpdb->prefix . $this->plugin_prefix . "terms.term_id=" . $wpdb->prefix . $this->plugin_prefix . "old_and_new_term_id.old_term_id
                        SET " . $wpdb->prefix . $this->plugin_prefix . "terms.term_id=" . $wpdb->prefix . $this->plugin_prefix . "old_and_new_term_id.new_term_id");

        # Resume primary key and auto increment
        $wpdb->query("ALTER TABLE " . $wpdb->prefix . $this->plugin_prefix . "terms ADD PRIMARY KEY (term_id)");
        $wpdb->query("ALTER TABLE " . $wpdb->prefix . $this->plugin_prefix . "terms MODIFY `term_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT");

        # Avoid clashing ids during update
        $wpdb->query("ALTER TABLE " . $wpdb->prefix . $this->plugin_prefix . "term_taxonomy DROP KEY `term_id_taxonomy`");

        $wpdb->query("UPDATE " . $wpdb->prefix . $this->plugin_prefix . "term_taxonomy
                    JOIN " . $wpdb->prefix . $this->plugin_prefix . "old_and_new_term_id 
                        ON " . $wpdb->prefix . $this->plugin_prefix . "term_taxonomy.term_id=" . $wpdb->prefix . $this->plugin_prefix . "old_and_new_term_id.old_term_id
                    SET " . $wpdb->prefix . $this->plugin_prefix . "term_taxonomy.term_id=" . $wpdb->prefix . $this->plugin_prefix . "old_and_new_term_id.new_term_id");

        $dup = $wpdb->get_results("SELECT term_id,taxonomy,COUNT(1) AS count FROM " . $wpdb->prefix . $this->plugin_prefix . "term_taxonomy 
                        GROUP BY term_id,taxonomy HAVING count > 1
        ");

        foreach ($dup as $dup_row)
        {
            // Should never ever be here, but in case we have to fix things up before continuing

            for($j=1; $j<$dup_row->count; $j++) {
                $del_id = $wpdb->query(
                    $wpdb->prepare("SELECT MAX(term_taxonomy_id) FROM " . $wpdb->prefix . $this->plugin_prefix . "term_taxonomy
                            WHERE term_id = %d AND taxonomy = %s",(int) $dup_row->term_id, $dup_row->taxonomy)
                );

                $wpdb->query("DELETE FROM " . $wpdb->prefix . $this->plugin_prefix . "term_taxonomy WHERE term_taxonomy_id = " . (int) $del_id);
            }
        }

        $wpdb->query("ALTER TABLE " . $wpdb->prefix . $this->plugin_prefix . "term_taxonomy ADD UNIQUE KEY `term_id_taxonomy` (`term_id`,`taxonomy`)");

        $wpdb->query("TRUNCATE TABLE " . $wpdb->prefix . $this->plugin_prefix . "old_and_new_term_taxonomy_id");

        $wpdb->query("INSERT INTO " . $wpdb->prefix . $this->plugin_prefix . "old_and_new_term_taxonomy_id
                    SELECT " . $wpdb->prefix . $this->plugin_prefix . "term_taxonomy.term_taxonomy_id AS old_term_taxonomy_id,  
                        $wpdb->term_taxonomy.term_taxonomy_id AS new_term_taxonomy_id
                    FROM $wpdb->term_taxonomy
                    JOIN " . $wpdb->prefix . $this->plugin_prefix . "term_taxonomy ON " . $wpdb->prefix . $this->plugin_prefix . "term_taxonomy.taxonomy=$wpdb->term_taxonomy.taxonomy 
                        AND " . $wpdb->prefix . $this->plugin_prefix . "term_taxonomy.term_id=$wpdb->term_taxonomy.term_id");

        $wpdb->query("TRUNCATE TABLE " . $wpdb->prefix . $this->plugin_prefix . "old_and_new_term_taxonomy_id_rjoin");

        $wpdb->query("INSERT INTO " . $wpdb->prefix . $this->plugin_prefix . "old_and_new_term_taxonomy_id_rjoin
                    SELECT " . $wpdb->prefix . $this->plugin_prefix . "term_taxonomy.term_taxonomy_id AS old_term_taxonomy_id,  
                        $wpdb->term_taxonomy.term_taxonomy_id AS new_term_taxonomy_id
                    FROM $wpdb->term_taxonomy
                    RIGHT JOIN " . $wpdb->prefix . $this->plugin_prefix . "term_taxonomy ON " . $wpdb->prefix . $this->plugin_prefix . "term_taxonomy.taxonomy=$wpdb->term_taxonomy.taxonomy 
                        AND " . $wpdb->prefix . $this->plugin_prefix . "term_taxonomy.term_id=$wpdb->term_taxonomy.term_id");
        
        # These ids will overlap during update - term_taxonomy_ids that match already existing one, but are not going to be updated because of different term / taxonomy couple - we need to update them with a new term_taxonomy_id:
        $ids_that_will_clash_for_sure = $wpdb->get_results("SELECT r.old_term_taxonomy_id FROM " . $wpdb->prefix . $this->plugin_prefix . "old_and_new_term_taxonomy_id_rjoin r
                JOIN  " . $wpdb->prefix . $this->plugin_prefix . "old_and_new_term_taxonomy_id j ON r.old_term_taxonomy_id=j.new_term_taxonomy_id
                WHERE r.new_term_taxonomy_id IS NULL");
            
        $max_id = (int) $wpdb->get_var("SELECT GREATEST( MAX(old_term_taxonomy_id), MAX(new_term_taxonomy_id) ) FROM " . $wpdb->prefix . $this->plugin_prefix . "old_and_new_term_taxonomy_id");

        $i=1;
        foreach($ids_that_will_clash_for_sure as $row)
        {
            $wpdb->query("UPDATE " . $wpdb->prefix . $this->plugin_prefix . "term_taxonomy SET term_taxonomy_id = " . (int) ($max_id + $i) . " WHERE term_taxonomy_id = " . (int) $row->old_term_taxonomy_id);
            $wpdb->query("UPDATE " . $wpdb->prefix . $this->plugin_prefix . "term_relationships SET term_taxonomy_id = " . (int) ($max_id + $i) . " WHERE term_taxonomy_id = " . (int) $row->old_term_taxonomy_id);
            $i = $i+1;
        }

        # Avoid clashing ids during update ( updating row by row with an id that currently exists will get a constraint error; 
        # at the end of the update, if the previous preparation has been done correctly, we will not have overlapping ids and we will re-introduce the primary key)
        # The next query removes AUTO_INCREMENT from the column definition, otherwise we won't be able to drop the primary key:
        $wpdb->query("ALTER TABLE " . $wpdb->prefix . $this->plugin_prefix . "term_taxonomy MODIFY `term_taxonomy_id` bigint(20) UNSIGNED NOT NULL");
        $wpdb->query("ALTER TABLE " . $wpdb->prefix . $this->plugin_prefix . "term_taxonomy DROP PRIMARY KEY");

        $wpdb->query("UPDATE " . $wpdb->prefix . $this->plugin_prefix . "term_taxonomy
        JOIN " . $wpdb->prefix . $this->plugin_prefix . "old_and_new_term_taxonomy_id 
            ON " . $wpdb->prefix . $this->plugin_prefix . "term_taxonomy.term_taxonomy_id=" . $wpdb->prefix . $this->plugin_prefix . "old_and_new_term_taxonomy_id.old_term_taxonomy_id
            SET " . $wpdb->prefix . $this->plugin_prefix . "term_taxonomy.term_taxonomy_id=" . $wpdb->prefix . $this->plugin_prefix . "old_and_new_term_taxonomy_id.new_term_taxonomy_id");


        $dup = $wpdb->get_results("SELECT term_taxonomy_id,COUNT(1) AS count FROM " . $wpdb->prefix . $this->plugin_prefix . "term_taxonomy 
                    GROUP BY term_taxonomy_id HAVING count > 1
        ");

        foreach ($dup as $dup_row)
        {
            // Should never ever be here, but in case we have to fix things up before continuing

            for($j=1; $j<$dup_row->count; $j++) {

                $wpdb->query("DELETE FROM " . $wpdb->prefix . $this->plugin_prefix . "term_taxonomy WHERE term_taxonomy_id = " . (int) $dup_row->term_taxonomy_id);
            }
        }

        $wpdb->query("ALTER TABLE " . $wpdb->prefix . $this->plugin_prefix . "term_taxonomy ADD PRIMARY KEY (term_taxonomy_id)");
        $wpdb->query("ALTER TABLE " . $wpdb->prefix . $this->plugin_prefix . "term_taxonomy MODIFY `term_taxonomy_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT");

        # Avoid clashing ids during update
        $wpdb->query("ALTER TABLE " . $wpdb->prefix . $this->plugin_prefix . "term_relationships DROP PRIMARY KEY");
        $wpdb->query("ALTER TABLE " . $wpdb->prefix . $this->plugin_prefix . "term_relationships DROP KEY `term_taxonomy_id`");

        $wpdb->query("UPDATE " . $wpdb->prefix . $this->plugin_prefix . "term_relationships
                    JOIN " . $wpdb->prefix . $this->plugin_prefix . "old_and_new_term_taxonomy_id 
                        ON " . $wpdb->prefix . $this->plugin_prefix . "term_relationships.term_taxonomy_id=" . $wpdb->prefix . $this->plugin_prefix . "old_and_new_term_taxonomy_id.old_term_taxonomy_id
                    SET " . $wpdb->prefix . $this->plugin_prefix . "term_relationships.term_taxonomy_id=" . $wpdb->prefix . $this->plugin_prefix . "old_and_new_term_taxonomy_id.new_term_taxonomy_id");

        $dup = $wpdb->get_results("SELECT object_id,term_taxonomy_id,COUNT(1) AS count FROM " . $wpdb->prefix . $this->plugin_prefix . "term_relationships 
                    GROUP BY object_id,term_taxonomy_id HAVING count > 1
        ");

        foreach ($dup as $dup_row)
        {
            // Should never ever be here, but in case we have to fix things up before continuing

            for($j=1; $j<$dup_row->count; $j++) {

                $wpdb->query("DELETE FROM " . $wpdb->prefix . $this->plugin_prefix . "term_relationships 
                        WHERE object_id = " . (int) $dup_row->object_id . " AND term_taxonomy_id = " . (int) $row->term_taxonomy_id );
            }
        }


        $wpdb->query("ALTER TABLE " . $wpdb->prefix . $this->plugin_prefix . "term_relationships ADD PRIMARY KEY (`object_id`,`term_taxonomy_id`)");
        $wpdb->query("ALTER TABLE " . $wpdb->prefix . $this->plugin_prefix . "term_relationships ADD KEY `term_taxonomy_id` (`term_taxonomy_id`)");

        return true;

    }

    protected function _fix_terms_counts()
    {
        global $wpdb;

        $wpdb->query("UPDATE $wpdb->term_taxonomy SET count = (
            SELECT COUNT(*) FROM $wpdb->term_relationships rel 
                LEFT JOIN $wpdb->posts po ON (po.ID = rel.object_id) 
                WHERE 
                    rel.term_taxonomy_id = $wpdb->term_taxonomy.term_taxonomy_id 
                    AND $wpdb->term_taxonomy.taxonomy NOT IN ('link_category')
                    AND po.post_status IN ('publish', 'future','inherit')
            )");

        return true;
    }

    protected function _insert_ignore_data() 
    {
        global $wpdb;

        $wpdb->query("INSERT IGNORE INTO " . $wpdb->prefix . "posts 
                        SELECT * FROM " . $wpdb->prefix . $this->plugin_prefix . "posts");

        $wpdb->query("INSERT IGNORE INTO " . $wpdb->prefix . "postmeta 
                    SELECT * FROM " . $wpdb->prefix . $this->plugin_prefix . "postmeta");

        $wpdb->query("INSERT IGNORE INTO " . $wpdb->prefix . "comments 
                    SELECT * FROM " . $wpdb->prefix . $this->plugin_prefix . "comments");
        
        $wpdb->query("INSERT IGNORE INTO " . $wpdb->prefix . "commentmeta 
                    SELECT * FROM " . $wpdb->prefix . $this->plugin_prefix . "commentmeta");

        $wpdb->query("INSERT IGNORE INTO " . $wpdb->prefix . "users 
                    SELECT * FROM " . $wpdb->prefix . $this->plugin_prefix . "users");
        
        $wpdb->query("INSERT IGNORE INTO " . $wpdb->prefix . "usermeta 
                    SELECT * FROM " . $wpdb->prefix . $this->plugin_prefix . "usermeta");

        $wpdb->query("INSERT IGNORE INTO " . $wpdb->prefix . "terms 
                    SELECT * FROM " . $wpdb->prefix . $this->plugin_prefix . "terms");

        $wpdb->query("INSERT IGNORE INTO " . $wpdb->prefix . "termmeta
                    SELECT * FROM " . $wpdb->prefix . $this->plugin_prefix . "termmeta");

        $wpdb->query("INSERT IGNORE INTO " . $wpdb->prefix . "term_taxonomy
                    SELECT * FROM " . $wpdb->prefix . $this->plugin_prefix . "term_taxonomy");

        $wpdb->query("INSERT IGNORE INTO " . $wpdb->prefix . "term_relationships
                    SELECT * FROM " . $wpdb->prefix . $this->plugin_prefix . "term_relationships");

        return true;
    }

    protected function return_bytes($val) {
        $val = trim($val);
        $last = strtolower($val[strlen($val)-1]);
        switch($last) {
            case 'g':
                $val = substr($val,0,-1);
                $val *= 1024;
            case 'm':
                $val = substr($val,0,-1);
                $val *= 1024;
            case 'k':
                $val = substr($val,0,-1);
                $val *= 1024;
        }
    
        return $val;
    }
    
}