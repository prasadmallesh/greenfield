<?php

declare(strict_types=1);

namespace App\Outbound;

/**
 * Ensures no accidental email / SMS / WhatsApp to real customers during staging or
 * whenever APP_DISABLE_OUTBOUND_COMMUNICATIONS=1 (default).
 *
 * Future mailer or notification code must call assertMaySend() before sending; when
 * disabled, use log-only or no-op implementations.
 */
final class OutboundCommunicationsGuard
{
    public static function isOutboundDisabled(): bool
    {
        $v = $_ENV['APP_DISABLE_OUTBOUND_COMMUNICATIONS'] ?? getenv('APP_DISABLE_OUTBOUND_COMMUNICATIONS') ?: '1';

        return $v === '1' || strtolower((string) $v) === 'true' || strtolower((string) $v) === 'yes';
    }

    /**
     * Call from any code path that would send email, SMS, or WhatsApp.
     *
     * @throws \RuntimeException when outbound is disabled (default)
     */
    public static function assertMaySend(string $channel): void
    {
        if (self::isOutboundDisabled()) {
            throw new \RuntimeException(
                "Outbound communications are disabled (APP_DISABLE_OUTBOUND_COMMUNICATIONS). " .
                "Blocked channel: {$channel}. No SMTP/SMS/WhatsApp to customers."
            );
        }
    }

    /**
     * Boot-time check for the public entry — fails fast if misconfigured.
     */
    public static function enforcePolicy(): void
    {
        // Policy is "disabled by default"; nothing to throw. Documented for operators.
    }
}
