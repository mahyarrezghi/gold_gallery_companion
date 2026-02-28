/**
 * Gold Gallery Companion - Admin JavaScript
 */

(function($) {
    'use strict';

    var GoldAdmin = {
        init: function() {
            this.bindEvents();
            this.initGoldFields();
        },

        bindEvents: function() {
            $(document).on('change', '#_is_gold_product', this.toggleGoldFields);
            $(document).on('click', '#gold-refresh-prices', this.refreshPrices);
            $(document).on('click', '.gold-variation-fields input[type="checkbox"]', this.toggleVariationFields);
        },

        initGoldFields: function() {
            this.toggleGoldFields({ target: $('#_is_gold_product')[0], currentTarget: $('#_is_gold_product')[0] });
        },

        toggleGoldFields: function(e) {
            var isChecked = $(e.target).is(':checked');
            var $fields = $('#_gold_karat, #_gold_weight, #_making_charge, #_branch_location');
            
            if (isChecked) {
                $fields.closest('p').show();
            } else {
                $fields.closest('p').hide();
            }
        },

        toggleVariationFields: function(e) {
            var $checkbox = $(this);
            var $container = $checkbox.closest('.gold-variation-fields');
            var $options = $container.find('.gold-variation-options');
            
            if ($checkbox.is(':checked')) {
                $options.slideDown();
            } else {
                $options.slideUp();
            }
        },

        refreshPrices: function(e) {
            e.preventDefault();
            
            var $btn = $(this);
            $btn.prop('disabled', true).text('Refreshing...');
            
            $.ajax({
                url: gold_gallery_vars.ajax_url,
                type: 'POST',
                data: {
                    action: 'gold_refresh_prices',
                    nonce: gold_gallery_vars.nonce
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert('Failed to refresh prices. Please try again.');
                        $btn.prop('disabled', false).text('Refresh Now');
                    }
                },
                error: function() {
                    alert('Error refreshing prices. Please try again.');
                    $btn.prop('disabled', false).text('Refresh Now');
                }
            });
        }
    };

    $(document).ready(function() {
        GoldAdmin.init();
    });

})(jQuery);
