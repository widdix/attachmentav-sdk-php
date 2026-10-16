<?php

declare(strict_types=1);

// Usage: ATTACHMENTAV_API_KEY=... php examples/usage.php

require __DIR__ . '/../vendor/autoload.php';

use AttachmentAV\AttachmentAVApi;

$api = AttachmentAVApi::saas((string) getenv('ATTACHMENTAV_API_KEY'));

$usage = $api->getUsage();
printf('%d of %d credits left this %s, tenant ID: %s' . PHP_EOL, $usage->getCredits(), $usage->getQuotaLimit(), strtolower($usage->getQuotaPeriod()), $api->whoami()->getTenantId());
