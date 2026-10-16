<?php

declare(strict_types=1);

namespace AttachmentAV\Laravel\Facades;

use AttachmentAV\AttachmentAVApi;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \AttachmentAV\Model\ScanResult scanSyncBinary(string|resource|\Psr\Http\Message\StreamInterface $content)
 * @method static \AttachmentAV\Model\ScanResult scanSyncFile(string $path)
 * @method static \AttachmentAV\Model\ScanResult scanSyncDownload(string $downloadUrl, array<string, string> $downloadHeaders = [])
 * @method static \AttachmentAV\Model\ScanResult scanSyncS3(string $bucket, string $key, ?string $version = null)
 * @method static string|null scanAsyncDownload(string $downloadUrl, array<string, string> $downloadHeaders = [], ?string $callbackUrl = null, array<string, string> $callbackHeaders = [], ?string $traceId = null, ?string $customData = null)
 * @method static string|null scanAsyncS3(string $bucket, string $key, ?string $version = null, ?string $callbackUrl = null, array<string, string> $callbackHeaders = [], ?string $traceId = null, ?string $customData = null)
 * @method static \AttachmentAV\Model\ScanResult|null getAsyncResult(string $traceId)
 * @method static \AttachmentAV\Model\CallbackFailureList getCallbackFailures(string $callbackUrl, ?string $cursor = null)
 * @method static \AttachmentAV\Model\Usage getUsage()
 * @method static \AttachmentAV\Model\Whoami whoami()
 * @method static void test()
 * @method static \AttachmentAV\Configuration getConfiguration()
 *
 * @see AttachmentAVApi
 */
class AttachmentAV extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return AttachmentAVApi::class;
    }
}
