<?php

declare(strict_types=1);

// Usage: ATTACHMENTAV_API_KEY=... php examples/async-download-polling.php

require __DIR__ . '/../vendor/autoload.php';

use AttachmentAV\AttachmentAVApi;

$api = AttachmentAVApi::saas((string) getenv('ATTACHMENTAV_API_KEY'));

$traceId = bin2hex(random_bytes(16));
$api->scanAsyncDownload(
    'https://www.w3.org/WAI/ER/tests/xhtml/testfiles/resources/pdf/dummy.pdf',
    traceId: $traceId
);
echo "Async download submitted, trace ID: $traceId" . PHP_EOL;

// Each poll counts against your monthly quota.
for ($i = 0; $i < 30; $i++) {
    sleep(10);
    $result = $api->getAsyncResult($traceId);
    if ($result !== null) {
        echo 'Async download scan result: ' . json_encode($result) . PHP_EOL;
        exit(0);
    }
    echo 'Scan not finished yet' . PHP_EOL;
}
exit(1);
