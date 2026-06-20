<?php
if (!defined('PHPWG_ROOT_PATH')) {
  die('Hacking attempt!');
}

/**
 * ----------------------------------------------------------------------
 * Module de mise en page des tirages.
 *
 * Quand une photo n'est pas exactement au ratio demandé (10x15cm ou
 * 15x20cm), on ajoute des bords blancs plutôt que de laisser Familink
 * recadrer l'image à sa façon. Aucun pixel de la photo originale n'est
 * recadré ou déformé : on agrandit le canevas, jamais l'image.
 * ----------------------------------------------------------------------
 */

/**
 * Ratio (côté long / côté court) attendu pour un format de tirage donné.
 */
function familink_prints_format_ratio($format)
{
  switch ($format) {
    case '10x15cm':
      return 15 / 10; // 1.5
    case '15x20cm':
      return 20 / 15; // 1.3333...
    default:
      return null;
  }
}

/**
 * Garde-fou mémoire : au-delà de cette taille, on saute le traitement
 * plutôt que de risquer un épuisement mémoire (gros scans, TIFF, etc.).
 */
function familink_prints_max_megapixels()
{
  return 40;
}

/**
 * Calcule la taille de canevas (et les marges associées) nécessaire pour
 * amener une image $w x $h au ratio du format demandé, sans recadrer ni
 * déformer l'image source. Retourne null si l'écart de ratio est déjà
 * dans la tolérance acceptée (pas besoin de bord).
 *
 * $tolerance est exprimée en fraction (0.01 = 1 %).
 */
function familink_prints_compute_padding($w, $h, $format, $tolerance = 0.01)
{
  $ratio = familink_prints_format_ratio($format);
  if ($ratio === null || $w <= 0 || $h <= 0) {
    return null;
  }

  $is_landscape = ($w >= $h);
  $target_ratio = $is_landscape ? $ratio : (1 / $ratio);
  $current_ratio = $w / $h;

  if ($target_ratio > 0 && abs($current_ratio - $target_ratio) / $target_ratio <= $tolerance) {
    return null;
  }

  if ($current_ratio > $target_ratio) {
    // L'image est plus "allongée" que le format cible : bords haut/bas.
    $new_w = $w;
    $new_h = (int)round($w / $target_ratio);
  } else {
    // L'image est plus "carrée"/haute que le format cible : bords gauche/droite.
    $new_h = $h;
    $new_w = (int)round($h * $target_ratio);
  }

  $pad_left = (int)floor(($new_w - $w) / 2);
  $pad_right = $new_w - $w - $pad_left;
  $pad_top = (int)floor(($new_h - $h) / 2);
  $pad_bottom = $new_h - $h - $pad_top;

  return array(
    'new_w' => $new_w,
    'new_h' => $new_h,
    'pad_left' => $pad_left,
    'pad_right' => $pad_right,
    'pad_top' => $pad_top,
    'pad_bottom' => $pad_bottom,
  );
}

/**
 * Point d'entrée principal : ajoute (si besoin) des bords blancs à
 * l'image $srcPath pour le format $format, et écrit le résultat en JPEG
 * dans $destPath. Ne lève jamais d'exception : en cas d'échec, retourne
 * 'ok' => false et l'appelant doit alors se replier sur l'image
 * d'origine plutôt que de bloquer toute la commande.
 *
 * Retourne un tableau :
 *   ok        bool    le traitement a pu être tenté sans erreur fatale
 *   padded    bool    un bord a effectivement été ajouté
 *   engine    string  'imagick' ou 'gd'
 *   src_w/h   int     dimensions sources (après redressement EXIF)
 *   dest_w/h  int     dimensions finales
 *   error     string  message d'erreur éventuel
 */
function familink_prints_pad_to_format($srcPath, $format, $destPath, $tolerance = 0.01, $quality = 92)
{
  if (!is_file($srcPath) || !is_readable($srcPath)) {
    return array('ok' => false, 'padded' => false, 'engine' => null, 'error' => 'Fichier source introuvable ou illisible');
  }

  $info = @getimagesize($srcPath);
  if (!is_array($info)) {
    return array('ok' => false, 'padded' => false, 'engine' => null, 'error' => 'Dimensions de l\'image illisibles');
  }

  $megapixels = ($info[0] * $info[1]) / 1000000;
  if ($megapixels > familink_prints_max_megapixels()) {
    return array(
      'ok' => false,
      'padded' => false,
      'engine' => null,
      'error' => 'Image trop volumineuse pour être traitée (' . round($megapixels) . ' MP)',
    );
  }

  if (extension_loaded('imagick')) {
    return familink_prints_pad_with_imagick($srcPath, $format, $destPath, $tolerance, $quality);
  }

  if (extension_loaded('gd')) {
    return familink_prints_pad_with_gd($srcPath, $format, $destPath, $tolerance, $quality);
  }

  return array('ok' => false, 'padded' => false, 'engine' => null, 'error' => 'Aucune extension Imagick ni GD disponible sur ce serveur');
}

/**
 * Implémentation via l'extension Imagick (privilégiée : meilleure
 * gestion de l'auto-rotation EXIF, du CMYK et des gros fichiers).
 */
function familink_prints_pad_with_imagick($srcPath, $format, $destPath, $tolerance, $quality)
{
  try {
    $img = new Imagick($srcPath);

    // Redressement selon l'orientation EXIF avant tout calcul de ratio :
    // sans ça, une photo portrait avec rotation EXIF pourrait être
    // évaluée comme paysage, et le bordurage se ferait dans le mauvais sens.
    $orientation = $img->getImageOrientation();
    switch ($orientation) {
      case Imagick::ORIENTATION_TOPRIGHT:
        $img->flopImage();
        break;
      case Imagick::ORIENTATION_BOTTOMRIGHT:
        $img->rotateImage('#FFFFFF', 180);
        break;
      case Imagick::ORIENTATION_BOTTOMLEFT:
        $img->flopImage();
        $img->rotateImage('#FFFFFF', 180);
        break;
      case Imagick::ORIENTATION_LEFTTOP:
        $img->flopImage();
        $img->rotateImage('#FFFFFF', -90);
        break;
      case Imagick::ORIENTATION_RIGHTTOP:
        $img->rotateImage('#FFFFFF', 90);
        break;
      case Imagick::ORIENTATION_RIGHTBOTTOM:
        $img->flopImage();
        $img->rotateImage('#FFFFFF', 90);
        break;
      case Imagick::ORIENTATION_LEFTBOTTOM:
        $img->rotateImage('#FFFFFF', -90);
        break;
    }
    $img->setImageOrientation(Imagick::ORIENTATION_TOPLEFT);

    $w = $img->getImageWidth();
    $h = $img->getImageHeight();

    $pad = familink_prints_compute_padding($w, $h, $format, $tolerance);

    if ($pad === null) {
      $img->destroy();
      return array(
        'ok' => true, 'padded' => false, 'engine' => 'imagick',
        'src_w' => $w, 'src_h' => $h, 'dest_w' => $w, 'dest_h' => $h, 'error' => null,
      );
    }

    // Aplatit toute transparence (PNG) ou CMYK sur fond blanc.
    $img->setImageBackgroundColor(new ImagickPixel('white'));
    $img = $img->mergeImageLayers(Imagick::LAYERMETHOD_FLATTEN);

    $canvas = new Imagick();
    $canvas->newImage($pad['new_w'], $pad['new_h'], new ImagickPixel('white'));
    $canvas->setImageFormat('jpeg');
    $canvas->compositeImage($img, Imagick::COMPOSITE_OVER, $pad['pad_left'], $pad['pad_top']);
    $canvas->setImageCompressionQuality($quality);
    $canvas->stripImage();
    $canvas->writeImage($destPath);
    $canvas->destroy();
    $img->destroy();

    return array(
      'ok' => true, 'padded' => true, 'engine' => 'imagick',
      'src_w' => $w, 'src_h' => $h, 'dest_w' => $pad['new_w'], 'dest_h' => $pad['new_h'], 'error' => null,
    );
  } catch (Throwable $e) {
    return array('ok' => false, 'padded' => false, 'engine' => 'imagick', 'error' => $e->getMessage());
  }
}

/**
 * Implémentation de repli via l'extension GD, pour les serveurs sans
 * Imagick.
 */
function familink_prints_pad_with_gd($srcPath, $format, $destPath, $tolerance, $quality)
{
  $info = @getimagesize($srcPath);
  if (!is_array($info)) {
    return array('ok' => false, 'padded' => false, 'engine' => 'gd', 'error' => 'Image illisible');
  }
  $type = $info[2];

  switch ($type) {
    case IMAGETYPE_JPEG:
      $src = @imagecreatefromjpeg($srcPath);
      break;
    case IMAGETYPE_PNG:
      $src = @imagecreatefrompng($srcPath);
      break;
    case IMAGETYPE_GIF:
      $src = @imagecreatefromgif($srcPath);
      break;
    case IMAGETYPE_WEBP:
      $src = function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($srcPath) : false;
      break;
    default:
      $src = false;
  }

  if (!$src) {
    return array('ok' => false, 'padded' => false, 'engine' => 'gd', 'error' => 'Type d\'image non supporté par GD');
  }

  // Auto-rotation EXIF (JPEG uniquement, nécessite l'extension exif).
  if ($type === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
    $exif = @exif_read_data($srcPath);
    if (!empty($exif['Orientation'])) {
      $rotated = null;
      switch ((int)$exif['Orientation']) {
        case 3:
          $rotated = imagerotate($src, 180, 0);
          break;
        case 6:
          $rotated = imagerotate($src, -90, 0);
          break;
        case 8:
          $rotated = imagerotate($src, 90, 0);
          break;
      }
      if ($rotated !== false && $rotated !== null) {
        imagedestroy($src);
        $src = $rotated;
      }
    }
  }

  $w = imagesx($src);
  $h = imagesy($src);

  $pad = familink_prints_compute_padding($w, $h, $format, $tolerance);

  if ($pad === null) {
    imagedestroy($src);
    return array(
      'ok' => true, 'padded' => false, 'engine' => 'gd',
      'src_w' => $w, 'src_h' => $h, 'dest_w' => $w, 'dest_h' => $h, 'error' => null,
    );
  }

  $canvas = imagecreatetruecolor($pad['new_w'], $pad['new_h']);
  $white = imagecolorallocate($canvas, 255, 255, 255);
  imagefill($canvas, 0, 0, $white);

  // imagealphablending(true) sur le canevas permet d'aplatir proprement
  // une éventuelle transparence (PNG) source sur le fond blanc pendant
  // la copie, plutôt que de la conserver.
  imagealphablending($canvas, true);
  imagecopy($canvas, $src, $pad['pad_left'], $pad['pad_top'], 0, 0, $w, $h);
  imagesavealpha($canvas, false);

  $ok = imagejpeg($canvas, $destPath, $quality);

  imagedestroy($canvas);
  imagedestroy($src);

  if (!$ok) {
    return array('ok' => false, 'padded' => false, 'engine' => 'gd', 'error' => 'Échec de l\'écriture JPEG (imagejpeg)');
  }

  return array(
    'ok' => true, 'padded' => true, 'engine' => 'gd',
    'src_w' => $w, 'src_h' => $h, 'dest_w' => $pad['new_w'], 'dest_h' => $pad['new_h'], 'error' => null,
  );
}

/**
 * ----------------------------------------------------------------------
 * Cache disque des images traitées.
 * ----------------------------------------------------------------------
 */

function familink_prints_cache_dir()
{
  $dir = FAMILINK_PRINTS_PATH . '_cache/';

  if (!is_dir($dir)) {
    @mkdir($dir, 0755, true);
  }

  $htaccess = $dir . '.htaccess';
  if (!is_file($htaccess)) {
    // Compatible Apache 2.2 (Deny from all) et 2.4 (Require all denied) :
    // on écrit les deux directives, l'une ou l'autre étant ignorée selon
    // la version par Apache sans provoquer d'erreur 500.
    @file_put_contents($htaccess, "Deny from all\nRequire all denied\n");
  }

  $index = $dir . 'index.php';
  if (!is_file($index)) {
    @file_put_contents($index, "<?php\ndie('Hacking attempt!');\n");
  }

  return $dir;
}

/**
 * Convertit un chemin absolu situé sous PHPWG_ROOT_PATH en chemin
 * relatif (même convention que la colonne `images.path` de Piwigo).
 */
function familink_prints_path_relative_to_root($absPath)
{
  $root = PHPWG_ROOT_PATH;
  if (strpos($absPath, $root) === 0) {
    return substr($absPath, strlen($root));
  }
  return $absPath;
}

/**
 * Détermine quel fichier doit réellement être servi par bridge.php pour
 * un article du panier donné : le fichier original, ou une version
 * mise en cache avec bords blancs ajoutés.
 *
 * $item doit contenir au moins : image_id, print_format, path.
 *
 * Ne fait jamais échouer la commande : en cas de problème (image
 * illisible, ni Imagick ni GD, fichier trop gros...), on revient
 * silencieusement à l'image d'origine et on remonte l'erreur dans
 * 'error' pour affichage informatif côté checkout.
 */
function familink_prints_resolve_serve_path($item, $pad_enabled, $tolerance)
{
  $relativePath = isset($item['path']) ? (string)$item['path'] : '';
  if (strpos($relativePath, './') === 0) {
    $relativePath = substr($relativePath, 2);
  }
  $relativePath = ltrim($relativePath, '/');

  $result = array(
    'serve_path' => $relativePath,
    'padded' => false,
    'engine' => null,
    'src_w' => null,
    'src_h' => null,
    'dest_w' => null,
    'dest_h' => null,
    'error' => null,
  );

  if (!$pad_enabled) {
    return $result;
  }

  $format = (string)$item['print_format'];
  $srcAbs = PHPWG_ROOT_PATH . $relativePath;

  if (!is_file($srcAbs) || !is_readable($srcAbs)) {
    $result['error'] = 'Fichier source introuvable, image envoyée sans modification';
    return $result;
  }

  $mtime = @filemtime($srcAbs);
  $cacheKey = (int)$item['image_id'] . '_' . preg_replace('/[^a-z0-9]/i', '', $format) . '_' . ($mtime !== false ? $mtime : '0') . '.jpg';
  $cacheDir = familink_prints_cache_dir();
  $cacheAbs = $cacheDir . $cacheKey;

  if (is_file($cacheAbs)) {
    // Déjà généré pour cette version exacte du fichier source (le nom
    // du fichier en cache inclut le mtime de la source : si la photo
    // est remplacée dans Piwigo, le cache est automatiquement invalidé).
    $info = @getimagesize($cacheAbs);
    $result['serve_path'] = familink_prints_path_relative_to_root($cacheAbs);
    $result['padded'] = true;
    $result['engine'] = 'cache';
    if (is_array($info)) {
      $result['dest_w'] = $info[0];
      $result['dest_h'] = $info[1];
    }
    return $result;
  }

  $padInfo = familink_prints_pad_to_format($srcAbs, $format, $cacheAbs, $tolerance);

  $result['src_w'] = isset($padInfo['src_w']) ? $padInfo['src_w'] : null;
  $result['src_h'] = isset($padInfo['src_h']) ? $padInfo['src_h'] : null;

  if (empty($padInfo['ok'])) {
    // On dégrade proprement : image d'origine envoyée telle quelle,
    // plutôt que de bloquer toute la commande pour une seule photo.
    $result['error'] = $padInfo['error'];
    return $result;
  }

  if (empty($padInfo['padded'])) {
    // Le ratio était déjà dans la tolérance acceptée : rien à garder en
    // cache, on continue avec le fichier d'origine.
    return $result;
  }

  $result['serve_path'] = familink_prints_path_relative_to_root($cacheAbs);
  $result['padded'] = true;
  $result['engine'] = $padInfo['engine'];
  $result['dest_w'] = $padInfo['dest_w'];
  $result['dest_h'] = $padInfo['dest_h'];

  return $result;
}

/**
 * Vide le cache des images traitées (bouton admin). Retourne le nombre
 * de fichiers supprimés.
 */
function familink_prints_clear_cache()
{
  $dir = familink_prints_cache_dir();
  $count = 0;

  foreach (glob($dir . '*.jpg') as $f) {
    if (@unlink($f)) {
      $count++;
    }
  }

  return $count;
}
