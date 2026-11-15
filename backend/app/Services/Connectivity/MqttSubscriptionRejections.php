<?php

namespace App\Services\Connectivity;

use Psr\Log\AbstractLogger;

/**
 * Catches the topic filters a broker refused.
 *
 * php-mqtt/client does not throw when a broker rejects one subscription of a
 * SUBACK (MQTT 3.1.1 return code 0x80): it logs a notice and carries on. A
 * broker whose ACL allows `hall1/press3/count` but not `hall1/#` would then
 * leave the connection "connected" and silent. Handed to the client as its
 * logger, this keeps those filters so the listener can fall back to the
 * narrower ones it pruned.
 */
final class MqttSubscriptionRejections extends AbstractLogger
{
    /** @var list<string> */
    private array $rejected = [];

    public function log($level, string|\Stringable $message, array $context = []): void
    {
        if (isset($context['topicFilter']) && str_contains((string) $message, 'rejected the subscription')) {
            $this->rejected[] = (string) $context['topicFilter'];
        }
    }

    /**
     * The filters rejected since the last call.
     *
     * @return list<string>
     */
    public function take(): array
    {
        $rejected = $this->rejected;
        $this->rejected = [];

        return $rejected;
    }
}
