<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Supplier portal availability
    |--------------------------------------------------------------------------
    |
    | The supplier portal is intentionally disabled by default. Supplier
    | records, purchasing, receiving and payables are internal restaurant
    | workflows and do not depend on this switch.
    |
    */
    'supplier_portal_enabled' => (bool) env('SUPPLIER_PORTAL_ENABLED', false),
];
