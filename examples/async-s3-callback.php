<?php

declare(strict_types=1);

// Usage: ATTACHMENTAV_API_KEY=... php examples/async-s3-callback.php <bucket> <key> <callback-url>

require __DIR__ . '/../vendor/autoload.php';

use AttachmentAV\AttachmentAVApi;

$api = AttachmentAVApi::saas((string) getenv('ATTACHMENTAV_API_KEY'));

$api->scanAsyncS3($argv[1], $argv[2], callbackUrl: $argv[3], customData: json_encode(['documentId' => 42]));
echo 'Async S3 scan submitted, the scan result is sent to ' . $argv[3] . PHP_EOL;
