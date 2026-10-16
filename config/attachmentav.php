<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Deployment
    |--------------------------------------------------------------------------
    |
    | "saas" for the attachmentAV API (SaaS) or "self-hosted" for the
    | attachmentAV API (Self-hosted on AWS).
    |
    */

    'deployment' => env('ATTACHMENTAV_DEPLOYMENT', 'saas'),

    /*
    |--------------------------------------------------------------------------
    | API Key
    |--------------------------------------------------------------------------
    |
    | SaaS: the API key of your subscription.
    | Self-hosted: one of the keys of the "ApiKeys" CloudFormation parameter.
    |
    */

    'api_key' => env('ATTACHMENTAV_API_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Region (SaaS only)
    |--------------------------------------------------------------------------
    |
    | Must match the region of your subscription: eu, us, canada, india,
    | or australia.
    |
    */

    'region' => env('ATTACHMENTAV_REGION', 'eu'),

    /*
    |--------------------------------------------------------------------------
    | Base URL
    |--------------------------------------------------------------------------
    |
    | Self-hosted (required): the URL of your installation, for example
    | https://attachmentav.yourcompany.com/api/v1
    | SaaS (optional): overrides the endpoint derived from the region.
    |
    */

    'base_url' => env('ATTACHMENTAV_BASE_URL'),

    /*
    |--------------------------------------------------------------------------
    | Tenant ID (SaaS only)
    |--------------------------------------------------------------------------
    |
    | Required to verify callbacks of async scans. Run
    | `AttachmentAV::whoami()->getTenantId()` once to get your tenant ID.
    |
    */

    'tenant_id' => env('ATTACHMENTAV_TENANT_ID'),

    /*
    |--------------------------------------------------------------------------
    | HTTP
    |--------------------------------------------------------------------------
    |
    | Timeout in seconds when Guzzle is used as HTTP client (sync scans may
    | take up to 60 seconds), and retries with exponential backoff when the
    | API throttles requests (429 Too Many Requests).
    |
    */

    'timeout' => (float) env('ATTACHMENTAV_TIMEOUT', 65),

    'max_retries' => (int) env('ATTACHMENTAV_MAX_RETRIES', 3),

    'retry_delay_ms' => (int) env('ATTACHMENTAV_RETRY_DELAY_MS', 500),

];
