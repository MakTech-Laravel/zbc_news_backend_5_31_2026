<?php

return [
    /** Estimated CPM in cents (e.g. 200 = $2.00 per 1,000 impressions). */
    'cpm_cents' => (int) env('MONETIZATION_CPM_CENTS', 200),

    /** Estimated monthly value per verified newsletter subscriber in cents. */
    'newsletter_subscriber_value_cents' => (int) env('MONETIZATION_NEWSLETTER_VALUE_CENTS', 500),
];
