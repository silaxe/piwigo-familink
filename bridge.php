<?php
define('PHPWG_ROOT_PATH', dirname(dirname(dirname(__FILE__))) . '/');
include_once(PHPWG_ROOT_PATH . 'include/common.inc.php');

header_remove('X-Powered-By');

/**
 * Safe debug logger for bridge diagnostics.
 * Writes to the system temp dir to avoid plugin-folder permission issues.
 */
function familink_bridge_log($message)
{
  $logFile = sys_get_temp_dir() . '/familink_bridge.log';
  @file_put_contents($logFile, '[' . date('c') . '] ' . $message . "\n", FILE_APPEND);
}

if (!isset($_GET['token']) || strlen($_GET['token']) < 32) {
  familink_bridge_log('Missing or invalid token parameter');
  http_response_code(400);
  echo 'Missing token';
  exit;
}

$token = $_GET['token'];

global $prefixeTable;

$row = pwg_db_fetch_assoc(pwg_query('
SELECT user_id, image_id, expires_at
FROM ' . $prefixeTable . 'familink_bridge_tokens
WHERE token="' . pwg_db_real_escape_string($token) . '"
LIMIT 1
'));

if (!$row) {
  familink_bridge_log('Invalid token: ' . $token);
  http_response_code(404);
  echo 'Invalid token';
  exit;
}

if (strtotime($row['expires_at']) < time()) {
  familink_bridge_log('Expired token for image_id=' . (int)$row['image_id'] . ', token=' . $token);
  http_response_code(410);
  echo 'Expired';
  exit;
}

$image_id = (int)$row['image_id'];

$img = pwg_db_fetch_assoc(pwg_query('
SELECT id, path, file
FROM ' . $prefixeTable . 'images
WHERE id=' . $image_id . '
LIMIT 1
'));

if (!$img) {
  familink_bridge_log('Image not found in DB for image_id=' . $image_id);
  http_response_code(404);
  echo 'Image not found';
  exit;
}

$relativePath = (string)$img['path'];
$full = PHPWG_ROOT_PATH . ltrim($relativePath, '/');

if (!is_file($full) || !is_readable($full)) {
  familink_bridge_log(
    'File missing or unreadable for image_id=' . $image_id .
    ', relative_path=' . $relativePath .
    ', full_path=' . $full
  );
  http_response_code(404);
  echo 'File missing';
  exit;
}

$filename = basename((string)$img['file']);
$filesize = @filesize($full);
if ($filesize === false) {
  $filesize = 0;
}

$mime = 'application/octet-stream';
if (function_exists('finfo_open')) {
  $finfo = finfo_open(FILEINFO_MIME_TYPE);
  if ($finfo) {
    $detected = finfo_file($finfo, $full);
    if ($detected !== false && is_string($detected) && $detected !== '') {
      $mime = $detected;
    }
    finfo_close($finfo);
  }
} else {
  $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
  if (in_array($ext, array('jpg', 'jpeg'), true)) {
    $mime = 'image/jpeg';
  } elseif ($ext === 'png') {
    $mime = 'image/png';
  } elseif ($ext === 'webp') {
    $mime = 'image/webp';
  } elseif ($ext === 'gif') {
    $mime = 'image/gif';
  } elseif ($ext === 'tif' || $ext === 'tiff') {
    $mime = 'image/tiff';
  }
}

// Try to read image dimensions for diagnostics.
$width = null;
$height = null;
$imageInfo = @getimagesize($full);
if (is_array($imageInfo)) {
  $width = isset($imageInfo[0]) ? (int)$imageInfo[0] : null;
  $height = isset($imageInfo[1]) ? (int)$imageInfo[1] : null;
}

// Helpful diagnostics: confirms exactly what Familink can fetch.
familink_bridge_log(
  'Serving image_id=' . $image_id .
  ', token=' . $token .
  ', relative_path=' . $relativePath .
  ', full_path=' . $full .
  ', filename=' . $filename .
  ', mime=' . $mime .
  ', bytes=' . $filesize .
  ', width=' . ($width !== null ? $width : 'unknown') .
  ', height=' . ($height !== null ? $height : 'unknown') .
  ', user_id=' . (int)$row['user_id'] .
  ', expires_at=' . $row['expires_at']
);

header('Content-Type: ' . $mime);
if ($filesize > 0) {
  header('Content-Length: ' . $filesize);
}
header('Content-Disposition: inline; filename="' . str_replace('"', '', $filename) . '"');
header('Cache-Control: private, max-age=0, no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Optional diagnostic headers, useful in browser/network inspector.
header('X-Familink-Bridge-Image-Id: ' . $image_id);
if ($width !== null && $height !== null) {
  header('X-Familink-Bridge-Dimensions: ' . $width . 'x' . $height);
}

$fp = fopen($full, 'rb');
if ($fp === false) {
  familink_bridge_log('Failed to fopen file for image_id=' . $image_id . ', path=' . $full);
  http_response_code(500);
  echo 'Cannot open file';
  exit;
}

while (!feof($fp)) {
  $chunk = fread($fp, 8192);
  if ($chunk === false) {
    fclose($fp);
    familink_bridge_log('Failed during fread for image_id=' . $image_id . ', path=' . $full);
    http_response_code(500);
    echo 'Read error';
    exit;
  }
  echo $chunk;
}

fclose($fp);
exit;
