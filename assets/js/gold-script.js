/**
 * Gold Gallery Companion - Frontend JavaScript
 */

(function($) {
    'use strict';

    var GoldGallery = {
        init: function() {
            $('body').addClass('gold-calc-js');
            this.defaultBreakdown = $('.gold-calc-tbody').html() || '';
            this.bindEvents();
            this.positionVariableBreakdown();
            this.initCountdownTimers();
        },

        bindEvents: function() {
            $(document).on('found_variation', '.variations_form', this.onFoundVariation);
            $(document).on('reset_data', '.variations_form', this.onResetVariation);
        },

        positionVariableBreakdown: function() {
            var $container = $('.gold-product-info-variable').first();

            if ($container.length === 0) {
                return;
            }

            var $target = $('.variations_form table.variations').first();

            if ($target.length === 0) {
                $target = $('.wp-block-woocommerce-add-to-cart-with-options-variation-selector').first();
            }

            if ($target.length > 0) {
                $container.insertAfter($target);
            }

            $container.addClass('gold-calc-positioned');
        },

        onFoundVariation: function(e, variation) {
            var $tbody = $('.gold-calc-tbody');

            if ($tbody.length === 0) {
                return;
            }

            if (variation && variation.gold_breakdown) {
                $tbody.html(variation.gold_breakdown);
                $('.gold-calc-breakdown').show();
            } else {
                GoldGallery.onResetVariation();
            }
        },

        onResetVariation: function() {
            var $tbody = $('.gold-calc-tbody');

            if ($tbody.length === 0) {
                return;
            }

            if (GoldGallery.defaultBreakdown) {
                $tbody.html(GoldGallery.defaultBreakdown);
                $('.gold-calc-breakdown').show();
            } else {
                $('.gold-calc-breakdown').hide();
            }
        },

        initCountdownTimers: function() {
            GoldGallery.updateCartTimer();
            GoldGallery.updateMiniCartTimers();
            
            setInterval(function() {
                GoldGallery.updateCartTimer();
                GoldGallery.updateMiniCartTimers();
            }, 1000);
        },

        updateCartTimer: function() {
            var $timer = $('.gold-countdown-timer');
            
            if ($timer.length === 0) {
                return;
            }

            var lockDuration = parseInt($timer.data('lock-duration')) || 3600;
            var earliestTimestamp = GoldGallery.getEarliestGoldTimestamp();
            
            if (earliestTimestamp === 0) {
                return;
            }

            var elapsed = Math.floor(Date.now() / 1000) - earliestTimestamp;
            var remaining = lockDuration - elapsed;

            if (remaining <= 0) {
                $('.countdown-time').text('00:00');
                $('.gold-cart-countdown').addClass('expired');
                
                if (typeof wc_cart_params !== 'undefined') {
                    $(document.body).trigger('wc_refresh_fragment');
                }
                return;
            }

            var minutes = Math.floor(remaining / 60);
            var seconds = remaining % 60;
            $('.countdown-time').text(
                String(minutes).padStart(2, '0') + ':' + 
                String(seconds).padStart(2, '0')
            );
        },

        updateMiniCartTimers: function() {
            $('.gold-mini-cart-timer').each(function() {
                var $timer = $(this);
                var timestamp = parseInt($timer.data('timestamp')) || 0;
                var lockDuration = parseInt($timer.data('lock-duration')) || 3600;
                
                if (timestamp === 0) {
                    return;
                }

                var elapsed = Math.floor(Date.now() / 1000) - timestamp;
                var remaining = lockDuration - elapsed;

                if (remaining <= 0) {
                    $timer.find('.gold-mini-cart-time').text('Expired');
                    return;
                }

                var minutes = Math.floor(remaining / 60);
                var seconds = remaining % 60;
                $timer.find('.gold-mini-cart-time').text(
                    String(minutes).padStart(2, '0') + ':' + 
                    String(seconds).padStart(2, '0')
                );
            });
        },

        getEarliestGoldTimestamp: function() {
            var earliest = 0;
            
            $('.gold-mini-cart-timer').each(function() {
                var timestamp = parseInt($(this).data('timestamp')) || 0;
                if (timestamp > 0) {
                    if (earliest === 0 || timestamp < earliest) {
                        earliest = timestamp;
                    }
                }
            });

            return earliest;
        }
    };

    $(document).ready(function() {
        GoldGallery.init();
    });

})(jQuery);
