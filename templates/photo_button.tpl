<link rel="stylesheet" href="plugins/familink_prints/familink_prints.css">

<div class="familink-ui">
  <div class="familink-card">
    <div class="familink-title">Tirages photo Familink</div>
    <div class="familink-print-btn">
      <form method="post" action="{$FAMILINK_ADD_URL}">
        <input type="hidden" name="image_id" value="{$FAMILINK_IMAGE_ID}">
        <input type="hidden" name="format" value="10x15cm">
        <input type="hidden" name="pwg_token" value="{$PWG_TOKEN}">
        <input type="hidden" name="redirect" value="{$FAMILINK_RETURN_URL|escape}">
        <button class="button" type="submit">Ajouter 10×15</button>
      </form>

      <form method="post" action="{$FAMILINK_ADD_URL}">
        <input type="hidden" name="image_id" value="{$FAMILINK_IMAGE_ID}">
        <input type="hidden" name="format" value="15x20cm">
        <input type="hidden" name="pwg_token" value="{$PWG_TOKEN}">
        <input type="hidden" name="redirect" value="{$FAMILINK_RETURN_URL|escape}">
        <button class="button" type="submit">Ajouter 15×20</button>
      </form>

      <a class="button" href="{$FAMILINK_CART_URL}">Voir le panier</a>
    </div>
  </div>
</div>
