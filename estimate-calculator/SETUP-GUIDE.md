# Estimate Calculator – Client Setup Guide

## Overview

This WordPress plugin provides a multi-step estimate calculator that:
- Captures lead info (name, email, phone)
- Calculates an estimate range based on client-specific pricing
- Creates/updates a contact in GoHighLevel (GHL)
- Fires a tracking pixel on submission (Facebook, Google, TikTok, or custom)
- Redirects to a thank-you page showing the estimate range + GHL booking calendar
- Passes UTM parameters throughout the entire flow

---

## Installation

1. Upload the `estimate-calculator` folder to `/wp-content/plugins/`
2. Activate the plugin in WordPress → Plugins
3. Go to **Estimate Calc** in the admin sidebar

---

## Step-by-Step Client Setup (Repeatable Process)

### 1. GHL Sub-Account API Key (SECURE METHOD)

> **IMPORTANT:** Never give clients agency-level admin access. Always use sub-account API keys.

1. Log into the client's **GHL sub-account** (not the agency dashboard)
2. Go to **Settings → Business Profile → API**
3. Generate a new API key — this is scoped to that sub-account only
4. Copy the API key and the **Location ID** (shown in Settings → Business Info)
5. Enter both in the plugin's **GHL Integration** tab

**Permission level:** Sub-account API keys can only access their own location. This is the secure, correct approach.

### 2. Calendar Setup

1. In GHL, go to **Calendars**
2. Create or identify the booking calendar for estimates
3. Click the calendar → **Calendar Settings**
4. Copy the **Calendar ID** from the URL (the alphanumeric string after `/calendars/`)
5. Copy the **Calendar Embed URL** — click "Embed" and grab the iframe src URL
   - Format: `https://link.clientdomain.com/widget/booking/XXXXXXX`
6. Enter both in the plugin's **GHL Integration** tab

### 3. Create WordPress Pages

**Calculator Page:**
1. Create a new page (e.g., "Painting Estimate")
2. Add the shortcode: `[estimate_calculator]`
3. Publish

**Thank-You Page:**
1. Create a new page (e.g., "Estimate Results")
2. Add the shortcode: `[estimate_thankyou]`
3. Publish
4. In the plugin settings → **Tracking & Pixel** tab, select this page as the Thank-You Page

### 4. Configure Services & Pricing

1. Go to **Services** tab — enable/disable Interior, Exterior, Cabinet as needed
2. Upload service images for each card
3. Go to each pricing tab (Interior, Exterior, Cabinet) and adjust:
   - Base prices per room size / home size / item
   - Material multipliers
   - Condition multipliers
   - Range calculation divisors/offsets

**Default pricing matches the original All American Trade Work calculator.** Adjust per client.

### 5. Tracking Pixel (Optional)

1. Go to **Tracking & Pixel** tab
2. Select pixel type (Facebook, Google Ads, TikTok, Custom, or None)
3. Enter the Pixel ID / Conversion ID
4. For custom tracking, paste the full script in the Custom Pixel Code field

**When the pixel fires:**
- Base pixel loads on the calculator page
- Conversion event fires on successful submission (before redirect)
- Conversion event also fires on the thank-you page load

### 6. Business Info & Google Online Estimate Schema

The plugin outputs **Schema.org JSON-LD** structured data compliant with [Google's Online Estimate spec](https://developers.google.com/search/docs/appearance/structured-data). This helps your estimate tool surface in Google Search with rich results.

1. Go to **Business & Schema** tab
2. Check "Enable Schema Output"
3. Pick the right **Business Type** (HousePainter, Plumber, RoofingContractor, etc.)
4. Fill in business name, phone, email, URL, logo
5. Add the full postal address (street, city, state, postal, country)
6. Set "Area Served" (e.g., "Portland, OR metro area")
7. Set price currency (USD, CAD, EUR, etc.)

**What gets output:**
- **Calculator page:** `Service` schema with `QuoteAction` (the Online Estimate action), `OfferCatalog` listing enabled services, and `LocalBusiness` provider
- **Results page:** `Offer` schema with `PriceSpecification` (minPrice, maxPrice) for the specific estimate

Verify with the [Google Rich Results Test](https://search.google.com/test/rich-results).

### 7. Branding & Labels

1. Go to **Branding & Labels** tab
2. Customize service names (e.g., "Interior Painting" → "Interior House Painting")
3. Set primary/secondary/button colors to match client brand
4. Update SMS consent text, phone number, privacy/terms URLs

### 7. GHL Pipeline (Optional)

If you want new leads automatically placed in a pipeline:
1. In GHL, go to **Opportunities → Pipelines**
2. Copy the Pipeline ID and the Stage ID for new leads
3. Enter in the plugin's GHL Integration tab

---

## Data Flow (Full Tracking Loop)

```
User visits calculator page
    ↓
Selects service → fills in project details → enters contact info
    ↓
Clicks "Get Estimate"
    ↓
[Client-Side] Pixel fires (Lead / Conversion event)
    ↓
[Server-Side] AJAX → WordPress calculates estimate → calls GHL API
    ↓
GHL: Contact created/updated with:
    - Name, email, phone
    - Tags: estimate-calculator, service-{type}, utm-{source}
    - Custom fields: estimate range, service details, date
    - Pipeline opportunity (if configured)
    ↓
Redirect to thank-you page with ?low=X&high=Y&service=Z
    ↓
Thank-you page displays estimate range
    ↓
GHL booking calendar iframe loads (pre-embedded)
    ↓
User books estimate appointment → GHL handles booking
    ↓
GHL: Contact updated with booking info via calendar workflow
```

---

## GHL Custom Fields to Create

In the client's GHL sub-account, create these custom fields (Contact → Custom Fields):

| Field Name | Field Key | Type |
|---|---|---|
| Estimate Service | `estimate_service` | Text |
| Estimate Total | `estimate_total` | Number |
| Estimate Low Range | `estimate_low_range` | Number |
| Estimate High Range | `estimate_high_range` | Number |
| Estimate Date | `estimate_date` | Date |
| Last General Source | `last_general_source` | Text |
| Interior Small Rooms | `interior_small_rooms` | Number |
| Interior Medium Rooms | `interior_medium_rooms` | Number |
| Interior Large Rooms | `interior_large_rooms` | Number |
| Interior XLarge Rooms | `interior_xlarge_rooms` | Number |
| Interior Doors | `interior_doors` | Number |
| Interior Condition | `interior_condition` | Text |
| Exterior Home Size | `exterior_home_size` | Text |
| Exterior Material | `exterior_material` | Text |
| Exterior Condition | `exterior_condition` | Text |
| Cabinet Doors | `cabinet_doors` | Number |
| Cabinet Drawers | `cabinet_drawers` | Number |
| Cabinet Island | `cabinet_island` | Text |
| Cabinet Condition | `cabinet_condition` | Text |

---

## GHL Tags Created Automatically

- `estimate-calculator` — all leads from the calculator
- `service-interior` / `service-exterior` / `service-cabinet`
- `utm-{source}` — if UTM source is present
- `estimate-booked` — after booking (if workflow configured)

---

## Troubleshooting

**Calculator not showing?**
- Ensure the shortcode `[estimate_calculator]` is on the page
- Check that at least one service is enabled in Settings → Services

**GHL contact not created?**
- Verify the API key is a sub-account key (not agency)
- Check the Location ID matches the sub-account
- Test with WP debug log enabled (`WP_DEBUG_LOG = true`)

**Pixel not firing?**
- Ensure the pixel base code is loading on the page (check browser console)
- Verify the Pixel ID is correct
- Check that pixel type is not set to "None"

**Calendar not showing on thank-you page?**
- Verify the Calendar Embed URL is entered in settings
- Ensure the thank-you page has `[estimate_thankyou]` shortcode
- Check that the URL is not blocked by browser ad-blockers

---

## Security Notes

- **Never use agency-level API keys** in the plugin. Always use sub-account keys.
- **Never give clients agency admin access.** The sub-account API key scopes them to their own data.
- API keys are stored in the WordPress `wp_options` table. Ensure your WordPress installation is secure.
- All form inputs are sanitized server-side before processing.
- AJAX requests are protected by WordPress nonces.
