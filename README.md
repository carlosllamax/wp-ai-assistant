# WP AI Assistant

🤖 **Free AI chatbot plugin for WordPress** powered by Groq/OpenAI. Add an intelligent assistant to any WordPress site in minutes.

![WordPress](https://img.shields.io/badge/WordPress-5.8+-blue.svg)
![PHP](https://img.shields.io/badge/PHP-7.4+-purple.svg)
![License](https://img.shields.io/badge/License-GPL--2.0--or--later-green.svg)
![Version](https://img.shields.io/badge/Version-1.0.0-orange.svg)

## ✨ Features

- 🆓 **Free to use** - BYOK (Bring Your Own Key) model, no monthly fees
- 🚀 **Groq Support** - Use Llama 3.1 70B for free with Groq API
- 🤖 **OpenAI Compatible** - Also supports GPT-4 and other OpenAI models
- 🛒 **WooCommerce Integration** - Order lookup with email verification
- 🌍 **Multilingual Ready** - WPML and Polylang compatible
- 🎨 **Fully Customizable** - Colors, icons, avatar, position, and more
- 📱 **Responsive Design** - Works perfectly on mobile devices
- 🌙 **Dark Mode** - Automatic dark mode support
- 🔒 **Privacy First** - No data sent to third parties (except AI provider)
- 🔄 **Auto Updates** - Self-hosted update system

## 📸 Screenshots

*Coming soon*

## 🚀 Quick Start

### 1. Get a Free API Key

Get a free Groq API key at [console.groq.com/keys](https://console.groq.com/keys)

### 2. Install the Plugin

**Option A: Download ZIP**
1. Download the latest release from [Releases](https://github.com/carlosllamax/wp-ai-assistant/releases)
2. Go to WordPress Admin → Plugins → Add New → Upload Plugin
3. Upload the ZIP file and activate

**Option B: Manual Installation**
1. Clone this repository to `/wp-content/plugins/wp-ai-assistant/`
2. Activate the plugin in WordPress Admin

### 3. Configure

1. Go to **WP AI Assistant** in the WordPress admin menu
2. Paste your Groq API key
3. Enable the assistant
4. Customize appearance as needed

That's it! The chat widget will appear on your site.

## ⚙️ Configuration Options

### AI Provider Settings

| Setting | Description | Default |
|---------|-------------|---------|
| Provider | Groq or OpenAI | Groq |
| API Key | Your API key | - |
| Model | AI model to use | llama-3.1-70b-versatile |
| System Prompt | Custom instructions for the AI | Auto-generated |

### Appearance

| Setting | Description | Default |
|---------|-------------|---------|
| Widget Position | Bottom-right or bottom-left | Bottom-right |
| Primary Color | Main color for the widget | #0073aa |
| Chat Icon | Icon style (chat, message, help, bot, headset, custom) | Chat |
| Custom Icon | Upload your own icon image | - |
| Header Avatar | Custom avatar for the chat header | - |
| Welcome Message | First message shown to users | "Hello! How can I help you today?" |

### Context Sources

| Setting | Description | Default |
|---------|-------------|---------|
| Include Pages | AI can reference your pages | ✅ Enabled |
| Include Products | AI knows about WooCommerce products | ✅ Enabled |
| Include FAQs | AI uses FAQ content | ✅ Enabled |
| Custom Context | Add custom knowledge base | - |
| Enable Order Lookup | Allow customers to check orders | ✅ Enabled |

### Other Settings

| Setting | Description | Default |
|---------|-------------|---------|
| Rate Limit | Max requests per minute per user | 20 |
| Hide Branding | Remove "Powered by" footer | ❌ Disabled |

## 🛒 WooCommerce Integration

When WooCommerce is active, customers can:

1. Click the order icon in the chat
2. Enter their order number and email
3. Ask questions about their specific order

The AI will have access to:
- Order status
- Items ordered
- Shipping information
- Order total

**Security**: Orders are verified by matching the email address before revealing any information.

## 🌍 Multilingual Support

The plugin automatically detects the current language when using:

- **WPML** - WordPress Multilingual Plugin
- **Polylang** - Multilingual plugin

The AI will respond in the detected language and only reference content in that language.

## 🎨 Customization

### CSS Customization

The widget uses CSS custom properties that you can override:

```css
#wpaia-chat-widget {
    --wpaia-primary: #your-color;
    --wpaia-radius: 16px;
    --wpaia-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
}
```

### Hooks & Filters

```php
// Modify the system prompt
add_filter('wpaia_system_prompt', function($prompt, $context) {
    return $prompt . "\n\nAdditional instructions here.";
}, 10, 2);

// Add custom context
add_filter('wpaia_context', function($context) {
    $context['custom'] = 'Your custom data here';
    return $context;
});

// Modify AI response before displaying
add_filter('wpaia_response', function($response) {
    return $response;
});
```

## 📋 Requirements

- WordPress 5.8 or higher
- PHP 7.4 or higher
- SSL certificate (HTTPS) recommended
- Groq or OpenAI API key

## 🔧 Development

### File Structure

```
wp-ai-assistant/
├── assets/
│   ├── css/
│   │   ├── admin.css
│   │   └── chat-widget.css
│   └── js/
│       ├── admin.js
│       └── chat-widget.js
├── includes/
│   ├── class-admin.php
│   ├── class-chat-widget.php
│   ├── class-context-builder.php
│   ├── class-conversation.php
│   ├── class-rest-api.php
│   └── providers/
│       ├── class-provider-interface.php
│       ├── class-groq.php
│       └── class-openai.php
├── integrations/
│   └── class-woocommerce.php
├── languages/
├── README.md
├── readme.txt
└── wp-ai-assistant.php
```

### Building for Production

```bash
# Create distributable ZIP
zip -r wp-ai-assistant.zip wp-ai-assistant -x "*.git*" -x "*.DS_Store"
```

## 📝 Changelog

### 1.0.0 (2025-01-XX)
- Initial release
- Groq and OpenAI provider support
- WooCommerce order lookup integration
- WPML/Polylang multilingual support
- Customizable chat widget
- Self-hosted update system

## 🤝 Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit your changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

## 📄 License

This project is licensed under the GPL-2.0-or-later License - see the [LICENSE](LICENSE) file for details.

## 👨‍💻 Author

**Carlos Llamas**
- Website: [carlosllamax.com](https://carlosllamax.com)
- GitHub: [@carlosllamax](https://github.com/carlosllamax)

## 💖 Support

If you find this plugin useful, consider:

- ⭐ Starring the repository
- 🐛 Reporting bugs
- 💡 Suggesting new features
- 📖 Improving documentation

---

Made with ❤️ for the WordPress community
