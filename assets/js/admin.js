/**
 * WP AI Assistant - Admin JavaScript
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        
        // Initialize color picker
        $('.wpaia-color-picker').wpColorPicker({
            change: function(event, ui) {
                // Update preview
                $('.wpaia-preview-bubble').css('background-color', ui.color.toString());
            }
        });

        // Toggle API key visibility
        $('.wpaia-toggle-visibility').on('click', function() {
            var $target = $('#' + $(this).data('target'));
            var $icon = $(this).find('.dashicons');
            
            if ($target.attr('type') === 'password') {
                $target.attr('type', 'text');
                $icon.removeClass('dashicons-visibility').addClass('dashicons-hidden');
            } else {
                $target.attr('type', 'password');
                $icon.removeClass('dashicons-hidden').addClass('dashicons-visibility');
            }
        });

        // Test connection
        $('.wpaia-test-connection').on('click', function() {
            var $btn = $(this);
            var $result = $('.wpaia-test-result');
            var provider = $('#wpaia_provider').val();
            var apiKey = $('#wpaia_api_key').val();

            if (!apiKey) {
                $result.removeClass('success').addClass('error')
                    .text(wpaiaAdmin.strings.error + 'API key is required');
                return;
            }

            $btn.prop('disabled', true);
            $result.removeClass('success error').text(wpaiaAdmin.strings.testing);

            $.ajax({
                url: wpaiaAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wpaia_test_connection',
                    nonce: wpaiaAdmin.nonce,
                    provider: provider,
                    api_key: apiKey
                },
                success: function(response) {
                    if (response.success) {
                        $result.removeClass('error').addClass('success')
                            .text(wpaiaAdmin.strings.success);
                    } else {
                        $result.removeClass('success').addClass('error')
                            .text(wpaiaAdmin.strings.error + (response.data.message || 'Unknown error'));
                    }
                },
                error: function() {
                    $result.removeClass('success').addClass('error')
                        .text(wpaiaAdmin.strings.error + 'Connection failed');
                },
                complete: function() {
                    $btn.prop('disabled', false);
                }
            });
        });

        // Provider change - update model options
        $('#wpaia_provider').on('change', function() {
            var provider = $(this).val();
            var $model = $('#wpaia_model');
            
            // Show/hide relevant optgroups
            $model.find('optgroup').each(function() {
                var $group = $(this);
                var groupName = $group.attr('label').toLowerCase();
                
                if (groupName.indexOf(provider) !== -1 || 
                    (provider === 'groq' && groupName === 'groq') ||
                    (provider === 'openai' && groupName === 'openai') ||
                    (provider === 'anthropic' && groupName === 'anthropic')) {
                    $group.show();
                    // Select first option of visible group
                    if (!$model.find('option:selected').parent().is(':visible')) {
                        $group.find('option:first').prop('selected', true);
                    }
                } else {
                    $group.hide();
                }
            });
        }).trigger('change');

        // Update preview bubble color on page load
        var currentColor = $('#wpaia_primary_color').val();
        if (currentColor) {
            $('.wpaia-preview-bubble').css('background-color', currentColor);
        }

        // Chat icon selection - show/hide custom icon upload
        $('#wpaia_chat_icon').on('change', function() {
            var value = $(this).val();
            if (value === 'custom') {
                $('.wpaia-custom-icon-row').slideDown();
            } else {
                $('.wpaia-custom-icon-row').slideUp();
            }
            
            // Update preview icon
            updatePreviewIcon(value);
        });
        
        function updatePreviewIcon(iconType) {
            var $preview = $('.wpaia-preview-bubble');
            var iconClass = 'dashicons-format-chat'; // default
            
            switch(iconType) {
                case 'message':
                    iconClass = 'dashicons-email-alt';
                    break;
                case 'help':
                    iconClass = 'dashicons-editor-help';
                    break;
                case 'bot':
                    iconClass = 'dashicons-superhero-alt';
                    break;
                case 'headset':
                    iconClass = 'dashicons-phone';
                    break;
                case 'custom':
                    var customUrl = $('#wpaia_custom_icon_url').val();
                    if (customUrl) {
                        $preview.html('<img src="' + customUrl + '" alt="" style="width: 30px; height: 30px;">');
                        return;
                    }
                    break;
            }
            
            $preview.html('<span class="dashicons ' + iconClass + '"></span>');
        }
        
        // Media uploader for custom chat icon
        var customIconFrame;
        $('.wpaia-upload-icon').on('click', function(e) {
            e.preventDefault();
            
            if (customIconFrame) {
                customIconFrame.open();
                return;
            }
            
            customIconFrame = wp.media({
                title: wpaiaAdmin.strings.selectIcon || 'Select Chat Icon',
                button: { text: wpaiaAdmin.strings.useImage || 'Use this image' },
                multiple: false,
                library: { type: 'image' }
            });
            
            customIconFrame.on('select', function() {
                var attachment = customIconFrame.state().get('selection').first().toJSON();
                $('#wpaia_custom_icon_url').val(attachment.url);
                $('.wpaia-custom-icon-preview').html('<img src="' + attachment.url + '" alt="Custom icon" style="max-width: 60px; max-height: 60px;">');
                $('.wpaia-remove-icon').show();
                updatePreviewIcon('custom');
            });
            
            customIconFrame.open();
        });
        
        $('.wpaia-remove-icon').on('click', function(e) {
            e.preventDefault();
            $('#wpaia_custom_icon_url').val('');
            $('.wpaia-custom-icon-preview').empty();
            $(this).hide();
            updatePreviewIcon($('#wpaia_chat_icon').val());
        });
        
        // Media uploader for header avatar
        var avatarFrame;
        $('.wpaia-upload-avatar').on('click', function(e) {
            e.preventDefault();
            
            if (avatarFrame) {
                avatarFrame.open();
                return;
            }
            
            avatarFrame = wp.media({
                title: wpaiaAdmin.strings.selectAvatar || 'Select Avatar',
                button: { text: wpaiaAdmin.strings.useImage || 'Use this image' },
                multiple: false,
                library: { type: 'image' }
            });
            
            avatarFrame.on('select', function() {
                var attachment = avatarFrame.state().get('selection').first().toJSON();
                $('#wpaia_header_avatar_url').val(attachment.url);
                $('.wpaia-avatar-preview').html('<img src="' + attachment.url + '" alt="Avatar" style="max-width: 50px; max-height: 50px; border-radius: 50%;">');
                $('.wpaia-remove-avatar').show();
            });
            
            avatarFrame.open();
        });
        
        $('.wpaia-remove-avatar').on('click', function(e) {
            e.preventDefault();
            $('#wpaia_header_avatar_url').val('');
            $('.wpaia-avatar-preview').html('<span class="description">Using default AI icon</span>');
            $(this).hide();
        });
        
        // Initialize preview icon on load
        updatePreviewIcon($('#wpaia_chat_icon').val());

        // Check for updates link
        $(document).on('click', '.wpaia-check-update', function(e) {
            e.preventDefault();
            var $link = $(this);
            var originalText = $link.text();
            
            $link.text('Checking...');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'wpaia_force_update_check',
                    nonce: $link.data('nonce')
                },
                success: function(response) {
                    if (response.success) {
                        $link.text('Done! Reloading...');
                        location.reload();
                    } else {
                        $link.text(originalText);
                        alert('Error checking for updates');
                    }
                },
                error: function() {
                    $link.text(originalText);
                    alert('Error checking for updates');
                }
            });
        });
    });

})(jQuery);

