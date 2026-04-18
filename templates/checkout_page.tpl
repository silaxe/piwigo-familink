<link rel="stylesheet" href="plugins/familink_prints/familink_prints.css">

<div class="familink-ui">
  <div class="familink-toolbar">
    <div>
      <h2 class="familink-title">Commande Familink</h2>
      <div class="familink-summary">
        Envoi des tirages avec génération d’URLs temporaires pour les photos privées.
      </div>
    </div>

    <div class="familink-actions">
      <a class="button" href="{$FAMILINK_CART_URL}">← Retour panier</a>
    </div>
  </div>

  {if !$FAMILINK_HAS_ITEMS}
    <div class="familink-card">
      <div class="familink-empty">
        Le panier est vide.
      </div>
    </div>
  {else}
    <div class="familink-card">
      <form id="familink-checkout-form">
        <div class="familink-form-grid">
          <div class="familink-field">
            <label for="company">Société</label>
            <input id="company" type="text" name="company" value="">
          </div>

          <div class="familink-field">
            <label for="enveloppe">Type d’enveloppe</label>
            <select id="enveloppe" name="enveloppe">
              <option value="auto" selected>auto</option>
            </select>
          </div>

          <div class="familink-field">
            <label for="finish">Finition</label>
            <select id="finish" name="finish">
              <option value="glossy" selected>Brillant</option>
              <option value="matte">Mat</option>
            </select>
          </div>

          <div class="familink-field">
            <label for="first_name">Prénom*</label>
            <input id="first_name" type="text" name="first_name" value="" required>
          </div>

          <div class="familink-field">
            <label for="last_name">Nom*</label>
            <input id="last_name" type="text" name="last_name" value="" required>
          </div>

          <div class="familink-field familink-field-full">
            <label for="address_1">Adresse 1*</label>
            <input id="address_1" type="text" name="address_1" value="" required>
          </div>

          <div class="familink-field familink-field-full">
            <label for="address_2">Adresse 2</label>
            <input id="address_2" type="text" name="address_2">
          </div>

          <div class="familink-field">
            <label for="city">Ville*</label>
            <input id="city" type="text" name="city" value="" required>
          </div>

          <div class="familink-field">
            <label for="postal_or_zip_code">Code postal*</label>
            <input id="postal_or_zip_code" type="text" name="postal_or_zip_code" value="" required>
          </div>

          <div class="familink-field">
            <label for="state">État / Région</label>
            <input id="state" type="text" name="state">
          </div>

          <div class="familink-field">
            <label for="country_code">Pays (ISO-2)*</label>
            <input id="country_code" type="text" name="country_code" value="" required maxlength="2">
          </div>
        </div>

        <div class="familink-actions">
          <button class="button" type="submit">Envoyer la commande</button>
        </div>
      </form>
    </div>

    <div class="familink-card">
      <div class="familink-title">Journal d’envoi</div>
      <div class="familink-subtitle familink-muted">
        Détails techniques utiles pour vérifier les fichiers, formats, quantités et la réponse serveur.
      </div>
      <pre id="familink-log" class="familink-log"></pre>
    </div>
  {/if}
</div>

<script>
  window.FAMILINK_CFG = {
    wsUrl: "{$FAMILINK_WS_URL|escape:'javascript'}",
    pluginBase: "{$FAMILINK_PLUGIN_BASE|escape:'javascript'}"
  };
</script>

<script>
  (function () {
    const form = document.getElementById('familink-checkout-form');
    const logEl = document.getElementById('familink-log');

    if (!form || !logEl) {
      return;
    }

    function log(msg, obj) {
      logEl.textContent += msg + "\n";
      if (typeof obj !== 'undefined') {
        if (typeof obj === 'string') {
          logEl.textContent += obj + "\n";
        } else {
          logEl.textContent += JSON.stringify(obj, null, 2) + "\n";
        }
      }
    }

    async function wsCall(method, params) {
      const body = new URLSearchParams();
      body.set('method', method);
      Object.entries(params || {}).forEach(([k, v]) => body.set(k, v));

      const res = await fetch(window.FAMILINK_CFG.wsUrl, {
        method: 'POST',
        credentials: 'include',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body
      });

      const raw = await res.text();

      let json;
      try {
        json = JSON.parse(raw);
      } catch (e) {
        throw new Error('Réponse WS non JSON: ' + raw);
      }

      if (json.stat !== 'ok') {
        throw new Error(json.message || 'WS error');
      }

      return json.result;
    }

    form.addEventListener('submit', async function (e) {
      e.preventDefault();
      logEl.textContent = '';

      try {
        log('Création des URLs temporaires...');
        const bridge = await wsCall('familink.bridge.create', { ttl: 900 });

        log('Photos envoyées :');
        bridge.urls.forEach(function (p, idx) {
          log(
            (idx + 1) +
            '. image_id=' + p.image_id +
            ' | name=' + (p.name || '') +
            ' | file=' + (p.file || '') +
            ' | format=' + p.format +
            ' | copies=' + p.copies +
            ' | url=' + p.url
          );
        });

        const payload = {
          recipient: {
            company: form.company.value || '',
            first_name: form.first_name.value.trim(),
            last_name: form.last_name.value.trim(),
            address_1: form.address_1.value.trim(),
            address_2: form.address_2.value.trim(),
            city: form.city.value.trim(),
            postal_or_zip_code: form.postal_or_zip_code.value.trim(),
            state: form.state.value.trim(),
            country_code: form.country_code.value.trim().toUpperCase()
          },
          enveloppe: form.enveloppe.value,
          finish: form.finish.value || 'glossy',
          photos: bridge.urls
        };

        log('Finition choisie : ' + (form.finish.value || 'glossy'));
        log('Envoi à order.php...', payload);

        const res = await fetch(window.FAMILINK_CFG.pluginBase + 'order.php', {
          method: 'POST',
          credentials: 'include',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload)
        });

        const raw = await res.text();

        log('HTTP status: ' + res.status);
        log('Headers Content-Type: ' + (res.headers.get('content-type') || '(absent)'));
        log('Réponse brute serveur :');
        log(raw || '(réponse vide)');

        try {
          const json = JSON.parse(raw);
          log('Réponse JSON serveur :', json);
        } catch (e) {
          log('Réponse non JSON ou vide.');
        }
      } catch (err) {
        log('Erreur : ' + (err.message || String(err)));
      }
    });
  })();
</script>
