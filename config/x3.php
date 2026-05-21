<?php

/**
 * Centralized Sage X3 + middleware configuration.
 *
 * The Sage `base` value differs per environment (BRDCT in local/staging,
 * BIOTECH in production). Read here, never hard-coded in business logic.
 *
 * Falls back to the existing BASE_MIDDLEWARE env so this can be deployed
 * without an immediate .env change.
 */
return [
    // Sage X3 base (a.k.a. nomBaseSAGE). E.g. "BRDCT" local, "BIOTECH" prod.
    'base' => env('X3_BASE', env('BASE_MIDDLEWARE', 'BIOTECH')),

    // Sage X3 generic SOAP action name. The full body posted to the
    // useX3GenericWSSoap endpoint is { base, action, data }, where `data`
    // is the pipe-joined ZSOH payload (E;... | L;... | END).
    'action' => env('X3_ACTION', 'ZSOH'),

    // Middleware endpoint path (relative to middleware base URI). Defaults
    // to the same path the e-dental-nest project uses.
    'sendorder_path' => env('X3_SENDORDER_PATH', '/v2/useX3GenericWSSoap'),

    // Recipient mailbox for distributor Excel exports.
    'adv_inter_email' => env('ADV_INTER_EMAIL', 'adv-inter@biotech-dental.com'),

    // Distributor checkout site code (header field SALFCY / STOFCY).
    'site' => env('X3_SITE', 'CRAP'),

    // Default order type code (header field SOHTYP).
    'order_type_code' => env('X3_ORDER_TYPE', 'SON'),
];
