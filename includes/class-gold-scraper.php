<?php

class Gold_Scraper {

    private static $instance = null;

    const TGJU_URL_18K = 'https://www.tgju.org/profile/geram18';
    const TGJU_URL_24K = 'https://www.tgju.org/profile/geram24';

    const TRANSIENT_KEY_18K = 'gold_price_18k';
    const TRANSIENT_KEY_24K = 'gold_price_24k';
    const TRANSIENT_TIMEOUT = GOLD_GALLERY_TRANSIENT_TIMEOUT;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function get_gold_price( $karat = '18k' ) {
        $karat = strtolower( $karat );
        
        if ( $karat === '24k' ) {
            return $this->get_price_24k();
        }
        
        return $this->get_price_18k();
    }

    public function get_price_18k() {
        $cached_price = get_transient( self::TRANSIENT_KEY_18K );
        
        if ( false !== $cached_price ) {
            return $cached_price;
        }

        $price = $this->fetch_price_from_tgju( self::TGJU_URL_18K );
        
        if ( $price > 0 ) {
            set_transient( self::TRANSIENT_KEY_18K, $price, self::TRANSIENT_TIMEOUT );
            return $price;
        }

        $manual_price = $this->get_manual_price( '18k' );
        if ( $manual_price > 0 ) {
            return $manual_price;
        }

        return 0;
    }

    public function get_price_24k() {
        $cached_price = get_transient( self::TRANSIENT_KEY_24K );
        
        if ( false !== $cached_price ) {
            return $cached_price;
        }

        $price = $this->fetch_price_from_tgju( self::TGJU_URL_24K );
        
        if ( $price > 0 ) {
            set_transient( self::TRANSIENT_KEY_24K, $price, self::TRANSIENT_TIMEOUT );
            return $price;
        }

        $price_18k = $this->get_price_18k();
        if ( $price_18k > 0 ) {
            $price_24k = ( $price_18k * 24 ) / 18;
            set_transient( self::TRANSIENT_KEY_24K, $price_24k, self::TRANSIENT_TIMEOUT );
            return $price_24k;
        }

        $manual_price = $this->get_manual_price( '24k' );
        if ( $manual_price > 0 ) {
            return $manual_price;
        }

        return 0;
    }

    private function fetch_price_from_tgju( $url ) {
        $response = wp_remote_get( $url, array(
            'timeout'   => 30,
            'sslverify' => false,
            'headers'   => array(
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
            ),
        ) );

        if ( is_wp_error( $response ) ) {
            return 0;
        }

        $body = wp_remote_retrieve_body( $response );
        
        if ( empty( $body ) ) {
            return 0;
        }

        $price = $this->parse_price_from_html( $body );

        return $price;
    }

    private function parse_price_from_html( $html ) {
        $price = 0;

        if ( preg_match( '/<td\s+class="text-right"[^>]*>نرخ\s+فعلی<\/td>\s*<td\s+class="text-left"[^>]*>([\d,]+)<\/td>/', $html, $matches ) ) {
            $price = isset( $matches[1] ) ? $matches[1] : '';
        }

        if ( empty( $price ) && preg_match( '/نرخ\s+فعلی[\s\S]*?<td[^>]*class="text-left"[^>]*>([\d,]+)/', $html, $matches ) ) {
            $price = isset( $matches[1] ) ? $matches[1] : '';
        }

        if ( empty( $price ) ) {
            $dom = new DOMDocument();
            libxml_use_internal_errors( true );
            $dom->loadHTML( $html );
            libxml_clear_errors();

            $xpath = new DOMXPath( $dom );
            $cells = $xpath->query( '//td[@class="text-right"]' );

            foreach ( $cells as $cell ) {
                if ( trim( $cell->textContent ) === 'نرخ فعلی' ) {
                    $next_sibling = $cell->nextSibling;
                    while ( $next_sibling && $next_sibling->nodeType !== XML_ELEMENT_NODE ) {
                        $next_sibling = $next_sibling->nextSibling;
                    }
                    if ( $next_sibling && $next_sibling->nodeType === XML_ELEMENT_NODE ) {
                        $price = trim( $next_sibling->textContent );
                    }
                    break;
                }
            }
        }

        if ( empty( $price ) ) {
            return 0;
        }

        $price = str_replace( ',', '', $price );
        $price = intval( trim( $price ) );

        return $price;
    }

    private function get_manual_price( $karat ) {
        $settings = get_option( 'gold_gallery_settings', array() );
        
        $key = 'manual_price_' . $karat;
        
        if ( isset( $settings[ $key ] ) && ! empty( $settings[ $key ] ) ) {
            return intval( $settings[ $key ] );
        }
        
        return 0;
    }

    public function get_price_in_toman( $karat = '18k' ) {
        $price_rial = $this->get_gold_price( $karat );
        
        if ( $price_rial <= 0 ) {
            return 0;
        }
        
        return $price_rial / 10;
    }

    public function get_display_price( $karat = '18k' ) {
        $settings = get_option( 'gold_gallery_settings', array() );
        $display_format = isset( $settings['price_display'] ) ? $settings['price_display'] : 'toman';
        
        $price_rial = $this->get_gold_price( $karat );
        
        if ( $price_rial <= 0 ) {
            return 0;
        }
        
        if ( 'rial' === $display_format ) {
            return $price_rial;
        }
        
        return $price_rial / 10;
    }

    public function refresh_prices() {
        delete_transient( self::TRANSIENT_KEY_18K );
        delete_transient( self::TRANSIENT_KEY_24K );
        
        $this->get_price_18k();
        $this->get_price_24k();
    }

    public function clear_cache() {
        delete_transient( self::TRANSIENT_KEY_18K );
        delete_transient( self::TRANSIENT_KEY_24K );
    }
}
