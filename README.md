# attachmentav-sdk-php

[![Packagist Version](https://img.shields.io/packagist/v/attachmentav/virus-scan-sdk)](https://packagist.org/packages/attachmentav/virus-scan-sdk)
[![PHP Version](https://img.shields.io/packagist/dependency-v/attachmentav/virus-scan-sdk/php)](https://packagist.org/packages/attachmentav/virus-scan-sdk)
[![Tests](https://github.com/widdix/attachmentav-sdk-php/actions/workflows/tests.yml/badge.svg)](https://github.com/widdix/attachmentav-sdk-php/actions/workflows/tests.yml)

An SDK to integrate virus and malware scan capabilities into PHP applications. Scan files for viruses, trojans, and other kinds of malware with [attachmentAV](https://attachmentav.com) powered by Sophos.

* Supports the attachmentAV API **(SaaS)** and **(Self-hosted on AWS)**.
* PHP 8.0 or newer.
* Works with any [PSR-18](https://www.php-fig.org/psr/psr-18/) HTTP client: Guzzle, Symfony HttpClient, and others are detected automatically.
* Integrates with **Laravel**: auto-discovered service provider, facade, config file, and a `VirusFree` validation rule.
* Works with **Symfony**, Slim, Laminas/Mezzio, CodeIgniter, Yii, CakePHP, and plain PHP.
* Verifies the signatures of callbacks sent by attachmentAV.

## Getting started

First, install the package [attachmentav/virus-scan-sdk](https://packagist.org/packages/attachmentav/virus-scan-sdk) from Packagist with [Composer](https://getcomposer.org/).

```bash
composer require attachmentav/virus-scan-sdk
```

The SDK needs a PSR-18 HTTP client. Laravel already includes Guzzle. Symfony applications can use `symfony/http-client` together with `nyholm/psr7`. If your project has no HTTP client yet, the `php-http/discovery` Composer plugin installs one for you, or you can install one yourself: `composer require guzzlehttp/guzzle`.

Second, get an API key by [subscribing to the attachmentAV API (SaaS)](https://attachmentav.com/subscribe/api/).

Third, send a scan request. Replace `<API_KEY_PLACEHOLDER>` with your API key.

```php
use AttachmentAV\AttachmentAVApi;
use AttachmentAV\Configuration;
use AttachmentAV\Region;

$api = new AttachmentAVApi(Configuration::saas('<API_KEY_PLACEHOLDER>', Region::EU));

$result = $api->scanSyncFile('/path/to/file');

if ($result->isInfected()) {
    echo 'Malware detected: ' . $result->getFinding();
}
```

The scan result looks like this:

```php
$result->getStatus();       // 'clean', 'infected', or 'no' (file could not be scanned)
$result->getFinding();      // e.g. 'EICAR-AV-Test' for infected files, otherwise null
$result->getSize();         // 8100
$result->getRealFileType(); // 'ASCII text / 8-bit Unicode Transformation Format'
```

## What is attachmentAV?

[attachmentAV](https://attachmentav.com) offers antivirus for SaaS and cloud platforms. Scan your files and attachments stored in the cloud for viruses, worms, and trojans. attachmentAV detects malware in real-time. Supports Amazon S3, Atlassian, Cloudflare R2, Salesforce, WordPress, and more.

The [attachmentAV Virus and Malware Scan API](https://attachmentav.com/solution/virus-malware-scan-api/) provides a REST API that allows you to integrate malware scans into your application. The solution comes in two variants:

* [attachmentAV Virus Scan API (SaaS)](https://attachmentav.com/help/virus-malware-scan-api/setup-guide/): Get started quickly with a fully managed service.
* [attachmentAV Virus Scan API (self-hosted on AWS)](https://attachmentav.com/help/virus-malware-scan-api-aws/setup-guide/): Deploy the production-ready API on AWS.

attachmentAV raises the bar for information security. Our solution is ISO 27001 certified and GDPR compliant. We are establishing, implementing, maintaining, and continually improving an information security management system (ISMS). Sensitive data is encrypted in transit as well as at rest and deleted immediately after processing. More than 1,000 customers trust our malware protection technology.

## Configure SDK

### Configure SDK (SaaS)

An [active subscription and API key](https://attachmentav.com/help/virus-malware-scan-api/setup-guide/#api-key) are required. The region must match the region selected for the subscription. The default is `Region::EU`.

| Region | Constant | API endpoint |
| --- | --- | --- |
| Europe | `Region::EU` | `https://eu.developer.attachmentav.com/v1` |
| United States of America | `Region::US` | `https://us.developer.attachmentav.com/v1` |
| Canada | `Region::CANADA` | `https://canada.developer.attachmentav.com/v1` |
| India | `Region::INDIA` | `https://india.developer.attachmentav.com/v1` |
| Australia | `Region::AUSTRALIA` | `https://australia.developer.attachmentav.com/v1` |

```php
use AttachmentAV\AttachmentAVApi;
use AttachmentAV\Configuration;
use AttachmentAV\Region;

$api = new AttachmentAVApi(Configuration::saas('<API_KEY_PLACEHOLDER>', Region::US));
```

The SDK sends the API key in the `x-api-key` header.

### Configure SDK (self-hosted on AWS)

When you followed the setup guide, you set the `ApiKeys` parameter of the CloudFormation stack. Replace `<API_KEY_PLACEHOLDER>` with one of those keys. Replace `attachmentav.yourcompany.com` with the [domain name of your attachmentAV API installation](https://attachmentav.com/help/virus-malware-scan-api-aws/developer/definition.html#domain-name).

```php
use AttachmentAV\AttachmentAVApi;
use AttachmentAV\Configuration;

$api = new AttachmentAVApi(Configuration::selfHosted('<API_KEY_PLACEHOLDER>', 'https://attachmentav.yourcompany.com/api/v1'));
```

The SDK sends the API key as a bearer token (`Authorization: Bearer ...`). If you pass only the domain (`https://attachmentav.yourcompany.com`), the SDK appends `/api/v1`.

> The self-hosted variant supports the sync actions: `scanSyncBinary()`, `scanSyncFile()`, `scanSyncDownload()`, and `scanSyncS3()`. Async scans, callbacks, `getUsage()`, `whoami()`, and `test()` are only available for the SaaS variant.

### Advanced configuration

```php
use AttachmentAV\AttachmentAVApi;
use AttachmentAV\Configuration;

$config = Configuration::saas('<API_KEY_PLACEHOLDER>')
    ->withMaxRetries(5)              // retries when requests are throttled (429), default: 3, 0 disables retries
    ->withRetryDelayMs(1000)         // base delay of the exponential backoff, default: 500 ms
    ->withTimeout(90)                // request timeout in seconds, default: 65 (sync scans take up to 60 seconds)
    ->withDefaultHeaders(['X-Request-Id' => 'abc']);

$api = new AttachmentAVApi($config);
```

If Guzzle or Symfony HttpClient is installed, the SDK creates the HTTP client with the configured timeout. If the API asks to wait longer than 30 seconds before retrying (`Retry-After`), the SDK throws a `RateLimitException` instead of blocking the request.

You can also pass your own PSR-18 client, e.g. to use a proxy. In that case, configure the timeout on your client:

```php
$httpClient = new \GuzzleHttp\Client(['timeout' => 65, 'connect_timeout' => 10, 'proxy' => 'http://proxy:3128']);

$api = new AttachmentAVApi($config, $httpClient);
```

You can also create the configuration from an array, for example from environment variables or framework configuration:

```php
$config = Configuration::fromArray([
    'deployment' => 'self-hosted',   // or 'saas'
    'api_key' => getenv('ATTACHMENTAV_API_KEY'),
    'region' => 'eu',                // SaaS only
    'base_url' => getenv('ATTACHMENTAV_BASE_URL'), // required for self-hosted
    'timeout' => 65,
]);
```

Empty values, such as environment variables that are set but blank, are treated as missing. So `ATTACHMENTAV_REGION=` falls back to `eu`.

## Examples

### Sync Scan: File

Upload a file to the attachmentAV Virus Scan API and get the scan result back.

SaaS: the maximum file size is 10 MB and the request timeout is 60 seconds.

```php
$result = $api->scanSyncFile('/path/to/file');

// Or pass the content as a string, a stream resource, or a PSR-7 stream.
$result = $api->scanSyncBinary(file_get_contents('/path/to/file'));
$result = $api->scanSyncBinary(fopen('/path/to/file', 'rb'));
```

The SDK always scans the whole content, from the start. A stream resource you pass stays open and is rewound afterwards, so you can keep using it, for example to store the file after a clean scan.

Find the full example: [examples/sync-binary.php](examples/sync-binary.php)

### Sync Scan: Download

Send a URL to the attachmentAV Virus Scan API. attachmentAV downloads the file and returns the scan result right away.

SaaS: the maximum file size is 200 MB and the request timeout is 60 seconds.

```php
$result = $api->scanSyncDownload('https://www.w3.org/WAI/ER/tests/xhtml/testfiles/resources/pdf/dummy.pdf');

// Optional: headers to send when downloading the file
$result = $api->scanSyncDownload('https://example.com/private.pdf', ['Authorization' => 'Bearer ...']);
```

Find the full example: [examples/sync-download.php](examples/sync-download.php)

### Sync Scan: S3

Send an S3 bucket name and object key to the attachmentAV Virus Scan API. attachmentAV downloads the object and returns the scan result right away.

SaaS: a [bucket policy](https://attachmentav.com/help/virus-malware-scan-api/developer/definition.html#s3-bucket-policy) is required to grant attachmentAV access to the S3 objects. The maximum file size is 200 MB and the request timeout is 60 seconds.

```php
$result = $api->scanSyncS3('<BUCKET_NAME_PLACEHOLDER>', '<OBJECT_KEY_PLACEHOLDER>');

// For versioned buckets
$result = $api->scanSyncS3('<BUCKET_NAME_PLACEHOLDER>', '<OBJECT_KEY_PLACEHOLDER>', '<OBJECT_VERSION_PLACEHOLDER>');
```

Find the full example: [examples/sync-s3.php](examples/sync-s3.php)

### Async Scan: Download (SaaS)

Send a URL to the attachmentAV Virus Scan API. attachmentAV downloads and scans the file in the background. The maximum file size is 5 GB.

There are two ways to get the scan result: **callback** or **polling**.

**Callback**: attachmentAV sends the scan result to your callback URL with an HTTPS POST request. See [Verify Callbacks](#verify-callbacks).

```php
$api->scanAsyncDownload(
    'https://www.w3.org/WAI/ER/tests/xhtml/testfiles/resources/pdf/dummy.pdf',
    callbackUrl: 'https://api.yourcompany.com/attachmentav/callback',
    customData: json_encode(['documentId' => 42]) // optional, passed through to the callback (max. 16 KB)
);
```

**Polling**: pass a trace ID and poll `getAsyncResult()`. It returns `null` until the scan result is available. Scan results are stored for 24 hours. Each poll counts against your monthly quota.

```php
$traceId = bin2hex(random_bytes(16));
$pollingUrl = $api->scanAsyncDownload('https://www.w3.org/WAI/ER/tests/xhtml/testfiles/resources/pdf/dummy.pdf', traceId: $traceId);
// $pollingUrl: https://eu.developer.attachmentav.com/v1/scan/async/result?trace_id=... (null when a callback URL is used)

do {
    sleep(10);
    $result = $api->getAsyncResult($traceId);
} while ($result === null);
```

Find the full example: [examples/async-download-polling.php](examples/async-download-polling.php)

### Async Scan: S3 (SaaS)

Send an S3 bucket name and object key to the attachmentAV Virus Scan API. attachmentAV downloads and scans the object in the background. A [bucket policy](https://attachmentav.com/help/virus-malware-scan-api/developer/definition.html#s3-bucket-policy) is required. The maximum file size is 5 GB.

```php
$api->scanAsyncS3(
    '<BUCKET_NAME_PLACEHOLDER>',
    '<OBJECT_KEY_PLACEHOLDER>',
    callbackUrl: 'https://api.yourcompany.com/attachmentav/callback'
);

// Or with polling
$api->scanAsyncS3('<BUCKET_NAME_PLACEHOLDER>', '<OBJECT_KEY_PLACEHOLDER>', traceId: $traceId);
```

Find the full example: [examples/async-s3-callback.php](examples/async-s3-callback.php)

### Callback failures (SaaS)

If attachmentAV cannot deliver a scan result to your callback URL after several retries, it records a callback failure. Callback failures are kept for 30 days.

```php
$cursor = null;
do {
    $page = $api->getCallbackFailures('https://api.yourcompany.com/attachmentav/callback', $cursor);
    foreach ($page as $failure) {
        echo $failure->getTime()?->format(DATE_ATOM) . ' ' . $failure->getDownloadUrl() . ' ' . $failure->getErrorJson() . PHP_EOL;
    }
    $cursor = $page->getNextCursor();
} while ($cursor !== null);
```

### Usage and account (SaaS)

```php
$usage = $api->getUsage();
echo $usage->getCredits() . ' of ' . $usage->getQuotaLimit() . ' credits left';

$tenantId = $api->whoami()->getTenantId();

$api->test(); // throws AuthenticationException if the API key is invalid
```

Each of these requests counts against your monthly quota.

Find the full example: [examples/usage.php](examples/usage.php)

## Verify Callbacks

attachmentAV signs every callback. Verify the signature before you trust the scan result. You need your tenant ID, which you can get once with `$api->whoami()->getTenantId()`. The SDK includes the [public keys](https://attachmentav.com/help/virus-malware-scan-api/developer/publickey.html) of all regions.

```php
use AttachmentAV\Callback\CallbackVerifier;
use AttachmentAV\Region;

$verifier = CallbackVerifier::forRegion(Region::EU, '<TENANT_ID_PLACEHOLDER>');

$payload = $verifier->verifyAndParse(
    file_get_contents('php://input'),   // the raw request body
    $_SERVER['HTTP_X_SIGNATURE'] ?? null,
    $_SERVER['HTTP_X_TIMESTAMP'] ?? null,
    'https://api.yourcompany.com/attachmentav/callback' // the callback URL used when submitting the scan job
); // throws InvalidSignatureException if the signature is invalid or older than 5 minutes

$payload->getStatus();     // 'clean', 'infected', or 'no'
$payload->getTraceId();
$payload->getCustomData();
```

Use `$verifier->verify(...)` to get a `bool` instead of an exception. Use `$verifier->verifyRequest($psr7Request, $callbackUrl)` with PSR-7 requests (Slim, Mezzio, ...).

Find the full example: [examples/verify-callback.php](examples/verify-callback.php)

## Error handling

Every exception thrown by the SDK implements `AttachmentAV\Exception\ExceptionInterface`.

| Exception | When |
| --- | --- |
| `AuthenticationException` | `401`/`403`: invalid API key, or the API endpoint does not match the region of the subscription. |
| `RateLimitException` | `429`: throttled (`isThrottled()`, retried automatically) or monthly quota exceeded (`isQuotaExceeded()`). |
| `NotFoundException` | `404` |
| `ApiException` | Any other non-2xx response; parent class of the three exceptions above. Use `getStatusCode()`, `getErrorMessage()`, `getResponseBody()`. |
| `TransportException` | Network error, e.g. DNS failure or timeout. |
| `UnexpectedResponseException` | The response cannot be parsed. |
| `InvalidArgumentException` | Invalid input, detected before a request is sent. |

```php
use AttachmentAV\Exception\ExceptionInterface;
use AttachmentAV\Exception\RateLimitException;

try {
    $result = $api->scanSyncFile($path);
} catch (RateLimitException $e) {
    // quota exceeded or still throttled after retries
} catch (ExceptionInterface $e) {
    // any other error
}
```

## Laravel

Try it: [examples/laravel](examples/laravel) is a complete upload form you can run with `docker compose up`.

Laravel registers the service provider and the `AttachmentAV` facade automatically. Add the configuration to your `.env` file:

```dotenv
# SaaS
ATTACHMENTAV_API_KEY=<API_KEY_PLACEHOLDER>
ATTACHMENTAV_REGION=eu
ATTACHMENTAV_TENANT_ID=<TENANT_ID_PLACEHOLDER> # optional, required to verify callbacks

# Self-hosted on AWS
ATTACHMENTAV_DEPLOYMENT=self-hosted
ATTACHMENTAV_API_KEY=<API_KEY_PLACEHOLDER>
ATTACHMENTAV_BASE_URL=https://attachmentav.yourcompany.com/api/v1
```

Optionally, publish the config file to `config/attachmentav.php`:

```bash
php artisan vendor:publish --tag=attachmentav-config
```

Validate uploads with the `VirusFree` rule (Laravel 10+). It fails if the file is infected, cannot be scanned, or if the API cannot be reached:

```php
use AttachmentAV\Laravel\Rules\VirusFree;

$request->validate([
    'attachment' => ['required', 'file', 'max:10240', new VirusFree()],
]);
```

Options:
* `new VirusFree(allowUnscannable: true)` accepts files that attachmentAV cannot scan (status `no`), such as encrypted archives.
* `new VirusFree(failOnError: false)` accepts files unscanned during transient errors (network errors, throttling, `5xx`). Errors that would let every file through unscanned, such as an invalid configuration, an invalid API key, or an exceeded quota, still fail validation.

Use the facade, or inject `AttachmentAV\AttachmentAVApi`:

```php
use AttachmentAV\AttachmentAVApi;
use AttachmentAV\Laravel\Facades\AttachmentAV;

$result = AttachmentAV::scanSyncFile($request->file('attachment')->getRealPath());

class DocumentController
{
    public function store(Request $request, AttachmentAVApi $attachmentAV) { /* ... */ }
}
```

Verify callbacks in a route. Exclude the route from CSRF protection:

```php
use AttachmentAV\Callback\CallbackVerifier;
use Illuminate\Http\Request;

Route::post('/attachmentav/callback', function (Request $request, CallbackVerifier $verifier) {
    if (!$verifier->verify($request->getContent(), $request->header('X-Signature'), $request->header('X-Timestamp'), url('/attachmentav/callback'))) {
        abort(403);
    }
    $payload = \AttachmentAV\Model\CallbackPayload::fromJson($request->getContent());
    // ...
    return response()->noContent();
});
```

## Symfony

Try it: [examples/symfony](examples/symfony) is a complete upload form you can run with `docker compose up`.

Register the client as a service in `config/services.yaml`. If `symfony/http-client` and `nyholm/psr7` are installed, Symfony's HTTP client is used.

```yaml
services:
    AttachmentAV\Configuration:
        factory: ['AttachmentAV\Configuration', 'fromArray']
        arguments:
            - deployment: '%env(default:attachmentav_default_deployment:ATTACHMENTAV_DEPLOYMENT)%'
              api_key: '%env(ATTACHMENTAV_API_KEY)%'
              region: '%env(default:attachmentav_default_region:ATTACHMENTAV_REGION)%'
              base_url: '%env(default::ATTACHMENTAV_BASE_URL)%'

    AttachmentAV\AttachmentAVApi:
        arguments:
            $configuration: '@AttachmentAV\Configuration'

parameters:
    attachmentav_default_deployment: 'saas'
    attachmentav_default_region: 'eu'
```

Then inject `AttachmentAV\AttachmentAVApi` with autowiring:

```php
use AttachmentAV\AttachmentAVApi;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class UploadController
{
    public function __construct(private AttachmentAVApi $attachmentAV) {}

    public function upload(UploadedFile $file): void
    {
        $result = $this->attachmentAV->scanSyncFile($file->getPathname());
        // ...
    }
}
```

## Other frameworks and plain PHP

`AttachmentAVApi` is a plain PHP class with no framework dependencies. Create it once and register it in your framework's container, for example a CodeIgniter service, a Yii component, a Laminas factory, or a PHP-DI definition. To use a specific HTTP client, pass any PSR-18 client and PSR-17 factories:

```php
$api = new AttachmentAVApi($config, $psr18Client, $psr17RequestFactory, $psr17StreamFactory);
```

## Development

See [DEV.md](DEV.md).

## License

[MIT](LICENSE)
