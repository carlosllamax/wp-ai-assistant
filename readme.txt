=== WP AI Assistant ===
Contributors: carlosllamax
Tags: ai, chatbot, assistant, groq, openai, woocommerce, customer support
Requires at least: 5.8
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 1.3.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AI-powered chat assistant for WordPress. Supports Groq, OpenAI, Anthropic. BYOK (Bring Your Own Key).

== Description ==

WP AI Assistant adds an intelligent chatbot to your WordPress site that can answer questions about your content, products, and services.

**Features:**

* 🤖 Multiple AI providers: Groq (free!), OpenAI, Anthropic (Claude)
* 🔑 BYOK (Bring Your Own Key) - use your own API keys
* 🛒 WooCommerce integration - product info & order lookup
* 🔐 Secure order verification (order # + email required)
* 📊 Lead capture with GDPR consent
* 💾 Conversation storage and export
* 🌐 Multilingual support (WPML/Polylang ready)
* 🎨 Customizable appearance (colors, position, messages)
* 📱 Fully responsive design
* 💬 Conversation history
* 🚦 Rate limiting to prevent abuse
* 📈 Analytics events for GA4/GTM
* 🔗 CRM webhooks for integrations

**How it works:**

1. Install and activate the plugin
2. Get a free API key from Groq (or use OpenAI/Anthropic)
3. Configure your settings
4. The chat widget appears on your site!

The AI assistant automatically learns about your site by reading your pages, products, and FAQs. It can answer questions about your business without any additional training.

**WooCommerce Integration:**

When WooCommerce is installed, the assistant can:
* Provide information about your products
* Look up order status (requires verification)
* Answer shipping and return questions

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/wp-ai-assistant/`
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Go to 'AI Assistant' in the admin menu
4. Enter your API key and configure settings
5. Enable the assistant

== Frequently Asked Questions ==

= Is this plugin free? =

Yes! The plugin is free and open source. You need an API key from an AI provider:
- **Groq** - Free tier available with generous limits
- **OpenAI** - Paid, usage-based pricing
- **Anthropic** - Paid, usage-based pricing

= How do I get a Groq API key? =

1. Go to https://console.groq.com/
2. Sign up for a free account
3. Create an API key
4. Paste it in the plugin settings

= Is customer data secure? =

Yes! Order information is only shown after verification with both order number AND email address. No sensitive data is stored or sent to AI providers beyond what's needed to answer questions.

= Can I customize the AI's responses? =

Yes! You can:
- Add custom knowledge in the settings
- Modify the system prompt
- Control what content sources the AI can access

= Does it work with WPML? =

Yes! The plugin is translation-ready and will respond in the user's language when WPML or Polylang is active.

= Is it GDPR compliant? =

Yes! The plugin includes GDPR features:
- Consent checkbox for lead capture
- Data export functionality
- Data deletion/anonymization

== Screenshots ==

1. Chat widget on frontend
2. Admin settings page
3. Order verification flow
4. Mobile responsive design

== Changelog ==

= 1.3.1 =
* New admin UI redesign with brand colors
* Added header with navigation links to carlosllamax.com
* Added Quick Start, Preview and Support cards in sidebar
* Added PRO badge for premium features
* Fixed: Hide Branding now requires valid license
* Improved accessibility for disabled switches

= 1.3.0 =
* Added Anthropic Claude provider (Sonnet, Haiku, Opus)
* Added GDPR compliance features (consent, export, delete)
* Added analytics events for GA4/GTM integration
* Added CRM webhooks (wpaia_lead_captured, wpaia_message_sent)
* Added error logging system with database storage
* Added token management with automatic context trimming
* Added complete uninstall cleanup
* Enhanced accessibility with ARIA labels and roles
* Security: XSS prevention in chat messages
* Security: Improved input validation (email, phone, message length)
* Security: Secure session ID generation
* Security: Dual rate limiting (IP + conversation)

= 1.2.0 =
* Added conversation storage to database
* Added lead capture system
* Added conversations admin page with search and export

= 1.0.0 =
* Initial release
* Support for Groq, OpenAI, Anthropic
* WooCommerce integration
* Customizable appearance
* Rate limiting
* Order verification

== Upgrade Notice ==

= 1.0.0 =
Initial release of WP AI Assistant.
