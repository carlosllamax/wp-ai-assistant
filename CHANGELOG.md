# Changelog

All notable changes to WP AI Assistant will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.4.0] - 2025-01-03

### Added
- **Fullscreen Mobile Experience**: Chat now takes full screen on mobile devices (≤480px)
  - Slide-up animation from bottom (like native messaging apps)
  - Body scroll lock prevents background page scrolling
  - iPhone safe area support (notch and home indicator)
  - Font-size 16px on inputs prevents iOS auto-zoom
  - Hidden toggle button when chat is open
  - Larger touch targets (44px) for better accessibility
  - Uses `100dvh` for dynamic viewport height (handles mobile browser chrome)

### Changed
- Improved mobile UX with app-like feel
- Better touch interactions on mobile devices

## [1.3.3] - 2024-12-29

### Fixed
- **Responsive height**: Chat window was too tall on mobile (100vh - 100px)
  - Now uses max-height 70vh on mobile devices
  - Added tablet breakpoint (768px) with 450px max-height
  - Added support for short screens (max-height 600px)
  - Proper min-height constraints for usability

### Changed
- Reduced header and input padding on mobile for more content space
- Messages container height adjusts proportionally to viewport

## [1.3.2] - 2024-12-29

### Fixed
- **Links not rendering**: Fixed markdown link parsing - links to products and pages now display correctly as clickable links
- **Markdown parse order**: Extract links before HTML escaping to preserve markdown syntax

### Changed
- **Improved product context**: Now includes explicit markdown links `[Product Name](URL)` that AI can copy directly
- **Improved page context**: Same markdown link format for pages
- **Enhanced system prompt**: Stronger instructions to include links in every product/page mention
- **Stock status**: Added product stock availability to context

## [1.3.1] - 2024-12-29

### Added
- **Admin UI Redesign**: Modern admin panel with brand colors (#000000, #EC6A6D)
  - New header with logo, version badge, and navigation links
  - Quick Start guide card in sidebar
  - Widget Preview card
  - Support card with documentation and contact links
  - Footer with credits and useful links
- **Premium Badge**: PRO badge for premium features (Hide Branding)
- **CSS Variables**: Consistent theming with CSS custom properties

### Changed
- **Hide Branding**: Now requires valid license (double validation)
  - Option must be enabled AND license must be valid
  - Disabled checkbox when no license
  - Lock icon with "Get a license" link
- **Improved Accessibility**: Better disabled state styling for switches

### Fixed
- Hide Branding could be enabled without valid license

## [1.3.0] - 2024-12-29

### Added
- **Anthropic Claude Provider**: Full support for Claude 3.5 Sonnet, Haiku, and Opus models
- **GDPR Compliance**: 
  - Consent checkbox in lead capture form
  - Export user data by email
  - Delete/anonymize user data
  - Privacy policy link configuration
- **Analytics Events**: Custom events for Google Analytics 4 / GTM integration
  - `wpaia_chat_open` - When chat widget is opened
  - `wpaia_message_sent` - When a message is sent
  - `wpaia_lead_captured` - When lead information is submitted
- **CRM Webhooks**: Action hooks for external integrations
  - `wpaia_lead_captured` - Fires when a new lead is captured
  - `wpaia_message_sent` - Fires after each message exchange
- **Error Logging System**: New logging class with database storage
  - Log levels: debug, info, warning, error
  - API error tracking with context
  - Auto-cleanup (keeps last 1000 entries)
- **Uninstall Cleanup**: Complete data removal on plugin uninstall
  - Removes all options and transients
  - Drops custom database tables
  - Cleans up scheduled cron jobs

### Changed
- **Token Management**: Automatic context trimming to prevent token overflow
  - Estimates tokens per message
  - Trims oldest messages when limit exceeded
  - Configurable max tokens (default 3000)
- **Rate Limiting**: Dual rate limiting by IP and conversation ID
- **Accessibility**: Enhanced ARIA labels, roles, and keyboard navigation
  - `role="dialog"` on chat window
  - `role="log"` on messages container
  - `aria-live="polite"` for screen reader updates
  - Proper labels on all interactive elements

### Security
- **XSS Prevention**: HTML escaping before markdown conversion in chat messages
- **Input Validation**: 
  - Email validation with `is_email()`
  - Phone format validation with regex
  - Message length limit (1000 characters)
  - Conversation ID format validation (UUID)
- **Secure Session IDs**: Using `crypto.randomUUID()` with secure fallbacks

### Fixed
- Update checker now uses GitHub automatic ZIP URLs
- Rate limit bypass through conversation ID manipulation

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
