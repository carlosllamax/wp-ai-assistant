/**
 * WP AI Assistant - Chat Widget JavaScript
 */

(function() {
    'use strict';

    // Configuration from PHP
    const config = window.wpaiaChat || {};
    
    // State
    let state = {
        isOpen: false,
        isLoading: false,
        conversationId: config.conversationId || generateUUID(),
        isVerified: false,
        messages: [],
        messageCount: 0,
        leadCaptured: false,
        leadFormShown: false
    };

    // DOM Elements
    let widget, chatWindow, messagesContainer, input, sendBtn, toggleBtn, orderBtn, orderForm, leadForm;

    /**
     * Dispatch analytics event for GA/GTM tracking
     * 
     * @param {string} eventName - The event name (e.g., 'wpaia_chat_open')
     * @param {object} eventData - Additional event data
     */
    function trackEvent(eventName, eventData = {}) {
        // Dispatch custom event for GA4/GTM
        const event = new CustomEvent(eventName, {
            detail: {
                ...eventData,
                conversationId: state.conversationId,
                timestamp: new Date().toISOString()
            },
            bubbles: true
        });
        document.dispatchEvent(event);
        
        // Also push to dataLayer if GTM is available
        if (window.dataLayer) {
            window.dataLayer.push({
                event: eventName,
                wpaia: {
                    ...eventData,
                    conversationId: state.conversationId
                }
            });
        }
        
        // Also trigger gtag if GA4 is available directly
        if (window.gtag) {
            window.gtag('event', eventName.replace('wpaia_', ''), {
                event_category: 'wp_ai_assistant',
                ...eventData
            });
        }
    }

    /**
     * Initialize widget
     */
    function init() {
        widget = document.getElementById('wpaia-chat-widget');
        if (!widget) return;

        chatWindow = widget.querySelector('.wpaia-chat-window');
        messagesContainer = widget.querySelector('.wpaia-messages');
        input = widget.querySelector('.wpaia-input');
        sendBtn = widget.querySelector('.wpaia-send-btn');
        toggleBtn = widget.querySelector('.wpaia-toggle-btn');
        orderBtn = widget.querySelector('.wpaia-order-btn');
        orderForm = widget.querySelector('.wpaia-order-form');
        leadForm = widget.querySelector('.wpaia-lead-form');

        // Set conversation ID cookie
        setCookie('wpaia_conv_id', state.conversationId, 1);
        
        // Check if lead was already captured
        state.leadCaptured = localStorage.getItem('wpaia_lead_captured_' + state.conversationId) === 'true';

        // Bind events
        bindEvents();

        // Show welcome message
        if (config.welcomeMessage) {
            addMessage('assistant', config.welcomeMessage);
        }
        
        // Check if we should show lead form before chat (mode: before)
        if (shouldShowLeadForm('before')) {
            showLeadForm();
        }
    }

    /**
     * Bind event listeners
     */
    function bindEvents() {
        // Toggle chat
        toggleBtn.addEventListener('click', toggleChat);
        widget.querySelector('.wpaia-minimize-btn').addEventListener('click', function() {
            // Check if should show lead form on close (mode: end)
            if (shouldShowLeadForm('end')) {
                showLeadForm();
            } else {
                toggleChat();
            }
        });

        // Send message
        sendBtn.addEventListener('click', sendMessage);
        input.addEventListener('keypress', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                sendMessage();
            }
        });

        // Order verification
        if (orderBtn) {
            orderBtn.addEventListener('click', toggleOrderForm);
        }

        if (orderForm) {
            orderForm.querySelector('.wpaia-btn-cancel').addEventListener('click', toggleOrderForm);
            orderForm.querySelector('.wpaia-btn-verify').addEventListener('click', verifyOrder);
        }
        
        // Lead capture form
        if (leadForm) {
            const skipBtn = leadForm.querySelector('.wpaia-btn-skip');
            const submitBtn = leadForm.querySelector('.wpaia-btn-submit-lead');
            
            if (skipBtn) {
                skipBtn.addEventListener('click', function() {
                    hideLeadForm();
                    state.leadFormShown = true;
                });
            }
            
            if (submitBtn) {
                submitBtn.addEventListener('click', submitLead);
            }
        }
    }

    /**
     * Toggle chat window
     */
    function toggleChat() {
        state.isOpen = !state.isOpen;
        widget.classList.toggle('wpaia-open', state.isOpen);
        
        if (state.isOpen) {
            input.focus();
            // Track chat open event
            trackEvent('wpaia_chat_open', {
                page_url: window.location.href,
                page_title: document.title
            });
        }
    }

    /**
     * Send message
     */
    async function sendMessage() {
        const message = input.value.trim();
        if (!message || state.isLoading) return;
        
        // Check if lead form is blocking (mode: before)
        if (config.leadCapture && config.leadCapture.enabled && 
            config.leadCapture.mode === 'before' && 
            !state.leadCaptured && !state.leadFormShown) {
            showLeadForm();
            return;
        }

        // Add user message
        addMessage('user', message);
        input.value = '';
        
        // Increment message count
        state.messageCount++;
        
        // Show typing indicator
        state.isLoading = true;
        sendBtn.disabled = true;
        showTyping();

        try {
            const response = await fetch(config.restUrl + 'chat', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': config.restNonce
                },
                body: JSON.stringify({
                    message: message,
                    conversation_id: state.conversationId
                })
            });

            const data = await response.json();

            hideTyping();

            if (data.success !== false && data.message) {
                addMessage('assistant', data.message);
                state.messageCount++;
                
                // Track message sent event
                trackEvent('wpaia_message_sent', {
                    message_count: state.messageCount,
                    message_length: message.length
                });
            } else {
                addMessage('assistant', data.message || config.strings.error);
            }
            
            // Check if should show lead form after X messages (mode: after)
            if (shouldShowLeadForm('after')) {
                setTimeout(showLeadForm, 500);
            }
        } catch (error) {
            hideTyping();
            addMessage('assistant', config.strings.error);
            console.error('WPAIA Error:', error);
        } finally {
            state.isLoading = false;
            sendBtn.disabled = false;
            input.focus();
        }
    }

    /**
     * Add message to chat
     */
    function addMessage(role, content) {
        const messageEl = document.createElement('div');
        messageEl.className = `wpaia-message wpaia-message-${role}`;
        
        // Parse markdown-like formatting
        content = formatMessage(content);
        messageEl.innerHTML = content;

        messagesContainer.appendChild(messageEl);
        scrollToBottom();

        state.messages.push({ role, content });
    }

    /**
     * Format message content (sanitized)
     */
    function formatMessage(content) {
        // First, escape HTML to prevent XSS
        content = escapeHtml(content);
        
        // Convert line breaks
        content = content.replace(/\n/g, '<br>');
        
        // Bold text **text**
        content = content.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
        
        // Italic text *text*
        content = content.replace(/\*(.*?)\*/g, '<em>$1</em>');
        
        // Code `code`
        content = content.replace(/`(.*?)`/g, '<code>$1</code>');
        
        // Links [text](url) - validate URL before creating link
        content = content.replace(/\[([^\]]+)\]\(([^)]+)\)/g, function(match, text, url) {
            // Only allow http, https, and relative URLs
            if (url.match(/^(https?:\/\/|\/)/i)) {
                return '<a href="' + url + '" target="_blank" rel="noopener noreferrer">' + text + '</a>';
            }
            return text;
        });
        
        // Lists - item
        content = content.replace(/^- (.+)$/gm, '<li>$1</li>');
        content = content.replace(/(<li>.*<\/li>)/s, '<ul>$1</ul>');
        
        return content;
    }
    
    /**
     * Escape HTML entities to prevent XSS
     */
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    /**
     * Show typing indicator
     */
    function showTyping() {
        const typingEl = document.createElement('div');
        typingEl.className = 'wpaia-typing';
        typingEl.id = 'wpaia-typing';
        typingEl.innerHTML = `
            <span class="wpaia-typing-dot"></span>
            <span class="wpaia-typing-dot"></span>
            <span class="wpaia-typing-dot"></span>
        `;
        messagesContainer.appendChild(typingEl);
        scrollToBottom();
    }

    /**
     * Hide typing indicator
     */
    function hideTyping() {
        const typingEl = document.getElementById('wpaia-typing');
        if (typingEl) {
            typingEl.remove();
        }
    }

    /**
     * Scroll messages to bottom
     */
    function scrollToBottom() {
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
    }

    /**
     * Toggle order verification form
     */
    function toggleOrderForm() {
        const isVisible = orderForm.style.display !== 'none';
        orderForm.style.display = isVisible ? 'none' : 'block';
        
        if (!isVisible) {
            orderForm.querySelector('#wpaia-order-id').focus();
        }
    }

    /**
     * Verify order
     */
    async function verifyOrder() {
        const orderIdInput = orderForm.querySelector('#wpaia-order-id');
        const emailInput = orderForm.querySelector('#wpaia-order-email');
        const verifyBtn = orderForm.querySelector('.wpaia-btn-verify');

        const orderId = orderIdInput.value.replace(/\D/g, '');
        const email = emailInput.value.trim();

        if (!orderId || !email) {
            return;
        }

        verifyBtn.disabled = true;
        verifyBtn.textContent = config.strings.verifying;

        try {
            const response = await fetch(config.restUrl + 'verify-order', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': config.restNonce
                },
                body: JSON.stringify({
                    order_id: parseInt(orderId),
                    email: email,
                    conversation_id: state.conversationId
                })
            });

            const data = await response.json();

            if (data.success) {
                state.isVerified = true;
                orderBtn.classList.add('wpaia-verified');
                orderForm.style.display = 'none';
                addMessage('assistant', config.strings.verified + '\n\n' + data.order_info);
            } else {
                addMessage('assistant', config.strings.verifyFailed);
            }
        } catch (error) {
            addMessage('assistant', config.strings.error);
            console.error('WPAIA Verify Error:', error);
        } finally {
            verifyBtn.disabled = false;
            verifyBtn.textContent = config.strings.verify;
        }
    }

    /**
     * Generate UUID (cryptographically secure)
     */
    function generateUUID() {
        // Use crypto.randomUUID if available (modern browsers)
        if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
            return crypto.randomUUID();
        }
        
        // Fallback using crypto.getRandomValues for older browsers
        if (typeof crypto !== 'undefined' && typeof crypto.getRandomValues === 'function') {
            return ([1e7]+-1e3+-4e3+-8e3+-1e11).replace(/[018]/g, function(c) {
                return (c ^ crypto.getRandomValues(new Uint8Array(1))[0] & 15 >> c / 4).toString(16);
            });
        }
        
        // Last resort fallback (less secure, but functional)
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
            const r = Math.random() * 16 | 0;
            const v = c === 'x' ? r : (r & 0x3 | 0x8);
            return v.toString(16);
        });
    }

    /**
     * Set cookie
     */
    function setCookie(name, value, hours) {
        const expires = new Date(Date.now() + hours * 60 * 60 * 1000).toUTCString();
        document.cookie = `${name}=${value}; expires=${expires}; path=/; SameSite=Lax`;
    }
    
    /**
     * Check if lead form should be shown
     */
    function shouldShowLeadForm(mode) {
        const lc = config.leadCapture;
        
        if (!lc || !lc.enabled || state.leadCaptured || state.leadFormShown) {
            return false;
        }
        
        if (lc.mode !== mode) {
            return false;
        }
        
        if (mode === 'after') {
            return state.messageCount >= (lc.afterMessages * 2); // User + assistant messages
        }
        
        return true;
    }
    
    /**
     * Show lead capture form
     */
    function showLeadForm() {
        if (!leadForm || state.leadCaptured) return;
        
        leadForm.style.display = 'block';
        
        // Focus first input
        const firstInput = leadForm.querySelector('input');
        if (firstInput) {
            setTimeout(() => firstInput.focus(), 100);
        }
    }
    
    /**
     * Hide lead capture form
     */
    function hideLeadForm() {
        if (!leadForm) return;
        leadForm.style.display = 'none';
    }
    
    /**
     * Submit lead information
     */
    async function submitLead() {
        const submitBtn = leadForm.querySelector('.wpaia-btn-submit-lead');
        const emailInput = leadForm.querySelector('#wpaia-lead-email');
        const phoneInput = leadForm.querySelector('#wpaia-lead-phone');
        const nameInput = leadForm.querySelector('#wpaia-lead-name');
        const gdprCheckbox = leadForm.querySelector('#wpaia-gdpr-checkbox');
        const gdprConsent = leadForm.querySelector('.wpaia-gdpr-consent');
        
        const email = emailInput ? emailInput.value.trim() : '';
        const phone = phoneInput ? phoneInput.value.trim() : '';
        const name = nameInput ? nameInput.value.trim() : '';
        
        // Validate GDPR consent if enabled
        if (config.gdpr && config.gdpr.enabled && gdprCheckbox && !gdprCheckbox.checked) {
            if (gdprConsent) gdprConsent.classList.add('wpaia-gdpr-error');
            gdprCheckbox.focus();
            return;
        }
        if (gdprConsent) gdprConsent.classList.remove('wpaia-gdpr-error');
        
        // Validate - at least email or phone required
        if (!email && !phone) {
            // Highlight the required fields
            if (emailInput) emailInput.classList.add('wpaia-input-error');
            if (phoneInput) phoneInput.classList.add('wpaia-input-error');
            return;
        }
        
        // Remove error classes
        if (emailInput) emailInput.classList.remove('wpaia-input-error');
        if (phoneInput) phoneInput.classList.remove('wpaia-input-error');
        
        submitBtn.disabled = true;
        submitBtn.textContent = '...';
        
        try {
            const formData = new FormData();
            formData.append('action', 'wpaia_save_lead');
            formData.append('nonce', config.nonce);
            formData.append('session_id', state.conversationId);
            formData.append('email', email);
            formData.append('phone', phone);
            formData.append('name', name);
            formData.append('page_url', window.location.href);
            formData.append('gdpr_consent', gdprCheckbox && gdprCheckbox.checked ? '1' : '0');
            
            const response = await fetch(config.ajaxUrl, {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                state.leadCaptured = true;
                localStorage.setItem('wpaia_lead_captured_' + state.conversationId, 'true');
                hideLeadForm();
                
                // Track lead captured event
                trackEvent('wpaia_lead_captured', {
                    has_email: !!email,
                    has_phone: !!phone,
                    has_name: !!name,
                    gdpr_consent: gdprCheckbox && gdprCheckbox.checked,
                    page_url: window.location.href
                });
                
                // Show thank you message
                addMessage('assistant', config.strings.thanks || 'Thank you! How can I help you?');
            } else {
                console.error('Lead save failed:', data);
            }
        } catch (error) {
            console.error('Lead submit error:', error);
        } finally {
            submitBtn.disabled = false;
            submitBtn.textContent = config.strings.submit || 'Submit';
        }
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
