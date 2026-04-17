<link rel="stylesheet" href="plugins/familink_prints/familink_prints.css">

<div class="familink-ui">
  <div class="familink-toolbar">
    <div>
      <h2 class="familink-title">Panier Familink</h2>
      <div class="familink-summary">
        Préparez vos tirages 10×15 et 15×20 avant l’envoi.
      </div>
    </div>

    {if not empty($FAMILINK_ITEMS)}
      <div class="familink-actions">
        <div class="familink-cart-footer">
  <div class="familink-cart-count">
    {$FAMILINK_TOTAL_PHOTOS}
    {if $FAMILINK_TOTAL_PHOTOS > 1}
      photos
    {else}
      photo
    {/if}
  </div>

  <a class="button" href="{$FAMILINK_CHECKOUT_URL}">
    Passer la commande
  </a>
</div>
      </div>
    {/if}
  </div>

  <div class="familink-card">
    {if empty($FAMILINK_ITEMS)}
      <div class="familink-empty">
        Votre panier est vide.
      </div>
    {else}
      <form method="post" action="{$FAMILINK_UPDATE_URL}">
        <input type="hidden" name="pwg_token" value="{$FAMILINK_CSRF}">

        <div class="familink-table-wrap">
          <table class="familink-table">
            <thead>
              <tr>
                <th>Photo</th>
                <th>Format</th>
                <th>Copies</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
            {foreach from=$FAMILINK_ITEMS item=it}
              <tr>
<td class="familink-photo-cell">
  <div class="familink-photo-row">
    <div class="familink-thumb-wrap">
      <img
        src="{$it.thumb_url|escape}"
        alt="{$it.name|default:'Photo'|escape}"
        class="familink-thumb"
      >
    </div>

    <div class="familink-photo-text">
      <div class="familink-photo-title">
        #{$it.image_id} — {$it.name|default:'(sans titre)'|escape}
      </div>
      <div class="familink-photo-file">
        {$it.file|escape}
      </div>
    </div>
  </div>
</td>
                <td>
                  <span class="familink-meta">
                    <select name="formats[{$it.image_id}|{$it.print_format}]">
                      <option value="10x15cm" {if $it.print_format == '10x15cm'}selected{/if}>10×15</option>
                      <option value="15x20cm" {if $it.print_format == '15x20cm'}selected{/if}>15×20</option>
                    </select>
                  </span>
                </td>
                <td>
                  <input
                    class="familink-qty"
                    type="number"
                    min="1"
                    max="99"
                    name="copies[{$it.image_id}|{$it.print_format}]"
                    value="{$it.copies}"
                  >
                </td>
                <td>
                  <button
                    type="submit"
                    name="remove_item"
                    value="{$it.image_id}|{$it.print_format}"
                    formaction="{$FAMILINK_REMOVE_URL}"
                    formmethod="post"
                  >
                    Retirer
                  </button>
                </td>
              </tr>
            {/foreach}
            </tbody>
          </table>
        </div>

        <div class="familink-actions">

<div class="familink-cart-actions">

  <div class="familink-actions-left">
    <button class="button" type="submit">
      Mettre à jour les quantités
    </button>

    <a class="button" href="{$FAMILINK_CHECKOUT_URL}">
      Passer la commande
    </a>
  </div>

<button class="button button-red"
        type="submit"
        formaction="{$FAMILINK_EMPTY_URL}"
        formmethod="post"
        onclick="return confirm('Vider complètement le panier ?');">
  Vider le panier
</button>

</div>

</div>

      </form>
    {/if}
  </div>
</div>
