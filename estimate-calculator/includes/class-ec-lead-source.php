<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Detects the source / channel of an incoming lead.
 *
 * Combines: UTM params, click IDs (fbclid, gclid, msclkid, ttclid, li_fat_id),
 * referrer URL, and landing-page URL. Falls back to "direct" when no signal.
 */
class EC_Lead_Source {

    /**
     * Detect the canonical source slug for a submission.
     *
     * @param array $data Normalised tracking data from the submission.
     * @return string  e.g. 'meta-paid', 'google-ads', 'google-organic', 'email', 'referral', 'direct'
     */
    public static function detect( $data ) {
        $utm_source = strtolower( trim( (string) ( $data['utm_source'] ?? '' ) ) );
        $utm_medium = strtolower( trim( (string) ( $data['utm_medium'] ?? '' ) ) );
        $referrer   = (string) ( $data['referrer'] ?? '' );

        // Click IDs are the most reliable signal — they unambiguously identify
        // a paid click from that platform.
        if ( ! empty( $data['fbclid'] ) )   return 'meta-paid';
        if ( ! empty( $data['gclid'] ) || ! empty( $data['gbraid'] ) || ! empty( $data['wbraid'] ) ) return 'google-ads';
        if ( ! empty( $data['msclkid'] ) )  return 'bing-ads';
        if ( ! empty( $data['ttclid'] ) )   return 'tiktok-ads';
        if ( ! empty( $data['li_fat_id'] ) ) return 'linkedin-ads';

        $paid_mediums = [ 'cpc', 'ppc', 'paid', 'cpm', 'paidsocial', 'paid-social', 'paid_social' ];

        // UTM source/medium combinations
        if ( in_array( $utm_source, [ 'facebook', 'fb', 'meta', 'instagram', 'ig' ], true ) ) {
            return in_array( $utm_medium, $paid_mediums, true ) ? 'meta-paid' : 'meta-organic';
        }
        if ( $utm_source === 'google' ) {
            return in_array( $utm_medium, $paid_mediums, true ) ? 'google-ads' : 'google-organic';
        }
        if ( $utm_source === 'bing' || $utm_source === 'microsoft' ) {
            return in_array( $utm_medium, $paid_mediums, true ) ? 'bing-ads' : 'bing-organic';
        }
        if ( $utm_source === 'linkedin' ) {
            return in_array( $utm_medium, $paid_mediums, true ) ? 'linkedin-ads' : 'linkedin-organic';
        }
        if ( $utm_source === 'tiktok' ) {
            return in_array( $utm_medium, $paid_mediums, true ) ? 'tiktok-ads' : 'tiktok-organic';
        }
        if ( $utm_source === 'youtube' )  return 'youtube';
        if ( $utm_medium === 'email' )     return 'email';
        if ( $utm_medium === 'organic' )   return 'organic';
        if ( $utm_medium === 'referral' )  return 'referral';
        if ( $utm_medium === 'affiliate' ) return 'affiliate';

        // Referrer-based fallback
        if ( $referrer ) {
            $host = parse_url( $referrer, PHP_URL_HOST );
            if ( $host ) {
                $host = strtolower( $host );
                if ( strpos( $host, 'google.' )    !== false ) return 'google-organic';
                if ( strpos( $host, 'bing.' )      !== false ) return 'bing-organic';
                if ( strpos( $host, 'duckduckgo.' ) !== false ) return 'organic';
                if ( strpos( $host, 'facebook.' )  !== false ) return 'meta-organic';
                if ( strpos( $host, 'instagram.' ) !== false ) return 'meta-organic';
                if ( strpos( $host, 'linkedin.' )  !== false ) return 'linkedin-organic';
                if ( strpos( $host, 'twitter.' )   !== false ) return 'twitter-organic';
                if ( $host === 'x.com' || strpos( $host, '.x.com' ) !== false ) return 'twitter-organic';
                if ( strpos( $host, 'youtube.' )   !== false ) return 'youtube';
                if ( strpos( $host, 'tiktok.' )    !== false ) return 'tiktok-organic';
                if ( strpos( $host, 'reddit.' )    !== false ) return 'reddit-organic';
                if ( strpos( $host, 'pinterest.' ) !== false ) return 'pinterest-organic';
                if ( strpos( $host, 'yelp.' )      !== false ) return 'yelp';
                if ( strpos( $host, 'nextdoor.' )  !== false ) return 'nextdoor';
                // Same-origin referrer → treat as direct
                $self_host = parse_url( home_url(), PHP_URL_HOST );
                if ( $self_host && stripos( $host, $self_host ) !== false ) return 'direct';
                return 'referral';
            }
        }

        return 'direct';
    }

    /**
     * Convert a source slug into the (paid/organic/email/...) channel category.
     */
    public static function source_channel( $source ) {
        $map = [
            'meta-paid'        => 'paid-social',
            'google-ads'       => 'paid-search',
            'bing-ads'         => 'paid-search',
            'tiktok-ads'       => 'paid-social',
            'linkedin-ads'     => 'paid-social',
            'affiliate'        => 'paid-other',

            'meta-organic'     => 'organic-social',
            'linkedin-organic' => 'organic-social',
            'twitter-organic'  => 'organic-social',
            'tiktok-organic'   => 'organic-social',
            'reddit-organic'   => 'organic-social',
            'pinterest-organic'=> 'organic-social',
            'youtube'          => 'organic-social',

            'google-organic'   => 'organic-search',
            'bing-organic'     => 'organic-search',
            'organic'          => 'organic-search',

            'yelp'             => 'referral',
            'nextdoor'         => 'referral',
            'referral'         => 'referral',

            'email'            => 'email',
            'direct'           => 'direct',
        ];
        return $map[ $source ] ?? 'unknown';
    }

    /**
     * Build the GHL tags array for a detected source.
     */
    public static function build_tags( $source, $data ) {
        $tags = [
            'lead-source-' . $source,
            'lead-channel-' . self::source_channel( $source ),
        ];

        if ( ! empty( $data['utm_campaign'] ) ) {
            $tags[] = 'utm-campaign-' . sanitize_title( $data['utm_campaign'] );
        }
        if ( ! empty( $data['utm_source'] ) && ! in_array( strtolower( $data['utm_source'] ), [ 'google', 'facebook', 'fb', 'meta', 'instagram', 'ig', 'bing', 'microsoft', 'linkedin', 'tiktok' ], true ) ) {
            $tags[] = 'utm-source-' . sanitize_title( $data['utm_source'] );
        }
        if ( ! empty( $data['utm_medium'] ) ) {
            $tags[] = 'utm-medium-' . sanitize_title( $data['utm_medium'] );
        }
        return array_values( array_unique( array_filter( $tags ) ) );
    }

    /**
     * Build GHL custom fields with full tracking detail for the lead.
     * Returns array shaped for the GHL V2 API: [ { id, field_value }, … ]
     */
    public static function build_custom_fields( $source, $data ) {
        // Note: the `lead_source` custom field is set by EC_GHL::build_custom_fields()
        // to a form-specific label ("Estimate Form- INT", etc.). We put the detected
        // traffic source in a separate field so both are available in GHL.
        $fields = [
            [ 'id' => 'lead_traffic_source',  'field_value' => $source ],
            [ 'id' => 'lead_channel',         'field_value' => self::source_channel( $source ) ],
            [ 'id' => 'lead_landing_page',    'field_value' => (string) ( $data['landing_page_url'] ?? '' ) ],
            [ 'id' => 'lead_referrer',        'field_value' => (string) ( $data['referrer'] ?? '' ) ],
        ];

        $passthroughs = [
            'utm_source'   => 'utm_source',
            'utm_medium'   => 'utm_medium',
            'utm_campaign' => 'utm_campaign',
            'utm_term'     => 'utm_term',
            'utm_content'  => 'utm_content',
            'fbclid'       => 'fbclid',
            'fbp'          => 'fbp',
            'fbc'          => 'fbc',
            // Google Click ID is synced to GHL as `gclid2` (avoids collision with
            // any existing `gclid` field on the sub-account).
            'gclid'        => 'gclid2',
            'gbraid'       => 'gbraid',
            'wbraid'       => 'wbraid',
            'msclkid'      => 'msclkid',
            'ttclid'       => 'ttclid',
            'li_fat_id'    => 'li_fat_id',
            // Landing-page A/B test variant — referenced in GHL as
            // {{contact.lp_variant}}
            'lp_variant'   => 'lp_variant',
        ];
        foreach ( $passthroughs as $form_key => $ghl_key ) {
            $val = $data[ $form_key ] ?? '';
            if ( $val !== '' ) {
                $fields[] = [ 'id' => $ghl_key, 'field_value' => (string) $val ];
            }
        }

        return $fields;
    }
}
