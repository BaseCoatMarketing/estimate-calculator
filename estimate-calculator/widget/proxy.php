<?php
/**
 * Estimate Calculator Widget — Standalone GHL Proxy
 *
 * Use this on ANY PHP-enabled server (no WordPress required).
 * Keeps your GHL Private Integration token OFF the browser.
 *
 * INSTALLATION:
 *   1. Upload this file somewhere on your server, e.g.
 *      https://yourdomain.com/estimate/proxy.php
 *   2. Fill in the CONFIG section below with your GHL credentials
 *   3. In your widget embed code, set endpoint to this file's URL
 *
 * SECURITY:
 *   - Change the $ALLOWED_ORIGINS list to restrict which sites can submit
 *   - Never commit this file with real credentials to a public repo
 *   - Use HTTPS only
 *
 * Compatible with: GHL Private Integration API V2.0
 */

// ===================== CONFIG =====================

// GHL Private Integration credentials
$GHL_API_KEY      = 'pit-244fad31-65e8-4087-96ac-6e22235e5664';
$GHL_LOCATION_ID  = 'kg307mH5QHxmKEkW9erN';

// Optional — if set, an opportunity will be created in this pipeline/stage
$GHL_PIPELINE_ID  = 'Bqau5D8uEyFeKQi06Dee';
$GHL_STAGE_ID     = 'New Lead';

// Allowed origins (CORS). Set to '*' for any site, or list specific ones.
$ALLOWED_ORIGINS = [
    '*',
    //'https://www.yourdomain.com',
    // 'http://localhost:8000',
];

// API version
$GHL_API_VERSION = '2021-07-28';

// Debug log file (optional). Leave empty to disable logging.
$DEBUG_LOG = __DIR__ . '/proxy-debug.log';

// ==================================================

// ---- CORS ----
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array('*', $ALLOWED_ORIGINS, true)) {
    header('Access-Control-Allow-Origin: *');
} elseif (in_array($origin, $ALLOWED_ORIGINS, true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
}
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// ---- Helpers ----
function ec_log($message) {
    global $DEBUG_LOG;
    if (empty($DEBUG_LOG)) return;
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $message . "\n";
    @file_put_contents($DEBUG_LOG, $line, FILE_APPEND);
}

function ec_sanitize($s) {
    return is_scalar($s) ? trim(strip_tags((string)$s)) : '';
}

function ec_ghl_request($method, $endpoint, $body, $api_key, $version) {
    $url = 'https://services.leadconnectorhq.com' . $endpoint;
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_POSTFIELDS     => json_encode($body),
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $api_key,
            'Content-Type: application/json',
            'Version: ' . $version,
            'Accept: application/json',
        ],
    ]);
    $response = curl_exec($ch);
    $code     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err      = curl_error($ch);
    curl_close($ch);

    ec_log("$method $url → HTTP $code — " . substr($response, 0, 500));

    return [
        'code'  => $code,
        'body'  => json_decode($response, true),
        'error' => $err,
        'raw'   => $response,
    ];
}

// ---- Parse input ----
$data = [];
$raw_input = file_get_contents('php://input');
$decoded = json_decode($raw_input, true);
if (is_array($decoded)) {
    $data = $decoded;
} else {
    $data = $_POST;
}

$service = ec_sanitize($data['service'] ?? '');
$is_builtin = in_array($service, ['interior', 'exterior', 'cabinet'], true);
$is_custom  = (strpos($service, 'custom_') === 0);
if (!$is_builtin && !$is_custom) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid service type.']);
    exit;
}

$email = filter_var($data['email'] ?? '', FILTER_VALIDATE_EMAIL);
if (!$email) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Valid email required.']);
    exit;
}

// ---- Check config ----
if (empty($GHL_API_KEY) || $GHL_API_KEY === 'YOUR_PRIVATE_INTEGRATION_ACCESS_TOKEN') {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'GHL proxy not configured.']);
    exit;
}

// ---- Build contact body ----
$full_name = ec_sanitize($data['full_name'] ?? '');
$name_parts = explode(' ', $full_name, 2);
$first_name = $name_parts[0] ?? '';
$last_name  = $name_parts[1] ?? '';

$estimate_low   = (float)($data['estimate_low']   ?? 0);
$estimate_high  = (float)($data['estimate_high']  ?? 0);
$estimate_total = (float)($data['estimate_total'] ?? 0);

$tags = ['estimate-calculator', 'service-' . $service];

// Lightweight lead-source detection (mirrors EC_Lead_Source in the WP plugin)
$ec_detect_source = function($d) {
    $utm_source = strtolower(trim((string)($d['utm_source'] ?? '')));
    $utm_medium = strtolower(trim((string)($d['utm_medium'] ?? '')));
    if (!empty($d['fbclid']))   return 'meta-paid';
    if (!empty($d['gclid']) || !empty($d['gbraid']) || !empty($d['wbraid'])) return 'google-ads';
    if (!empty($d['msclkid']))  return 'bing-ads';
    if (!empty($d['ttclid']))   return 'tiktok-ads';
    if (!empty($d['li_fat_id'])) return 'linkedin-ads';
    $paid = ['cpc','ppc','paid','cpm','paidsocial','paid-social','paid_social'];
    if (in_array($utm_source, ['facebook','fb','meta','instagram','ig'], true)) {
        return in_array($utm_medium, $paid, true) ? 'meta-paid' : 'meta-organic';
    }
    if ($utm_source === 'google') {
        return in_array($utm_medium, $paid, true) ? 'google-ads' : 'google-organic';
    }
    if ($utm_medium === 'email')    return 'email';
    if ($utm_medium === 'organic')  return 'organic';
    if ($utm_medium === 'referral') return 'referral';
    if (!empty($d['referrer'])) {
        $host = parse_url($d['referrer'], PHP_URL_HOST);
        if ($host) {
            $host = strtolower($host);
            if (strpos($host, 'google.')   !== false) return 'google-organic';
            if (strpos($host, 'bing.')     !== false) return 'bing-organic';
            if (strpos($host, 'facebook.') !== false || strpos($host, 'instagram.') !== false) return 'meta-organic';
            if (strpos($host, 'linkedin.') !== false) return 'linkedin-organic';
            return 'referral';
        }
    }
    return 'direct';
};

$ec_channel_for = function($source) {
    $map = [
        'meta-paid' => 'paid-social', 'tiktok-ads' => 'paid-social', 'linkedin-ads' => 'paid-social',
        'google-ads' => 'paid-search', 'bing-ads' => 'paid-search',
        'meta-organic' => 'organic-social', 'linkedin-organic' => 'organic-social',
        'google-organic' => 'organic-search', 'bing-organic' => 'organic-search', 'organic' => 'organic-search',
        'email' => 'email', 'referral' => 'referral', 'direct' => 'direct',
    ];
    return $map[$source] ?? 'unknown';
};

$lead_source  = $ec_detect_source($data);
$lead_channel = $ec_channel_for($lead_source);
$tags[]       = 'lead-source-' . $lead_source;
$tags[]       = 'lead-channel-' . $lead_channel;

if (!empty($data['utm_campaign'])) $tags[] = 'utm-campaign-' . preg_replace('/[^a-z0-9\-]/i', '-', strtolower($data['utm_campaign']));
if (!empty($data['utm_medium']))   $tags[] = 'utm-medium-' . preg_replace('/[^a-z0-9\-]/i', '-', strtolower($data['utm_medium']));
if (!empty($data['utm_source']) && !in_array(strtolower($data['utm_source']), ['google','facebook','fb','meta','instagram','ig','bing','linkedin','tiktok'], true)) {
    $tags[] = 'utm-source-' . preg_replace('/[^a-z0-9\-]/i', '-', strtolower($data['utm_source']));
}

// Form-specific label — applied to the OPPORTUNITY's native `source` field
// (see the opportunity creation section further down), not a contact field.
$form_labels = [
    'interior' => 'Estimate Form- INT',
    'exterior' => 'Estimate Form- EXT',
    'cabinet'  => 'Estimate Form - CAB',
];
$opp_source_label = $is_custom ? 'Estimate Form - OTH' : ( $form_labels[$service] ?? 'Estimate Form - OTH' );

// Human-readable price range: "$4,455 - $7,425"
$range_str = '$' . number_format((float) $estimate_low)
           . ' - $' . number_format((float) $estimate_high);

$custom_fields = [
    ['id' => 'estimate_service',    'field_value' => $service],
    ['id' => 'estimate_total',      'field_value' => (string)$estimate_total],
    ['id' => 'estimate_low_range',  'field_value' => (string)$estimate_low],
    ['id' => 'estimate_high_range', 'field_value' => (string)$estimate_high],
    ['id' => 'estimate_date',       'field_value' => date('Y-m-d H:i:s')],
    ['id' => 'last_general_source', 'field_value' => ec_sanitize($data['utm_source'] ?? 'direct')],

    // Lead source tracking (traffic-source only — form label lives on the opportunity)
    ['id' => 'lead_traffic_source', 'field_value' => $lead_source],
    ['id' => 'lead_channel',        'field_value' => $lead_channel],
    ['id' => 'lead_landing_page',   'field_value' => ec_sanitize($data['landing_page_url'] ?? '')],
    ['id' => 'lead_referrer',       'field_value' => ec_sanitize($data['referrer'] ?? '')],
];
// Pass through every tracking ID we received
// Map: form-field name → GHL custom-field ID (usually the same, but gclid
// is synced as `gclid2` to avoid colliding with existing GHL fields).
$tracking_map = [
    'utm_source'   => 'utm_source',
    'utm_medium'   => 'utm_medium',
    'utm_campaign' => 'utm_campaign',
    'utm_term'     => 'utm_term',
    'utm_content'  => 'utm_content',
    'fbclid'       => 'fbclid',
    'fbp'          => 'fbp',
    'fbc'          => 'fbc',
    'gclid'        => 'gclid2',
    'gbraid'       => 'gbraid',
    'wbraid'       => 'wbraid',
    'msclkid'      => 'msclkid',
    'ttclid'       => 'ttclid',
    'li_fat_id'    => 'li_fat_id',
    'lp_variant'   => 'lp_variant',
];
foreach ($tracking_map as $form_key => $ghl_id) {
    if (!empty($data[$form_key])) {
        $custom_fields[] = ['id' => $ghl_id, 'field_value' => ec_sanitize((string)$data[$form_key])];
    }
}

// Service-specific fields
$yn = function($v) { return !empty($v) && $v !== '0' && $v !== 'no' ? 'yes' : 'no'; };

if ($service === 'interior') {
    $custom_fields[] = ['id' => 'interior_small_rooms',  'field_value' => ec_sanitize($data['small_rooms']   ?? '0')];
    $custom_fields[] = ['id' => 'interior_medium_rooms', 'field_value' => ec_sanitize($data['medium_rooms']  ?? '0')];
    $custom_fields[] = ['id' => 'interior_large_rooms',  'field_value' => ec_sanitize($data['large_rooms']   ?? '0')];
    $custom_fields[] = ['id' => 'interior_xlarge_rooms', 'field_value' => ec_sanitize($data['xlarge_rooms']  ?? '0')];
    $custom_fields[] = ['id' => 'interior_entry_doors',  'field_value' => ec_sanitize($data['entry_doors']   ?? '0')];
    $custom_fields[] = ['id' => 'interior_closet_doors', 'field_value' => ec_sanitize($data['closet_doors']  ?? '0')];
    $custom_fields[] = ['id' => 'interior_ceilings',     'field_value' => $yn($data['ceilings'] ?? '')];
    $custom_fields[] = ['id' => 'interior_trim',         'field_value' => $yn($data['trim']     ?? '')];
    $custom_fields[] = ['id' => 'interior_condition',    'field_value' => ec_sanitize($data['condition']     ?? '')];

    // Legacy-format fields
    $custom_fields[] = ['id' => 'interior_price_range',                       'field_value' => $range_str];
    $custom_fields[] = ['id' => 'score_high_range_interior_painting',         'field_value' => (string) $estimate_high];
    $custom_fields[] = ['id' => 'score_low_range_interior_painting',          'field_value' => (string) $estimate_low];
    $custom_fields[] = ['id' => 'score_total_estimated_cost_interior_painting','field_value' => (string) $estimate_total];
    $custom_fields[] = ['id' => 'interior_calc_small_room_count',             'field_value' => ec_sanitize($data['small_rooms']  ?? '0')];
    $custom_fields[] = ['id' => 'interior_calc_medium_room_count',            'field_value' => ec_sanitize($data['medium_rooms'] ?? '0')];
    $custom_fields[] = ['id' => 'interior_calc_large_room_count',             'field_value' => ec_sanitize($data['large_rooms']  ?? '0')];
    $custom_fields[] = ['id' => 'interior_calc_xlarge_room_count',            'field_value' => ec_sanitize($data['xlarge_rooms'] ?? '0')];
    $custom_fields[] = ['id' => 'interior_calc_door_count',                   'field_value' => (string) ((int)($data['entry_doors'] ?? 0) + (int)($data['closet_doors'] ?? 0))];
} elseif ($service === 'exterior') {
    $custom_fields[] = ['id' => 'exterior_home_size',     'field_value' => ec_sanitize($data['home_size']      ?? '')];
    $custom_fields[] = ['id' => 'exterior_material',      'field_value' => ec_sanitize($data['material']       ?? '')];
    $custom_fields[] = ['id' => 'exterior_single_garage', 'field_value' => ec_sanitize($data['single_garage']  ?? '0')];
    $custom_fields[] = ['id' => 'exterior_double_garage', 'field_value' => ec_sanitize($data['double_garage']  ?? '0')];
    $custom_fields[] = ['id' => 'exterior_shutters',      'field_value' => ec_sanitize($data['shutters'] ?? '0')];
    $custom_fields[] = ['id' => 'exterior_trim',          'field_value' => $yn($data['ext_trim']     ?? '')];
    $custom_fields[] = ['id' => 'exterior_gutters',       'field_value' => $yn($data['ext_gutters']  ?? '')];
    $custom_fields[] = ['id' => 'exterior_condition',     'field_value' => ec_sanitize($data['condition']      ?? '')];

    // Legacy-format fields
    $custom_fields[] = ['id' => 'exterior_price_range',                          'field_value' => $range_str];
    $custom_fields[] = ['id' => 'score_high_range_exterior_painting',            'field_value' => (string) $estimate_high];
    $custom_fields[] = ['id' => 'score_low_range_exterior_painting',             'field_value' => (string) $estimate_low];
    $custom_fields[] = ['id' => 'score_total_estimated_cost_exterior_painting',  'field_value' => (string) $estimate_total];
    $custom_fields[] = ['id' => 'exterior_calc_single_garage_door_count',        'field_value' => ec_sanitize($data['single_garage'] ?? '0')];
    $custom_fields[] = ['id' => 'exterior_calc_double_garage_door_count',        'field_value' => ec_sanitize($data['double_garage'] ?? '0')];
    $custom_fields[] = ['id' => 'exterior_calc_shutter_count',                   'field_value' => ec_sanitize($data['shutters']      ?? '0')];
} elseif ($service === 'cabinet') {
    $custom_fields[] = ['id' => 'cabinet_doors',     'field_value' => ec_sanitize($data['cab_doors']   ?? '0')];
    $custom_fields[] = ['id' => 'cabinet_drawers',   'field_value' => ec_sanitize($data['cab_drawers'] ?? '0')];
    $custom_fields[] = ['id' => 'cabinet_island',    'field_value' => ec_sanitize($data['has_island']  ?? 'no')];
    $custom_fields[] = ['id' => 'cabinet_condition', 'field_value' => ec_sanitize($data['condition']   ?? '')];

    // Legacy-format fields
    $custom_fields[] = ['id' => 'cabinet_price_range',        'field_value' => $range_str];
    $custom_fields[] = ['id' => 'score_total_estimated_cost', 'field_value' => (string) $estimate_total];
    $custom_fields[] = ['id' => 'score_high_range',           'field_value' => (string) $estimate_high];
    $custom_fields[] = ['id' => 'score_low_range',            'field_value' => (string) $estimate_low];
    $custom_fields[] = ['id' => 'cabinet_calc_door_count',    'field_value' => ec_sanitize($data['cab_doors']   ?? '0')];
    $custom_fields[] = ['id' => 'cabinet_calc_drawer_count',  'field_value' => ec_sanitize($data['cab_drawers'] ?? '0')];
} elseif ($is_custom) {
    $slug = substr($service, 7);
    $custom_fields[] = ['id' => 'custom_service_slug', 'field_value' => $slug];
    $custom_fields[] = ['id' => $slug . '_sqft',       'field_value' => ec_sanitize($data['sqft']      ?? '0')];
    $custom_fields[] = ['id' => $slug . '_condition',  'field_value' => ec_sanitize($data['condition'] ?? '')];
}

// Pass through any custom-question answers (custom_* fields) as GHL custom fields.
// Optional payload map `custom_field_map[slug] = ghl_key` lets the widget route
// a select-type answer to a specific GHL custom field (default is {service}_q_{slug}).
$cf_map = isset( $data['custom_field_map'] ) && is_array( $data['custom_field_map'] ) ? $data['custom_field_map'] : [];
foreach ( $data as $key => $value ) {
    if ( ! is_string( $key ) ) continue;
    if ( strpos( $key, 'custom_' ) !== 0 ) continue;
    $slug = substr( $key, 7 );
    $slug = preg_replace( '/[^a-z0-9_-]/i', '', $slug );
    if ( $slug === '' ) continue;

    $ghl_key = isset( $cf_map[ $slug ] ) && $cf_map[ $slug ] !== ''
        ? preg_replace( '/[^a-z0-9_-]/i', '', (string) $cf_map[ $slug ] )
        : $service . '_q_' . $slug;

    $custom_fields[] = [
        'id'          => $ghl_key,
        'field_value' => ec_sanitize( (string) $value ),
    ];
}

$contact_body = [
    'firstName'    => $first_name,
    'lastName'     => $last_name,
    'email'        => $email,
    'phone'        => ec_sanitize($data['phone'] ?? ''),
    'locationId'   => $GHL_LOCATION_ID,
    'source'       => 'Estimate Calculator Widget',
    'tags'         => $tags,
    'customFields' => $custom_fields,
];

// ZIP / postal code → GHL native postalCode field (only when provided)
if (!empty($data['zip_code'])) {
    $contact_body['postalCode'] = ec_sanitize((string) $data['zip_code']);
}

ec_log('--- CONTACT UPSERT ---');
ec_log('Email: ' . $email);

// ---- Upsert contact ----
$contact_resp = ec_ghl_request('POST', '/contacts/upsert', $contact_body, $GHL_API_KEY, $GHL_API_VERSION);

if ($contact_resp['code'] >= 400) {
    $msg = $contact_resp['body']['message'] ?? 'GHL API error';
    ec_log('Contact upsert FAILED: ' . $msg);
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to create contact: ' . $msg,
        'ghl_code' => $contact_resp['code'],
    ]);
    exit;
}

$contact_id = $contact_resp['body']['contact']['id'] ?? '';
ec_log('Contact upserted. ID: ' . $contact_id);

// ---- Create Opportunity ----
$opp_status = 'skipped';
$opp_id     = '';
$opp_reason = '';

if ($contact_id && $GHL_PIPELINE_ID && $GHL_STAGE_ID) {
    $opp_body = [
        'pipelineId'      => $GHL_PIPELINE_ID,
        'locationId'      => $GHL_LOCATION_ID,
        'name'            => trim($full_name) . ' - ' . ucfirst($service) . ' Estimate',
        'pipelineStageId' => $GHL_STAGE_ID,
        'contactId'       => $contact_id,
        'status'          => 'open',
        'monetaryValue'   => round($estimate_total, 2),
        // Form label lands on the opportunity's native source field.
        'source'          => $opp_source_label,
    ];

    ec_log('--- OPPORTUNITY CREATE ---');
    $opp_resp = ec_ghl_request('POST', '/opportunities/', $opp_body, $GHL_API_KEY, $GHL_API_VERSION);

    if ($opp_resp['code'] >= 400) {
        // Fallback to upsert
        ec_log('Opp create failed, trying upsert...');
        $opp_resp = ec_ghl_request('POST', '/opportunities/upsert', $opp_body, $GHL_API_KEY, $GHL_API_VERSION);
    }

    if ($opp_resp['code'] >= 400) {
        $opp_status = 'error';
        $opp_reason = $opp_resp['body']['message'] ?? 'Unknown error';
        ec_log('Opportunity FAILED: ' . $opp_reason);
    } else {
        $opp_id = $opp_resp['body']['opportunity']['id'] ?? ($opp_resp['body']['id'] ?? '');
        $opp_status = 'success';
        ec_log('Opportunity created. ID: ' . $opp_id);
    }
} else {
    ec_log('Opportunity skipped: pipeline/stage not configured');
}

// ---- Success ----
echo json_encode([
    'success'    => true,
    'contact_id' => $contact_id,
    'opp_status' => $opp_status,
    'opp_id'     => $opp_id,
    'opp_reason' => $opp_reason,
    'estimate'   => [
        'low'   => $estimate_low,
        'high'  => $estimate_high,
        'total' => $estimate_total,
    ],
]);
