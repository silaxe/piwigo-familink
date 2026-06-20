<?php
/*
Version: 1.0.2
Plugin Name: Familink Prints
Author: You
Description: Piwigo cart + temporary URLs + Familink sandbox checkout for 10x15 / 15x20 prints,
             avec ajout automatique de bords blancs pour les photos qui ne sont pas exactement
             au format demandé.
Has Settings: true
*/

if (!defined('PHPWG_ROOT_PATH')) {
  die('Hacking attempt!');
}

define('FAMILINK_PRINTS_ID', basename(dirname(__FILE__)));
define('FAMILINK_PRINTS_PATH', PHPWG_PLUGINS_PATH . FAMILINK_PRINTS_ID . '/');

include_once(FAMILINK_PRINTS_PATH . 'include/functions.inc.php');
include_once(FAMILINK_PRINTS_PATH . 'include/image.inc.php');
include_once(FAMILINK_PRINTS_PATH . 'include/api.inc.php');

add_event_handler('loc_begin_picture', 'familink_prints_picture_hook');
add_event_handler('init', 'familink_prints_handle_actions');
add_event_handler('loc_begin_page_header', 'familink_prints_handle_pages');
add_event_handler('get_admin_plugin_menu_links', 'familink_admin_menu');

function familink_admin_menu($menu)
{
  $menu[] = array(
    'NAME' => 'Familink Prints',
    'URL'  => get_root_url() . 'admin.php?page=plugin-familink_prints'
  );
  return $menu;
}

function familink_prints_picture_hook()
{
  global $template, $page;

  if (empty($page['image_id'])) {
    return;
  }

  $image_id = (int)$page['image_id'];

  $template->set_filename(
    'familink_photo_button',
    realpath(FAMILINK_PRINTS_PATH . 'templates/photo_button.tpl')
  );

  $template->assign(array(
    'FAMILINK_ADD_URL' => familink_prints_url('add'),
    'FAMILINK_IMAGE_ID' => $image_id,
    'FAMILINK_CART_URL' => familink_prints_url('cart'),
    // Correctif : la virgule remplaçait par erreur le "=>", ce qui faisait que
    // cette clé n'était jamais réellement assignée au template.
    'FAMILINK_RETURN_URL' => get_absolute_root_url() . ltrim($_SERVER['REQUEST_URI'], '/'),
    'PWG_TOKEN' => get_pwg_token(),
  ));

  $template->append('PLUGIN_PICTURE_BUTTONS', $template->parse('familink_photo_button', true));
}

/**
 * Hook 'init' : ne traite QUE les actions qui modifient des données
 * (ajout / suppression / mise à jour / vidage du panier). Ces actions se
 * terminent toujours par un redirect(), qui appelle exit() : on n'a donc
 * jamais besoin du cycle de rendu de page Piwigo pour elles, et il n'y a
 * aucun risque de double traitement avec familink_prints_handle_pages()
 * ci-dessous (qui ne gère plus que les pages d'affichage).
 */
function familink_prints_handle_actions()
{
  if (empty($_GET['familink_prints'])) {
    return;
  }

  $action = $_GET['familink_prints'];

  if (in_array($action, array('add', 'update', 'remove', 'empty'), true)) {
    familink_prints_purge_expired_tokens();
    familink_prints_dispatch($action);
    // Sécurité : familink_prints_dispatch() se termine toujours par un
    // redirect() qui fait exit(), mais on l'explicite ici pour éviter
    // qu'une évolution future du code ne fasse remonter l'exécution
    // jusqu'au hook loc_begin_page_header.
    exit;
  }
}

/**
 * Hook 'loc_begin_page_header' : ne gère que les pages d'AFFICHAGE
 * (panier, commande). Pas de exit() ici : on laisse Piwigo terminer le
 * rendu normal de la page (menu, en-tête, pied de page) autour du
 * contenu injecté par le plugin.
 */
function familink_prints_handle_pages()
{
  if (empty($_GET['familink_prints'])) {
    return;
  }

  $action = $_GET['familink_prints'];

  if (in_array($action, array('cart', 'checkout'), true)) {
    familink_prints_dispatch($action);
  }
}
