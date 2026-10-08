<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Schema.org structured data for Google Online Estimate compliance.
 *
 * Implements:
 *  - Service (with hasOfferCatalog listing each estimate type)
 *  - LocalBusiness / HomeAndConstructionBusiness (provider)
 *  - PotentialAction → QuoteAction (the online-estimate action)
 *  - Offer → PriceSpecification (the specific estimate range on results)
 *
 * Reference:
 *  https://schema.org/Service
 *  https://schema.org/QuoteAction
 *  https://schema.org/Offer
 *  https://schema.org/PriceSpecification
 *  https://developers.google.com/search/docs/appearance/structured-data
 */
class EC_Schema {

    /**
     * Build the base Service + Provider schema shown on the calculator page.
     * Advertises the online-estimate capability.
     *
     * @param array $settings Plugin settings.
     * @param array $services Enabled services (interior, exterior, cabinet).
     * @param array $labels   Service labels.
     * @return array JSON-LD data structure (ready for wp_json_encode).
     */
    public static function build_service_schema( $settings, $services, $labels ) {
        $b = EC_Settings::get_business( $settings );

        $page_url = is_singular() ? get_permalink() : home_url( add_query_arg( [] ) );

        // Provider (LocalBusiness) — referenced by the Service
        $provider = self::build_provider( $b );

        // Build the OfferCatalog — one Offer per enabled service
        $offer_items = [];
        foreach ( $services as $svc ) {
            $offer_items[] = [
                '@type'       => 'Offer',
                'itemOffered' => [
                    '@type'       => 'Service',
                    'name'        => $labels[ $svc ] ?? ucfirst( $svc ),
                    'serviceType' => $labels[ $svc ] ?? ucfirst( $svc ),
                ],
                'priceCurrency' => $b['currency'],
                'priceSpecification' => [
                    '@type'         => 'PriceSpecification',
                    'priceCurrency' => $b['currency'],
                    'price'         => '0',
                    'description'   => 'Free online estimate',
                ],
            ];
        }

        $schema = [
            '@context'    => 'https://schema.org',
            '@type'       => 'Service',
            'name'        => ( $b['name'] ? $b['name'] . ' — ' : '' ) . 'Online Painting Estimate',
            'serviceType' => 'Painting',
            'provider'    => $provider,
            'url'         => $page_url,

            // PotentialAction — this is the Google "Online Estimate" action
            'potentialAction' => [
                '@type'  => 'QuoteAction',
                'name'   => 'Get a free online estimate',
                'target' => [
                    '@type'       => 'EntryPoint',
                    'urlTemplate' => $page_url,
                    'actionPlatform' => [
                        'https://schema.org/DesktopWebPlatform',
                        'https://schema.org/MobileWebPlatform',
                    ],
                ],
                'result' => [
                    '@type'       => 'Reservation',
                    'name'        => 'Painting Estimate',
                    'description' => 'A price range estimate for painting services.',
                ],
            ],

            'hasOfferCatalog' => [
                '@type'           => 'OfferCatalog',
                'name'            => 'Painting Services',
                'itemListElement' => $offer_items,
            ],
        ];

        if ( $b['area_served'] ) {
            $schema['areaServed'] = $b['area_served'];
        }

        return $schema;
    }

    /**
     * Build an Offer with PriceSpecification for a specific completed estimate.
     * Used on the results step/page.
     */
    public static function build_offer_schema( $settings, $service, $label, $low, $high ) {
        $b = EC_Settings::get_business( $settings );

        return [
            '@context'    => 'https://schema.org',
            '@type'       => 'Offer',
            'name'        => $label . ' Estimate',
            'description' => 'Estimated price range for ' . strtolower( $label ) . ' based on project details.',
            'seller'      => self::build_provider( $b ),
            'itemOffered' => [
                '@type'       => 'Service',
                'name'        => $label,
                'serviceType' => $label,
            ],
            'priceSpecification' => [
                '@type'         => 'PriceSpecification',
                'priceCurrency' => $b['currency'],
                'minPrice'      => (float) $low,
                'maxPrice'      => (float) $high,
                'valueAddedTaxIncluded' => false,
            ],
            'availability' => 'https://schema.org/InStock',
            'validFrom'    => current_time( 'c' ),
        ];
    }

    /**
     * Build a LocalBusiness / HomeAndConstructionBusiness provider node.
     */
    private static function build_provider( $b ) {
        $provider = [
            '@type' => $b['type'] ?: 'HomeAndConstructionBusiness',
        ];

        if ( $b['name'] )  $provider['name']  = $b['name'];
        if ( $b['url'] )   $provider['url']   = $b['url'];
        if ( $b['phone'] ) $provider['telephone'] = $b['phone'];
        if ( $b['email'] ) $provider['email'] = $b['email'];
        if ( $b['logo'] )  $provider['logo']  = $b['logo'];

        // PostalAddress — only if at least one address field is set
        if ( $b['street'] || $b['city'] || $b['region'] || $b['postal'] ) {
            $address = [ '@type' => 'PostalAddress' ];
            if ( $b['street'] ) $address['streetAddress']   = $b['street'];
            if ( $b['city'] )   $address['addressLocality'] = $b['city'];
            if ( $b['region'] ) $address['addressRegion']   = $b['region'];
            if ( $b['postal'] ) $address['postalCode']      = $b['postal'];
            if ( $b['country'] )$address['addressCountry']  = $b['country'];
            $provider['address'] = $address;
        }

        if ( $b['area_served'] ) {
            $provider['areaServed'] = $b['area_served'];
        }

        return $provider;
    }

    /**
     * Render a JSON-LD <script> tag.
     */
    public static function render( $schema_data ) {
        if ( empty( $schema_data ) ) return '';
        $json = wp_json_encode( $schema_data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT );
        if ( ! $json ) return '';
        return '<script type="application/ld+json">' . $json . '</script>' . "\n";
    }
}
