const { __, _x, _n, _nx } = wp.i18n;

jQuery(document).ready(function(){

    jQuery('#canceljob.cheritto-wordpress-importer-button').click(function(){ 
            let confirmation = confirm(__('Are you sure? All uploaded files will be deleted from the server.','cheritto-wordpress-importer')); 
            if (confirmation===true) {

                jQuery('.cheritto-wordpress-importer-button').prop("disabled",true);

                var data = {
                    'action': 'cheritto_wordpress_importer_cancel_job'
                };

                jQuery.post( ajaxurl, data, function(response) {
                    alert(__("Job has been successfully closed.",'cheritto-wordpress-importer'));
                    location.reload();
                }).fail(
                    function() {
                        alert(__("There was an error handling your request. Please reload the page.",'cheritto-wordpress-importer'));
                    }
                );
            } else {
                alert(__("Canceled"));
            }
    });

    jQuery('#checkfiles.cheritto-wordpress-importer-button').click(function(){ 
            let confirmation = confirm(__('The importer will now check the uploaded files.','cheritto-wordpress-importer')); 
            if (confirmation===true) {

                jQuery('#pickfiles.cheritto-wordpress-importer-button').prop("disabled",true);
                jQuery('#checkfiles.cheritto-wordpress-importer-button').prop("disabled",true);
                jQuery("#checkfiles.cheritto-wordpress-importer-button").val("Checking...");
                jQuery('#startjob.cheritto-wordpress-importer-button').prop("disabled",true);

                var output = document.getElementById("output");
                let row = document.createElement("div");
                row.style.marginTop='10px';
                row.innerHTML = __("<strong>You can reload the page to monitor progress.</strong>",'cheritto-wordpress-importer');
                output.appendChild(row);

                var data = {
                    'action': 'cheritto_wordpress_importer_check_files'
                };             

                jQuery.post( ajaxurl, data, function(response) {
                    //var output = document.getElementById("output");
                    //let row = document.createElement("div");
                    //row.innerHTML = response;
                    //output.appendChild(row);
                    //location.reload();
                }).fail(
                    function() {
                        alert(__("There was an error handling your request. Please reload the page.",'cheritto-wordpress-importer'));
                    }
                );
            } else {
                alert(__("Check canceled."));
            }
    });

    jQuery('#startqueue.cheritto-wordpress-importer-button').click(function(){ 
        let confirmation = confirm(__('The importer will now download all attachments in background. You can check later the status of the queue by reloading the current page.','cheritto-wordpress-importer')); 
        if (confirmation===true) {

            var data = {
                'action': 'cheritto_wordpress_importer_start_download_queue'
            };

            jQuery("#startqueue.cheritto-wordpress-importer-button").prop("disabled",true);
            jQuery("#startqueue.cheritto-wordpress-importer-button").val("Downloading...");

            var output = document.getElementById("output");
            let row = document.createElement("div");
            row.style.marginTop='10px';
            row.innerHTML = __("<strong>You can reload the page to monitor progress.</strong>",'cheritto-wordpress-importer','cheritto-wordpress-importer');
            output.appendChild(row);

            jQuery.post( ajaxurl, data, function(response) {
                
            }).fail(
                function() {
                    alert(__("There was an error handling your request. Please reload the page.",'cheritto-wordpress-importer'));
                }
            );
        } else {
            alert(__("Queue canceled."));
        }
    });

    jQuery('#pausequeue.cheritto-wordpress-importer-button').click(function(){ 
        let confirmation = confirm(__('The importer will now puase the download queue. You can resume it later.','cheritto-wordpress-importer')); 
        if (confirmation===true) {

            var data = {
                'action': 'cheritto_wordpress_importer_pause_download_queue'
            };

            jQuery.post( ajaxurl, data, function(response) {
                jQuery("#pausequeue.cheritto-wordpress-importer-button").prop("disabled",true);
                jQuery("#pausequeue.cheritto-wordpress-importer-button").val("Paused.");
            }).fail(
                function() {
                    alert(__("There was an error handling your request. Please reload the page.",'cheritto-wordpress-importer'));
                }
            );
        } else {
            alert(__("Pause canceled."));
        }
    });

    jQuery('#startjob.cheritto-wordpress-importer-button').click(function(){ 
        let confirmation = confirm(__('The importer will now import all data.','cheritto-wordpress-importer')); 
        if (confirmation===true) {

            jQuery("#startjob.cheritto-wordpress-importer-button").prop("disabled",true);
            jQuery("#startjob.cheritto-wordpress-importer-button").val("Importing data...");

            var output = document.getElementById("output");
            let row = document.createElement("div");
            row.style.marginTop='10px';
            row.innerHTML = __("<strong>You can reload the page to monitor progress.</strong>",'cheritto-wordpress-importer');
            output.appendChild(row);

            var data = {
                'action': 'cheritto_wordpress_importer_start_data_import',
                'duplicate_posts_strategy':  jQuery('input[name="cheritto-wordpress-importer-duplicate-posts-strategy[]"]:checked').val(),
                'duplicate_terms_strategy': jQuery('input[name="cheritto-wordpress-importer-duplicate-terms-strategy[]"]:checked').val()
            };

            jQuery.post( ajaxurl, data, function(response) {
                
            }).fail(
                function() {
                    alert(__("There was an error handling your request. Please reload the page.",'cheritto-wordpress-importer'));
                }
            );
        } else {
            alert(__("Import canceled.",'cheritto-wordpress-importer'));
        }
    });

    jQuery('#startthumbnailsqueue.cheritto-wordpress-importer-button').click(function(){ 
        let confirmation = confirm(__('The importer will now generate all the thumbnails in background. You can check later the status of the queue by reloading the current page.','cheritto-wordpress-importer')); 
        if (confirmation===true) {

            var data = {
                'action': 'cheritto_wordpress_importer_start_thumbnails_queue'
            };

            jQuery("#startthumbnailsqueue.cheritto-wordpress-importer-button").prop("disabled",true);
            jQuery("#startthumbnailsqueue.cheritto-wordpress-importer-button").val("Generating thumbnails...");

            var output = document.getElementById("output");
            let row = document.createElement("div");
            row.style.marginTop='10px';
            row.innerHTML = __("<strong>You can reload the page to monitor progress.</strong>",'cheritto-wordpress-importer');
            output.appendChild(row);

            jQuery.post( ajaxurl, data, function(response) {
                
            }).fail(
                function() {
                    alert(__("There was an error handling your request. Please reload the page.",'cheritto-wordpress-importer'));
                }
            );
        } else {
            alert(__("Queue canceled."));
        }
    });

    jQuery('#pausethumbnailsqueue.cheritto-wordpress-importer-button').click(function(){ 
        let confirmation = confirm(__('The importer will now puase the thumbnails generator queue. You can resume it later.','cheritto-wordpress-importer')); 
        if (confirmation===true) {

            var data = {
                'action': 'cheritto_wordpress_importer_pause_thumbnails_queue'
            };

            jQuery.post( ajaxurl, data, function(response) {
                jQuery("#pausethumbnailsqueue.cheritto-wordpress-importer-button").prop("disabled",true);
                jQuery("#pausethumbnailsqueue.cheritto-wordpress-importer-button").val("Paused.");
            }).fail(
                function() {
                    alert(__("There was an error handling your request. Please reload the page.",'cheritto-wordpress-importer'));
                }
            );
        } else {
            alert(__("Pause canceled.",'cheritto-wordpress-importer'));
        }
    });

   

});