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
</form>
