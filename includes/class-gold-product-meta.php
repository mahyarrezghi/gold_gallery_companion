<?php

class Gold_Product_Meta {

    private static $instance = null;

    const META_KEYS = array(
        'is_gold_product'    => '_is_gold_product',
        'gold_karat'         => '_gold_karat',
        'gold_weight'        => '_gold_weight',
        'making_charge'      => '_making_charge',
        'branch_location'    => '_branch_location',
    );

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'woocommerce_product_options_general_product_data', array( $this, 'product_fields' ) );
        add_action( 'woocommerce_process_product_meta', array( $this, 'save_product_fields' ) );
        add_action( 'woocommerce_variation_options', array( $this, 'variation_fields' ), 10, 3 );
        add_action( 'woocommerce_save_product_variation', array( $this, 'save_variation_fields' ), 10, 2 );
        add_action( 'woocommerce_update_product', array( $this, 'update_variable_product_prices' ), 10, 2 );
        
        add_filter( 'woocommerce_product_is_visible', array( $this, 'filter_product_visibility' ), 10, 2 );
    }
    
    public function update_variable_product_prices( $product_id, $product ) {
        if ( $product->is_type( 'variable' ) && Gold_Product_Meta::is_gold_product( $product_id ) ) {
            $calculator = Gold_Price_Calculator::get_instance();
            $variations = $product->get_children();
            foreach ( $variations as $variation_id ) {
                $calculator->save_price_to_db( $variation_id, true );
            }
        }
    }

    public function render_meta_box( $post ) {
        $product = wc_get_product( $post->ID );
        
        if ( ! $product ) {
            return;
        }

        $is_gold = $product->get_meta( self::META_KEYS['is_gold_product'], true );
        $karat = $product->get_meta( self::META_KEYS['gold_karat'], true );
        $weight = $product->get_meta( self::META_KEYS['gold_weight'], true );
        $making_charge = $product->get_meta( self::META_KEYS['making_charge'], true );
        $branch = $product->get_meta( self::META_KEYS['branch_location'], true );

        $default_karat = Gold_Settings::get_setting( 'default_karat', '18k' );
        $default_making_charge = Gold_Settings::get_setting( 'default_making_charge', 10 );
        
        $karat = $karat ?: $default_karat;
        $making_charge = $making_charge !== '' ? $making_charge : $default_making_charge;
        ?>
        <div class="gold-product-meta-box">
            <div class="gold-field-row">
                <label for="<?php echo esc_attr( self::META_KEYS['is_gold_product'] ); ?>">
                    <input type="checkbox" 
                           id="<?php echo esc_attr( self::META_KEYS['is_gold_product'] ); ?>" 
                           name="<?php echo esc_attr( self::META_KEYS['is_gold_product'] ); ?>" 
                           value="yes" 
                           <?php checked( $is_gold, 'yes' ); ?>>
                    <?php esc_html_e( 'This is a gold product', 'gold-gallery-companion' ); ?>
                </label>
            </div>

            <div class="gold-fields-container" style="<?php echo $is_gold !== 'yes' ? 'display:none;' : ''; ?>">
                <div class="gold-field-row">
                    <label for="<?php echo esc_attr( self::META_KEYS['gold_karat'] ); ?>">
                        <?php esc_html_e( 'Gold Karat:', 'gold-gallery-companion' ); ?>
                    </label>
                    <select id="<?php echo esc_attr( self::META_KEYS['gold_karat'] ); ?>" 
                            name="<?php echo esc_attr( self::META_KEYS['gold_karat'] ); ?>">
                        <option value="18k" <?php selected( $karat, '18k' ); ?>>
                            <?php esc_html_e( '18K (750)', 'gold-gallery-companion' ); ?>
                        </option>
                        <option value="24k" <?php selected( $karat, '24k' ); ?>>
                            <?php esc_html_e( '24K (999)', 'gold-gallery-companion' ); ?>
                        </option>
                    </select>
                </div>

                <div class="gold-field-row">
                    <label for="<?php echo esc_attr( self::META_KEYS['gold_weight'] ); ?>">
                        <?php esc_html_e( 'Weight (grams):', 'gold-gallery-companion' ); ?>
                    </label>
                    <input type="number" 
                           id="<?php echo esc_attr( self::META_KEYS['gold_weight'] ); ?>" 
                           name="<?php echo esc_attr( self::META_KEYS['gold_weight'] ); ?>" 
                           value="<?php echo esc_attr( $weight ); ?>" 
                           step="0.01" 
                           min="0"
                           placeholder="0.00">
                </div>

                <div class="gold-field-row">
                    <label for="<?php echo esc_attr( self::META_KEYS['making_charge'] ); ?>">
                        <?php esc_html_e( 'Making Charge (%):', 'gold-gallery-companion' ); ?>
                    </label>
                    <input type="number" 
                           id="<?php echo esc_attr( self::META_KEYS['making_charge'] ); ?>" 
                           name="<?php echo esc_attr( self::META_KEYS['making_charge'] ); ?>" 
                           value="<?php echo esc_attr( $making_charge ); ?>" 
                           step="0.1" 
                           min="0"
                           max="100"
                           placeholder="<?php echo esc_attr( $default_making_charge ); ?>">
                </div>

                <div class="gold-field-row">
                    <label for="<?php echo esc_attr( self::META_KEYS['branch_location'] ); ?>">
                        <?php esc_html_e( 'Branch/Location:', 'gold-gallery-companion' ); ?>
                    </label>
                    <input type="text"
                           id="<?php echo esc_attr( self::META_KEYS['branch_location'] ); ?>" 
                           name="<?php echo esc_attr( self::META_KEYS['branch_location'] ); ?>" 
                           value="<?php echo esc_attr( $branch ); ?>" 
                           placeholder="<?php esc_html_e( 'e.g., Tehran - Main Branch', 'gold-gallery-companion' ); ?>">
                </div>

                <div class="gold-price-preview">
                    <h4><?php esc_html_e( 'Current Price Preview', 'gold-gallery-companion' ); ?></h4>
                    <?php
                    $scraper = Gold_Scraper::get_instance();
                    $price_18k = $scraper->get_display_price( '18k' );
                    $price_24k = $scraper->get_display_price( '24k' );
                    
                    $calculator = Gold_Price_Calculator::get_instance();
                    $calculated_price = $calculator->calculate_price( $weight, $karat, $making_charge );
                    
                    $currency = Gold_Settings::get_setting( 'price_display', 'toman' );
                    $currency_label = 'toman' === $currency ? __( 'Toman', 'gold-gallery-companion' ) : __( 'Rial', 'gold-gallery-companion' );
                    ?>
                    <p><strong><?php esc_html_e( '18K Price:', 'gold-gallery-companion' ); ?></strong> <?php echo number_format( $price_18k ); ?> <?php echo esc_html( $currency_label ); ?>/<?php esc_html_e( 'gram', 'gold-gallery-companion' ); ?></p>
                    <p><strong><?php esc_html_e( '24K Price:', 'gold-gallery-companion' ); ?></strong> <?php echo number_format( $price_24k ); ?> <?php echo esc_html( $currency_label ); ?>/<?php esc_html_e( 'gram', 'gold-gallery-companion' ); ?></p>
                    <?php if ( $weight > 0 ) : ?>
                    <p class="calculated-price"><strong><?php esc_html_e( 'Calculated Price:', 'gold-gallery-companion' ); ?></strong> <?php echo number_format( $calculated_price ); ?> <?php echo esc_html( $currency_label ); ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }

    public function product_fields() {
        global $post;
        
        $product = wc_get_product( $post->ID );
        
        if ( ! $product ) {
            return;
        }

        $is_gold = $product->get_meta( self::META_KEYS['is_gold_product'], true );
        $karat = $product->get_meta( self::META_KEYS['gold_karat'], true );
        $weight = $product->get_meta( self::META_KEYS['gold_weight'], true );
        $making_charge = $product->get_meta( self::META_KEYS['making_charge'], true );
        $branch = $product->get_meta( self::META_KEYS['branch_location'], true );
        
        $default_karat = Gold_Settings::get_setting( 'default_karat', '18k' );
        $default_making_charge = Gold_Settings::get_setting( 'default_making_charge', 10 );
        
        $karat = $karat ?: $default_karat;
        $making_charge = $making_charge !== '' ? $making_charge : $default_making_charge;
        ?>
        <div class="options_group">
            <p class="form-field _is_gold_product_field">
                <label for="<?php echo esc_attr( self::META_KEYS['is_gold_product'] ); ?>">
                    <input type="checkbox" 
                           id="<?php echo esc_attr( self::META_KEYS['is_gold_product'] ); ?>" 
                           name="<?php echo esc_attr( self::META_KEYS['is_gold_product'] ); ?>" 
                           value="yes" 
                           <?php checked( $is_gold, 'yes' ); ?>>
                    <?php esc_html_e( 'Gold Product', 'gold-gallery-companion' ); ?>
                </label>
                <span class="description"><?php esc_html_e( 'Enable gold pricing for this product', 'gold-gallery-companion' ); ?></span>
            </p>

            <p class="form-field _gold_karat_field gold-karat-field" style="<?php echo $is_gold !== 'yes' ? 'display:none;' : ''; ?>">
                <label for="<?php echo esc_attr( self::META_KEYS['gold_karat'] ); ?>">
                    <?php esc_html_e( 'Gold Karat', 'gold-gallery-companion' ); ?>
                </label>
                <select id="<?php echo esc_attr( self::META_KEYS['gold_karat'] ); ?>" 
                        name="<?php echo esc_attr( self::META_KEYS['gold_karat'] ); ?>" 
                        class="select">
                    <option value="18k" <?php selected( $karat, '18k' ); ?>>
                        <?php esc_html_e( '18K (750)', 'gold-gallery-companion' ); ?>
                    </option>
                    <option value="24k" <?php selected( $karat, '24k' ); ?>>
                        <?php esc_html_e( '24K (999)', 'gold-gallery-companion' ); ?>
                    </option>
                </select>
            </p>

            <p class="form-field _gold_weight_field gold-weight-field" style="<?php echo $is_gold !== 'yes' ? 'display:none;' : ''; ?>">
                <label for="<?php echo esc_attr( self::META_KEYS['gold_weight'] ); ?>">
                    <?php esc_html_e( 'Gold Weight (grams)', 'gold-gallery-companion' ); ?>
                </label>
                <input type="number" 
                       id="<?php echo esc_attr( self::META_KEYS['gold_weight'] ); ?>" 
                       name="<?php echo esc_attr( self::META_KEYS['gold_weight'] ); ?>" 
                       value="<?php echo esc_attr( $weight ); ?>" 
                       step="0.01" 
                       min="0" 
                       class="short">
            </p>

            <p class="form-field _making_charge_field gold-making-charge-field" style="<?php echo $is_gold !== 'yes' ? 'display:none;' : ''; ?>">
                <label for="<?php echo esc_attr( self::META_KEYS['making_charge'] ); ?>">
                    <?php esc_html_e( 'Making Charge (%)', 'gold-gallery-companion' ); ?>
                </label>
                <input type="number" 
                       id="<?php echo esc_attr( self::META_KEYS['making_charge'] ); ?>" 
                       name="<?php echo esc_attr( self::META_KEYS['making_charge'] ); ?>" 
                       value="<?php echo esc_attr( $making_charge ); ?>" 
                       step="0.1" 
                       min="0" 
                       max="100" 
                       class="short">
                <span class="description"><?php esc_html_e( 'Ojrat-e-Saakht percentage', 'gold-gallery-companion' ); ?></span>
            </p>

            <p class="form-field _branch_location_field gold-branch-field" style="<?php echo $is_gold !== 'yes' ? 'display:none;' : ''; ?>">
                <label for="<?php echo esc_attr( self::META_KEYS['branch_location'] ); ?>">
                    <?php esc_html_e( 'Branch/Location', 'gold-gallery-companion' ); ?>
                </label>
                <input type="text" 
                       id="<?php echo esc_attr( self::META_KEYS['branch_location'] ); ?>" 
                       name="<?php echo esc_attr( self::META_KEYS['branch_location'] ); ?>" 
                       value="<?php echo esc_attr( $branch ); ?>" 
                       class="short">
                <span class="description"><?php esc_html_e( 'Physical store location', 'gold-gallery-companion' ); ?></span>
            </p>
        </div>
        <?php
    }

    public function save_product_fields( $post_id ) {
        $is_gold = isset( $_POST[ self::META_KEYS['is_gold_product'] ] ) ? 'yes' : 'no';
        update_post_meta( $post_id, self::META_KEYS['is_gold_product'], $is_gold );

        if ( isset( $_POST[ self::META_KEYS['gold_karat'] ] ) ) {
            update_post_meta( $post_id, self::META_KEYS['gold_karat'], sanitize_text_field( $_POST[ self::META_KEYS['gold_karat'] ] ) );
        }

        if ( isset( $_POST[ self::META_KEYS['gold_weight'] ] ) ) {
            update_post_meta( $post_id, self::META_KEYS['gold_weight'], floatval( $_POST[ self::META_KEYS['gold_weight'] ] ) );
        }

        if ( isset( $_POST[ self::META_KEYS['making_charge'] ] ) ) {
            update_post_meta( $post_id, self::META_KEYS['making_charge'], floatval( $_POST[ self::META_KEYS['making_charge'] ] ) );
        }

        if ( isset( $_POST[ self::META_KEYS['branch_location'] ] ) ) {
            update_post_meta( $post_id, self::META_KEYS['branch_location'], sanitize_text_field( $_POST[ self::META_KEYS['branch_location'] ] ) );
        }
        
        if ( $is_gold === 'yes' ) {
            $calculator = Gold_Price_Calculator::get_instance();
            $calculator->save_price_to_db( $post_id, false );
        }
    }

    public function variation_fields( $loop, $variation_data, $variation ) {
        $is_gold = get_post_meta( $variation->ID, self::META_KEYS['is_gold_product'], true );
        $karat = get_post_meta( $variation->ID, self::META_KEYS['gold_karat'], true );
        $weight = get_post_meta( $variation->ID, self::META_KEYS['gold_weight'], true );
        $making_charge = get_post_meta( $variation->ID, self::META_KEYS['making_charge'], true );
        $branch = get_post_meta( $variation->ID, self::META_KEYS['branch_location'], true );
        
        $default_karat = Gold_Settings::get_setting( 'default_karat', '18k' );
        $default_making_charge = Gold_Settings::get_setting( 'default_making_charge', 10 );
        
        ?>
        <div class="gold-variation-fields">
            <p>
                <label>
                    <input type="checkbox" 
                           name="variation_is_gold[<?php echo esc_attr( $loop ); ?>]" 
                           value="yes" 
                           <?php checked( $is_gold, 'yes' ); ?>>
                    <?php esc_html_e( 'Gold Product', 'gold-gallery-companion' ); ?>
                </label>
            </p>
            
            <div class="gold-variation-options" style="<?php echo $is_gold !== 'yes' ? 'display:none;' : ''; ?>">
                <p>
                    <label><?php esc_html_e( 'Karat:', 'gold-gallery-companion' ); ?></label>
                    <select name="variation_gold_karat[<?php echo esc_attr( $loop ); ?>]">
                        <option value="18k" <?php selected( $karat ?: $default_karat, '18k' ); ?>><?php esc_html_e( '18K', 'gold-gallery-companion' ); ?></option>
                        <option value="24k" <?php selected( $karat ?: $default_karat, '24k' ); ?>><?php esc_html_e( '24K', 'gold-gallery-companion' ); ?></option>
                    </select>
                </p>
                
                <p>
                    <label><?php esc_html_e( 'Weight (g):', 'gold-gallery-companion' ); ?></label>
                    <input type="number" 
                           name="variation_gold_weight[<?php echo esc_attr( $loop ); ?>]" 
                           value="<?php echo esc_attr( $weight ); ?>" 
                           step="0.01" min="0">
                </p>
                
                <p>
                    <label><?php esc_html_e( 'Making Charge (%):', 'gold-gallery-companion' ); ?></label>
                    <input type="number" 
                           name="variation_making_charge[<?php echo esc_attr( $loop ); ?>]" 
                           value="<?php echo esc_attr( $making_charge !== '' ? $making_charge : $default_making_charge ); ?>" 
                           step="0.1" min="0" max="100">
                </p>
                
                <p>
                    <label><?php esc_html_e( 'Branch:', 'gold-gallery-companion' ); ?></label>
                    <input type="text" 
                           name="variation_branch_location[<?php echo esc_attr( $loop ); ?>]" 
                           value="<?php echo esc_attr( $branch ); ?>">
                </p>
            </div>
        </div>
        <?php
    }

    public function save_variation_fields( $variation_id, $loop ) {
        $is_gold = isset( $_POST[ 'variation_is_gold' ][ $loop ] ) ? 'yes' : 'no';
        update_post_meta( $variation_id, self::META_KEYS['is_gold_product'], $is_gold );

        if ( isset( $_POST[ 'variation_gold_karat' ][ $loop ] ) ) {
            update_post_meta( $variation_id, self::META_KEYS['gold_karat'], sanitize_text_field( $_POST[ 'variation_gold_karat' ][ $loop ] ) );
        }

        if ( isset( $_POST[ 'variation_gold_weight' ][ $loop ] ) ) {
            update_post_meta( $variation_id, self::META_KEYS['gold_weight'], floatval( $_POST[ 'variation_gold_weight' ][ $loop ] ) );
        }

        if ( isset( $_POST[ 'variation_making_charge' ][ $loop ] ) ) {
            update_post_meta( $variation_id, self::META_KEYS['making_charge'], floatval( $_POST[ 'variation_making_charge' ][ $loop ] ) );
        }

        if ( isset( $_POST[ 'variation_branch_location' ][ $loop ] ) ) {
            update_post_meta( $variation_id, self::META_KEYS['branch_location'], sanitize_text_field( $_POST[ 'variation_branch_location' ][ $loop ] ) );
        }
        
        if ( $is_gold === 'yes' ) {
            $calculator = Gold_Price_Calculator::get_instance();
            $calculator->save_price_to_db( $variation_id, true );
        }
    }

    public function filter_product_visibility( $visible, $product_id ) {
        return $visible;
    }

    public static function is_gold_product( $product_id ) {
        $is_gold = get_post_meta( $product_id, self::META_KEYS['is_gold_product'], true );
        return $is_gold === 'yes';
    }

    public static function get_gold_data( $product_id ) {
        $product = wc_get_product( $product_id );
        
        if ( ! $product ) {
            return array();
        }

        return array(
            'is_gold'       => $product->get_meta( self::META_KEYS['is_gold_product'], true ) === 'yes',
            'karat'         => $product->get_meta( self::META_KEYS['gold_karat'], true ) ?: Gold_Settings::get_setting( 'default_karat', '18k' ),
            'weight'        => floatval( $product->get_meta( self::META_KEYS['gold_weight'], true ) ),
            'making_charge' => floatval( $product->get_meta( self::META_KEYS['making_charge'], true ) ) ?: Gold_Settings::get_setting( 'default_making_charge', 10 ),
            'branch'        => $product->get_meta( self::META_KEYS['branch_location'], true ),
        );
    }
}
