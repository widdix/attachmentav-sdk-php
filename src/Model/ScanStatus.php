<?php

declare(strict_types=1);

namespace AttachmentAV\Model;

/**
 * Possible values of {@see ScanResult::getStatus()}.
 */
final class ScanStatus
{
    /** No virus or malware detected. */
    public const CLEAN = 'clean';

    /** Virus or malware detected, see {@see ScanResult::getFinding()}. */
    public const INFECTED = 'infected';

    /** The file could not be scanned (e.g. encrypted or password-protected archive). */
    public const NO = 'no';

    private function __construct()
    {
    }
}
