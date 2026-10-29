<?php
/**
 * Plugin Name: Directorist Map Popups on Demand (Staging)
 * Description: Ticket 7833 staging-only trial: retain every map marker and render popup cards only when opened.
 * Version: 0.1.0
 * Requires PHP: 7.4
 */

defined( 'ABSPATH' ) || exit;

final class BBQ7833_Map_Popups {
    private static $popup_request = false;
    private static $native_templates = [];
    private static $map_index = 0;

    public static function allowed_environment() {
        $host = wp_parse_url( home_url(), PHP_URL_HOST );
        return 'staging.bbqtravelclub.com' === $host || 'localhost' === $host || '127.0.0.1' === $host || '.local' === substr( (string) $host, -6 );
    }

    public static function active_page() {
        if ( ! self::allowed_environment() ) {
            return false;
        }
        if ( is_page( [ 'global-bbq-directory', 'ticket-7833-map-qa' ] ) ) {
            return true;
        }
        // Preserve map filtering requests from the same directory page.
        if ( wp_doing_ajax() ) {
            $referrer = wp_get_referer();
            return $referrer && wp_parse_url( $referrer, PHP_URL_HOST ) === wp_parse_url( home_url(), PHP_URL_HOST ) && in_array( trim( (string) wp_parse_url( $referrer, PHP_URL_PATH ), '/' ), [ 'global-bbq-directory', 'ticket-7833-map-qa' ], true );
        }
        return false;
    }

    public static function template( $file, $name, $args ) {
        if ( ! self::allowed_environment() ) {
            return $file;
        }
        if ( self::$popup_request && in_array( $name, [ 'archive/fields/google-map', 'archive/fields/openstreet-map' ], true ) ) {
            self::$native_templates[ $name ] = $file;
            return __DIR__ . '/templates/popup.php';
        }
        if ( 'archive/map-view' === $name && self::active_page() && ! empty( $args['listings'] ) ) {
            return __DIR__ . '/templates/map.php';
        }
        return $file;
    }

    public static function assets() {
        if ( self::active_page() ) {
            wp_enqueue_script( 'bbq7833-map-popup', plugins_url( 'popup.js', __FILE__ ), [], '0.1.0', true );
            wp_add_inline_script( 'bbq7833-map-popup', 'window.BBQ7833Popup=' . wp_json_encode( [ 'endpoint' => admin_url( 'admin-ajax.php' ) ] ) . ';', 'before' );
        }
    }

    private static function placeholder( $id, $provider, $map_height ) {
        // Reserve the final card's space so native map auto-pan also works without a bridge.
        $height = max( 100, min( 270, absint( $map_height ) - 80 ) );
        return '<div class="map-info-wrapper map-listing-card-single map-info-wrapper--show bbq7833-popup" style="height:' . $height . 'px;width:280px;max-width:max(120px,calc(100vw - 180px))" data-bbq7833-id="' . absint( $id ) . '" data-bbq7833-provider="' . esc_attr( $provider ) . '"><div class="map-listing-card-single__content"><p role="status">Loading listing details…</p><a href="' . esc_url( get_permalink( $id ) ) . '">View listing</a></div></div>';
    }

    public static function render_map( $listings ) {
        $ids = $listings->query_results->ids ?? [];
        if ( $ids ) {
            _prime_post_caches( $ids, true, true );
        }
        $provider = 'google' === $listings->select_listing_map ? 'google' : 'openstreetmap';
        $opt = $listings->get_map_options();
        $markers = [];
        foreach ( $ids as $id ) {
            $lat = get_post_meta( $id, '_manual_lat', true );
            $lng = get_post_meta( $id, '_manual_lng', true );
            // Match Directorist's existing coordinate eligibility without changing its query.
            if ( empty( $lat ) || empty( $lng ) || ! is_numeric( $lat ) || ! is_numeric( $lng ) ) {
                continue;
            }
            $cats = get_the_terms( $id, ATBDP_CATEGORY );
            $icon = ! empty( $cats ) && ! is_wp_error( $cats ) ? get_term_meta( $cats[0]->term_id, 'category_icon', true ) : '';
            $icon = apply_filters( 'directorist_listings_map_view_marker_icon', $icon ?: 'fas fa-map-pin', $cats );
            $markers[] = [ 'listing_id' => (int) $id, 'latitude' => $lat, 'longitude' => $lng, 'cat_icon' => directorist_icon( $icon, false ), 'content' => empty( $opt['map_is_disabled'] ) ? self::placeholder( $id, $provider, $listings->listings_map_height ) : '' ];
        }
        self::$map_index++;
        $instance = wp_unique_id( 'directorist-map-' );
        if ( 'google' === $provider ) {
            \Directorist\Helper::add_hidden_data_to_dom( 'atbdp_map', [
                'plugin_url' => ATBDP_URL,
                'disable_info_window' => empty( $opt['display_map_info'] ) ? 'yes' : 'no',
                'zoom' => $opt['zoom'],
                'default_latitude' => $listings->options['default_latitude'],
                'default_longitude' => $listings->options['default_longitude'],
                'use_def_lat_long' => $listings->options['use_def_lat_long'],
            ] );
            echo '<div class="atbdp-body atbdp-map embed-responsive embed-responsive-16by9 atbdp-margin-bottom" data-bbq7833-optimized="1" data-directorist-map-instance="' . esc_attr( $instance ) . '" data-type="' . esc_attr( $listings->options['marker_clustering'] ) . '" style="height:' . absint( $listings->listings_map_height ) . 'px">';
            foreach ( $markers as $marker ) {
                echo '<div class="marker" data-listing-id="' . absint( $marker['listing_id'] ) . '" data-latitude="' . esc_attr( $marker['latitude'] ) . '" data-longitude="' . esc_attr( $marker['longitude'] ) . '" data-icon="' . esc_attr( wp_json_encode( $marker['cat_icon'] ) ) . '">' . $marker['content'] . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped locally generated markup.
            }
            echo '</div>';
        } else {
            $map_id = 1 === self::$map_index ? 'map' : $instance;
            echo '<div id="' . esc_attr( $map_id ) . '" class="directorist-openstreet-map" data-bbq7833-optimized="1" style="height:' . absint( $listings->listings_map_height ) . 'px" data-directorist-map-instance="' . esc_attr( $instance ) . '" data-card="' . directorist_esc_json( wp_json_encode( $markers ) ) . '" data-options="' . directorist_esc_json( wp_json_encode( $listings->map_options() ) ) . '"><div id="gmap_full_screen_button"><span class="fullscreen-enable">' . directorist_icon( 'fas fa-expand', false ) . '</span><span class="fullscreen-disable">' . directorist_icon( 'fas fa-compress', false ) . '</span></div></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- data encoded using Directorist's native JSON escaping.
        }
    }

    public static function native_popup( $args, $name ) {
        $data = $args['ls_data'];
        $attachment = ! empty( $data['listing_prv_img'] ) ? $data['listing_prv_img'] : ( $data['listing_img'][0] ?? 0 );
        if ( $attachment ) {
            $metadata = wp_get_attachment_metadata( $attachment );
            $size = 'thumbnail';
            foreach ( [ 'directorist_preview', 'medium', 'thumbnail' ] as $candidate ) {
                if ( ! empty( $metadata['sizes'][ $candidate ] ) ) {
                    $size = $candidate;
                    break;
                }
            }
            $url = wp_get_attachment_image_url( $attachment, $size );
            $args['ls_data']['prv_image'] = $url;
            $args['ls_data']['gallery_img'] = $url;
        }
        extract( $args, EXTR_SKIP );
        include self::$native_templates[ $name ];
    }

    public static function popup() {
        $id = isset( $_GET['listing_id'] ) ? absint( $_GET['listing_id'] ) : 0;
        $post = get_post( $id );
        if ( ! self::allowed_environment() || ! class_exists( '\Directorist\Directorist_Listings' ) || ! $post || ATBDP_POST_TYPE !== $post->post_type || 'publish' !== $post->post_status || $post->post_password ) {
            wp_send_json_error( [ 'message' => 'Listing unavailable.' ], 404 );
        }
        $provider = isset( $_GET['provider'] ) && 'openstreetmap' === $_GET['provider'] ? 'openstreetmap' : 'google';
        $listings = new \Directorist\Directorist_Listings( [ 'view' => 'map' ], 'listing', [ 'post_type' => ATBDP_POST_TYPE, 'post_status' => 'publish', 'post__in' => [ $id ], 'posts_per_page' => 1 ] );
        $listings->select_listing_map = 'google' === $provider ? 'google' : 'openstreet';
        $html = '';
        $capture = static function( $content, $listing_id ) use ( &$html, $id ) {
            if ( (int) $listing_id === $id ) {
                $html = $content;
            }
            return $content;
        };
        self::$popup_request = true;
        add_filter( 'directorist_map_card_content', $capture, PHP_INT_MAX, 2 );
        $buffer_level = ob_get_level();
        ob_start();
        try {
            if ( 'google' === $provider ) {
                $listings->load_google_map();
            } else {
                $listings->openstreet_map_card_data();
            }
        } finally {
            while ( ob_get_level() > $buffer_level ) {
                ob_end_clean();
            }
            self::$popup_request = false;
            remove_filter( 'directorist_map_card_content', $capture, PHP_INT_MAX );
        }
        if ( ! $html ) {
            wp_send_json_error( [ 'message' => 'Listing unavailable.' ], 404 );
        }
        nocache_headers(); // Cards can contain the current user's favorite state.
        wp_send_json_success( [ 'html' => $html ] );
    }
}

add_filter( 'directorist_template_file_path', [ 'BBQ7833_Map_Popups', 'template' ], 20, 3 );
add_action( 'wp_enqueue_scripts', [ 'BBQ7833_Map_Popups', 'assets' ], 50 );
add_action( 'wp_ajax_bbq7833_map_popup', [ 'BBQ7833_Map_Popups', 'popup' ] );
add_action( 'wp_ajax_nopriv_bbq7833_map_popup', [ 'BBQ7833_Map_Popups', 'popup' ] );
