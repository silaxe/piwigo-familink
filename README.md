# 📸 Piwigo Familink Prints Plugin

A lightweight plugin for **Piwigo** that allows users to order photo prints directly from their private galleries and send them via the **Familink** service.

---

## ✨ Features

- 🛒 Add photos to a cart from the Piwigo interface  
- 🖼️ Select print formats (10x15 cm, 15x20 cm)  
- 🔢 Adjust quantities directly in the cart  
- 🧾 Simple checkout form with delivery address  
- 🎨 Choose print finish (e.g. glossy)  
- 🔐 Secure temporary URLs for image transfer  
- 📦 Server-side order processing  
- 🧹 Empty cart functionality  

---

## 🧩 How it works

The plugin is built around a simple 3-step architecture:

1. **Piwigo Plugin Layer**
   - Adds UI elements (cart, buttons, checkout)
   - Stores selected photos in a database table

2. **Bridge Layer**
   - Generates secure, temporary URLs to access original images
   - Ensures controlled external access

3. **Order Processing**
   - Sends selected images and user data to the Familink API
   - Handles sandbox or production modes

---

## ⚙️ Installation

1. Copy the plugin folder into your Piwigo installation:

```
plugins/familink_prints/
```

2. Activate the plugin from the Piwigo admin panel

3. Configure:
   - Familink API key
   - Sandbox mode (recommended for testing)

---

## 🔧 Configuration

The plugin provides an admin interface to:

- Set your **Familink API key**
- Enable/disable **sandbox mode**
- Test API connectivity

---

## 🧪 Sandbox vs Production

- **Sandbox mode**:  
  - No real prints are sent  
  - Useful for testing and development  

- **Production mode**:  
  - Real orders are processed and shipped  

---

## 📁 Project Structure

```
familink_prints/
├── admin.php
├── main.inc.php
├── include/
│   ├── functions.inc.php
│   ├── order.php
│   └── bridge.php
├── template/
│   ├── cart_page.tpl
│   └── checkout_page.tpl
├── css/
├── README.md
└── .gitignore
```

---

## 🔐 Security Notes

- Images are exposed through **temporary signed URLs**
- API keys should never be committed to the repository
- CSRF protection is enforced for all actions

---

## 🚀 Roadmap / Ideas

- Album printing support  
- Multiple formats per order  
- Order history  
- UI improvements & mobile optimization  

---

## 🤝 Contributing

Contributions are welcome!  
Feel free to open issues or submit pull requests.

---

## ⚠️ Disclaimer

This plugin is an independent project and is **not officially affiliated with Familink or Piwigo**.

---

## 🤖 About this project

This plugin was designed and developed with the assistance of **ChatGPT**, used as a development partner for architecture, debugging, and implementation.

---

## 📄 License

GNU General Public License v3.0

---

## 🙌 Acknowledgements

- Piwigo community  
- Familink API  
- OpenAI / ChatGPT
