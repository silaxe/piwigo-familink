<?php
if (!defined('PHPWG_ROOT_PATH')) {
  die('Hacking attempt!');
}

class familink_prints_maintain extends PluginMaintain
{
  private function createTables()
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

  /**
   * Ajoute la colonne serve_path aux installations existantes qui ont
   * été créées avec une version antérieure du plugin (avant l'ajout du
   * bordurage automatique). createTables() utilise CREATE TABLE IF NOT
   * EXISTS et ne modifierait donc pas une table déjà présente : cette
   * étape complémentaire est nécessaire pour les mises à jour.
   */
  private function ensureSchema()
  {
    global $prefixeTable;

    $table = $prefixeTable . 'familink_bridge_tokens';
    $check = @pwg_query("SHOW COLUMNS FROM `$table` LIKE 'serve_path'");

    if ($check && pwg_db_num_rows($check) === 0) {
      pwg_query("ALTER TABLE `$table` ADD COLUMN serve_path VARCHAR(500) NOT NULL DEFAULT '' AFTER print_format");
    }
  }

  public function install($plugin_version, &$errors = array())
  {
    $this->createTables();
    $this->ensureSchema();
  }

  public function activate($plugin_version, &$errors = array())
  {
    $this->createTables();
    $this->ensureSchema();
  }

  public function update($old_version, $new_version, &$errors = array())
  {
    $this->createTables();
    $this->ensureSchema();
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
