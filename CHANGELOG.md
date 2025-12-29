# Changelog

All notable changes to WP AI Assistant will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.2.0] - 2024-12-29

### Added
- **Conversation Storage**: All conversations are now saved to the database for permanent storage
- **Lead Capture System**: Capture visitor contact information (email, phone, name)
  - Configurable timing: before chat, after X messages, or when closing chat
  - Selectable fields: email, phone, and/or name
  - Customizable form title and description
  - Skip option for visitors
- **Conversations Admin Page**: New admin interface under AI Assistant menu
  - View all conversations with contact info
  - Stats dashboard (total conversations, leads, today, this week)
  - Search by email or phone
  - View full conversation details in modal
  - Delete individual conversations
  - Export all conversations to CSV
- New database tables: `wpaia_leads` and `wpaia_conversations`
- Automatic database migration on plugin update

### Changed
- Improved admin settings organization with new "Lead Capture & Conversations" section
- Updated JavaScript for lead form handling with localStorage state persistence

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
