/**
 * WP AI Assistant - Plugins Page Script
 * Handles "Check for updates" link in plugins list
 */
(function($) {
    'use strict';
    
    $(document).ready(function() {
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
                        alert('Error checking for updates: ' + (response.data || 'Unknown error'));
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
