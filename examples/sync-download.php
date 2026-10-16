<?php

declare(strict_types=1);

// Usage: ATTACHMENTAV_API_KEY=... php examples/sync-download.php

require __DIR__ . '/../vendor/autoload.php';

use AttachmentAV\AttachmentAVApi;

$api = AttachmentAVApi::saas((string) getenv('ATTACHMENTAV_API_KEY'));

$result = $api->scanSyncDownload('https://www.w3.org/WAI/ER/tests/xhtml/testfiles/resources/pdf/dummy.pdf');
echo 'Sync download scan result: ' . json_encode($result) . PHP_EOL;
