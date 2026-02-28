<?php

class Gold_Frontend {

    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'woocommerce_single_product_summary', array( $this, 'display_gold_price_info' ), 15 );
        add_action( 'woocommerce_after_shop_loop_item', array( $this, 'display_gold_price_loop' ), 15 );
        
        add_action( 'woocommerce_after_cart', array( $this, 'display_cart_countdown' ) );
        add_action( 'woocommerce_mini_cart_contents', array( $this, 'display_mini_cart_info' ), 10 );
        
        add_action( 'wp_footer', array( $this, 'render_price_modal' ) );
    }

    public function display_gold_price_info() {
        global $product;
        
        if ( ! $product ) {
            return;
        }

        $product_id = $product->get_id();
        
        if ( ! Gold_Product_Meta::is_gold_product( $product_id ) ) {
            return;
        }

        $gold_data = Gold_Product_Meta::get_gold_data( $product_id );
        
        if ( ! $gold_data['is_gold'] || $gold_data['weight'] <= 0 ) {
            return;
        }

        $scraper = Gold_Scraper::get_instance();
        $calculator = Gold_Price_Calculator::get_instance();
        
        $price_18k = $scraper->get_display_price( '18k' );
        $price_24k = $scraper->get_display_price( '24k' );
        $current_price_per_gram = $scraper->get_display_price( $gold_data['karat'] );
        
        $calculated_price = $calculator->calculate_price(
            $gold_data['weight'],
            $gold_data['karat'],
            $gold_data['making_charge']
        );
        
        $price_breakdown = $calculator->calculate_price_array(
            $gold_data['weight'],
            $gold_data['karat'],
            $gold_data['making_charge']
        );

        $currency = Gold_Settings::get_setting( 'price_display', 'toman' );
        $currency_label = 'toman' === $currency ? __( 'Toman', 'gold-gallery-companion' ) : __( 'Rial', 'gold-gallery-companion' );
        
        ?>
        <div class="gold-product-info">
            <div class="gold-price-per-gram">
                <span class="label"><?php esc_html_e( 'Current Gold Price:', 'gold-gallery-companion' ); ?></span>
                <span class="price-18k"><?php echo number_format( $price_18k ); ?> <?php echo esc_html( $currency_label ); ?>/<?php esc_html_e( 'g', 'gold-gallery-companion' ); ?> (18K)</span>
                <span class="separator">|</span>
                <span class="price-24k"><?php echo number_format( $price_24k ); ?> <?php echo esc_html( $currency_label ); ?>/<?php esc_html_e( 'g', 'gold-gallery-companion' ); ?> (24K)</span>
            </div>
            
            <div class="gold-product-details">
                <p><strong><?php esc_html_e( 'Karat:', 'gold-gallery-companion' ); ?></strong> <?php echo esc_html( strtoupper( $gold_data['karat'] ) ); ?></p>
                <p><strong><?php esc_html_e( 'Weight:', 'gold-gallery-companion' ); ?></strong> <?php echo esc_html( $gold_data['weight'] ); ?> g</p>
                <p><strong><?php esc_html_e( 'Making Charge:', 'gold-gallery-companion' ); ?></strong> <?php echo esc_html( $gold_data['making_charge'] ); ?>%</p>
                <?php if ( ! empty( $gold_data['branch'] ) ) : ?>
                <p><strong><?php esc_html_e( 'Branch:', 'gold-gallery-companion' ); ?></strong> <?php echo esc_html( $gold_data['branch'] ); ?></p>
                <?php endif; ?>
            </div>
            
            <button type="button" class="gold-price-breakdown-btn button" data-product-id="<?php echo esc_attr( $product_id ); ?>">
                <?php esc_html_e( 'View Price Breakdown', 'gold-gallery-companion' ); ?>
            </button>
            
            <div class="gold-price-breakdown-data" 
                 data-weight="<?php echo esc_attr( $gold_data['weight'] ); ?>"
                 data-karat="<?php echo esc_attr( $gold_data['karat'] ); ?>"
                 data-making-charge="<?php echo esc_attr( $gold_data['making_charge'] ); ?>"
                 data-gold-price="<?php echo esc_attr( $price_breakdown['gold_price_per_gram'] ); ?>"
                 data-gold-value="<?php echo esc_attr( $price_breakdown['gold_value'] ); ?>"
                 data-making-charge-pct="<?php echo esc_attr( $price_breakdown['making_charge_pct'] ); ?>"
                 data-making-charge-amount="<?php echo esc_attr( $price_breakdown['making_charge'] ); ?>"
                 data-subtotal="<?php echo esc_attr( $price_breakdown['subtotal'] ); ?>"
                 data-profit-margin-pct="<?php echo esc_attr( $price_breakdown['profit_margin_pct'] ); ?>"
                 data-profit-margin="<?php echo esc_attr( $price_breakdown['profit_margin'] ); ?>"
                 data-vat-pct="<?php echo esc_attr( $price_breakdown['vat_pct'] ); ?>"
                 data-vat="<?php echo esc_attr( $price_breakdown['vat'] ); ?>"
                 data-final-price="<?php echo esc_attr( $price_breakdown['final_price'] ); ?>"
                 data-currency="<?php echo esc_attr( $currency_label ); ?>"
                 style="display:none;">
            </div>
        </div>
        <?php
    }

    public function display_gold_price_loop() {
        global $product;
        
        if ( ! $product ) {
            return;
        }

        $product_id = $product->get_id();
        
        if ( ! Gold_Product_Meta::is_gold_product( $product_id ) ) {
            return;
        }

        $gold_data = Gold_Product_Meta::get_gold_data( $product_id );
        
        if ( ! $gold_data['is_gold'] || $gold_data['weight'] <= 0 ) {
            return;
        }

        $scraper = Gold_Scraper::get_instance();
        
        $price_per_gram = $scraper->get_display_price( $gold_data['karat'] );
        
        $currency = Gold_Settings::get_setting( 'price_display', 'toman' );
        $currency_label = 'toman' === $currency ? __( 'Toman', 'gold-gallery-companion' ) : __( 'Rial', 'gold-gallery-companion' );
        
        echo '<div class="gold-loop-info">';
        echo '<span class="gold-karat">' . esc_html( strtoupper( $gold_data['karat'] ) ) . '</span>';
        echo '<span class="gold-weight">' . esc_html( $gold_data['weight'] ) . 'g</span>';
        echo '<span class="gold-price-per-gram">' . number_format( $price_per_gram ) . ' ' . esc_html( $currency_label ) . '/g</span>';
        echo '</div>';
    }

    public function display_cart_countdown() {
        if ( is_null( WC()->cart ) ) {
            return;
        }

        $has_gold = false;
        
        foreach ( WC()->cart->get_cart() as $cart_item ) {
            if ( isset( $cart_item['gold_data'] ) && $cart_item['gold_data']['is_gold_product'] ) {
                $has_gold = true;
                break;
            }
        }

        if ( ! $has_gold ) {
            return;
        }

        ?>
        <div class="gold-cart-countdown">
            <div class="gold-timer-icon">⏱️</div>
            <div class="gold-timer-content">
                <p><?php esc_html_e( 'Gold prices are reserved for 1 hour from the time added to cart.', 'gold-gallery-companion' ); ?></p>
                <div class="gold-countdown-timer" data-lock-duration="<?php echo esc_attr( GOLD_GALLERY_TRANSIENT_TIMEOUT ); ?>">
                    <span class="countdown-label"><?php esc_html_e( 'Time remaining:', 'gold-gallery-companion' ); ?></span>
                    <span class="countdown-time">--:--</span>
                </div>
            </div>
        </div>
        <?php
    }

    public function display_mini_cart_info() {
        if ( is_null( WC()->cart ) ) {
            return;
        }

        $has_gold = false;
        $earliest_timestamp = 0;
        
        foreach ( WC()->cart->get_cart() as $cart_item ) {
            if ( isset( $cart_item['gold_data'] ) && $cart_item['gold_data']['is_gold_product'] ) {
                $has_gold = true;
                $timestamp = isset( $cart_item['gold_data']['price_timestamp'] ) ? $cart_item['gold_data']['price_timestamp'] : time();
                if ( $earliest_timestamp === 0 || $timestamp < $earliest_timestamp ) {
                    $earliest_timestamp = $timestamp;
                }
            }
        }

        if ( ! $has_gold ) {
            return;
        }

        $lock_duration = GOLD_GALLERY_TRANSIENT_TIMEOUT;
        $elapsed = time() - $earliest_timestamp;
        $remaining = $lock_duration - $elapsed;

        if ( $remaining > 0 ) {
            $minutes = floor( $remaining / 60 );
            $seconds = $remaining % 60;
            $time_text = sprintf( '%02d:%02d', $minutes, $seconds );
        } else {
            $time_text = __( 'Expired', 'gold-gallery-companion' );
        }

        echo '<div class="gold-mini-cart-timer" data-timestamp="' . esc_attr( $earliest_timestamp ) . '" data-lock-duration="' . esc_attr( $lock_duration ) . '">';
        echo '<span class="gold-mini-cart-label">' . esc_html__( 'Price reserved:', 'gold-gallery-companion' ) . '</span>';
        echo '<span class="gold-mini-cart-time">' . esc_html( $time_text ) . '</span>';
        echo '</div>';
    }

    public function render_price_modal() {
        ?>
        <div id="gold-price-modal" class="gold-modal" style="display:none;">
            <div class="gold-modal-content">
                <span class="gold-modal-close">&times;</span>
                <h2><?php esc_html_e( 'Gold Price Breakdown', 'gold-gallery-companion' ); ?></h2>
                
                <div class="gold-modal-body">
                    <table class="gold-price-table">
                        <tr>
                            <td><?php esc_html_e( 'Pure Gold Weight', 'gold-gallery-companion' ); ?></td>
                            <td><span class="modal-pure-weight">-</span> g</td>
                        </tr>
                        <tr>
                            <td><?php esc_html_e( 'Gold Price per Gram', 'gold-gallery-companion' ); ?></td>
                            <td><span class="modal-gold-price">-</span> <span class="modal-currency">-</span></td>
                        </tr>
                        <tr class="highlight">
                            <td><?php esc_html_e( 'Gold Value', 'gold-gallery-companion' ); ?></td>
                            <td><span class="modal-gold-value">-</span> <span class="modal-currency">-</span></td>
                        </tr>
                        <tr>
                            <td><?php esc_html_e( 'Making Charge', 'gold-gallery-companion' ); ?> (<span class="modal-making-pct">-</span>%)</td>
                            <td><span class="modal-making-charge">-</span> <span class="modal-currency">-</span></td>
                        </tr>
                        <tr class="subtotal">
                            <td><?php esc_html_e( 'Subtotal', 'gold-gallery-companion' ); ?></td>
                            <td><span class="modal-subtotal">-</span> <span class="modal-currency">-</span></td>
                        </tr>
                        <tr>
                            <td><?php esc_html_e( 'Profit Margin', 'gold-gallery-companion' ); ?> (<span class="modal-profit-pct">-</span>%)</td>
                            <td><span class="modal-profit">-</span> <span class="modal-currency">-</span></td>
                        </tr>
                        <tr>
                            <td><?php esc_html_e( 'VAT', 'gold-gallery-companion' ); ?> (<span class="modal-vat-pct">-</span>%)</td>
                            <td><span class="modal-vat">-</span> <span class="modal-currency">-</span></td>
                        </tr>
                        <tr class="total">
                            <td><strong><?php esc_html_e( 'Final Price', 'gold-gallery-companion' ); ?></strong></td>
                            <td><strong><span class="modal-final-price">-</span> <span class="modal-currency">-</span></strong></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
        <?php
    }

    public static function format_price( $price ) {
        $currency = Gold_Settings::get_setting( 'price_display', 'toman' );
        
        if ( 'rial' === $currency ) {
            return number_format( $price ) . ' ' . __( 'Rial', 'gold-gallery-companion' );
        }
        
        return number_format( $price ) . ' ' . __( 'Toman', 'gold-gallery-companion' );
    }
}
