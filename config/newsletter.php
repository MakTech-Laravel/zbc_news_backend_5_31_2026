<?php

return [
  'batch_size' => (int) env('NEWSLETTER_BATCH_SIZE', 50),

  'providers' => [
    'smtp',
    'resend',
    'brevo',
    'mailchimp',
  ],

  'default_from_email' => env('NEWSLETTER_FROM_EMAIL', env('MAIL_FROM_ADDRESS', 'newsletter@example.com')),
  'default_from_name' => env('NEWSLETTER_FROM_NAME', env('MAIL_FROM_NAME', 'ZBC News')),
];
