<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Meta (Facebook) Conversions API integration — server-side Lead events
 * that pair with the browser pixel for better attribution + iOS 14.5+ tracking.
 *
 * Reference: https://developers.facebook.com/docs/marketing-api/conversions-api
 *
 * Requires (configured in plugin admin):
 *   - Pixel ID
 *   - Access Token (from Meta Events Manager → Settings → Conversions API)
 *   - (optional) Test event code for sandbox testing
 */
class EC_Meta_CAPI {

    private $pixel_id;
    private $access_token;
    private $test_event_code;
    private $api_version = 'v19.0';

    public function __construct( $settings ) {
        $this->pixel_id        = $settings['meta_capi_pixel_id']    ?? '';
        $this->access_token    = $settings['meta_capi_access_token'] ?? '';
        $this->test_event_code = $settings['meta_capi_test_code']   ?? '';
    }

    public function is_configured() {
        return ! empty( $this->pixel_id ) && ! empty( $this->access_token );
    }

    /**
     * Send a Lead event to Meta. Returns true if attempted, false if skipped.
     *
     * @param array $data     Form/tracking data (email, phone, fbclid, fbp, etc.)
     * @param array $estimate Estimate result (low, high, total)
     * @param string $service Service slug
     */
    public function fire_lead( $data, $estimate, $service ) {
        if ( ! $this->is_configured() ) return false;

        $event_time = time();
        $event_id   = $this->event_id( $data, $service, $event_time );

        $user_data = $this->build_user_data( $data, $event_time );

        $payload = [
            'data' => [ [
                'event_name'       => 'Lead',
                'event_time'       => $event_time,
                'event_id'         => $event_id,
                'event_source_url' => (string) ( $data['landing_page_url'] ?? home_url( '/' ) ),
                'action_source'    => 'website',
                'user_data'        => $user_data,
                'custom_data'      => [
                    'value'        => isset( $estimate['total'] ) ? (float) $estimate['total'] : 0,
                    'currency'     => 'USD',
                    'content_name' => $service,
                    'content_category' => 'estimate',
                    'lead_event_source' => 'estimate-calculator',
                ],
            ] ],
        ];

        if ( ! empty( $this->test_event_code ) ) {
            $payload['test_event_code'] = $this->test_event_code;
        }

        $url = sprintf(
            'https://graph.facebook.com/%s/%s/events?access_token=%s',
            $this->api_version,
            rawurlencode( $this->pixel_id ),
            rawurlencode( $this->access_token )
        );

        $response = wp_remote_post( $url, [
            'method'  => 'POST',
            'headers' => [ 'Content-Type' => 'application/json' ],
            'body'    => wp_json_encode( $payload ),
            'timeout' => 15,
        ] );

        $this->log( $response, $payload );
        return true;
    }

    /**
     * Build user_data for the event — hashes PII per Meta requirements.
     */
    private function build_user_data( $data, $event_time ) {
        $ud = [];

        // Hashed PII (SHA-256, lowercase, trimmed)
        if ( ! empty( $data['email'] ) ) {
            $ud['em'] = [ hash( 'sha256', strtolower( trim( $data['email'] ) ) ) ];
        }
        if ( ! empty( $data['phone'] ) ) {
            // Meta wants digits-only for phone
            $phone_digits = preg_replace( '/\D+/', '', (string) $data['phone'] );
            if ( $phone_digits !== '' ) {
                $ud['ph'] = [ hash( 'sha256', $phone_digits ) ];
            }
        }
        if ( ! empty( $data['full_name'] ) ) {
            $parts = explode( ' ', trim( $data['full_name'] ), 2 );
            if ( ! empty( $parts[0] ) ) $ud['fn'] = [ hash( 'sha256', strtolower( trim( $parts[0] ) ) ) ];
            if ( ! empty( $parts[1] ) ) $ud['ln'] = [ hash( 'sha256', strtolower( trim( $parts[1] ) ) ) ];
        }

        // Browser cookies (not hashed)
        if ( ! empty( $data['fbp'] ) ) {
            $ud['fbp'] = (string) $data['fbp'];
        }
        // Build fbc from fbclid if not provided directly
        if ( ! empty( $data['fbc'] ) ) {
            $ud['fbc'] = (string) $data['fbc'];
        } elseif ( ! empty( $data['fbclid'] ) ) {
            $ud['fbc'] = 'fb.1.' . ( $event_time * 1000 ) . '.' . $data['fbclid'];
        }

        // Client environment
        $ip = $this->client_ip();
        if ( $ip ) $ud['client_ip_address'] = $ip;
        if ( ! empty( $_SERVER['HTTP_USER_AGENT'] ) ) {
            $ud['client_user_agent'] = sanitize_text_field( $_SERVER['HTTP_USER_AGENT'] );
        }

        return $ud;
    }

    private function client_ip() {
        // Trust X-Forwarded-For only if present and not behind a hostile proxy.
        if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
            $ips = explode( ',', $_SERVER['HTTP_X_FORWARDED_FOR'] );
            $ip  = trim( $ips[0] );
            if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) return $ip;
        }
        if ( ! empty( $_SERVER['REMOTE_ADDR'] ) && filter_var( $_SERVER['REMOTE_ADDR'], FILTER_VALIDATE_IP ) ) {
            return $_SERVER['REMOTE_ADDR'];
        }
        return '';
    }

    /**
     * Build a deterministic event ID so client-side pixel + CAPI deduplicate.
     */
    private function event_id( $data, $service, $event_time ) {
        $base = ( $data['email'] ?? '' ) . '|' . ( $data['phone'] ?? '' ) . '|' . $service . '|' . $event_time;
        return 'ec_lead_' . substr( hash( 'sha256', $base ), 0, 16 );
    }

    private function log( $response, $payload ) {
        if ( ! ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ) return;
        if ( is_wp_error( $response ) ) {
            error_log( '[EC-MetaCAPI] HTTP error: ' . $response->get_error_message() );
            return;
        }
        $code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );
        error_log( '[EC-MetaCAPI] HTTP ' . $code . ': ' . substr( $body, 0, 400 ) );
    }
}
