<?php

/**
 * Import page.
 * 
 * @since      1.0.0
 * @package    Cheritto_Wordpress_Importer
 * @subpackage Cheritto_Wordpress_Importer/includes
 * @author     Flavio Iulita <fiulita@gmail.com>
 */

$current_job = get_option('cheritto-wordpress-importer-current-job');
$jobs_dir = get_option('cheritto-wordpress-importer-current-job-path');
$current_stage = get_option('cheritto-wordpress-importer-current-job-stage');
$current_queue_stage = get_option('cheritto-wordpress-importer-attachment-queue-stage');
$current_queue_lock = get_option('cheritto-wordpress-importer-attachment-queue-lock');
$current_thumbnails_queue_stage = get_option('cheritto-wordpress-importer-thumbnails-queue-stage');
$current_thumbnails_queue_lock = get_option('cheritto-wordpress-importer-thumbnails-queue-lock');

$path = $jobs_dir . DIRECTORY_SEPARATOR . $current_job;

if (!$current_job) {
    $current_job = uniqid('JOB-',true);
    $current_stage = 'init';
    $current_job_path = wp_upload_dir()['path'] . DIRECTORY_SEPARATOR . "cheritto-wordpress-importer-jobs";
    update_option('cheritto-wordpress-importer-current-job',$current_job);
    update_option('cheritto-wordpress-importer-current-job-stage',$current_stage);
    update_option('cheritto-wordpress-importer-current-job-path',$current_job_path);
} 

$files = [];

if ($current_stage=='first_upload_complete') {
    $files = scandir($path);
    $files = array_diff(scandir($path), array('.', '..'));
}

if ($current_queue_stage!='') {
    global $wpdb;
    global $cheritto_wordpress_importer_prefix;
    $downloads_completed = $wpdb->get_var("SELECT COUNT(1) FROM " . $wpdb->prefix . $cheritto_wordpress_importer_prefix . "attachments WHERE downloaded_at IS NOT NULL");
    $downloads_errors = $wpdb->get_var("SELECT COUNT(1) FROM " . $wpdb->prefix . $cheritto_wordpress_importer_prefix . "attachments WHERE error IS NOT NULL");
    $downloads_remaining = $wpdb->get_var("SELECT COUNT(1) FROM " . $wpdb->prefix . $cheritto_wordpress_importer_prefix . "attachments WHERE downloaded_at IS NULL AND error IS NULL");
}

if ($current_thumbnails_queue_stage!='') {
    global $wpdb;
    global $cheritto_wordpress_importer_prefix;
    $thumbnails_completed = $wpdb->get_var("SELECT COUNT(1) FROM " . $wpdb->prefix . $cheritto_wordpress_importer_prefix . "attachments WHERE downloaded_at IS NOT NULL AND thumbnailed_at IS NOT NULL");
    $thumbnails_remaining = $wpdb->get_var("SELECT COUNT(1) FROM " . $wpdb->prefix . $cheritto_wordpress_importer_prefix . "attachments WHERE downloaded_at IS NOT NULL AND thumbnailed_at IS NULL");
}

$alive_check = false;
$is_alive = true;

$download_queue_alive_check = false;
$download_queue_is_alive = true;

$thumbnails_queue_alive_check = false;
$thumbnails_queue_is_alive = true;

/*
 * Check if job is alive
 */ 
if ($current_stage=='check_files') {

    global $wpdb;
    global $cheritto_wordpress_importer_prefix;
    $alive_check = true;
    $is_alive = $wpdb->get_var("SELECT TIMEDIFF( NOW(), last_seen_at ) <= TIME('00:01:00') FROM " . $wpdb->prefix . $cheritto_wordpress_importer_prefix . "keepalives WHERE name = 'job'");
} else if ( $current_queue_stage=='running' && $current_queue_lock=='unlocked' ) {
    global $wpdb;
    global $cheritto_wordpress_importer_prefix;
    $download_queue_alive_check = true;
    $download_queue_is_alive = $wpdb->get_var("SELECT TIMEDIFF( NOW(), last_seen_at ) <= TIME('00:01:00') FROM " . $wpdb->prefix . $cheritto_wordpress_importer_prefix . "keepalives WHERE name = 'attachment_queue'");  
} else if ( $current_thumbnails_queue_stage=='running' && $current_thumbnails_queue_lock=='unlocked' ) {
    global $wpdb;
    global $cheritto_wordpress_importer_prefix;
    $thumbnails_queue_alive_check = true;
    $thumbnails_queue_is_alive = $wpdb->get_var("SELECT TIMEDIFF( NOW(), last_seen_at ) <= TIME('00:01:00') FROM " . $wpdb->prefix . $cheritto_wordpress_importer_prefix . "keepalives WHERE name = 'thumbnails_queue'");  
}

?>

<div class="cheritto-wordpress-importer-container">

    <div class="cheritto-wordpress-importer-left-container">

        <h1>Cheritto's Wordpress Importer!</h1>

        <?php
            
            /*
             * An alive check has failed. Job is stuck, notify user so that job can be canceled.
             */

            if ( ($alive_check && !$is_alive) || ($download_queue_alive_check && !$download_queue_is_alive) || ($thumbnails_queue_alive_check && !$thumbnails_queue_is_alive) ) {
                echo "<h2 class='cheritto-wordpress-importer-warning'>" . __("Warning") . "</h2><br/>";
                echo __("<p>It seems that the current job is stuck. You may want to cancel the job and retry.</p>",'cherittos-importer');
                ?>
                <input class="cheritto-wordpress-importer-button" type="button" id="canceljob" value="Cancel job"/>
                <br/><hr/>
                <?php
            }
        
        ?>

        <?php if ($current_stage=="init" || $current_stage=="first_upload_complete"): ?>
            
            <?php 
            /*
            * Phase 1: Upload
            */ 
            ?>

            <?php echo "<h2>" . __("Phase 1: xml file upload",'cherittos-importer') . "</h2>"; ?>
        <p>
            <?php echo __("Upload one or more Wordpress Exported xml files to start an import job.",'cherittos-importer'); ?>
        </p>

        <input class="cheritto-wordpress-importer-button" type="button" id="pickfiles" value="<?php echo count($files)>0 ? __('Upload more files','cherittos-importer') : __('Upload','cherittos-importer'); ?>"/>

        <div id="filelist"></div>

        <?php endif; ?>

        <?php if (count($files)>0) : ?>

            <p><strong><?php echo __("Currently uploaded files:",'cherittos-importer'); ?></strong></p>

            <ul class="cheritto-wordpress-importer-ul">

                <?php foreach($files as $f): ?>

                    <li><?php echo esc_html($f);?></li>

                <?php endforeach; ?>

            </ul>

            <?php 
            /*
            * Phase 2: File checking and parsing
            */ 
            ?>

            <?php echo "<h2>" . __("Phase 2: check files",'cherittos-importer') . "</h2>"; ?>

            <input class="cheritto-wordpress-importer-button" type="button" id="checkfiles" value="Check files"/> or
            <!-- <input class="cheritto-wordpress-importer-button cheritto-wordpress-importer-button-start" type="button" id="startjob" value="Start import job"/> or  -->
            <input class="cheritto-wordpress-importer-button" type="button" id="canceljob" value="Cancel job"/>


        <?php endif; ?>

        <?php if ($current_stage=="check_files" || $current_stage=="files_checked" || $current_stage=="import_ended" || $current_stage=="import_started" ) 
        { 
            if ($current_stage=="check_files") {
                echo "<h2 class='cheritto-wordpress-importer-check-running'>" . __("CHECK IS RUNNING... below are reported partial results.") . "</h2>";
            } else if ($current_stage=="import_started") {
                echo "<h2 class='cheritto-wordpress-importer-check-running'>" . __("IMPORT IS RUNNING...") . "</h2>";
            } else
            {

                if ($current_stage!="import_ended")
                {
                    echo "<h2>" . __("All files have been checked: ",'cherittos-importer') . "</h2>";

                    //echo "<h2 class='cheritto-wordpress-importer-check-summary'>" . __("Check summary") . "</h2><br/>";

                    $summary = file_get_contents( $jobs_dir . DIRECTORY_SEPARATOR . $current_job . "_check_summary.html");

                    $allowed = [ 'strong' => [], 'table' => [ 'class'=>[] ], 'thead' => [], 'th' => [], 'tbody' => [], 'tr' => [], 'td' => []
                                    ];

                    echo wp_kses( $summary, $allowed );
                }

                if ($current_queue_stage=='running') {

                    echo "<h2>" . __("Download of attachments has been started...",'cherittos-importer') . "</h2>";

                    if ($current_queue_lock=='locked') {
                        echo "<p><strong>" . __("...and paused afterwards. Click the 'Resume download queue now' button to resume the queue.",'cherittos-importer') . "</strong></p>";
                        ?>
                        <input class="cheritto-wordpress-importer-button cheritto-wordpress-importer-button-start-download-queue" type="button" id="startqueue" value="Resume download queue now"/> 
                        <?php
                    } else {
                        echo "<p><strong>" . __("If you want to pause the download queue, click the 'Pause queue' button to pause the queue.",'cherittos-importer') . "</strong></p>";
                        ?>
                        <input class="cheritto-wordpress-importer-button cheritto-wordpress-importer-button-pause-download-queue" type="button" id="pausequeue" value="Pause download queue now"/> 
                        <?php
                    }

                    echo "<p>" . __("Queue status so far: ",'cherittos-importer') . "</p>";
                    echo "<p> " . (int) $downloads_completed . __(" downloads completed",'cherittos-importer') . ', ' . 
                                (int) $downloads_errors .  __(" downloads errors",'cherittos-importer') . ', ' .
                                    (int) $downloads_remaining .  __(" downloads remaining.",'cherittos-importer');

                } else if ($current_queue_stage=='ended') {
                    echo "<h2>" . __("Download of attachments completed",'cherittos-importer') . "</h2>";
                    echo "<p> " . (int) $downloads_completed . __(" downloads completed",'cherittos-importer') . ', ' . 
                                (int) $downloads_errors .  __(" downloads errors",'cherittos-importer') . ', ' .
                                    (int) $downloads_remaining .  __(" downloads remaining.",'cherittos-importer');

                    if ($current_stage=="import_ended") {

                        if ($current_thumbnails_queue_stage=="running") {

                            echo "<h2>" . __("Thumbnails generation has been started...",'cherittos-importer') . "</h2>";

                            if ($current_thumbnails_queue_lock=='locked') {
                                echo "<p><strong>" . __("...and paused afterwards. Click the 'Resume thumbnails queue now' button to resume the queue.",'cherittos-importer') . "</strong></p>";
                                ?>
                                <input class="cheritto-wordpress-importer-button cheritto-wordpress-importer-button-start-thumbnails-queue" type="button" id="startthumbnailsqueue" value="Resume thumbnails queue now"/> 
                                <?php
                            } else {
                                echo "<p><strong>" . __("If you want to pause the thumbnals queue, click the 'Pause queue' button to pause the queue.",'cherittos-importer') . "</strong></p>";
                                ?>
                                <input class="cheritto-wordpress-importer-button cheritto-wordpress-importer-button-pause-thumbnails-queue" type="button" id="pausethumbnailsqueue" value="Pause thumbnails queue now"/> 
                                <?php
                            }

                            echo "<p>" . __("Queue status so far: ",'cherittos-importer') . "</p>";
                            echo "<p> " . (int) $thumbnails_completed . __(" thumbnails completed",'cherittos-importer') . ', ' . 
                                            (int) $thumbnails_remaining .  __(" thumbnails remaining.",'cherittos-importer');

                        } else if ($current_thumbnails_queue_stage=="ended") {

                            echo "<h2>" . __("Thumbnails generation completed",'cherittos-importer') . "</h2>";

                            echo "<p> " . (int) $thumbnails_completed . __(" thumbnails completed",'cherittos-importer') . ', ' . 
                                            (int) $thumbnails_remaining .  __(" downloads remaining.",'cherittos-importer');

                        } else if ($current_thumbnails_queue_stage=="" && $current_queue_stage=="ended") {

                            /*
                            * Phase 4a: Thumbnails
                            */ 

                            echo "<h2>" . __("Phase 4a: generate thumbnails",'cherittos-importer') . "</h2>";
                            ?>
                            <input class="cheritto-wordpress-importer-button cheritto-wordpress-importer-button-start-thumbnails-queue" type="button" id="startthumbnailsqueue" value="Start thumbnails queue now"/> 
                            or <input class="cheritto-wordpress-importer-button" type="button" id="canceljob" value="Close job"/>
                            <br/><hr/>
                            <?php
                        }

                    }
                } 

                if ( $current_stage!='import_ended' ) {

                    /*
                    * Phase 3: Import
                    */ 

                    echo "<h2>" . __("Phase 3: define the import strategy and start importing",'cherittos-importer') . "</h2>";
                    ?>
                    <table>
                        <tbody>
    
                            <tr>
                                <td class="cheritto-wordpress-importer-td-strategy-label">
                                    <label for="cheritto-wordpress-importer-duplicate-posts-strategy"><?php echo __("Choose according to your needs: ",'cherittos-importer'); ?></label>
                                </td>
                                <td>
                                    <input type="radio" checked value="1" name="cheritto-wordpress-importer-duplicate-posts-strategy[]"/><span><?php echo __("Do not import posts with same title, date and type of already existing posts",'cherittos-importer'); ?>&nbsp;&nbsp;
                                    <br/>
                                    <input type="radio" value="2" name="cheritto-wordpress-importer-duplicate-posts-strategy[]"/><span><?php echo __("Import all",'cherittos-importer'); ?>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <p><i><?php echo __("Note: tags, categories and authors will be imported but not duplicated if already existing.<br/><br/>
                        If you are importing to an empty wordpress installation, choose 'Import all' to avoid useless checks.",'cherittos-importer'); ?>
                        <br/>
                        
                    </i></p>
                    <?php echo "<h2>" . __("Then:",'cherittos-importer') . "</h2>"; ?>
                    <input class="cheritto-wordpress-importer-button cheritto-wordpress-importer-button-start" type="button" id="startjob" value="Start import job"/> or 
                    <input class="cheritto-wordpress-importer-button" type="button" id="canceljob" value="Cancel job"/>
                    <br/><hr/>
                <?php
                } else {

                    if ( $current_queue_stage=='') {

                        echo "<h2>" . __("Data import completed!",'cherittos-importer') . "</h2>";

                        echo "<p>" . __("You can now download all attachments, if necessary. Skip this step if you plan to manually copy the 'uploads' folder.",'cherittos-importer') . "</p>";
                        
                        /*
                        * Phase 4a: Attachments download
                        */ 
            
                        echo "<h2>" . __("Phase 4: attachments download",'cherittos-importer') . "</h2>";
                        ?>
                        <table>
                            <tbody>
                                <tr>
                                    <td class="cheritto-wordpress-importer-td-strategy-label">
                                        <label for="cheritto-wordpress-importer-attachment-strategy"><?php echo __("Download attachments:",'cherittos-importer'); ?></label>
                                    </td>
                                    <td>
                                        <input class="cheritto-wordpress-importer-button cheritto-wordpress-importer-button-start-download-queue" type="button" id="startqueue" value="Start download queue now"/> 
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        <p><i>
                        <?php echo __("Attachments download is optional if you plan to manually copy the 'uploads' folder to your server. 
                                If you choose to download attachments, a queue will be started and the job will be completed in background.<br/>
                                You will be able to monitor progress through this page. 
                                <br/><br/>Be sure to check this page to monitor the results, 
                                    especially if you have a lot of images to download.",'cherittos-importer'); ?>
                        </i></p>
                        or <input class="cheritto-wordpress-importer-button" type="button" id="canceljob" value="Close job"/> to clean up tables.
                        <br/><hr/>
                <?php
                    } else if ($current_queue_stage=="ended" && $current_thumbnails_queue_stage=="ended") 
                    {
                        ?>
                        <br/><hr/>
                        <?php echo "<h2>" . __("Data import and attachments download completed. You can now close the job to clean up tables.",'cherittos-importer') . "</h2>"; ?>
                        <input class="cheritto-wordpress-importer-button" type="button" id="canceljob" value="Close job"/>
                        <br/>
                        <?php
                    }

                } 
            }

            if ($current_stage=='check_files') {
                $report = file_get_contents( $jobs_dir . DIRECTORY_SEPARATOR . $current_job . "_check.html");

                $allowed = [ 'strong' => [], 'table' => [ 'class'=>[] ], 'thead' => [], 'th' => [], 'tbody' => [], 'tr' => [], 'td' => [], 'br' => [], 'h4' => [], 'p' => []
                                    ];
                echo wp_kses( $report, $allowed );
            }

        }
        ?>

        <div id="output"></div>
    </div>

<?php

    /*
     * Usage
     */ 

?>
    <div class="cheritto-wordpress-importer-right-container">

        <a href="#usage"><?php echo __("Usage",'cherittos-importer'); ?></a> | 
        <a href="#faq"><?php echo __("FAQ",'cherittos-importer'); ?></a>

        <a id="description"></a>
        <?php echo "<h3>" . __("Description",'cherittos-importer') . "</h3>"; ?>

        <p>
            <?php 
                echo __("<strong>Cheritto's Wordpress Importer</strong> is a tool that let you import data (posts, comments, pages, authors, tags, categories and custom post types exported with the export tool) from one or more Wordpress Exported xml files (wxr).",'cherittos-importer');
            ?>
        </p>

        <ul class="cheritto-wordpress-importer-description-list">
            <?php
                echo '<li>' . __("Files are <strong>uploaded in chunks</strong>, whose size is determined according to your current php settings, so you don't have to worry about them;",'cherittos-importer') . '</li>';
            ?>
            <?php
                echo '<li>' . __("file checking, import and attachments downloads are done <strong>asynchronously</strong>, so you don't have to worry if you close the page;",'cherittos-importer') . '</li>';
            ?>
             <?php
                echo '<li>' . __("files are <strong>processed in chunks</strong> so you don't have to worry about memory usage and large files;",'cherittos-importer') . '</li>';
            ?>
            <?php
                echo '<li>' . __("supports tag, category and authors import, avoiding content duplication.",'cherittos-importer') . '</li>';
            ?>
             <?php
                echo '<li>' . __("supports pause and resume of downloads and thumbnails generation.",'cherittos-importer') . '</li>';
            ?>
        </ul>

        <?php
            echo __("If you encounter issues using this plugin, please open a topic on the support forum on wordpress.org or email me: <a href='mailto:fiulita@gmail.com'>fiulita@gmail.com</a>.<br/>
            <br/>
            If you like this plugin, please consider leaving a feedback on the plugin page on wordpress.org and making a donation to support development: 
                <a href='https://www.paypal.com/paypalme/fiulita' target='_blank'>click to go to donation page</a>  - and if you choose to do so, a big thank you from my side, 
                    you are telling me that I was helpful and you are contributing to the quality of this plugin.");
        ?>

        <a id="usage"></a>
        <?php echo "<h3>" . __("Usage",'cherittos-importer') . "</h3>"; ?>
        

        <p>
            <?php echo __(" . <strong>Preparation</strong>: since this plugins deals with the content of your Wordpress installation, it is reccomended that you do a full backup of your database before importing anything. 
                                If something goes wrong, you will be able to restore the initial state. Anyway, keep in mind that the importer will only 'add' new content, it will never 'delete' or 'overwrite' anything.
                                The only thing it 'updates' at the end of the import is the 'count' columns that you find in <i>Posts -> Tags</i> and <i>Posts -> Categories</i> 
                                to reflect new inserted content from your xml files.
                                <br/><br/>
                            . <strong>Phase 1</strong>: upload one or more Wordpress Exported xml file(s) to the server. Please note that this is the only phase in which you can't reload the page until upload has finished.<br/><br/>
                            . <strong>Phase 2</strong>: check files. The importer will check files integrity and import data in temporary tables (nothing is imported in Wordpress tables during this phase).<br/><br/>
                            . <strong>Phase 3</strong>: import data. You can exclude posts with same title, date and type of existing posts from the import.<br/><br/>
                            . <strong>Phase 4</strong>: download attachments. The importer will start the download queue.<br/><br/>
                            . <strong>Phase 4a</strong>: generate thumbnails and other image sizes. The importer will start generating different image sizes for every imported image.<br/><br/>

                            In every stage, you can stop the importer by clicking on 'Cancel job' or 'Close job'. This will clean up all temporary data. Deactivating the plugin has the same effect, while uninstalling will also delete all tables.<br/><br/>
                            ",'cherittos-importer');
            ?>
        </p>

        <a href="#description"><?php echo __("Back to top",'cherittos-importer'); ?></a>

        <a id="faq"></a>
        <?php echo "<h3>" . __("FAQ",'cherittos-importer') . "</h3>"; ?>

        <p>
            <?php echo __("<strong>Q: Will the import alter in some way my posts, pages or any data in the current Wordpress installation?</strong>","cherittos-importer"); ?>
            <?php echo __("<br/><i>A: No, the plugin will only add new data.</i><br/><br/>","cherittos-importer"); ?>

            <?php echo __("<strong>Q: Can i import multiple files at once?</strong>","cherittos-importer"); ?>
            <?php echo __("<br/><i>A: Yes, as long as they belong to the same export job (usually, multiple files come from an export done via 'wp cli'). 
                If they do not come from the same export job, you will have to go through the import procedure for each xml file.
            </i><br/><br/>","cherittos-importer"); ?>

            <?php echo __("<strong>Q: Does the plugin duplicate my content?</strong>","cherittos-importer"); ?>
            <?php echo __("<br/><i>A: You can choose to import all as is or to check if posts or pages with same title, date and type already exist, in which case they
                won't be imported. In any case, authors, categories and tags will never be duplicated; only added if not existing.</i><br/><br/>","cherittos-importer"); ?>

            <?php echo __("<strong>Q: How are authors imported?</strong>","cherittos-importer"); ?>
            <?php echo __("<br/><i>A: The importer will create new users with role 'author' if no user exists with the same email or nicename. 
                A random password will be generated, authors have to reset this password to login.</i><br/><br/>","cherittos-importer"); ?>


            <?php echo __("<strong>Q: Why the import phase is faster than the checking one?</strong>","cherittos-importer"); ?>
            <?php echo __("<br/><i>A: During the check phase, all files are read and parsed, and data converted in database tables, 
                        while during the import phase, data is transferred only between database tables. The difference in speed is more evident with large file(s).
            </i><br/><br/>","cherittos-importer"); ?>

            <?php echo __("<strong>Q: Why the download phase is slow?</strong>","cherittos-importer"); ?>
            <?php echo __("<br/><i>A: During the download phase, the importer must make a request and save the image. Depending on the ability of the called server to handle requests, it may take a while to complete for a large number of images.
            </i><br/><br/>","cherittos-importer"); ?>

            <?php echo __("<strong>Q: The importer page says 'Check is running'. How can i be sure that the job is not stuck?</strong>","cherittos-importer"); ?>
            <?php echo __("<br/><i>A: The importer page uses and independent check to verify that the job is running and it does so at every page load, so if you see 'Check is running' it means that all is going fine. If this check fails for more than a minute, you will see a red warning.</i><br/><br/>","cherittos-importer"); ?>

            <?php echo __("<strong>Q: I see a red warning alerting me that the job is stuck. What should i do?</strong>","cherittos-importer"); ?>
            <?php echo __("<br/><i>A: Something has stopped the running job. It may be a server side issue (for example your hosting not allowing long running tasks) or an error. In either case, you should cancel the job clicking on 'Cancel job' and retry from start.
                            If the issue persists, open a topic on the support forum or email me: fiulita@gmail.com (i will likely need your xml file(s) in order to check the issue). 
                            </i><br/><br/>","cherittos-importer"); ?>

            
        <a href="#description"><?php echo __("Back to top",'cherittos-importer'); ?></a>
            
        </p>
        <hr/>

        <?php
            echo __("If you encounter issues using this plugin, please open a topic on the support forum on wordpress.org or email me: <a href='mailto:fiulita@gmail.com'>fiulita@gmail.com</a>.<br/>
            <br/>
            If you like this plugin, please consider leaving a feedback on the plugin page on wordpress.org and making a donation to support development: 
                <a href='https://www.paypal.com/paypalme/fiulita' target='_blank'>click to go to donation page</a>  - and if you choose to do so, a big thank you from my side, 
                    you are telling me that I was helpful and you are contributing to the quality of this plugin.");
        ?>


        

    </div>

    <div class="cheritto-wordpress-importer-clear"></div>

</div>


