<?php

declare(strict_types=1);

// Usage: ATTACHMENTAV_API_KEY=... php examples/sync-binary.php /path/to/file
// Self-hosted on AWS: also set ATTACHMENTAV_BASE_URL=https://attachmentav.yourcompany.com/api/v1

require __DIR__ . '/../vendor/autoload.php';

use AttachmentAV\AttachmentAVApi;
use AttachmentAV\Configuration;

$baseUrl = getenv('ATTACHMENTAV_BASE_URL');
$config = $baseUrl !== false && $baseUrl !== ''
    ? Configuration::selfHosted((string) getenv('ATTACHMENTAV_API_KEY'), $baseUrl)
    : Configuration::saas((string) getenv('ATTACHMENTAV_API_KEY'), getenv('ATTACHMENTAV_REGION') ?: 'eu');
$api = new AttachmentAVApi($config);

$result = $api->scanSyncFile($argv[1] ?? __FILE__);
echo 'Sync binary scan result: ' . json_encode($result) . PHP_EOL;
