<?php
if (!defined('PHPWG_ROOT_PATH')) {
  die('Hacking attempt!');
}

add_event_handler('ws_add_methods', 'familink_prints_ws_add_methods');

function familink_prints_ws_add_methods($arr)
{
  $service = &$arr[0];

  $service->addMethod(
    'familink.cart.get',
    'familink_prints_ws_cart_get',
    array(),
    'Get cart content for current user'
  );

  $service->addMethod(
    'familink.bridge.create',
    'familink_prints_ws_bridge_create',
    array(
      'ttl' => array('type' => WS_TYPE_INT, 'default' => 900),
    ),
    'Create temporary signed URLs for cart items'
  );

  return $arr;
}

function familink_prints_ws_cart_get($params, &$service)
{
  if (is_a_guest()) {
    return new PwgError(403, 'Auth required');
  }

  global $prefixeTable, $user;
  $uid = (int)$user['id'];

  $items = query2array('
SELECT c.image_id, c.print_format, c.copies, i.name, i.file, i.path
FROM ' . $prefixeTable . 'familink_cart_items c
JOIN ' . $prefixeTable . 'images i ON i.id = c.image_id
WHERE c.user_id=' . $uid . '
ORDER BY c.created_at DESC, c.id DESC
');

  return array('items' => $items);
}

function familink_prints_ws_bridge_create($params, &$service)
{
  if (is_a_guest()) {
    return new PwgError(403, 'Auth required');
  }

  global $prefixeTable, $user;
  $uid = (int)$user['id'];
  $ttl = max(60, min(3600, (int)$params['ttl']));

  $items = query2array('
SELECT c.image_id, c.print_format, c.copies, i.name, i.file, i.path
FROM ' . $prefixeTable . 'familink_cart_items c
JOIN ' . $prefixeTable . 'images i ON i.id = c.image_id
WHERE c.user_id=' . $uid . '
ORDER BY c.created_at DESC, c.id DESC
');

  $urls = array();

  foreach ($items as $it) {
    $token = familink_prints_token(64);
    $expires = date('Y-m-d H:i:s', time() + $ttl);

    pwg_query('
INSERT INTO ' . $prefixeTable . 'familink_bridge_tokens
(token, user_id, image_id, print_format, expires_at, created_at)
VALUES
(
  "' . pwg_db_real_escape_string($token) . '",
  ' . $uid . ',
  ' . (int)$it['image_id'] . ',
  "' . pwg_db_real_escape_string($it['print_format']) . '",
  "' . $expires . '",
  "' . familink_prints_now() . '"
)
');

    $urls[] = array(
      'image_id' => (int)$it['image_id'],
      'name' => (string)$it['name'],
      'file' => (string)$it['file'],
      'path' => (string)$it['path'],
      'format' => (string)$it['print_format'],
      'copies' => (int)$it['copies'],
      'url' => get_absolute_root_url() . 'plugins/' . FAMILINK_PRINTS_ID . '/bridge.php?token=' . $token,
      'expires_at' => $expires,
    );
  }

  return array('urls' => $urls);
}
