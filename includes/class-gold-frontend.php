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

        add_filter( 'woocommerce_available_variation', array( $this, 'add_variation_breakdown' ), 10, 3 );
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

        $currency = Gold_Settings::get_setting( 'price_display', 'toman' );
        $currency_label = 'toman' === $currency ? __( 'Toman', 'gold-gallery-companion' ) : __( 'Rial', 'gold-gallery-companion' );

        if ( $product->is_type( 'variable' ) ) {
            $default_rows = '';

            foreach ( $product->get_available_variations() as $variation_data ) {
                if ( ! empty( $variation_data['gold_breakdown'] ) ) {
                    $default_rows = $variation_data['gold_breakdown'];
                    break;
                }
            }

            if ( '' === $default_rows ) {
                return;
            }

            echo '<div class="gold-product-info gold-product-info-variable">';
            $this->render_breakdown_table( $default_rows );
            echo '</div>';

            return;
        }

        $gold_data = Gold_Product_Meta::get_gold_data( $product_id );
        
        if ( ! $gold_data['is_gold'] || $gold_data['weight'] <= 0 ) {
            return;
        }

        $rows = $this->render_breakdown_rows(
            $gold_data['weight'],
            $gold_data['karat'],
            $gold_data['making_charge']
        );

        if ( '' === $rows ) {
            return;
        }

        $scraper = Gold_Scraper::get_instance();
        $price_18k = $scraper->get_display_price( '18k' );
        $price_24k = $scraper->get_display_price( '24k' );
        
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
                <p><strong><?php esc_html_e( 'Making Charge:', 'gold-gallery-companion' ); ?></strong> <?php echo esc_html( $this->format_percent( $gold_data['making_charge'] ) ); ?>%</p>
            </div>

            <?php $this->render_breakdown_table( $rows ); ?>
        </div>
        <?php
    }

    public function add_variation_breakdown( $data, $product, $variation ) {
        if ( ! Gold_Product_Meta::is_gold_product( $variation->get_parent_id() ) ) {
            return $data;
        }

        if ( ! Gold_Product_Meta::is_gold_product( $variation->get_id() ) ) {
            return $data;
        }

        $gold_data = $this->get_variation_gold_data( $variation );

        if ( $gold_data['weight'] <= 0 ) {
            return $data;
        }

        $rows = $this->render_breakdown_rows(
            $gold_data['weight'],
            $gold_data['karat'],
            $gold_data['making_charge']
        );

        if ( '' !== $rows ) {
            $data['gold_breakdown'] = $rows;
        }

        return $data;
    }

    private function get_variation_gold_data( $variation ) {
        $gold_data = Gold_Product_Meta::get_gold_data( $variation->get_parent_id() );
        $variation_id = $variation->get_id();

        $weight = get_post_meta( $variation_id, '_gold_weight', true );
        $karat = get_post_meta( $variation_id, '_gold_karat', true );
        $making_charge = get_post_meta( $variation_id, '_making_charge', true );

        if ( '' !== $weight && false !== $weight ) {
            $gold_data['weight'] = floatval( $weight );
        }
        if ( ! empty( $karat ) ) {
            $gold_data['karat'] = $karat;
        }
        if ( '' !== $making_charge && false !== $making_charge ) {
            $gold_data['making_charge'] = floatval( $making_charge );
        }

        return $gold_data;
    }

    private function render_breakdown_table( $rows_html ) {
        ?>
        <div class="gold-calc-breakdown">
            <h4 class="gold-calc-title"><?php esc_html_e( 'Gold Price Breakdown', 'gold-gallery-companion' ); ?></h4>
            <table class="gold-calc-table">
                <tbody class="gold-calc-tbody">
                    <?php echo $rows_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    private function render_breakdown_rows( $weight, $karat, $making_charge ) {
        $calculator = Gold_Price_Calculator::get_instance();
        $breakdown = $calculator->calculate_price_array( $weight, $karat, $making_charge );

        if ( $breakdown['gold_price_per_gram'] <= 0 ) {
            return '';
        }

        $currency = Gold_Settings::get_setting( 'price_display', 'toman' );
        $unit = 'toman' === $currency ? __( 'Toman', 'gold-gallery-companion' ) : __( 'Rial', 'gold-gallery-companion' );
        $divisor = 'toman' === $currency ? 10 : 1;

        $amount = function ( $value ) use ( $divisor ) {
            return number_format( $value / $divisor );
        };

        ob_start();
        ?>
        <tr class="gold-calc-row">
            <td class="gold-calc-label"><?php esc_html_e( 'Gold Value', 'gold-gallery-companion' ); ?></td>
            <td class="gold-calc-amount"><span class="gold-calc-value"><?php echo esc_html( $amount( $breakdown['gold_value'] ) ); ?></span> <span class="gold-calc-unit"><?php echo esc_html( $unit ); ?></span></td>
            <td class="gold-calc-formula"><?php esc_html_e( 'Weight × daily gold rate', 'gold-gallery-companion' ); ?></td>
        </tr>
        <tr class="gold-calc-row">
            <td class="gold-calc-label"><?php esc_html_e( 'Making Charge', 'gold-gallery-companion' ); ?></td>
            <td class="gold-calc-amount"><span class="gold-calc-value"><?php echo esc_html( $amount( $breakdown['making_charge'] ) ); ?></span> <span class="gold-calc-unit"><?php echo esc_html( $unit ); ?></span></td>
            <td class="gold-calc-formula"><?php
                printf(
                    /* translators: %s: making charge percentage */
                    esc_html__( 'Gold price × %s%%', 'gold-gallery-companion' ),
                    esc_html( $this->format_percent( $breakdown['making_charge_pct'] ) )
                );
            ?></td>
        </tr>
        <tr class="gold-calc-row">
            <td class="gold-calc-label"><?php esc_html_e( 'Profit', 'gold-gallery-companion' ); ?></td>
            <td class="gold-calc-amount"><span class="gold-calc-value"><?php echo esc_html( $amount( $breakdown['profit_margin'] ) ); ?></span> <span class="gold-calc-unit"><?php echo esc_html( $unit ); ?></span></td>
            <td class="gold-calc-formula"><?php
                printf(
                    /* translators: %s: profit margin percentage */
                    esc_html__( '(Gold price + making charge) × %s%%', 'gold-gallery-companion' ),
                    esc_html( $this->format_percent( $breakdown['profit_margin_pct'] ) )
                );
            ?></td>
        </tr>
        <tr class="gold-calc-row">
            <td class="gold-calc-label"><?php esc_html_e( 'VAT', 'gold-gallery-companion' ); ?></td>
            <td class="gold-calc-amount"><span class="gold-calc-value"><?php echo esc_html( $amount( $breakdown['vat'] ) ); ?></span> <span class="gold-calc-unit"><?php echo esc_html( $unit ); ?></span></td>
            <td class="gold-calc-formula"><?php
                printf(
                    /* translators: %s: VAT percentage */
                    esc_html__( '(Profit + making charge) × %s%%', 'gold-gallery-companion' ),
                    esc_html( $this->format_percent( $breakdown['vat_pct'] ) )
                );
            ?></td>
        </tr>
        <tr class="gold-calc-row gold-calc-total">
            <td class="gold-calc-label"><?php esc_html_e( 'Final Price', 'gold-gallery-companion' ); ?></td>
            <td class="gold-calc-amount"><span class="gold-calc-value"><?php echo esc_html( $amount( $breakdown['final_price'] ) ); ?></span> <span class="gold-calc-unit"><?php echo esc_html( $unit ); ?></span></td>
            <td class="gold-calc-formula"><?php esc_html_e( 'Gold price + making charge + profit + VAT', 'gold-gallery-companion' ); ?></td>
        </tr>
        <?php
        return ob_get_clean();
    }

    private function format_percent( $value ) {
        $formatted = number_format( floatval( $value ), 2, '.', '' );
        return rtrim( rtrim( $formatted, '0' ), '.' );
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

    public static function format_price( $price ) {
        $currency = Gold_Settings::get_setting( 'price_display', 'toman' );
        
        if ( 'rial' === $currency ) {
            return number_format( $price ) . ' ' . __( 'Rial', 'gold-gallery-companion' );
        }
        
        return number_format( $price ) . ' ' . __( 'Toman', 'gold-gallery-companion' );
    }
}
