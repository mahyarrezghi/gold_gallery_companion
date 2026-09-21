<?php

class Gold_Scraper {

    private static $instance = null;

    const TGJU_URL_18K = 'https://www.tgju.org/profile/geram18';
    const TGJU_URL_24K = 'https://www.tgju.org/profile/geram24';
    const TALA_URL_18K = 'https://www.tala.ir/price/18k';

    const TRANSIENT_KEY_18K = 'gold_price_18k';
    const TRANSIENT_KEY_24K = 'gold_price_24k';
    const TRANSIENT_TIMEOUT = GOLD_GALLERY_TRANSIENT_TIMEOUT;

    const SOURCE_TGJU = 'tgju';
    const SOURCE_TALA = 'tala';

    const HIGHS_OPTION = 'gold_price_highs';
    const LAST_KNOWN_OPTION = 'gold_price_last_known';
    const LOCK_OPTION_PREFIX = 'gold_price_fetch_lock_';
    const LOCK_TTL = 120;

    const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36';

    private $tala_parsed = null;
    private $tala_fetched = false;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function get_sources() {
        return array( self::SOURCE_TGJU, self::SOURCE_TALA );
    }

    public function get_source() {
        $source = Gold_Settings::get_setting( 'price_source', self::SOURCE_TGJU );
        $source = strtolower( (string) $source );

        return in_array( $source, $this->get_sources(), true ) ? $source : self::SOURCE_TGJU;
    }

    private function get_other_source( $source ) {
        return ( self::SOURCE_TALA === $source ) ? self::SOURCE_TGJU : self::SOURCE_TALA;
    }

    public function get_transient_key( $karat, $source = null ) {
        $karat = ( '24k' === strtolower( (string) $karat ) ) ? '24k' : '18k';

        if ( null === $source ) {
            $source = $this->get_source();
        }

        $base = ( '24k' === $karat ) ? self::TRANSIENT_KEY_24K : self::TRANSIENT_KEY_18K;

        return $base . '_' . $source;
    }

    public function get_gold_price( $karat = '18k' ) {
        $karat = strtolower( $karat );

        if ( $karat === '24k' ) {
            return $this->get_price_24k();
        }

        return $this->get_price_18k();
    }

    public function get_price_18k() {
        $source = $this->get_source();
        $other  = $this->get_other_source( $source );

        $cached_price = get_site_transient( $this->get_transient_key( '18k', $source ) );

        if ( false !== $cached_price && $cached_price > 0 ) {
            return $cached_price;
        }

        $locked = false;
        $price  = $this->try_fetch_18k( $source, $locked );

        if ( $price > 0 ) {
            return $price;
        }

        if ( ! $locked ) {
            $price = $this->try_fetch_18k( $other, $locked );

            if ( $price > 0 ) {
                set_site_transient( $this->get_transient_key( '18k', $source ), $price, self::TRANSIENT_TIMEOUT );
                return $price;
            }
        }

        return $this->get_fallback_18k( $source, $other );
    }

    public function get_price_24k() {
        $source = $this->get_source();
        $other  = $this->get_other_source( $source );

        $cached_price = get_site_transient( $this->get_transient_key( '24k', $source ) );

        if ( false !== $cached_price && $cached_price > 0 ) {
            return $cached_price;
        }

        if ( self::SOURCE_TALA === $source ) {
            $price_18k = $this->get_price_18k();

            if ( $price_18k > 0 ) {
                $price = $price_18k * 24 / 18;

                if ( false !== get_site_transient( $this->get_transient_key( '18k', $source ) ) ) {
                    $this->record_high( '24k', $price, $source );
                    $this->record_last_known( '24k', $price, $source );
                    set_site_transient( $this->get_transient_key( '24k', $source ), $price, self::TRANSIENT_TIMEOUT );
                }

                return $price;
            }
        } else {
            $locked = false;
            $price  = $this->try_fetch_24k( $source, $locked );

            if ( $price > 0 ) {
                return $price;
            }

            if ( ! $locked ) {
                $price_18k = $this->get_price_18k();

                if ( $price_18k > 0 ) {
                    $price = $price_18k * 24 / 18;

                    if ( false !== get_site_transient( $this->get_transient_key( '18k', $source ) )
                        || false !== get_site_transient( $this->get_transient_key( '18k', $other ) ) ) {
                        $this->record_high( '24k', $price, $source );
                        $this->record_last_known( '24k', $price, $source );
                        set_site_transient( $this->get_transient_key( '24k', $source ), $price, self::TRANSIENT_TIMEOUT );
                    }

                    return $price;
                }
            }
        }

        return $this->get_fallback_24k( $source, $other );
    }

    private function get_fallback_18k( $source, $other ) {
        $fallback = $this->get_last_known( '18k', $source );

        if ( $fallback <= 0 ) {
            $fallback = $this->get_last_known( '18k', $other );
        }

        if ( $fallback <= 0 ) {
            $fallback = $this->get_daily_high( '18k', $source );
        }

        if ( $fallback <= 0 ) {
            $fallback = $this->get_daily_high( '18k', $other );
        }

        if ( $fallback <= 0 ) {
            $fallback = $this->get_manual_price( '18k' );
        }

        return $fallback > 0 ? $fallback : 0;
    }

    private function get_fallback_24k( $source, $other ) {
        $fallback = $this->get_last_known( '24k', $source );

        if ( $fallback <= 0 ) {
            $fallback = $this->get_last_known( '24k', $other );
        }

        if ( $fallback <= 0 ) {
            $fallback = $this->get_daily_high( '24k', $source );
        }

        if ( $fallback <= 0 ) {
            $fallback = $this->get_daily_high( '24k', $other );
        }

        if ( $fallback <= 0 ) {
            $fallback = $this->get_manual_price( '24k' );
        }

        return $fallback > 0 ? $fallback : 0;
    }

    private function try_fetch_18k( $source, &$locked ) {
        $locked = false;

        if ( ! $this->acquire_fetch_lock( $source ) ) {
            $locked = true;
            return 0;
        }

        $price = $this->fetch_price( '18k', $source );

        if ( $price > 0 ) {
            $this->store_live_price( '18k', $price, $source );
        }

        $this->release_fetch_lock( $source );

        return $price;
    }

    private function try_fetch_24k( $source, &$locked ) {
        $locked = false;

        if ( ! $this->acquire_fetch_lock( $source ) ) {
            $locked = true;
            return 0;
        }

        $price = $this->fetch_price( '24k', $source );

        if ( $price > 0 ) {
            $this->store_live_price( '24k', $price, $source );
        }

        $this->release_fetch_lock( $source );

        return $price;
    }

    private function store_live_price( $karat, $price, $source ) {
        $this->record_high( $karat, $price, $source );
        $this->record_last_known( $karat, $price, $source );
        set_site_transient( $this->get_transient_key( $karat, $source ), $price, self::TRANSIENT_TIMEOUT );
    }

    private function acquire_fetch_lock( $source ) {
        $key = self::LOCK_OPTION_PREFIX . $source;
        $now = time();

        if ( add_site_option( $key, $now ) ) {
            return true;
        }

        $existing = (int) get_site_option( $key );

        if ( $existing > 0 && ( $now - $existing ) > self::LOCK_TTL ) {
            update_site_option( $key, $now );
            return true;
        }

        return false;
    }

    private function release_fetch_lock( $source ) {
        delete_site_option( self::LOCK_OPTION_PREFIX . $source );
    }

    private function fetch_price( $karat, $source ) {
        $karat = strtolower( $karat );

        if ( self::SOURCE_TALA === $source ) {
            if ( '18k' !== $karat ) {
                return 0;
            }

            return $this->fetch_price_from_tala( '18k' );
        }

        $url = ( '24k' === $karat ) ? self::TGJU_URL_24K : self::TGJU_URL_18K;

        return $this->fetch_price_from_tgju( $url );
    }

    private function fetch_url( $url ) {
        $response = wp_remote_get( $url, array(
            'timeout'   => 30,
            'sslverify' => false,
            'headers'   => array(
                'User-Agent'      => self::USER_AGENT,
                'Accept-Language' => 'fa-IR,fa;q=0.9,en;q=0.8',
            ),
        ) );

        if ( is_wp_error( $response ) ) {
            return '';
        }

        return (string) wp_remote_retrieve_body( $response );
    }

    private function fetch_price_from_tgju( $url ) {
        $body = $this->fetch_url( $url );

        if ( empty( $body ) ) {
            return 0;
        }

        return $this->parse_price_from_html( $body );
    }

    private function fetch_price_from_tala( $karat = '18k' ) {
        if ( ! $this->tala_fetched ) {
            $this->tala_fetched = true;
            $this->tala_parsed  = $this->parse_tala_prices( $this->fetch_url( self::TALA_URL_18K ) );
        }

        if ( is_array( $this->tala_parsed ) && isset( $this->tala_parsed[ $karat ] ) ) {
            return $this->tala_parsed[ $karat ];
        }

        return 0;
    }

    private function parse_tala_prices( $html ) {
        $result = array( '18k' => 0 );

        if ( empty( $html ) ) {
            return $result;
        }

        $price = $this->extract_tala_last_price( $html );

        if ( $price <= 0 ) {
            $price = $this->extract_tala_card_value( $html, 'عیار 750 یا 18' );
        }

        if ( $price > 0 ) {
            $result['18k'] = $price * 10;
        }

        return $result;
    }

    private function extract_tala_last_price( $html ) {
        if ( ! class_exists( 'DOMDocument' ) ) {
            return 0;
        }

        $dom = new DOMDocument();
        libxml_use_internal_errors( true );
        $dom->loadHTML( $html );
        libxml_clear_errors();

        $xpath = new DOMXPath( $dom );

        foreach ( $xpath->query( '//h2' ) as $heading ) {
            if ( trim( $heading->textContent ) !== 'آخرین قیمت' ) {
                continue;
            }

            $start = $heading->parentNode ? $heading->parentNode->nextSibling : $heading->nextSibling;

            while ( $start ) {
                if ( $start->nodeType === XML_ELEMENT_NODE ) {
                    $value = $this->parse_number( $start->textContent );

                    if ( $value > 0 ) {
                        return $value;
                    }
                }

                $start = $start->nextSibling;
            }
        }

        return 0;
    }

    private function extract_tala_card_value( $html, $label ) {
        if ( ! class_exists( 'DOMDocument' ) ) {
            return 0;
        }

        $dom = new DOMDocument();
        libxml_use_internal_errors( true );
        $dom->loadHTML( $html );
        libxml_clear_errors();

        $xpath = new DOMXPath( $dom );

        foreach ( $xpath->query( '//h4' ) as $heading ) {
            if ( mb_strpos( $heading->textContent, $label ) === false ) {
                continue;
            }

            $sibling = $heading->nextSibling;

            while ( $sibling && $sibling->nodeType !== XML_ELEMENT_NODE ) {
                $sibling = $sibling->nextSibling;
            }

            if ( $sibling ) {
                $value = $this->parse_number( $sibling->textContent );

                if ( $value > 0 ) {
                    return $value;
                }
            }
        }

        return 0;
    }

    private function parse_number( $text ) {
        $text = $this->normalize_digits( $text );

        if ( ! preg_match( '/[0-9][0-9,\s]*/', $text, $matches ) ) {
            return 0;
        }

        $number = str_replace( array( ',', '٬', ' ' ), '', $matches[0] );

        return intval( $number );
    }

    private function normalize_digits( $text ) {
        return strtr( (string) $text, array(
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            '٬' => ',',
        ) );
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

    private function get_today_key() {
        try {
            $timezone = new DateTimeZone( 'Asia/Tehran' );
        } catch ( Exception $e ) {
            $timezone = null;
        }

        $now = new DateTime( 'now', $timezone );

        return $now->format( 'Y-m-d' );
    }

    public function record_high( $karat, $price, $source ) {
        $price = intval( $price );

        if ( $price <= 0 || ! in_array( $source, $this->get_sources(), true ) ) {
            return;
        }

        $karat = ( '24k' === strtolower( (string) $karat ) ) ? '24k' : '18k';

        $highs = get_site_option( self::HIGHS_OPTION, array() );

        if ( ! is_array( $highs ) ) {
            $highs = array();
        }

        $today = $this->get_today_key();

        if ( ! isset( $highs[ $source ] ) || ! is_array( $highs[ $source ] ) || ! isset( $highs[ $source ]['date'] ) || $highs[ $source ]['date'] !== $today ) {
            $highs[ $source ] = array(
                'date' => $today,
                '18k'  => 0,
                '24k'  => 0,
            );
        }

        $current = isset( $highs[ $source ][ $karat ] ) ? intval( $highs[ $source ][ $karat ] ) : 0;

        if ( $price > $current ) {
            $highs[ $source ][ $karat ] = $price;
        }

        update_site_option( self::HIGHS_OPTION, $highs );
    }

    public function record_last_known( $karat, $price, $source ) {
        $price = intval( $price );

        if ( $price <= 0 || ! in_array( $source, $this->get_sources(), true ) ) {
            return;
        }

        $karat = ( '24k' === strtolower( (string) $karat ) ) ? '24k' : '18k';

        $prices = get_site_option( self::LAST_KNOWN_OPTION, array() );

        if ( ! is_array( $prices ) ) {
            $prices = array();
        }

        if ( ! isset( $prices[ $source ] ) || ! is_array( $prices[ $source ] ) ) {
            $prices[ $source ] = array();
        }

        $prices[ $source ][ $karat ] = $price;

        update_site_option( self::LAST_KNOWN_OPTION, $prices );
    }

    public function get_last_known( $karat, $source = null ) {
        if ( null === $source ) {
            $source = $this->get_source();
        }

        $karat = ( '24k' === strtolower( (string) $karat ) ) ? '24k' : '18k';

        $prices = get_site_option( self::LAST_KNOWN_OPTION, array() );

        if ( ! is_array( $prices ) || empty( $prices[ $source ] ) || ! is_array( $prices[ $source ] ) ) {
            return 0;
        }

        return isset( $prices[ $source ][ $karat ] ) ? intval( $prices[ $source ][ $karat ] ) : 0;
    }

    public function get_daily_high( $karat, $source = null ) {
        if ( null === $source ) {
            $source = $this->get_source();
        }

        $karat = ( '24k' === strtolower( (string) $karat ) ) ? '24k' : '18k';

        $highs = get_site_option( self::HIGHS_OPTION, array() );

        if ( ! is_array( $highs ) || empty( $highs[ $source ] ) || ! is_array( $highs[ $source ] ) ) {
            return 0;
        }

        if ( ! isset( $highs[ $source ]['date'] ) || $highs[ $source ]['date'] !== $this->get_today_key() ) {
            return 0;
        }

        return isset( $highs[ $source ][ $karat ] ) ? intval( $highs[ $source ][ $karat ] ) : 0;
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
        $source = $this->get_source();
        $other  = $this->get_other_source( $source );

        $prices = array(
            '18k' => 0,
            '24k' => 0,
        );

        if ( ! $this->acquire_fetch_lock( $source ) ) {
            $prices['18k'] = $this->get_price_18k();
            $prices['24k'] = $this->get_price_24k();

            return $prices;
        }

        $price_18k = $this->fetch_price( '18k', $source );

        if ( $price_18k > 0 ) {
            $this->store_live_price( '18k', $price_18k, $source );
            $prices['18k'] = $price_18k;
        }

        if ( self::SOURCE_TALA === $source ) {
            if ( $price_18k > 0 ) {
                $price_24k = $price_18k * 24 / 18;
                $this->record_high( '24k', $price_24k, $source );
                $this->record_last_known( '24k', $price_24k, $source );
                set_site_transient( $this->get_transient_key( '24k', $source ), $price_24k, self::TRANSIENT_TIMEOUT );
                $prices['24k'] = $price_24k;
            }
        } else {
            $price_24k = $this->fetch_price( '24k', $source );

            if ( $price_24k > 0 ) {
                $this->store_live_price( '24k', $price_24k, $source );
                $prices['24k'] = $price_24k;
            }
        }

        $this->release_fetch_lock( $source );

        if ( $prices['18k'] <= 0 ) {
            $prices['18k'] = $this->get_fallback_18k( $source, $other );
        }

        if ( $prices['24k'] <= 0 ) {
            $prices['24k'] = $this->get_fallback_24k( $source, $other );
        }

        return $prices;
    }

    public function clear_cache() {
        foreach ( $this->get_sources() as $source ) {
            delete_site_transient( $this->get_transient_key( '18k', $source ) );
            delete_site_transient( $this->get_transient_key( '24k', $source ) );

            delete_transient( $this->get_transient_key( '18k', $source ) );
            delete_transient( $this->get_transient_key( '24k', $source ) );
        }

        delete_site_transient( self::TRANSIENT_KEY_18K );
        delete_site_transient( self::TRANSIENT_KEY_24K );
        delete_transient( self::TRANSIENT_KEY_18K );
        delete_transient( self::TRANSIENT_KEY_24K );
    }
}
