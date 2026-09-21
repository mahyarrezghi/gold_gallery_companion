<?php

class Gold_Price_Calculator {

    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_filter( 'woocommerce_product_get_price', array( $this, 'get_dynamic_price' ), 999, 2 );
        add_filter( 'woocommerce_product_variation_get_price', array( $this, 'get_dynamic_price' ), 999, 2 );
        add_filter( 'woocommerce_product_variation_get_regular_price', array( $this, 'get_dynamic_price' ), 999, 2 );
        add_filter( 'woocommerce_get_price_html', array( $this, 'get_price_html' ), 999, 2 );
        
        add_filter( 'woocommerce_product_loop_price', array( $this, 'get_loop_price' ), 999, 2 );
        add_filter( 'woocommerce_variable_price_html', array( $this, 'get_variable_price_html' ), 999, 2 );
        
        add_filter( 'woocommerce_add_cart_item_data', array( $this, 'add_cart_item_data' ), 10, 4 );
        add_filter( 'woocommerce_get_cart_item_from_session', array( $this, 'get_cart_item_from_session' ), 10, 2 );
        add_filter( 'woocommerce_cart_item_price', array( $this, 'cart_item_price' ), 10, 3 );
        
        add_action( 'woocommerce_checkout_create_order', array( $this, 'save_order_meta' ), 10, 2 );
        add_action( 'woocommerce_thankyou', array( $this, 'display_thankyou_info' ) );
        
        add_action( 'gold_update_all_prices', array( $this, 'update_all_gold_prices' ) );
    }

    public function save_price_to_db( $product_id, $is_variation = false ) {
        $product = wc_get_product( $product_id );
        if ( ! $product ) {
            return false;
        }
        
        $parent_id = $product_id;
        if ( $is_variation ) {
            $parent_id = $product->get_parent_id();
        }
        
        if ( ! Gold_Product_Meta::is_gold_product( $parent_id ) ) {
            return false;
        }
        
        $gold_data = Gold_Product_Meta::get_gold_data( $parent_id );
        
        if ( $is_variation ) {
            $variation_karat = get_post_meta( $product_id, '_gold_karat', true );
            $variation_weight = get_post_meta( $product_id, '_gold_weight', true );
            $variation_making_charge = get_post_meta( $product_id, '_making_charge', true );
            
            if ( ! empty( $variation_weight ) ) {
                $gold_data['weight'] = floatval( $variation_weight );
            }
            if ( ! empty( $variation_karat ) ) {
                $gold_data['karat'] = $variation_karat;
            }
            if ( $variation_making_charge !== '' ) {
                $gold_data['making_charge'] = floatval( $variation_making_charge );
            }
        }
        
        if ( ! $gold_data['is_gold'] || $gold_data['weight'] <= 0 ) {
            return false;
        }
        
        $calculated_price = $this->calculate_price(
            $gold_data['weight'],
            $gold_data['karat'],
            $gold_data['making_charge']
        );
        
        if ( $calculated_price > 0 ) {
            $currency = Gold_Settings::get_setting( 'price_display', 'toman' );
            if ( 'toman' === $currency ) {
                $calculated_price = $calculated_price / 10;
            }
            
            update_post_meta( $product_id, '_price', $calculated_price );
            update_post_meta( $product_id, '_regular_price', $calculated_price );
            update_post_meta( $product_id, '_sale_price', $calculated_price );
            return true;
        }
        
        return false;
    }

    public function update_all_gold_prices() {
        $updated_count = 0;
        
        $args = array(
            'post_type' => 'product',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'meta_key' => '_is_gold_product',
            'meta_value' => 'yes'
        );
        
        $products = get_posts( $args );
        
        foreach ( $products as $product_post ) {
            $product = wc_get_product( $product_post->ID );
            if ( ! $product ) {
                continue;
            }
            
            if ( $product->is_type( 'variable' ) ) {
                $variations = $product->get_children();
                foreach ( $variations as $variation_id ) {
                    if ( $this->save_price_to_db( $variation_id, true ) ) {
                        $updated_count++;
                    }
                }
            } elseif ( $product->is_type( 'simple' ) ) {
                if ( $this->save_price_to_db( $product->get_id(), false ) ) {
                    $updated_count++;
                }
            }
        }
        
        update_option( 'gold_last_price_update', current_time( 'timestamp' ) );
        
        return $updated_count;
    }

    public static function manual_refresh_prices() {
        $instance = self::get_instance();
        return $instance->update_all_gold_prices();
    }

    public function calculate_price( $weight, $karat = '18k', $making_charge = null ) {
        if ( $weight <= 0 ) {
            return 0;
        }

        $karat = strtolower( $karat );
        
        $scraper = Gold_Scraper::get_instance();
        $gold_price_per_gram = $scraper->get_gold_price( $karat );
        
        if ( $gold_price_per_gram <= 0 ) {
            return 0;
        }

        if ( $making_charge === null ) {
            $making_charge = Gold_Settings::get_setting( 'default_making_charge', 10 );
        }

        $profit_margin = Gold_Settings::get_setting( 'profit_margin', 7 );
        $vat = Gold_Settings::get_setting( 'vat', 10 );

        $gold_value = floatval( $weight ) * $gold_price_per_gram;
        
        $making_charge_amount = $gold_value * ( floatval( $making_charge ) / 100 );
        
        $subtotal = $gold_value + $making_charge_amount;
        
        $profit_amount = $subtotal * ( floatval( $profit_margin ) / 100 );
        
        $vat_amount = ( $making_charge_amount + $profit_amount ) * ( floatval( $vat ) / 100 );
        
        $final_price = $subtotal + $profit_amount + $vat_amount;
        
        return $this->round_final_price( $final_price );
    }

    private function round_final_price( $price ) {
        $currency = Gold_Settings::get_setting( 'price_display', 'toman' );
        $step = ( 'toman' === $currency ) ? 10000 : 1000;
        return floor( $price / $step ) * $step;
    }

    public function calculate_price_array( $weight, $karat = '18k', $making_charge = null ) {
        if ( $weight <= 0 ) {
            return array(
                'gold_value'         => 0,
                'making_charge'      => 0,
                'making_charge_pct'  => 0,
                'subtotal'           => 0,
                'profit_margin'      => 0,
                'profit_margin_pct'  => 0,
                'vat'                => 0,
                'vat_pct'            => 0,
                'final_price'        => 0,
                'weight'             => 0,
                'gold_price_per_gram' => 0,
            );
        }

        $karat = strtolower( $karat );
        
        $scraper = Gold_Scraper::get_instance();
        $gold_price_per_gram = $scraper->get_gold_price( $karat );
        
        if ( $gold_price_per_gram <= 0 ) {
            return array(
                'gold_value'         => 0,
                'making_charge'      => 0,
                'making_charge_pct'  => 0,
                'subtotal'           => 0,
                'profit_margin'      => 0,
                'profit_margin_pct'  => 0,
                'vat'                => 0,
                'vat_pct'            => 0,
                'final_price'        => 0,
                'weight'             => 0,
                'gold_price_per_gram' => 0,
            );
        }

        if ( $making_charge === null ) {
            $making_charge = Gold_Settings::get_setting( 'default_making_charge', 10 );
        }

        $profit_margin = Gold_Settings::get_setting( 'profit_margin', 7 );
        $vat = Gold_Settings::get_setting( 'vat', 10 );

        $gold_value = floatval( $weight ) * $gold_price_per_gram;
        
        $making_charge_amount = $gold_value * ( floatval( $making_charge ) / 100 );
        
        $subtotal = $gold_value + $making_charge_amount;
        
        $profit_amount = $subtotal * ( floatval( $profit_margin ) / 100 );
        
        $vat_amount = ( $making_charge_amount + $profit_amount ) * ( floatval( $vat ) / 100 );
        
        $final_price = $subtotal + $profit_amount + $vat_amount;

        return array(
            'gold_value'         => round( $gold_value ),
            'making_charge'     => round( $making_charge_amount ),
            'making_charge_pct' => floatval( $making_charge ),
            'subtotal'          => round( $subtotal ),
            'profit_margin'     => round( $profit_amount ),
            'profit_margin_pct' => floatval( $profit_margin ),
            'vat'               => round( $vat_amount ),
            'vat_pct'           => floatval( $vat ),
            'final_price'       => $this->round_final_price( $final_price ),
            'weight'            => floatval( $weight ),
            'gold_price_per_gram' => $gold_price_per_gram,
        );
    }

    public function get_dynamic_price( $price, $product ) {
        if ( ! is_a( $product, 'WC_Product' ) ) {
            return $price;
        }

        $product_id = $product->get_id();
        
        if ( $product->is_type( 'variation' ) ) {
            $product_id = $product->get_parent_id();
        }
        
        if ( ! Gold_Product_Meta::is_gold_product( $product_id ) ) {
            return $price;
        }

        $gold_data = Gold_Product_Meta::get_gold_data( $product_id );
        
        if ( $product->is_type( 'variation' ) ) {
            $variation_id = $product->get_id();
            $variation_karat = get_post_meta( $variation_id, '_gold_karat', true );
            $variation_weight = get_post_meta( $variation_id, '_gold_weight', true );
            $variation_making_charge = get_post_meta( $variation_id, '_making_charge', true );
            
            if ( ! empty( $variation_weight ) ) {
                $gold_data['weight'] = floatval( $variation_weight );
            }
            if ( ! empty( $variation_karat ) ) {
                $gold_data['karat'] = $variation_karat;
            }
            if ( $variation_making_charge !== '' ) {
                $gold_data['making_charge'] = floatval( $variation_making_charge );
            }
        }
        
        if ( ! $gold_data['is_gold'] || $gold_data['weight'] <= 0 ) {
            return $price;
        }

        $calculated_price = $this->calculate_price(
            $gold_data['weight'],
            $gold_data['karat'],
            $gold_data['making_charge']
        );

        if ( $calculated_price > 0 ) {
            $product->set_price( $calculated_price );
            $product->set_regular_price( $calculated_price );
            $product->set_sale_price( $calculated_price );
            return $calculated_price;
        }

        return $price;
    }

    public function get_price_html( $price_html, $product ) {
        if ( ! is_a( $product, 'WC_Product' ) ) {
            return $price_html;
        }

        $product_id = $product->get_id();
        
        if ( $product->is_type( 'variation' ) ) {
            $product_id = $product->get_parent_id();
        }
        
        if ( ! Gold_Product_Meta::is_gold_product( $product_id ) ) {
            return $price_html;
        }

        $gold_data = Gold_Product_Meta::get_gold_data( $product_id );
        
        if ( $product->is_type( 'variation' ) ) {
            $variation_id = $product->get_id();
            $variation_karat = get_post_meta( $variation_id, '_gold_karat', true );
            $variation_weight = get_post_meta( $variation_id, '_gold_weight', true );
            $variation_making_charge = get_post_meta( $variation_id, '_making_charge', true );
            
            if ( ! empty( $variation_weight ) ) {
                $gold_data['weight'] = floatval( $variation_weight );
            }
            if ( ! empty( $variation_karat ) ) {
                $gold_data['karat'] = $variation_karat;
            }
            if ( $variation_making_charge !== '' ) {
                $gold_data['making_charge'] = floatval( $variation_making_charge );
            }
        }
        
        if ( ! $gold_data['is_gold'] || $gold_data['weight'] <= 0 ) {
            return $price_html;
        }

        $calculated_price = $this->calculate_price(
            $gold_data['weight'],
            $gold_data['karat'],
            $gold_data['making_charge']
        );

        if ( $calculated_price > 0 ) {
            $currency = Gold_Settings::get_setting( 'price_display', 'toman' );
            $currency_symbol = 'toman' === $currency ? 'تومان' : 'ریال';
            if ( 'toman' === $currency ) {
                $calculated_price = $calculated_price / 10;
            }
            return wc_price( $calculated_price ) . ' <span class="gold-currency">' . $currency_symbol . '</span>' . $this->get_branch_notice( $product_id, $product->is_type( 'variation' ) ? $product->get_id() : 0 );
        }

        return $price_html;
    }

    private function get_branch_notice( $parent_id, $variation_id = 0 ) {
        if ( ! function_exists( 'is_product' ) || ! is_product() ) {
            return '';
        }

        $branch = '';

        if ( $variation_id ) {
            $branch = get_post_meta( $variation_id, '_branch_location', true );
        }

        if ( '' === $branch || false === $branch ) {
            $branch = get_post_meta( $parent_id, '_branch_location', true );
        }

        if ( '' === $branch || false === $branch ) {
            return '';
        }

        return ' <span class="gold-branch">' . sprintf(
            /* translators: %s: branch name */
            esc_html__( 'Available in %s branch.', 'gold-gallery-companion' ),
            esc_html( $branch )
        ) . '</span>';
    }

    public function get_loop_price( $price, $product ) {
        if ( ! is_a( $product, 'WC_Product' ) ) {
            return $price;
        }

        $product_id = $product->get_id();
        
        if ( $product->is_type( 'variable' ) ) {
            $variations = $product->get_children();
            if ( ! empty( $variations ) ) {
                foreach ( $variations as $variation_id ) {
                    $is_gold = get_post_meta( $variation_id, '_is_gold_product', true );
                    if ( $is_gold === 'yes' ) {
                        $weight = get_post_meta( $variation_id, '_gold_weight', true );
                        if ( ! empty( $weight ) && floatval( $weight ) > 0 ) {
                            $karat = get_post_meta( $variation_id, '_gold_karat', true ) ?: '18k';
                            $making_charge = get_post_meta( $variation_id, '_making_charge', true );
                            
                            $calculated_price = $this->calculate_price(
                                floatval( $weight ),
                                $karat,
                                $making_charge
                            );
                            
                            if ( $calculated_price > 0 ) {
                                return $this->format_gold_price( $calculated_price );
                            }
                        }
                    }
                }
            }
        }
        
        return $price;
    }

    public function get_variable_price_html( $price_html, $product ) {
        if ( ! is_a( $product, 'WC_Product' ) ) {
            return $price_html;
        }

        $product_id = $product->get_id();
        
        if ( ! Gold_Product_Meta::is_gold_product( $product_id ) ) {
            return $price_html;
        }

        if ( function_exists( 'is_product' ) && is_product() ) {
            return '';
        }

        $variations = $product->get_children();
        $min_price = 0;
        
        if ( ! empty( $variations ) ) {
            foreach ( $variations as $variation_id ) {
                $is_gold = get_post_meta( $variation_id, '_is_gold_product', true );
                if ( $is_gold === 'yes' ) {
                    $weight = get_post_meta( $variation_id, '_gold_weight', true );
                    if ( ! empty( $weight ) && floatval( $weight ) > 0 ) {
                        $karat = get_post_meta( $variation_id, '_gold_karat', true ) ?: '18k';
                        $making_charge = get_post_meta( $variation_id, '_making_charge', true );
                        
                        $calculated_price = $this->calculate_price(
                            floatval( $weight ),
                            $karat,
                            $making_charge
                        );
                        
                        if ( $calculated_price > 0 ) {
                            if ( $min_price == 0 || $calculated_price < $min_price ) {
                                $min_price = $calculated_price;
                            }
                        }
                    }
                }
            }
        }
        
        if ( $min_price > 0 ) {
            return 'از ' . $this->format_gold_price( $min_price ) . $this->get_branch_notice( $product_id );
        }
        
        return $price_html;
    }

    private function format_gold_price( $price ) {
        $currency = Gold_Settings::get_setting( 'price_display', 'toman' );
        $currency_symbol = 'toman' === $currency ? 'تومان' : 'ریال';
        if ( 'toman' === $currency ) {
            $price = $price / 10;
        }
        return number_format( $price ) . ' ' . $currency_symbol;
    }

    public function add_cart_item_data( $cart_item_data, $product_id, $variation_id, $quantity ) {
        $is_variation = $variation_id > 0;
        $effective_product_id = $is_variation ? $variation_id : $product_id;
        $parent_product_id = $product_id;
        
        if ( ! Gold_Product_Meta::is_gold_product( $parent_product_id ) && ! Gold_Product_Meta::is_gold_product( $effective_product_id ) ) {
            return $cart_item_data;
        }

        $gold_data = Gold_Product_Meta::get_gold_data( $parent_product_id );
        
        if ( $is_variation ) {
            $variation_karat = get_post_meta( $variation_id, '_gold_karat', true );
            $variation_weight = get_post_meta( $variation_id, '_gold_weight', true );
            $variation_making_charge = get_post_meta( $variation_id, '_making_charge', true );
            
            if ( ! empty( $variation_weight ) ) {
                $gold_data['weight'] = floatval( $variation_weight );
            }
            if ( ! empty( $variation_karat ) ) {
                $gold_data['karat'] = $variation_karat;
            }
            if ( $variation_making_charge !== '' ) {
                $gold_data['making_charge'] = floatval( $variation_making_charge );
            }
        }
        
        if ( ! $gold_data['is_gold'] || $gold_data['weight'] <= 0 ) {
            return $cart_item_data;
        }

        $scraper = Gold_Scraper::get_instance();
        
        $price_data = array(
            'is_gold_product'      => true,
            'gold_karat'           => $gold_data['karat'],
            'gold_weight'          => $gold_data['weight'],
            'making_charge'        => $gold_data['making_charge'],
            'gold_price_18k'        => $scraper->get_gold_price( '18k' ),
            'gold_price_24k'        => $scraper->get_gold_price( '24k' ),
            'gold_price_per_gram'  => $scraper->get_gold_price( $gold_data['karat'] ),
            'price_timestamp'       => time(),
            'calculated_price'     => $this->calculate_price(
                $gold_data['weight'],
                $gold_data['karat'],
                $gold_data['making_charge']
            ),
            'branch'               => $gold_data['branch'],
        );

        $cart_item_data['gold_data'] = $price_data;

        return $cart_item_data;
    }

    public function get_cart_item_from_session( $cart_item, $values ) {
        if ( isset( $values['gold_data'] ) && $values['gold_data']['is_gold_product'] ) {
            $cart_item['gold_data'] = $values['gold_data'];
            
            $price_locked = $this->check_price_lock( $cart_item['gold_data'] );
            
            if ( ! $price_locked ) {
                $recalculated_price = $this->calculate_price(
                    $cart_item['gold_data']['gold_weight'],
                    $cart_item['gold_data']['gold_karat'],
                    $cart_item['gold_data']['making_charge']
                );
                
                $cart_item['gold_data']['calculated_price'] = $recalculated_price;
                $cart_item['gold_data']['price_updated'] = true;
            }
        }
        
        return $cart_item;
    }

    private function check_price_lock( $gold_data ) {
        if ( ! isset( $gold_data['price_timestamp'] ) ) {
            return false;
        }

        $lock_duration = GOLD_GALLERY_TRANSIENT_TIMEOUT;
        $elapsed = time() - $gold_data['price_timestamp'];
        
        return $elapsed < $lock_duration;
    }

    public function cart_item_price( $price, $cart_item, $cart_item_key ) {
        if ( ! isset( $cart_item['gold_data'] ) || ! $cart_item['gold_data']['is_gold_product'] ) {
            return $price;
        }

        $gold_data = $cart_item['gold_data'];
        $display_price = $gold_data['calculated_price'];
        
        $currency = Gold_Settings::get_setting( 'price_display', 'toman' );
        $currency_symbol = 'toman' === $currency ? 'تومان' : 'ریال';
        
        $output = wc_price( $display_price );
        
        if ( isset( $gold_data['price_updated'] ) && $gold_data['price_updated'] ) {
            $output .= '<span class="gold-price-updated">' . esc_html__( ' (Price updated)', 'gold-gallery-companion' ) . '</span>';
        }

        return $output;
    }

    public function save_order_meta( $order, $posted_data ) {
        foreach ( $order->get_items() as $item_id => $item ) {
            $product = $item->get_product();
            
            if ( ! $product ) {
                continue;
            }
            
            if ( Gold_Product_Meta::is_gold_product( $product->get_id() ) ) {
                $gold_data = Gold_Product_Meta::get_gold_data( $product->get_id() );
                
                if ( $gold_data['is_gold'] ) {
                    $scraper = Gold_Scraper::get_instance();
                    
                    $item->add_meta_data( __( 'Gold Karat', 'gold-gallery-companion' ), $gold_data['karat'], true );
                    $item->add_meta_data( __( 'Gold Weight', 'gold-gallery-companion' ), $gold_data['weight'] . ' g', true );
                    $item->add_meta_data( __( 'Making Charge', 'gold-gallery-companion' ), $gold_data['making_charge'] . '%', true );
                    $item->add_meta_data( __( 'Gold Price 18K', 'gold-gallery-companion' ), number_format( $scraper->get_gold_price( '18k' ) ) . ' ' . __( 'Rial/gram', 'gold-gallery-companion' ), true );
                    $item->add_meta_data( __( 'Gold Price 24K', 'gold-gallery-companion' ), number_format( $scraper->get_gold_price( '24k' ) ) . ' ' . __( 'Rial/gram', 'gold-gallery-companion' ), true );
                    
                    if ( ! empty( $gold_data['branch'] ) ) {
                        $item->add_meta_data( __( 'Pickup Branch', 'gold-gallery-companion' ), $gold_data['branch'], true );
                    }
                }
            }
        }
    }

    public function display_thankyou_info( $order_id ) {
        $order = wc_get_order( $order_id );
        
        if ( ! $order ) {
            return;
        }

        $has_gold = false;
        
        foreach ( $order->get_items() as $item ) {
            $product = $item->get_product();
            
            if ( $product && Gold_Product_Meta::is_gold_product( $product->get_id() ) ) {
                $has_gold = true;
                break;
            }
        }

        if ( ! $has_gold ) {
            return;
        }

        $scraper = Gold_Scraper::get_instance();
        
        echo '<div class="gold-order-info">';
        echo '<h2>' . esc_html__( 'Gold Purchase Details', 'gold-gallery-companion' ) . '</h2>';
        echo '<p>' . esc_html__( 'Current Gold Prices:', 'gold-gallery-companion' ) . '</p>';
        echo '<ul>';
        echo '<li>' . esc_html__( '18K Gold:', 'gold-gallery-companion' ) . ' ' . number_format( $scraper->get_display_price( '18k' ) ) . ' ' . esc_html__( 'Toman/gram', 'gold-gallery-companion' ) . '</li>';
        echo '<li>' . esc_html__( '24K Gold:', 'gold-gallery-companion' ) . ' ' . number_format( $scraper->get_display_price( '24k' ) ) . ' ' . esc_html__( 'Toman/gram', 'gold-gallery-companion' ) . '</li>';
        echo '</ul>';
        echo '</div>';
    }
}
