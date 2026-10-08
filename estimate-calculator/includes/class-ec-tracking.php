<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Tracking pixel helper.
 *
 * Generates the JS snippet that fires on successful calculator submission.
 * Supports Facebook/Meta, Google Ads, TikTok, and custom code.
 */
class EC_Tracking {

    /**
     * Return the JS code to fire a conversion event.
     * Called client-side after successful AJAX submission.
     */
    public static function get_pixel_fire_js( $pixel_type, $pixel_id, $custom_code = '' ) {
        switch ( $pixel_type ) {
            case 'facebook':
                if ( empty( $pixel_id ) ) return '';
                return "if(typeof fbq==='function'){fbq('track','Lead');}";

            case 'google':
                if ( empty( $pixel_id ) ) return '';
                // pixel_id format: AW-XXXXXXXXX/conversion-label
                return "if(typeof gtag==='function'){gtag('event','conversion',{'send_to':'" . esc_js( $pixel_id ) . "'});}";

            case 'tiktok':
                if ( empty( $pixel_id ) ) return '';
                return "if(typeof ttq!=='undefined'){ttq.track('SubmitForm');}";

            case 'custom':
                return $custom_code;

            default:
                return '';
        }
    }

    /**
     * Return the base pixel initialization script to load in <head>.
     * This should be placed on pages with the calculator.
     */
    public static function get_pixel_init_html( $pixel_type, $pixel_id ) {
        switch ( $pixel_type ) {
            case 'facebook':
                if ( empty( $pixel_id ) ) return '';
                return '<script>!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version="2.0";n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,"script","https://connect.facebook.net/en_US/fbevents.js");fbq("init","' . esc_attr( $pixel_id ) . '");fbq("track","PageView");</script>';

            case 'google':
                if ( empty( $pixel_id ) ) return '';
                $tag_id = explode( '/', $pixel_id )[0];
                return '<script async src="https://www.googletagmanager.com/gtag/js?id=' . esc_attr( $tag_id ) . '"></script><script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag("js",new Date());gtag("config","' . esc_attr( $tag_id ) . '");</script>';

            case 'tiktok':
                if ( empty( $pixel_id ) ) return '';
                return '<script>!function(w,d,t){w.TiktokAnalyticsObject=t;var ttq=w[t]=w[t]||[];ttq.methods=["page","track","identify","instances","debug","on","off","once","ready","alias","group","enableCookie","disableCookie"];ttq.setAndDefer=function(t,e){t[e]=function(){t.push([e].concat(Array.prototype.slice.call(arguments,0)))}};for(var i=0;i<ttq.methods.length;i++)ttq.setAndDefer(ttq,ttq.methods[i]);ttq.instance=function(t){for(var e=ttq._i[t]||[],n=0;n<ttq.methods.length;n++)ttq.setAndDefer(e,ttq.methods[n]);return e};ttq.load=function(e,n){var i="https://analytics.tiktok.com/i18n/pixel/events.js";ttq._i=ttq._i||{};ttq._i[e]=[];ttq._i[e]._u=i;ttq._t=ttq._t||{};ttq._t[e+\"_\"+n]=1;var o=document.createElement("script");o.type="text/javascript";o.async=!0;o.src=i+"?sdkid="+e+"&lib="+t;var a=document.getElementsByTagName("script")[0];a.parentNode.insertBefore(o,a)};ttq.load("' . esc_attr( $pixel_id ) . '");ttq.page();}(window,document,"ttq");</script>';

            default:
                return '';
        }
    }
}
