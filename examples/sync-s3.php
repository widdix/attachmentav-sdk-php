<?php

declare(strict_types=1);

// Usage: ATTACHMENTAV_API_KEY=... php examples/sync-s3.php <bucket> <key>

require __DIR__ . '/../vendor/autoload.php';

use AttachmentAV\AttachmentAVApi;

$api = AttachmentAVApi::saas((string) getenv('ATTACHMENTAV_API_KEY'));

$result = $api->scanSyncS3($argv[1], $argv[2]);
echo 'Sync S3 scan result: ' . json_encode($result) . PHP_EOL;
