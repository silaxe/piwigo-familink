<?php
defined('PHPWG_ROOT_PATH') or die('Hacking attempt!');

global $template, $page, $conf;

load_language('plugin.lang', dirname(__FILE__) . '/');

$page['title'] = 'Familink Prints - Configuration';

$msg = null;
$msg_type = 'infos';

// ----------------------------
// SAUVEGARDE CONFIGURATION
// ----------------------------
if (isset($_POST['submit'])) {

  check_pwg_token();

  $api_token = trim($_POST['api_token'] ?? '');
  $sandbox = !empty($_POST['sandbox']) ? 1 : 0;
  $endpoint = $_POST['endpoint'] ?? '';

  conf_update_param('familink_api_token', $api_token);
  conf_update_param('familink_sandbox', $sandbox);
  conf_update_param('familink_endpoint', $endpoint);

// 🔥 FORCER relecture config
load_conf_from_db();

  $msg = 'Configuration enregistrée';
}

// ----------------------------
// TEST API
// ----------------------------
if (isset($_POST['test_api'])) {

  check_pwg_token();

  $api_token = trim($_POST['api_token'] ?? ($conf['familink_api_token'] ?? ''));
  $sandbox = !empty($_POST['sandbox']) ? 1 : 0;

  if (empty($api_token)) {

    $msg = 'API token vide';
    $msg_type = 'errors';

  } else {

    $endpoint = 'https://web.familinkframe.com/api/prints/external-order';

    $payload_array = array(
      'recipient' => array(
        'first_name' => 'Test',
        'last_name' => 'User',
        'address_1' => 'Test address',
        'city' => 'Test',
        'postal_or_zip_code' => '00000',
        'country_code' => 'FR'
      ),
      'photos' => array(),
      'sandbox' => (bool)$sandbox
    );

    $payload = json_encode($payload_array, JSON_PRETTY_PRINT);

    $ch = curl_init($endpoint);

    // ---- LOG CURL ----
    $verbose = fopen('php://temp', 'w+');

    curl_setopt_array($ch, array(
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_POST => true,
      CURLOPT_HTTPHEADER => array(
        'Authorization: Token ' . $api_token,
        'Content-Type: application/json'
      ),
      CURLOPT_POSTFIELDS => $payload,
      CURLOPT_TIMEOUT => 15,
      CURLOPT_VERBOSE => true,
      CURLOPT_STDERR => $verbose
    ));

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    rewind($verbose);
    $curl_log = stream_get_contents($verbose);

    if (curl_errno($ch)) {

      $msg = '❌ Erreur cURL : ' . curl_error($ch)
        . '<br><pre class="curl-log">' . htmlspecialchars($curl_log) . '</pre>';

      $msg_type = 'errors';

    } else {

      if ($http_code >= 200 && $http_code < 300) {

        $msg = '
<div class="api-debug">

  <div class="debug-card">
    <h3>✅ API OK (HTTP ' . $http_code . ')</h3>
  </div>

  <div class="debug-card">
    <h3>📤 Payload envoyé</h3>
    <pre class="curl-log">'
      . htmlspecialchars($payload) .
    '</pre>
  </div>

  <div class="debug-card">
    <h3>📥 Réponse API</h3>
    <pre class="curl-log">'
      . htmlspecialchars($response) .
    '</pre>
  </div>

  <div class="debug-card">
    <h3>🧾 Log cURL</h3>
    <pre class="curl-log curl-log-large">'
      . htmlspecialchars($curl_log) .
    '</pre>
  </div>

</div>';

        $msg_type = 'infos';

      } else {

$msg = '
<div class="api-debug">

  <div class="debug-card">
    <h3>❌ API erreur (HTTP ' . $http_code . ')</h3>
  </div>

  <div class="debug-card">
    <h3>📤 Payload envoyé</h3>
    <pre class="curl-log">'
      . htmlspecialchars($payload) .
    '</pre>
  </div>

  <div class="debug-card">
    <h3>📥 Réponse API</h3>
    <pre class="curl-log">'
      . htmlspecialchars($response) .
    '</pre>
  </div>

  <div class="debug-card">
    <h3>🧾 Log cURL</h3>
    <pre class="curl-log curl-log-large">'
      . htmlspecialchars($curl_log) .
    '</pre>
  </div>

</div>';

        $msg_type = 'errors';
      }
    }

    curl_close($ch);
  }
}

// ----------------------------
// VALEURS ACTUELLES
// ----------------------------

$api_token = isset($conf['familink_api_token']) ? $conf['familink_api_token'] : '';
$sandbox = !empty($conf['familink_sandbox']);

$template->assign(array(
  'FAMILINK_API_TOKEN' => $api_token,
  'FAMILINK_SANDBOX'   => $sandbox,
  'FAMILINK_MSG'       => $msg,
  'FAMILINK_MSG_TYPE'  => $msg_type,
  'FAMILINK_ACTION'    => get_root_url() . 'admin.php?page=plugin-familink_prints',
  'PWG_TOKEN'          => get_pwg_token(),
));

$template->set_filename('plugin_admin_content', dirname(__FILE__) . '/templates/admin.tpl');
$template->assign_var_from_handle('ADMIN_CONTENT', 'plugin_admin_content');
