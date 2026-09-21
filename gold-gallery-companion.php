<?php
/**
 * Plugin Name: Gold Gallery Companion
 * Plugin URI: https://site0.ir/
 * Description: WooCommerce plugin for selling gold products with automatic price calculation based on Iranian gold pricing formula
 * Version: 1.4.2
 * Author: Mahyar Rezghi
 * Author URI: https://site0.ir/
 * Text Domain: gold-gallery-companion
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'GOLD_GALLERY_VERSION', '1.4.2' );
define( 'GOLD_GALLERY_PATH', plugin_dir_path( __FILE__ ) );
define( 'GOLD_GALLERY_URL', plugin_dir_url( __FILE__ ) );
define( 'GOLD_GALLERY_TRANSIENT_TIMEOUT', 3600 );

class Gold_Gallery_Companion {

    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->init_hooks();
    }

    private function init_hooks() {
        add_action( 'plugins_loaded', array( $this, 'load_textdomain' ), 5 );
        add_action( 'init', array( $this, 'init_classes' ) );
        add_action( 'init', array( $this, 'schedule_cron_jobs' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_assets' ) );
        add_action( 'wp_ajax_gold_refresh_prices', array( $this, 'ajax_refresh_prices' ) );
    }

    public function ajax_refresh_prices() {
        check_ajax_referer( 'gold_gallery_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'gold-gallery-companion' ) ) );
        }

        if ( ! class_exists( 'Gold_Scraper' ) ) {
            wp_send_json_error( array( 'message' => __( 'Gold pricing is unavailable.', 'gold-gallery-companion' ) ) );
        }

        $prices = Gold_Scraper::get_instance()->refresh_prices();

        Gold_Price_Calculator::manual_refresh_prices();

        wp_send_json_success( array(
            '18k' => isset( $prices['18k'] ) ? $prices['18k'] : 0,
            '24k' => isset( $prices['24k'] ) ? $prices['24k'] : 0,
        ) );
    }

    public function schedule_cron_jobs() {
        if ( ! wp_next_scheduled( 'gold_update_all_prices' ) ) {
            wp_schedule_event( time(), 'hourly', 'gold_update_all_prices' );
        }
    }

    public function load_textdomain() {
        load_plugin_textdomain( 'gold-gallery-companion', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
    }

    public function init_classes() {
        if ( ! class_exists( 'WooCommerce' ) ) {
            add_action( 'admin_notices', array( $this, 'woocommerce_missing_notice' ) );
            return;
        }

        require_once GOLD_GALLERY_PATH . 'includes/class-gold-scraper.php';
        require_once GOLD_GALLERY_PATH . 'includes/class-gold-settings.php';
        require_once GOLD_GALLERY_PATH . 'includes/class-gold-product-meta.php';
        require_once GOLD_GALLERY_PATH . 'includes/class-gold-price-calculator.php';
        require_once GOLD_GALLERY_PATH . 'includes/class-gold-frontend.php';

        new Gold_Settings();
        Gold_Scraper::get_instance();
        Gold_Product_Meta::get_instance();
        Gold_Price_Calculator::get_instance();
        Gold_Frontend::get_instance();
    }

    public function enqueue_assets() {
        wp_enqueue_style( 'gold-gallery-style', GOLD_GALLERY_URL . 'assets/css/gold-style.css', array(), GOLD_GALLERY_VERSION );
        wp_enqueue_script( 'gold-gallery-script', GOLD_GALLERY_URL . 'assets/js/gold-script.js', array( 'jquery' ), GOLD_GALLERY_VERSION, true );
        
        wp_localize_script( 'gold-gallery-script', 'gold_gallery_vars', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'gold_gallery_nonce' ),
            'i18n'     => array(
                'price_reserved'   => __( 'Price reserved for', 'gold-gallery-companion' ),
                'minutes_left'     => __( 'minutes left', 'gold-gallery-companion' ),
                'price_updated'    => __( 'Price updated - prices have changed', 'gold-gallery-companion' ),
                'calculating'      => __( 'Calculating...', 'gold-gallery-companion' ),
            )
        ) );
    }

    public function admin_enqueue_assets( $hook ) {
        if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ) ) ) {
            return;
        }
        
        global $post_type;
        if ( 'product' !== $post_type ) {
            return;
        }

        wp_enqueue_style( 'gold-gallery-admin-style', GOLD_GALLERY_URL . 'assets/css/gold-admin.css', array(), GOLD_GALLERY_VERSION );
        wp_enqueue_script( 'gold-gallery-admin-script', GOLD_GALLERY_URL . 'assets/js/gold-admin.js', array( 'jquery' ), GOLD_GALLERY_VERSION, true );
        
        wp_localize_script( 'gold-gallery-admin-script', 'gold_gallery_vars', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'gold_gallery_nonce' ),
        ) );
    }

    public function woocommerce_missing_notice() {
        ?>
        <div class="notice notice-error">
            <p><?php esc_html_e( 'Gold Gallery Companion requires WooCommerce to be installed and active.', 'gold-gallery-companion' ); ?></p>
        </div>
        <?php
    }

    public static function activate( $network_wide = false ) {
        if ( ! class_exists( 'WooCommerce' ) ) {
            deactivate_plugins( plugin_basename( __FILE__ ), false, $network_wide );
            wp_die( esc_html__( 'Gold Gallery Companion requires WooCommerce to be installed and active.', 'gold-gallery-companion' ) );
        }
        
        if ( false === get_option( 'gold_gallery_settings' ) ) {
            $default_settings = array(
                'enable_auto_update'     => 'yes',
                'price_source'           => 'tgju',
                'default_karat'         => '18k',
                'default_making_charge' => 10,
                'profit_margin'         => 7,
                'vat'                   => 10,
                'price_display'         => 'toman',
            );
            update_option( 'gold_gallery_settings', $default_settings );
        }
    }

    public static function deactivate( $network_wide = false ) {
        foreach ( array( 'tgju', 'tala' ) as $source ) {
            delete_transient( 'gold_price_18k_' . $source );
            delete_transient( 'gold_price_24k_' . $source );
        }

        delete_transient( 'gold_price_18k' );
        delete_transient( 'gold_price_24k' );

        if ( $network_wide || ! is_multisite() ) {
            self::delete_global_data();
        }
    }

    public static function uninstall() {
        self::delete_global_data();

        foreach ( array( 'tgju', 'tala' ) as $source ) {
            delete_transient( 'gold_price_18k_' . $source );
            delete_transient( 'gold_price_24k_' . $source );
        }

        delete_transient( 'gold_price_18k' );
        delete_transient( 'gold_price_24k' );
    }

    public static function delete_global_data() {
        foreach ( array( 'tgju', 'tala' ) as $source ) {
            delete_site_transient( 'gold_price_18k_' . $source );
            delete_site_transient( 'gold_price_24k_' . $source );
            delete_site_option( 'gold_price_fetch_lock_' . $source );
        }

        delete_site_transient( 'gold_price_18k' );
        delete_site_transient( 'gold_price_24k' );
        delete_site_option( 'gold_price_highs' );
        delete_site_option( 'gold_price_last_known' );
    }
}

register_activation_hook( __FILE__, array( 'Gold_Gallery_Companion', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Gold_Gallery_Companion', 'deactivate' ) );
register_uninstall_hook( __FILE__, array( 'Gold_Gallery_Companion', 'uninstall' ) );

function Gold_Gallery() {
    return Gold_Gallery_Companion::get_instance();
}

Gold_Gallery();
