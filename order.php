<?php
define('PHPWG_ROOT_PATH', dirname(dirname(dirname(__FILE__))) . '/');
include_once(PHPWG_ROOT_PATH . 'include/common.inc.php');

global $user;
global $conf;

header('Content-Type: application/json; charset=utf-8');

function familink_json_response($data, $statusCode = 200)
{
  http_response_code($statusCode);
  echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}

function familink_shutdown_handler()
{
  $err = error_get_last();
  if ($err && in_array($err['type'], array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR), true)) {
    http_response_code(500);
    echo json_encode(array(
      'error' => 'Fatal PHP error',
      'message' => $err['message'],
      'file' => $err['file'],
      'line' => $err['line'],
    ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
  }
}

register_shutdown_function('familink_shutdown_handler');

try {
  if (is_a_guest()) {
    familink_json_response(array('error' => 'Auth required'), 403);
  }

  //$configFile = __DIR__ . '/familink_config.local.php';
  //if (!file_exists($configFile)) {
  //  familink_json_response(array('error' => 'Missing familink_config.local.php'), 500);
  //}

  //$cfg = include $configFile;

  //if (!is_array($cfg)) {
  //  familink_json_response(array('error' => 'familink_config.local.php must return an array'), 500);
  //}

  if (empty((isset($conf['familink_api_token']) ? $conf['familink_api_token'] : '')) || empty($conf['familink_endpoint'])) {
    familink_json_response(array('error' => 'Invalid Familink config'), 500);
  }

  if (!function_exists('curl_init')) {
    familink_json_response(array('error' => 'cURL extension is not available in PHP'), 500);
  }

  $inputRaw = file_get_contents('php://input');
  if ($inputRaw === false) {
    familink_json_response(array('error' => 'Cannot read request body'), 400);
  }

  $input = json_decode($inputRaw, true);
  if (!is_array($input)) {
    familink_json_response(array(
      'error' => 'Invalid JSON body',
      'raw' => $inputRaw,
      'json_error' => json_last_error_msg(),
    ), 400);
  }

  $recipient = isset($input['recipient']) && is_array($input['recipient']) ? $input['recipient'] : array();
  $photos = isset($input['photos']) && is_array($input['photos']) ? $input['photos'] : array();
  $enveloppe = !empty($input['enveloppe']) ? $input['enveloppe'] : 'auto';
  $finish = !empty($input['finish']) ? (string)$input['finish'] : 'glossy';
  if (!in_array($finish, array('glossy', 'matte'), true)) {
    $finish = 'glossy';
  }

  $requiredRecipient = array('first_name', 'last_name', 'address_1', 'city', 'postal_or_zip_code', 'country_code');
  foreach ($requiredRecipient as $field) {
    if (empty($recipient[$field])) {
      familink_json_response(array(
        'error' => 'Missing recipient field',
        'field' => $field,
      ), 400);
    }
  }

  if (count($photos) < 1) {
    familink_json_response(array('error' => 'No photos to order'), 400);
  }

  $familinkPhotos = array();

  foreach ($photos as $p) {
    $url = isset($p['url']) ? trim((string)$p['url']) : '';
    $format = isset($p['format']) ? (string)$p['format'] : '';
    $copies = isset($p['copies']) ? (int)$p['copies'] : 1;

    if ($url === '') {
      continue;
    }

    if (!in_array($format, array('10x15cm', '15x20cm'), true)) {
      continue;
    }

    $copies = max(1, min(99, $copies));

    $familinkPhotos[] = array(
    'url' => $url,
    'copies' => $copies,
    'format' => $format,
    'finish' => $finish,
    );
  }

  if (count($familinkPhotos) < 1) {
    familink_json_response(array('error' => 'No valid photos'), 400);
  }

  $userId = isset($user['id']) ? (int)$user['id'] : 0;

  $payload = array(
    'sandbox' => !empty($conf['sandbox']),
    'recipient' => array(
      'company' => isset($recipient['company']) ? (string)$recipient['company'] : '',
      'first_name' => (string)$recipient['first_name'],
      'last_name' => (string)$recipient['last_name'],
      'address_1' => (string)$recipient['address_1'],
      'address_2' => isset($recipient['address_2']) ? (string)$recipient['address_2'] : '',
      'city' => (string)$recipient['city'],
      'postal_or_zip_code' => (string)$recipient['postal_or_zip_code'],
      'state' => isset($recipient['state']) ? (string)$recipient['state'] : '',
      'country_code' => strtoupper((string)$recipient['country_code']),
    ),
    'merchant_reference' => 'piwigo-' . date('Ymd-His') . '-u' . $userId,
    'enveloppe' => $enveloppe,
    'photos' => $familinkPhotos,
  );

  $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  if ($payloadJson === false) {
    familink_json_response(array(
      'error' => 'json_encode failed for payload',
      'json_error' => json_last_error_msg(),
      'payload_preview' => $payload,
    ), 500);
  }

  $ch = curl_init();
  curl_setopt_array($ch, array(
    CURLOPT_URL => $conf['familink_endpoint'],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => array(
      'Authorization: Token ' . (isset($conf['familink_api_token']) ? $conf['familink_api_token'] : ''),
      'Content-Type: application/json',
    ),
    CURLOPT_POSTFIELDS => $payloadJson,
    CURLOPT_TIMEOUT => 60,
  ));

  $response = curl_exec($ch);
  $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $curlError = curl_error($ch);
  curl_close($ch);

  if ($response === false) {
    familink_json_response(array(
      'error' => 'Curl error',
      'detail' => $curlError,
    ), 500);
  }

  if ($response === '' || $response === null) {
    familink_json_response(array(
      'error' => 'Empty response from Familink',
      'http_code' => $httpCode,
      'payload_sent' => $payload,
    ), 502);
  }

  $decoded = json_decode($response, true);
  if (json_last_error() === JSON_ERROR_NONE) {
    http_response_code($httpCode);
    echo json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
  }

  familink_json_response(array(
    'error' => 'Familink returned non-JSON response',
    'http_code' => $httpCode,
    'raw_response' => $response,
    'payload_sent' => $payload,
  ), 502);

} catch (Throwable $e) {
  familink_json_response(array(
    'error' => 'Unhandled exception',
    'message' => $e->getMessage(),
    'file' => $e->getFile(),
    'line' => $e->getLine(),
  ), 500);
}
