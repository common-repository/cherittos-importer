<?php

/** WordPress Administration Bootstrap */
require_once __DIR__ . '/../../../../wp-admin/admin.php';

require_once __DIR__ . '/class-cheritto-wordpress-importer-parser.php';

/*
 * Check permissions
 */ 
if (!current_user_can('import')) {
  http_response_code(403);
  die();
}

// Helper
function verbose ($ok=1, $info="") {
  if ($ok==0) { http_response_code(400); }
  exit(json_encode(["ok"=>$ok, "info"=>$info]));
}

if (empty($_FILES) || $_FILES["file"]["error"]) {
  verbose(0, __("Failed to move uploaded file.",'cheritto-wordpress-importer'));
}

$current_job = get_option('cheritto-wordpress-importer-current-job');
$current_job_path = get_option('cheritto-wordpress-importer-current-job-path');

// No current job here? Something's off
if (!$current_job) {
  http_response_code(500);
  die();
}

$filePath = $current_job_path . DIRECTORY_SEPARATOR . $current_job;

if (!file_exists($filePath)) { 
  if (!mkdir($filePath, 0777, true)) {
    verbose(0, __("Failed to create",'cheritto-wordpress-importer'). " " . $filePath);
  }
}

$fileName = isset($_REQUEST["name"]) ? sanitize_file_name($_REQUEST["name"]) : sanitize_file_name($_FILES["file"]["name"]);
$filePath = $filePath . DIRECTORY_SEPARATOR . $fileName;

/*
 * Chunks
 */ 
$chunk  = isset($_REQUEST["chunk"]) ? intval($_REQUEST["chunk"]) : 0;
$chunks = isset($_REQUEST["chunks"]) ? intval($_REQUEST["chunks"]) : 0;
$out    = @fopen("{$filePath}.part", $chunk == 0 ? "wb" : "ab");
if ($out) {
  $in = @fopen($_FILES["file"]["tmp_name"], "rb");
  if ($in) { while ($buff = fread($in, 4096)) { fwrite($out, $buff); } }
  else { verbose(0, __("Failed to open input stream",'cheritto-wordpress-importer')); }
  @fclose($in);
  @fclose($out);
  @unlink($_FILES["file"]["tmp_name"]);
} else { 
  verbose(0, __("Failed to open output stream",'cheritto-wordpress-importer')); 
}

/*
 * Upload complete
 */ 
if (!$chunks || $chunk == $chunks - 1) { 
  rename("{$filePath}.part", $filePath);
  update_option('cheritto-wordpress-importer-current-job-stage','first_upload_complete');
}
verbose(1, __("Upload OK",'cheritto-wordpress-importer'));