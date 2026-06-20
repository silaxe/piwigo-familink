<link rel="stylesheet" href="plugins/familink_prints/familink_prints.css">

<div class="titrePage">
  <h2>Familink Prints</h2>
</div>

{if isset($FAMILINK_MSG)}
  <div class="{$FAMILINK_MSG_TYPE}">
    {$FAMILINK_MSG}
  </div>
{/if}

<form method="post" action="{$FAMILINK_ACTION}">
  <input type="hidden" name="pwg_token" value="{$PWG_TOKEN}">

  <input type="hidden" name="endpoint" value="https://web.familinkframe.com/api/prints/external-order">

  <fieldset>
    <legend>Configuration API</legend>

    <p>
      <label>API Token</label><br>
      <input type="text" name="api_token" value="{$FAMILINK_API_TOKEN|escape}" size="60">
    </p>

    <p>
      <label>
        <input type="checkbox" name="sandbox" value="1" {if $FAMILINK_SANDBOX}checked{/if}>
        Mode sandbox (test)
      </label>
    </p>
  </fieldset>

  <fieldset>
    <legend>Mise en page des tirages</legend>

    <p>
      <label>
        <input type="checkbox" name="pad_enabled" value="1" {if $FAMILINK_PAD_ENABLED}checked{/if}>
        Ajouter des bords blancs si le format ne correspond pas exactement
      </label>
    </p>

    <p>
      <label>Tolérance d'écart de ratio acceptée sans bordurage (%)</label><br>
      <input type="number" name="pad_tolerance" value="{$FAMILINK_PAD_TOLERANCE}" min="0" max="20" step="0.5" size="6">
      <span class="familink-meta">Au-delà de cet écart entre le ratio de la photo et le ratio du format choisi, des bords blancs sont ajoutés.</span>
    </p>

    {if $FAMILINK_PAD_ENGINE}
      <p class="familink-meta">
        Moteur de traitement d'image détecté sur ce serveur : <strong>{$FAMILINK_PAD_ENGINE}</strong>.
      </p>
    {else}
      <p class="errors">
        ⚠️ Ni l'extension PHP Imagick ni l'extension GD ne sont disponibles sur ce serveur :
        les photos seront envoyées sans bordurage, quel que soit ce réglage.
      </p>
    {/if}
  </fieldset>

  <p>
    <input class="button" type="submit" name="submit" value="Enregistrer">
  </p>

  <fieldset>
    <legend>Test API</legend>

    <p>
      Vérifie que l’API Familink est accessible avec le token fourni.
    </p>

    <p>
      <input class="button" type="submit" name="test_api" value="Tester la connexion API">
    </p>
  </fieldset>

  <fieldset>
    <legend>Cache des images bordurées</legend>

    <p>
      Les photos bordurées sont mises en cache pour éviter de refaire le traitement à chaque commande.
      Le cache est automatiquement invalidé si la photo d'origine est remplacée dans Piwigo.
    </p>

    <p>
      <input class="button" type="submit" name="clear_cache" value="Vider le cache des images bordurées"
             onclick="return confirm('Vider le cache des images bordurées ? Elles seront régénérées à la prochaine commande.');">
    </p>
  </fieldset>
</form>
