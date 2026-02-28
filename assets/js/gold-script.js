/**
 * Gold Gallery Companion - Frontend JavaScript
 */

(function($) {
    'use strict';

    var GoldGallery = {
        init: function() {
            this.bindEvents();
            this.initCountdownTimers();
        },

        bindEvents: function() {
            $(document).on('click', '.gold-price-breakdown-btn', this.openPriceModal);
            $(document).on('click', '.gold-modal-close', this.closePriceModal);
            $(document).on('click', '#gold-price-modal', this.closeOnBackdrop);
            $(document).on('keydown', this.handleEscKey);
        },

        openPriceModal: function(e) {
            e.preventDefault();
            
            var $btn = $(this);
            var $dataContainer = $btn.siblings('.gold-price-breakdown-data');
            
            if ($dataContainer.length === 0) {
                return;
            }

            var data = {
                pureWeight: $dataContainer.data('weight'),
                karat: $dataContainer.data('karat'),
                goldPrice: $dataContainer.data('gold-price'),
                goldValue: $dataContainer.data('gold-value'),
                makingPct: $dataContainer.data('making-charge-pct'),
                makingAmount: $dataContainer.data('making-charge-amount'),
                subtotal: $dataContainer.data('subtotal'),
                profitPct: $dataContainer.data('profit-margin-pct'),
                profitAmount: $dataContainer.data('profit-margin'),
                vatPct: $dataContainer.data('vat-pct'),
                vatAmount: $dataContainer.data('vat'),
                finalPrice: $dataContainer.data('final-price'),
                currency: $dataContainer.data('currency')
            };

            $('.modal-pure-weight').text(data.pureWeight);
            $('.modal-gold-price').text(GoldGallery.formatNumber(data.goldPrice));
            $('.modal-gold-value').text(GoldGallery.formatNumber(data.goldValue));
            $('.modal-making-pct').text(data.makingPct);
            $('.modal-making-charge').text(GoldGallery.formatNumber(data.makingAmount));
            $('.modal-subtotal').text(GoldGallery.formatNumber(data.subtotal));
            $('.modal-profit-pct').text(data.profitPct);
            $('.modal-profit').text(GoldGallery.formatNumber(data.profitAmount));
            $('.modal-vat-pct').text(data.vatPct);
            $('.modal-vat').text(GoldGallery.formatNumber(data.vatAmount));
            $('.modal-final-price').text(GoldGallery.formatNumber(data.finalPrice));
            $('.modal-currency').text(data.currency);

            $('#gold-price-modal').fadeIn(300);
        },

        closePriceModal: function() {
            $('#gold-price-modal').fadeOut(300);
        },

        closeOnBackdrop: function(e) {
            if ($(e.target).hasClass('gold-modal')) {
                $(this).fadeOut(300);
            }
        },

        handleEscKey: function(e) {
            if (e.key === 'Escape' || e.keyCode === 27) {
                $('#gold-price-modal').fadeOut(300);
            }
        },

        formatNumber: function(num) {
            return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
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
