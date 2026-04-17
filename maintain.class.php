<?php
if (!defined('PHPWG_ROOT_PATH')) {
  die('Hacking attempt!');
}

class familink_prints_maintain extends PluginMaintain
{
  public function install($plugin_version, &$errors = array())
  {
    global $prefixeTable;

    $sql = file_get_contents(dirname(__FILE__) . '/init_db.sql');
    $sql = str_replace('piwigo_', $prefixeTable, $sql);

    foreach (preg_split('/;\s*[\r\n]+/', trim($sql)) as $query) {
      $query = trim($query);
      if ($query !== '') {
        pwg_query($query);
      }
    }
  }

  public function activate($plugin_version, &$errors = array())
  {
  }

  public function update($old_version, $new_version, &$errors = array())
  {
  }

  public function deactivate()
  {
  }

  public function uninstall()
  {
    global $prefixeTable;

    pwg_query('DROP TABLE IF EXISTS ' . $prefixeTable . 'familink_cart_items;');
    pwg_query('DROP TABLE IF EXISTS ' . $prefixeTable . 'familink_bridge_tokens;');
  }
}
