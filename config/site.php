<?php

return [
    'name' => env('SITE_NAME', 'ToolJoy'),
    'tagline' => env('SITE_TAGLINE', 'Everyday file tools, made joyful.'),
    'support_email' => env('SITE_SUPPORT_EMAIL', 'hello@example.test'),
    'logo' => env('SITE_LOGO', '/brand/tooljoy-logo.png'),
    'social_image' => env('SITE_SOCIAL_IMAGE', '/brand/social-preview.svg'),
    'analytics_enabled' => (bool) env('SITE_ANALYTICS_ENABLED', false),
    'advertising_enabled' => (bool) env('SITE_ADVERTISING_ENABLED', false),
    'analytics_script_url' => env('SITE_ANALYTICS_SCRIPT_URL'),
    'contact_address' => env('SITE_CONTACT_ADDRESS', 'Contact us by email for the current postal address.'),
];
