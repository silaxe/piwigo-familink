# 📸 Piwigo Familink Prints Plugin

A lightweight plugin for **Piwigo** that allows users to order photo prints directly from their private galleries and send them via the **Familink** service.

---

## ✨ Features

* 🛒 Add photos to a cart from the Piwigo interface
* 🖼️ Select print formats (10x15 cm, 15x20 cm)
* 🔢 Adjust quantities directly in the cart
* 🧾 Simple checkout form with delivery address
* 🎨 Choose print finish (e.g. glossy)
* ⬜ Automatic white border padding when a photo doesn't exactly match the requested print ratio
* 🔐 Secure temporary URLs for image transfer
* 📦 Server-side order processing
* 🧹 Empty cart functionality

---

## 🧩 How it works

The plugin is built around a simple 3-step architecture:

1. **Piwigo Plugin Layer**

   * Adds UI elements (cart, buttons, checkout)
   * Stores selected photos in a database table
2. **Bridge Layer**

   * Generates secure, temporary URLs to access the images Familink will fetch
   * Ensures controlled external access
   * Serves either the original photo, or a cached version with white
     borders added if its aspect ratio doesn't match the chosen print format
3. **Order Processing**

   * Sends selected images and user data to the Familink API
   * Handles sandbox or production modes

---

## ⬜ White border padding

A 10x15cm print and a 15x20cm print each have a fixed aspect ratio. If a
photo doesn't match that ratio exactly (e.g. a 4:3 photo ordered as
10x15cm), most labs will crop part of the image to fill the frame.

Instead, this plugin can add white borders around the photo so that
**no part of the original image is ever cropped or stretched**. This
happens automatically when the checkout step creates the temporary
bridge URLs (see `include/image.inc.php`):

* The image is auto-rotated according to its EXIF orientation before
  any ratio calculation, so a portrait photo isn't mistaken for a
  landscape one.
* The shorter side is padded with white pixels (centered) just enough
  to reach the target ratio.
* A small tolerance (configurable, default 1%) avoids adding a
  practically-invisible border for photos that are already very close
  to the right ratio.
* Processed photos are cached on disk (`_cache/`, protected from direct
  web access) and keyed by the source file's modification time, so a
  photo replaced in Piwigo is automatically reprocessed.
* Uses the **Imagick** PHP extension when available (better EXIF and
  color-space handling), with a **GD** fallback. If neither is
  available, photos are sent unmodified rather than blocking the order.
* Can be disabled, and the tolerance adjusted, from the plugin's admin
  page. A button is also provided to clear the cache.

---

## ⚙️ Installation

⚠️ **The installation folder must be named exactly `familink_prints`**,
regardless of the GitHub repository name (`piwigo-familink`). The plugin's
code (`maintain.class.php`, internal URL generation, admin configuration
link) relies on the folder name under `plugins/` — a different name will
cause an activation error or a disabled "Configure" button.

**If you use `git clone`**, specify the target folder name explicitly:
```bash
git clone --branch V1.0.2 https://github.com/silaxe/piwigo-familink.git plugins/familink_prints
```

**If you download an archive from the GitHub "Releases" page**, use the
`familink_prints-X.Y.Z.zip` asset attached to the release (its internal
folder is already named `familink_prints/`) — **not** the generic
"Code → Download ZIP" button, which produces an archive named after the
repository (`piwigo-familink-main.zip`) and would result in a wrongly
named folder.

Once `plugins/familink_prints/` is in place:

1. Activate the plugin from the Piwigo admin panel
2. Configure:

   * Familink API key
   * Sandbox mode (recommended for testing)
   * Automatic white border padding (enabled by default)

---

## 🔧 Configuration

The plugin provides an admin interface to:

* Set your **Familink API key**
* Enable/disable **sandbox mode**
* Enable/disable **white border padding**, and set its tolerance
* Clear the processed-images cache
* Test API connectivity

---

## 🧪 Sandbox vs Production

* **Sandbox mode**:

  + No real prints are sent
  + Useful for testing and development
* **Production mode**:

  + Real orders are processed and shipped

---

## 📁 Project Structure

```
familink_prints/
├── admin.php
├── bridge.php
├── order.php
├── main.inc.php
├── maintain.class.php
├── init_db.sql
├── familink_prints.css
├── include/
│   ├── functions.inc.php
│   ├── image.inc.php
│   └── api.inc.php
├── templates/
│   ├── admin.tpl
│   ├── cart_page.tpl
│   ├── checkout_page.tpl
│   └── photo_button.tpl
├── _cache/            (generated at runtime, not versioned)
├── README.md
└── .gitignore
```

---

## 🔐 Security Notes

* Images are exposed through **temporary signed URLs**
* The padded-images cache directory is protected against direct web access
* API keys should never be committed to the repository
* CSRF protection is enforced for all actions

---

## 🚀 Roadmap / Ideas

* Album printing support
* Multiple formats per order
* Order history
* UI improvements & mobile optimization

---

## 📝 Changelog

### 1.0.4: 

* **Fix:** added the Plugin URI field (i.e., piwigo.org)
Without this header field, Piwigo had no way to link the locally 
installed plugin to its entry in the piwigo.org catalog. As a result, 
the native update notification could never be triggered, even when 
a new version was released.

### 1.0.3

* **Fix (critical):** adding a photo to the cart returned a 404 error
  on any Piwigo installation not served from the domain root. The
  post-add redirect URL concatenated `get_absolute_root_url()` (which
  already includes the Piwigo subfolder) with `$_SERVER['REQUEST_URI']`
  (which already includes that same subfolder), producing a duplicated
  path segment. The item was still correctly added to the cart; only
  the redirect afterwards failed.
* **Improved:** the free-text "Pays (ISO-2)" field on the checkout page
  was replaced with a country dropdown, removing the need for the user
  to know or type an ISO-3166-1 alpha-2 code by hand.

### 1.0.2

* **Fix (critical):** the sandbox flag sent to the Familink API always
  read a non-existent `$conf['sandbox']` key instead of
  `$conf['familink_sandbox']`, so orders were always processed as
  production regardless of the admin "sandbox mode" checkbox.
* **Fix:** `FAMILINK_RETURN_URL` was never actually assigned to the
  photo-button template due to a misplaced comma in the template
  variables array.
* **Fix:** removed duplicated/dead dispatch logic between the `init`
  and `loc_begin_page_header` hooks.
* **Fix:** added the `Has Settings: true` header field, required since
  Piwigo 11 for the plugin's "Configuration" button to be enabled
  (orange) instead of greyed out, in addition to the existing
  `get_admin_plugin_menu_links` hook used for older Piwigo versions.
* **New:** automatic white border padding for photos that don't match
  the exact print ratio (see above).

### 0.1.0

* Initial version.

---

## 🤝 Contributing

Contributions are welcome!
Feel free to open issues or submit pull requests.

---

## ⚠️ Disclaimer

This plugin is an independent project and is **not officially affiliated with Familink or Piwigo**.

---

## 🤖 About this project

This plugin was designed and developed with the assistance of **ChatGPT** and **Claude**, used as development partners for architecture, debugging, and implementation.

---

## 📄 License

GNU General Public License v3.0

---

## 🙌 Acknowledgements

* Piwigo community
* Familink API
* OpenAI / ChatGPT, Anthropic / Claude
