<?php

class Gold_Settings {

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
    }

    public function add_admin_menu() {
        add_submenu_page(
            'woocommerce',
            __( 'Gold Settings', 'gold-gallery-companion' ),
            __( 'Gold Settings', 'gold-gallery-companion' ),
            'manage_options',
            'gold-settings',
            array( $this, 'render_settings_page' )
        );
    }

    public function register_settings() {
        register_setting( 'gold_gallery_settings_group', 'gold_gallery_settings', array(
            'sanitize_callback' => array( $this, 'sanitize_settings' ),
        ) );

        add_settings_section(
            'gold_pricing_section',
            __( 'Gold Pricing Settings', 'gold-gallery-companion' ),
            array( $this, 'pricing_section_callback' ),
            'gold-settings'
        );

        add_settings_field(
            'enable_auto_update',
            __( 'Enable Auto Price Update', 'gold-gallery-companion' ),
            array( $this, 'checkbox_field_callback' ),
            'gold-settings',
            'gold_pricing_section',
            array(
                'id'          => 'enable_auto_update',
                'label'       => __( 'Automatically update gold prices from the selected source', 'gold-gallery-companion' ),
                'default'     => 'yes',
            )
        );

        add_settings_field(
            'price_source',
            __( 'Price Source', 'gold-gallery-companion' ),
            array( $this, 'select_field_callback' ),
            'gold-settings',
            'gold_pricing_section',
            array(
                'id'          => 'price_source',
                'options'     => array(
                    'tgju' => __( 'TGJU (tgju.org)', 'gold-gallery-companion' ),
                    'tala' => __( 'tala.ir', 'gold-gallery-companion' ),
                ),
                'description' => __( 'Primary source for automatic gold prices. The other source is used as a fallback, and if both are unavailable the highest price recorded today is used.', 'gold-gallery-companion' ),
                'default'     => 'tgju',
            )
        );

        add_settings_field(
            'default_karat',
            __( 'Default Gold Karat', 'gold-gallery-companion' ),
            array( $this, 'select_field_callback' ),
            'gold-settings',
            'gold_pricing_section',
            array(
                'id'          => 'default_karat',
                'options'     => array(
                    '18k' => __( '18K (750)', 'gold-gallery-companion' ),
                    '24k' => __( '24K (999)', 'gold-gallery-companion' ),
                ),
                'description' => __( 'Default karat for new products', 'gold-gallery-companion' ),
                'default'     => '18k',
            )
        );

        add_settings_field(
            'default_making_charge',
            __( 'Default Making Charge (%)', 'gold-gallery-companion' ),
            array( $this, 'number_field_callback' ),
            'gold-settings',
            'gold_pricing_section',
            array(
                'id'          => 'default_making_charge',
                'description' => __( 'Default Ojrat-e-Saakht percentage', 'gold-gallery-companion' ),
                'default'     => 10,
                'min'         => 0,
                'max'         => 100,
                'step'        => 0.1,
            )
        );

        add_settings_field(
            'profit_margin',
            __( 'Profit Margin (%)', 'gold-gallery-companion' ),
            array( $this, 'number_field_callback' ),
            'gold-settings',
            'gold_pricing_section',
            array(
                'id'          => 'profit_margin',
                'description' => __( 'Profit margin percentage', 'gold-gallery-companion' ),
                'default'     => 7,
                'min'         => 0,
                'max'         => 100,
                'step'        => 0.1,
            )
        );

        add_settings_field(
            'vat',
            __( 'VAT (%)', 'gold-gallery-companion' ),
            array( $this, 'number_field_callback' ),
            'gold-settings',
            'gold_pricing_section',
            array(
                'id'          => 'vat',
                'description' => __( 'Value Added Tax percentage', 'gold-gallery-companion' ),
                'default'     => 9,
                'min'         => 0,
                'max'         => 100,
                'step'        => 0.1,
            )
        );

        add_settings_field(
            'price_display',
            __( 'Price Display Format', 'gold-gallery-companion' ),
            array( $this, 'select_field_callback' ),
            'gold-settings',
            'gold_pricing_section',
            array(
                'id'          => 'price_display',
                'options'     => array(
                    'toman' => __( 'Toman', 'gold-gallery-companion' ),
                    'rial'  => __( 'Rial', 'gold-gallery-companion' ),
                ),
                'description' => __( 'How to display prices to customers', 'gold-gallery-companion' ),
                'default'     => 'toman',
            )
        );

        add_settings_section(
            'gold_manual_section',
            __( 'Manual Price Fallback', 'gold-gallery-companion' ),
            array( $this, 'manual_section_callback' ),
            'gold-settings'
        );

        add_settings_field(
            'manual_price_18k',
            __( 'Manual 18K Price (Rial/gram)', 'gold-gallery-companion' ),
            array( $this, 'number_field_callback' ),
            'gold-settings',
            'gold_manual_section',
            array(
                'id'          => 'manual_price_18k',
                'description' => __( 'Optional: Used when auto-fetch fails', 'gold-gallery-companion' ),
                'default'     => '',
            )
        );

        add_settings_field(
            'manual_price_24k',
            __( 'Manual 24K Price (Rial/gram)', 'gold-gallery-companion' ),
            array( $this, 'number_field_callback' ),
            'gold-settings',
            'gold_manual_section',
            array(
                'id'          => 'manual_price_24k',
                'description' => __( 'Optional: Used when auto-fetch fails', 'gold-gallery-companion' ),
                'default'     => '',
            )
        );
    }

    public function sanitize_settings( $input ) {
        $sanitized = array();

        if ( isset( $input['enable_auto_update'] ) ) {
            $sanitized['enable_auto_update'] = 'yes';
        } else {
            $sanitized['enable_auto_update'] = 'no';
        }

        $sanitized['default_karat'] = isset( $input['default_karat'] ) && in_array( $input['default_karat'], array( '18k', '24k' ) ) ? $input['default_karat'] : '18k';

        $sanitized['price_source'] = isset( $input['price_source'] ) && in_array( $input['price_source'], array( 'tgju', 'tala' ), true ) ? $input['price_source'] : 'tgju';

        $sanitized['default_making_charge'] = isset( $input['default_making_charge'] ) ? floatval( $input['default_making_charge'] ) : 10;
        $sanitized['profit_margin'] = isset( $input['profit_margin'] ) ? floatval( $input['profit_margin'] ) : 7;
        $sanitized['vat'] = isset( $input['vat'] ) ? floatval( $input['vat'] ) : 9;

        $sanitized['price_display'] = isset( $input['price_display'] ) && in_array( $input['price_display'], array( 'toman', 'rial' ) ) ? $input['price_display'] : 'toman';

        $sanitized['manual_price_18k'] = isset( $input['manual_price_18k'] ) ? floatval( $input['manual_price_18k'] ) : '';
        $sanitized['manual_price_24k'] = isset( $input['manual_price_24k'] ) ? floatval( $input['manual_price_24k'] ) : '';

        return $sanitized;
    }

    public function pricing_section_callback() {
        echo '<p>' . esc_html__( 'Configure gold pricing and display settings.', 'gold-gallery-companion' ) . '</p>';
    }

    public function manual_section_callback() {
        echo '<p>' . esc_html__( 'Set manual prices as fallback when the selected price source is unavailable. Enter prices in Rial (not Toman).', 'gold-gallery-companion' ) . '</p>';
    }

    public function checkbox_field_callback( $args ) {
        $options = get_option( 'gold_gallery_settings', array() );
        $value = isset( $options[ $args['id'] ] ) ? $options[ $args['id'] ] : $args['default'];
        ?>
        <input type="checkbox" id="<?php echo esc_attr( $args['id'] ); ?>" name="gold_gallery_settings[<?php echo esc_attr( $args['id'] ); ?>]" value="yes" <?php checked( $value, 'yes' ); ?>>
        <label for="<?php echo esc_attr( $args['id'] ); ?>"><?php echo esc_html( $args['label'] ); ?></label>
        <?php
    }

    public function select_field_callback( $args ) {
        $options = get_option( 'gold_gallery_settings', array() );
        $value = isset( $options[ $args['id'] ] ) ? $options[ $args['id'] ] : $args['default'];
        ?>
        <select id="<?php echo esc_attr( $args['id'] ); ?>" name="gold_gallery_settings[<?php echo esc_attr( $args['id'] ); ?>]">
            <?php foreach ( $args['options'] as $option_value => $option_label ) : ?>
                <option value="<?php echo esc_attr( $option_value ); ?>" <?php selected( $value, $option_value ); ?>><?php echo esc_html( $option_label ); ?></option>
            <?php endforeach; ?>
        </select>
        <?php if ( ! empty( $args['description'] ) ) : ?>
            <p class="description"><?php echo esc_html( $args['description'] ); ?></p>
        <?php endif;
    }

    public function number_field_callback( $args ) {
        $options = get_option( 'gold_gallery_settings', array() );
        $value = isset( $options[ $args['id'] ] ) ? $options[ $args['id'] ] : $args['default'];
        ?>
        <input type="number" 
               id="<?php echo esc_attr( $args['id'] ); ?>" 
               name="gold_gallery_settings[<?php echo esc_attr( $args['id'] ); ?>]" 
               value="<?php echo esc_attr( $value ); ?>"
               <?php if ( isset( $args['min'] ) ) : ?> min="<?php echo esc_attr( $args['min'] ); ?>"<?php endif; ?>
               <?php if ( isset( $args['max'] ) ) : ?> max="<?php echo esc_attr( $args['max'] ); ?>"<?php endif; ?>
               <?php if ( isset( $args['step'] ) ) : ?> step="<?php echo esc_attr( $args['step'] ); ?>"<?php endif; ?>
               class="small-text">
        <?php if ( ! empty( $args['description'] ) ) : ?>
            <p class="description"><?php echo esc_html( $args['description'] ); ?></p>
        <?php endif;
    }

    public function render_settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $scraper = Gold_Scraper::get_instance();
        $price_18k = $scraper->get_gold_price( '18k' );
        $price_24k = $scraper->get_gold_price( '24k' );

        $price_18k_toman = $price_18k > 0 ? number_format( $price_18k / 10 ) : 'N/A';
        $price_24k_toman = $price_24k > 0 ? number_format( $price_24k / 10 ) : 'N/A';

        $cache_time = get_site_option( '_site_transient_timeout_' . $scraper->get_transient_key( '18k' ), false );
        $time_remaining = $cache_time ? human_time_diff( time(), $cache_time ) : 'N/A';

        $active_source = $scraper->get_source();

        $last_update = get_option( 'gold_last_price_update', false );
        $last_update_text = $last_update ? human_time_diff( $last_update, time() ) : __( 'Never', 'gold-gallery-companion' );
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

            <div class="gold-price-info-box" style="background: #fff; padding: 20px; margin-bottom: 20px; border: 1px solid #ccd0d4;">
                <h2><?php esc_html_e( 'Current Gold Prices', 'gold-gallery-companion' ); ?></h2>
                <p><strong><?php esc_html_e( '18K Gold:', 'gold-gallery-companion' ); ?></strong> <?php echo esc_html( $price_18k_toman ); ?> <?php esc_html_e( 'Toman/gram', 'gold-gallery-companion' ); ?></p>
                <p><strong><?php esc_html_e( '24K Gold:', 'gold-gallery-companion' ); ?></strong> <?php echo esc_html( $price_24k_toman ); ?> <?php esc_html_e( 'Toman/gram', 'gold-gallery-companion' ); ?></p>
                <p><strong><?php esc_html_e( 'Active Source:', 'gold-gallery-companion' ); ?></strong> <?php echo esc_html( 'tala' === $active_source ? __( 'tala.ir', 'gold-gallery-companion' ) : __( 'TGJU', 'gold-gallery-companion' ) ); ?></p>
                <p class="description"><?php esc_html_e( 'Prices refresh automatically every 1 hour.', 'gold-gallery-companion' ); ?></p>
                <p class="description"><?php printf( esc_html__( 'Next refresh in: %s', 'gold-gallery-companion' ), '<strong>' . esc_html( $time_remaining ) . '</strong>' ); ?></p>
                <button type="button" class="button button-secondary" id="gold-refresh-prices">
                    <?php esc_html_e( 'Refresh Gold Prices', 'gold-gallery-companion' ); ?>
                </button>
            </div>

            <div class="gold-update-info-box" style="background: #fff; padding: 20px; margin-bottom: 20px; border: 1px solid #ccd0d4;">
                <h2><?php esc_html_e( 'Update Product Prices', 'gold-gallery-companion' ); ?></h2>
                <p><strong><?php esc_html_e( 'Last Update:', 'gold-gallery-companion' ); ?></strong> <?php echo esc_html( $last_update_text ); ?></p>
                <p class="description"><?php esc_html_e( 'Calculate and save gold prices to database for all products.', 'gold-gallery-companion' ); ?></p>
                <p class="description"><?php esc_html_e( 'Prices are automatically updated hourly via cron job.', 'gold-gallery-companion' ); ?></p>
                <form method="post">
                    <?php wp_nonce_field( 'gold_update_products', 'gold_update_products_nonce' ); ?>
                    <button type="submit" name="gold_update_all_products" class="button button-primary" id="gold-update-all-products">
                        <?php esc_html_e( 'Update All Product Prices Now', 'gold-gallery-companion' ); ?>
                    </button>
                </form>
            </div>

            <form method="post" action="options.php">
                <?php settings_fields( 'gold_gallery_settings_group' ); ?>
                <?php do_settings_sections( 'gold-settings' ); ?>
                <?php submit_button( __( 'Save Settings', 'gold-gallery-companion' ) ); ?>
            </form>
        </div>

        <?php
        if ( isset( $_POST['gold_update_all_products'] ) && isset( $_POST['gold_update_products_nonce'] ) && wp_verify_nonce( $_POST['gold_update_products_nonce'], 'gold_update_products' ) ) {
            $updated = Gold_Price_Calculator::manual_refresh_prices();
            update_option( 'gold_last_price_update', current_time( 'timestamp' ) );
            ?>
            <div class="notice notice-success is-dismissible">
                <p><?php esc_html_e( 'All gold product prices have been updated!', 'gold-gallery-companion' ); ?></p>
            </div>
            <?php
        }
    }

    public static function get_setting( $key, $default = '' ) {
        $settings = get_option( 'gold_gallery_settings', array() );
        return isset( $settings[ $key ] ) ? $settings[ $key ] : $default;
    }
}
