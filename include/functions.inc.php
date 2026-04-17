<?php
if (!defined('PHPWG_ROOT_PATH')) {
  die('Hacking attempt!');
}

function familink_prints_url($action, $params = array())
{
  $base = get_absolute_root_url() . 'index.php';
  $params = array_merge(array('familink_prints' => $action), $params);
  return add_url_params($base, $params);
}

function familink_prints_require_auth()
{
  if (is_a_guest() || familink_prints_user_id() <= 0) {
    access_denied();
  }
}

function familink_prints_user_id()
{
  global $user;

  if (is_a_guest()) {
    return 0;
  }

  return isset($user['id']) ? (int)$user['id'] : 0;
}

function familink_prints_now()
{
  return date('Y-m-d H:i:s');
}

function familink_prints_token($len = 64)
{
  return bin2hex(random_bytes($len / 2));
}

function familink_prints_valid_format($format)
{
  return in_array($format, array('10x15cm', '15x20cm'), true);
}

function familink_prints_purge_expired_tokens()
{
  global $prefixeTable;
  pwg_query('DELETE FROM ' . $prefixeTable . 'familink_bridge_tokens WHERE expires_at < "' . familink_prints_now() . '"');
}

function familink_prints_dispatch()
{
  // Récupération de l'action depuis ton système
  $action = '';

if (isset($_GET['familink_prints'])) {
  $action = $_GET['familink_prints'];
} elseif (isset($_GET['action'])) {
  $action = $_GET['action'];
}

  if (empty($action)) {
    familink_prints_page_cart();
    return;
  }

  switch ($action) {

    case 'add':
      familink_prints_action_add();
      break;

    case 'update':
      familink_prints_action_update();
      break;

    case 'remove':
      familink_prints_action_remove();
      break;

    case 'empty':
      familink_prints_action_empty();
      break;

    case 'checkout':
      familink_prints_page_checkout();
      break;

    default:
      familink_prints_page_cart();
  }
}

//function familink_prints_dispatch($action)
//{
//  familink_prints_purge_expired_tokens();
//
//  switch ($action) {
//    case 'add':
//      familink_prints_action_add();
//      break;
//    case 'remove':
//      familink_prints_action_remove();
//      break;
//    case 'update':
//      familink_prints_action_update();
//      break;
//    case 'checkout':
//      familink_prints_page_checkout();
//      break;
//    case 'cart':
//    default:
//      familink_prints_page_cart();
//      break;
//    case 'empty':
//      familink_prints_action_empty();
//      break;
//  }
//}

function familink_prints_action_add()
{
  familink_prints_require_auth();

  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    access_denied();
  }

  check_pwg_token();

  $uid = familink_prints_user_id();
  $image_id = (int)@$_POST['image_id'];
  $format = @$_POST['format'];

  if ($uid <= 0 || $image_id <= 0 || !familink_prints_valid_format($format)) {
    redirect(get_absolute_root_url());
  }

  global $prefixeTable;

  $sql = '
INSERT INTO ' . $prefixeTable . 'familink_cart_items
(user_id, image_id, print_format, copies, created_at)
VALUES
(' . $uid . ', ' . $image_id . ', "' . pwg_db_real_escape_string($format) . '", 1, "' . familink_prints_now() . '")
ON DUPLICATE KEY UPDATE copies = copies + 1
';
  pwg_query($sql);

$redirect = '';

// 1. Priorité au champ POST
if (!empty($_POST['redirect'])) {
  $redirect = $_POST['redirect'];
}
// 2. fallback sur HTTP_REFERER
elseif (!empty($_SERVER['HTTP_REFERER'])) {
  $redirect = $_SERVER['HTTP_REFERER'];
}

// 3. sécurité : uniquement URLs internes
if (!empty($redirect) && strpos($redirect, get_absolute_root_url()) === 0) {
  redirect($redirect);
}

// fallback final
redirect(familink_prints_url('cart'));

}

function familink_prints_action_remove()
{
  familink_prints_require_auth();

  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    access_denied();
  }

  check_pwg_token();

  $uid = familink_prints_user_id();

  $value = isset($_POST['remove_item']) ? $_POST['remove_item'] : '';
  $parts = explode('|', $value, 2);

  if (count($parts) !== 2) {
    redirect(familink_prints_url('cart'));
  }

  $image_id = (int)$parts[0];
  $format = $parts[1];

  if (!familink_prints_valid_format($format)) {
    redirect(familink_prints_url('cart'));
  }

  global $prefixeTable;
  pwg_query('
DELETE FROM ' . $prefixeTable . 'familink_cart_items
WHERE user_id=' . $uid . '
  AND image_id=' . $image_id . '
  AND print_format="' . pwg_db_real_escape_string($format) . '"
');

  redirect(familink_prints_url('cart'));
}

function familink_prints_action_update()
{
  familink_prints_require_auth();

  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    access_denied();
  }

  check_pwg_token();

  $uid = familink_prints_user_id();
  global $prefixeTable;

  $copiesArr = isset($_POST['copies']) ? $_POST['copies'] : array();
  $formatsArr = isset($_POST['formats']) ? $_POST['formats'] : array();

  foreach ($copiesArr as $key => $copies) {

    $parts = explode('|', $key, 2);
    if (count($parts) !== 2) continue;

    $image_id = (int)$parts[0];
    $old_format = $parts[1];

    $copies = (int)$copies;
    if ($copies < 1) $copies = 1;
    if ($copies > 99) $copies = 99;

    $new_format = isset($formatsArr[$key]) ? $formatsArr[$key] : $old_format;

    if (!familink_prints_valid_format($new_format)) {
      $new_format = $old_format;
    }

    // -------- FORMAT IDENTIQUE --------
    if ($new_format === $old_format) {

      $sql = '
        UPDATE ' . $prefixeTable . 'familink_cart_items
        SET copies = ' . (int)$copies . '
        WHERE user_id = ' . (int)$uid . '
          AND image_id = ' . (int)$image_id . '
          AND print_format = \'' . pwg_db_real_escape_string($old_format) . '\'
      ';

      pwg_query($sql);

    } else {

      // -------- FORMAT CHANGE --------

      $sql = '
        SELECT id, copies
        FROM ' . $prefixeTable . 'familink_cart_items
        WHERE user_id = ' . (int)$uid . '
          AND image_id = ' . (int)$image_id . '
          AND print_format = \'' . pwg_db_real_escape_string($new_format) . '\'
      ';

      $exists = query2array($sql);

      if (!empty($exists)) {

        // -------- FUSION --------
        $newCopies = (int)$exists[0]['copies'] + (int)$copies;

        $sql = '
          UPDATE ' . $prefixeTable . 'familink_cart_items
          SET copies = ' . (int)$newCopies . '
          WHERE id = ' . (int)$exists[0]['id'] . '
        ';
        pwg_query($sql);

        $sql = '
          DELETE FROM ' . $prefixeTable . 'familink_cart_items
          WHERE user_id = ' . (int)$uid . '
            AND image_id = ' . (int)$image_id . '
            AND print_format = \'' . pwg_db_real_escape_string($old_format) . '\'
        ';
        pwg_query($sql);

      } else {

        // -------- UPDATE SIMPLE --------
        $sql = '
          UPDATE ' . $prefixeTable . 'familink_cart_items
          SET print_format = \'' . pwg_db_real_escape_string($new_format) . '\',
              copies = ' . (int)$copies . '
          WHERE user_id = ' . (int)$uid . '
            AND image_id = ' . (int)$image_id . '
            AND print_format = \'' . pwg_db_real_escape_string($old_format) . '\'
        ';
        pwg_query($sql);
      }
    }
  }

  redirect(familink_prints_url('cart'));
}

function familink_prints_action_empty()
{
  familink_prints_require_auth();

  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    access_denied();
  }

  check_pwg_token();

  global $prefixeTable;
  $uid = familink_prints_user_id();

  pwg_query('
    DELETE FROM ' . $prefixeTable . 'familink_cart_items
    WHERE user_id = ' . (int)$uid . '
  ');

  redirect(familink_prints_url('cart'));
}

function familink_prints_page_cart()
{
  familink_prints_require_auth();

  global $template, $prefixeTable, $page;

  $uid = familink_prints_user_id();

  $items = query2array('
SELECT c.image_id, c.print_format, c.copies, i.name, i.file, i.path
FROM ' . $prefixeTable . 'familink_cart_items c
JOIN ' . $prefixeTable . 'images i ON i.id = c.image_id
WHERE c.user_id=' . $uid . '
ORDER BY c.created_at DESC, c.id DESC
');

$total_photos = 0;

foreach ($items as $it) {
  $total_photos += (int)$it['copies'];
}

  $page['title'] = 'Panier Familink';

  $template->set_filename(
    'familink_cart_content',
    realpath(FAMILINK_PRINTS_PATH . 'templates/cart_page.tpl')
  );

foreach ($items as &$item) {
  $relativePath = isset($item['path']) ? (string)$item['path'] : '';

  // Piwigo stocke souvent les chemins avec "./"
  if (strpos($relativePath, './') === 0) {
    $relativePath = substr($relativePath, 2);
  }

  $item['thumb_url'] = get_absolute_root_url() . ltrim($relativePath, '/');
}
unset($item);

$template->assign(array(
  'FAMILINK_ITEMS' => $items,
  'FAMILINK_TOTAL_PHOTOS' => $total_photos,
  'FAMILINK_UPDATE_URL' => familink_prints_url('update'),
  'FAMILINK_REMOVE_URL' => familink_prints_url('remove'),
  'FAMILINK_CHECKOUT_URL' => familink_prints_url('checkout'),
  'FAMILINK_EMPTY_URL' => familink_prints_url('empty'),
  'FAMILINK_CSRF' => get_pwg_token(),
));

//$template->assign(array(
//  'FAMILINK_ITEMS' => $items,
//  'FAMILINK_UPDATE_URL' => familink_prints_url('update'),
//  'FAMILINK_REMOVE_URL' => familink_prints_url('remove'),
//  'FAMILINK_CHECKOUT_URL' => familink_prints_url('checkout'),
//  'FAMILINK_CSRF' => get_pwg_token(),
//));

  $template->assign_var_from_handle('CONTENT', 'familink_cart_content');
}

function familink_prints_page_checkout()
{
  familink_prints_require_auth();

  global $template, $prefixeTable, $page;

  $uid = familink_prints_user_id();

  $count = pwg_db_fetch_assoc(pwg_query('
SELECT COUNT(*) AS cnt
FROM ' . $prefixeTable . 'familink_cart_items
WHERE user_id=' . $uid
  ));
  $cart_count = (int)$count['cnt'];

  $page['title'] = 'Commande Familink';

  $template->set_filename(
    'familink_checkout_content',
    realpath(FAMILINK_PRINTS_PATH . 'templates/checkout_page.tpl')
  );

  $template->assign(array(
    'FAMILINK_WS_URL' => get_absolute_root_url() . 'ws.php?format=json',
    'FAMILINK_PLUGIN_BASE' => get_absolute_root_url() . 'plugins/' . FAMILINK_PRINTS_ID . '/',
    'FAMILINK_CART_URL' => familink_prints_url('cart'),
    'FAMILINK_HAS_ITEMS' => $cart_count > 0 ? 1 : 0,
  ));

  $template->assign_var_from_handle('CONTENT', 'familink_checkout_content');
}
