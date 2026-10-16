<?php

declare(strict_types=1);

// Receives and verifies callbacks of async scans.
//
// 1. Make your local server reachable from the Internet, e.g. with ngrok: `ngrok http 8081`
// 2. Start the server:
//    ATTACHMENTAV_TENANT_ID=... CALLBACK_URL=https://xxxx.ngrok-free.app php -S 0.0.0.0:8081 examples/verify-callback.php
//    (get the tenant ID with examples/usage.php)
// 3. Submit a scan job with the callback URL, e.g.
//    php examples/async-s3-callback.php <bucket> <key> https://xxxx.ngrok-free.app

require __DIR__ . '/../vendor/autoload.php';

use AttachmentAV\Callback\CallbackVerifier;
use AttachmentAV\Region;

$verifier = CallbackVerifier::forRegion(getenv('ATTACHMENTAV_REGION') ?: Region::EU, (string) getenv('ATTACHMENTAV_TENANT_ID'));

$body = (string) file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_SIGNATURE'] ?? null;
$timestamp = $_SERVER['HTTP_X_TIMESTAMP'] ?? null;

if (!$verifier->verify($body, $signature, $timestamp, (string) getenv('CALLBACK_URL'))) {
    http_response_code(403);
    error_log('Received invalid callback: ' . $body);
    exit;
}

$payload = AttachmentAV\Model\CallbackPayload::fromJson($body);
error_log(sprintf('Received valid callback: status=%s finding=%s custom_data=%s', $payload->getStatus(), $payload->getFinding() ?? '-', $payload->getCustomData() ?? '-'));
http_response_code(204);
