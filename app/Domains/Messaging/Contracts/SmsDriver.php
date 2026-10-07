<?php

namespace App\Domains\Messaging\Contracts;

/**
 * @phpstan-type Result array{sent: bool, reference: ?string, error: ?string}
 */
interface SmsDriver
{
    /** @return array{sent: bool, reference: ?string, error: ?string} */
    public function send(string $to, string $message, string $from): array;

    public function name(): string;
}
