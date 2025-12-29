# Changelog

All notable changes to WP AI Assistant will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2025-01-XX

### Added
- Initial release
- Groq API integration (free tier with Llama 3.1 70B)
- OpenAI API integration (GPT-4, GPT-3.5)
- WooCommerce order lookup with email verification
- WPML and Polylang multilingual support
- Customizable chat widget (colors, position, icons)
- Custom chat bubble icon selection (6 presets + custom upload)
- Custom header avatar upload
- Markdown support in responses (bold, italic, links, lists, code)
- Rate limiting per user
- Self-hosted auto-update system
- Dark mode support
- Mobile responsive design
- "Powered by" branding with hide option
- Context builder from pages, products, and FAQs
- Custom knowledge base via admin settings

### Security
- Email verification for order lookups
- Nonce verification on all AJAX requests
- Input sanitization throughout
- Rate limiting to prevent abuse
