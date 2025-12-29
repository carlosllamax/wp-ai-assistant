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
        messages: []
    };

    // DOM Elements
    let widget, chatWindow, messagesContainer, input, sendBtn, toggleBtn, orderBtn, orderForm;

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

        // Set conversation ID cookie
        setCookie('wpaia_conv_id', state.conversationId, 1);

        // Bind events
        bindEvents();

        // Show welcome message
        if (config.welcomeMessage) {
            addMessage('assistant', config.welcomeMessage);
        }
    }

    /**
     * Bind event listeners
     */
    function bindEvents() {
        // Toggle chat
        toggleBtn.addEventListener('click', toggleChat);
        widget.querySelector('.wpaia-minimize-btn').addEventListener('click', toggleChat);

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
    }

    /**
     * Toggle chat window
     */
    function toggleChat() {
        state.isOpen = !state.isOpen;
        widget.classList.toggle('wpaia-open', state.isOpen);
        
        if (state.isOpen) {
            input.focus();
        }
    }

    /**
     * Send message
     */
    async function sendMessage() {
        const message = input.value.trim();
        if (!message || state.isLoading) return;

        // Add user message
        addMessage('user', message);
        input.value = '';
        
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
            } else {
                addMessage('assistant', data.message || config.strings.error);
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
     * Format message content
     */
    function formatMessage(content) {
        // Convert line breaks
        content = content.replace(/\n/g, '<br>');
        
        // Bold text **text**
        content = content.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
        
        // Italic text *text*
        content = content.replace(/\*(.*?)\*/g, '<em>$1</em>');
        
        // Code `code`
        content = content.replace(/`(.*?)`/g, '<code>$1</code>');
        
        // Links [text](url)
        content = content.replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2" target="_blank" rel="noopener">$1</a>');
        
        // Lists - item
        content = content.replace(/^- (.+)$/gm, '<li>$1</li>');
        content = content.replace(/(<li>.*<\/li>)/s, '<ul>$1</ul>');
        
        return content;
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
     * Generate UUID
     */
    function generateUUID() {
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

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
