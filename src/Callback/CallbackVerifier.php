<?php

declare(strict_types=1);

namespace AttachmentAV\Callback;

use AttachmentAV\Exception\InvalidArgumentException;
use AttachmentAV\Model\CallbackPayload;
use Psr\Http\Message\RequestInterface;

/**
 * Verifies the signature of callbacks sent by attachmentAV (SaaS) to the `callback_url` of async scan jobs.
 *
 * attachmentAV signs `${timestamp}.${tenantId}.${callbackUrl}.${body}` with RSA-SHA256 and sends the hex encoded
 * signature in the `X-Signature` header and the Unix time in milliseconds in the `X-Timestamp` header.
 *
 * @see https://attachmentav.com/help/virus-malware-scan-api/developer/definition.html#callback-verify
 */
final class CallbackVerifier
{
    public const SIGNATURE_HEADER = 'X-Signature';
    public const TIMESTAMP_HEADER = 'X-Timestamp';
    public const DEFAULT_TOLERANCE_MS = 5 * 60 * 1000;

    private string $publicKeyPem;

    private string $tenantId;

    private int $toleranceMs;

    /** @var callable(): int */
    private $clock;

    /**
     * @param string $publicKeyPem The public key in PEM format, see {@see PublicKeys::forRegion()}.
     * @param string $tenantId     Your tenant ID, see {@see \AttachmentAV\AttachmentAVApi::whoami()}.
     * @param int    $toleranceMs  Accepted difference between the X-Timestamp header and now (prevents replay attacks).
     */
    public function __construct(string $publicKeyPem, string $tenantId, int $toleranceMs = self::DEFAULT_TOLERANCE_MS)
    {
        if (!function_exists('openssl_verify')) {
            throw new \LogicException('The OpenSSL extension (ext-openssl) is required to verify callbacks.');
        }
        if ($tenantId === '') {
            throw new InvalidArgumentException('The tenant ID must not be empty.');
        }
        if (openssl_pkey_get_public($publicKeyPem) === false) {
            throw new InvalidArgumentException('The public key is not a valid PEM encoded public key.');
        }
        $this->publicKeyPem = $publicKeyPem;
        $this->tenantId = $tenantId;
        $this->toleranceMs = $toleranceMs;
        $this->clock = static function (): int {
            return (int) floor(microtime(true) * 1000);
        };
    }

    /**
     * Create a verifier with the public key of the region of your SaaS subscription.
     */
    public static function forRegion(string $region, string $tenantId, int $toleranceMs = self::DEFAULT_TOLERANCE_MS): self
    {
        return new self(PublicKeys::forRegion($region), $tenantId, $toleranceMs);
    }

    /**
     * Replace the clock returning the current Unix time in milliseconds. Intended for tests.
     *
     * @param callable(): int $clock
     */
    public function setClock(callable $clock): void
    {
        $this->clock = $clock;
    }

    /**
     * Verify a callback.
     *
     * @param string      $body        The raw request body (do not decode and re-encode the JSON).
     * @param string|null $signature   The value of the `X-Signature` header.
     * @param string|null $timestamp   The value of the `X-Timestamp` header.
     * @param string      $callbackUrl The callback URL used when submitting the scan job.
     */
    public function verify(string $body, ?string $signature, ?string $timestamp, string $callbackUrl): bool
    {
        if ($signature === null || $timestamp === null || !ctype_digit($timestamp)) {
            return false;
        }
        if (strlen($signature) % 2 !== 0 || !ctype_xdigit($signature)) {
            return false;
        }
        if (abs(($this->clock)() - (int) $timestamp) > $this->toleranceMs) {
            return false;
        }
        $data = $timestamp . '.' . $this->tenantId . '.' . $callbackUrl . '.' . $body;
        $binarySignature = hex2bin($signature);
        if ($binarySignature === false) {
            return false;
        }

        return openssl_verify($data, $binarySignature, $this->publicKeyPem, OPENSSL_ALGO_SHA256) === 1;
    }

    /**
     * Verify a callback received as PSR-7 request (e.g. Symfony via PSR-7 bridge, Slim, Laminas, Mezzio).
     */
    public function verifyRequest(RequestInterface $request, string $callbackUrl): bool
    {
        $body = $request->getBody();
        if ($body->isSeekable()) {
            $body->rewind();
        }
        $contents = $body->getContents();
        if ($body->isSeekable()) {
            $body->rewind();
        }

        return $this->verify(
            $contents,
            $request->hasHeader(self::SIGNATURE_HEADER) ? $request->getHeaderLine(self::SIGNATURE_HEADER) : null,
            $request->hasHeader(self::TIMESTAMP_HEADER) ? $request->getHeaderLine(self::TIMESTAMP_HEADER) : null,
            $callbackUrl
        );
    }

    /**
     * Verify a callback and return the parsed payload.
     *
     * @throws InvalidSignatureException if the signature or timestamp is invalid
     */
    public function verifyAndParse(string $body, ?string $signature, ?string $timestamp, string $callbackUrl): CallbackPayload
    {
        if (!$this->verify($body, $signature, $timestamp, $callbackUrl)) {
            throw new InvalidSignatureException('The signature of the attachmentAV callback is invalid or expired.');
        }

        return CallbackPayload::fromJson($body);
    }
}
