/**
 * WP AI Assistant - Conversations Admin
 */
(function($) {
    'use strict';

    $(document).ready(function() {
        var $modal = $('#wpaia-conversation-modal');

        // View conversation
        $(document).on('click', '.wpaia-view-btn', function() {
            var sessionId = $(this).data('session');
            loadConversation(sessionId);
        });

        // Delete conversation
        $(document).on('click', '.wpaia-delete-btn', function() {
            if (!confirm(wpaiaConv.strings.confirmDelete)) {
                return;
            }

            var $btn = $(this);
            var $row = $btn.closest('tr');
            var sessionId = $btn.data('session');

            $btn.prop('disabled', true);

            $.ajax({
                url: wpaiaConv.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wpaia_delete_conversation',
                    nonce: wpaiaConv.nonce,
                    session_id: sessionId
                },
                success: function(response) {
                    if (response.success) {
                        $row.fadeOut(300, function() {
                            $(this).remove();
                        });
                    } else {
                        alert('Error deleting conversation');
                        $btn.prop('disabled', false);
                    }
                },
                error: function() {
                    alert('Error deleting conversation');
                    $btn.prop('disabled', false);
                }
            });
        });

        // Close modal
        $(document).on('click', '.wpaia-modal-close', function() {
            $modal.hide();
        });

        $(document).on('click', '.wpaia-modal', function(e) {
            if ($(e.target).hasClass('wpaia-modal')) {
                $modal.hide();
            }
        });

        // ESC to close
        $(document).on('keyup', function(e) {
            if (e.key === 'Escape') {
                $modal.hide();
            }
        });

        // Export CSV
        $(document).on('click', '.wpaia-export-btn', function() {
            var $btn = $(this);
            $btn.prop('disabled', true).text('Exporting...');

            $.ajax({
                url: wpaiaConv.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wpaia_export_conversations',
                    nonce: wpaiaConv.nonce
                },
                success: function(response) {
                    if (response.success && response.data.data) {
                        downloadCSV(response.data.data);
                    }
                    $btn.prop('disabled', false).html('<span class="dashicons dashicons-download"></span> Export CSV');
                },
                error: function() {
                    alert('Error exporting data');
                    $btn.prop('disabled', false).html('<span class="dashicons dashicons-download"></span> Export CSV');
                }
            });
        });

        function loadConversation(sessionId) {
            var $body = $modal.find('.wpaia-modal-body');
            $body.find('.wpaia-contact-info').html('');
            $body.find('.wpaia-messages-container').html('<div class="wpaia-loading">' + wpaiaConv.strings.loading + '</div>');
            $modal.show();

            $.ajax({
                url: wpaiaConv.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wpaia_get_conversation',
                    nonce: wpaiaConv.nonce,
                    session_id: sessionId
                },
                success: function(response) {
                    if (response.success) {
                        renderConversation(response.data);
                    } else {
                        $body.find('.wpaia-messages-container').html('<p>Error loading conversation</p>');
                    }
                },
                error: function() {
                    $body.find('.wpaia-messages-container').html('<p>Error loading conversation</p>');
                }
            });
        }

        function renderConversation(data) {
            var $contactInfo = $modal.find('.wpaia-contact-info');
            var $messages = $modal.find('.wpaia-messages-container');

            // Contact info
            if (data.lead && (data.lead.email || data.lead.phone)) {
                var contactHtml = '<h4>Contact Information</h4>';
                if (data.lead.name) {
                    contactHtml += '<p><strong>Name:</strong> ' + escapeHtml(data.lead.name) + '</p>';
                }
                if (data.lead.email) {
                    contactHtml += '<p><strong>Email:</strong> <a href="mailto:' + escapeHtml(data.lead.email) + '">' + escapeHtml(data.lead.email) + '</a></p>';
                }
                if (data.lead.phone) {
                    contactHtml += '<p><strong>Phone:</strong> <a href="tel:' + escapeHtml(data.lead.phone) + '">' + escapeHtml(data.lead.phone) + '</a></p>';
                }
                if (data.lead.page_url) {
                    contactHtml += '<p><strong>Page:</strong> <a href="' + escapeHtml(data.lead.page_url) + '" target="_blank">' + escapeHtml(data.lead.page_url) + '</a></p>';
                }
                $contactInfo.html(contactHtml);
            } else {
                $contactInfo.html('<p><em>Anonymous visitor</em></p>');
            }

            // Messages
            if (!data.messages || data.messages.length === 0) {
                $messages.html('<p>' + wpaiaConv.strings.noMessages + '</p>');
                return;
            }

            var messagesHtml = '';
            data.messages.forEach(function(msg) {
                if (msg.role === 'system') return;
                
                var time = new Date(msg.created_at).toLocaleString();
                messagesHtml += '<div class="wpaia-message ' + msg.role + '">';
                messagesHtml += '<div class="wpaia-message-content">' + escapeHtml(msg.message) + '</div>';
                messagesHtml += '<span class="wpaia-message-time">' + time + '</span>';
                messagesHtml += '</div>';
            });

            $messages.html(messagesHtml);
        }

        function downloadCSV(data) {
            var csv = 'Lead ID,Email,Phone,Name,Role,Message,Time\n';
            
            data.forEach(function(row) {
                csv += '"' + (row.lead_id || '') + '",';
                csv += '"' + (row.email || '') + '",';
                csv += '"' + (row.phone || '') + '",';
                csv += '"' + (row.name || '') + '",';
                csv += '"' + (row.role || '') + '",';
                csv += '"' + (row.message || '').replace(/"/g, '""') + '",';
                csv += '"' + (row.message_time || '') + '"\n';
            });

            var blob = new Blob([csv], { type: 'text/csv' });
            var url = window.URL.createObjectURL(blob);
            var a = document.createElement('a');
            a.href = url;
            a.download = 'conversations-' + new Date().toISOString().split('T')[0] + '.csv';
            a.click();
            window.URL.revokeObjectURL(url);
        }

        function escapeHtml(text) {
            if (!text) return '';
            var div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    });
})(jQuery);
