<?php

namespace Kilo\FilamentQueueMonitor\QueueMonitor\DTO;

class FailedJobInfo
{
    public function __construct(
        public readonly string|int|null $id = null,
        public readonly ?string $uuid = null,
        public readonly string $connection,
        public readonly string $queue,
        public readonly string $payload = '',
        public readonly string $exception = '',
        public readonly ?\Illuminate\Support\Carbon $failedAt = null,
    ) {}

    public function resolveJobName(): ?string
    {
        if (blank($this->payload)) {
            return null;
        }

        try {
            $data = json_decode($this->payload, true, 512, JSON_THROW_ON_ERROR);

            return $data['displayName']
                ?? $data['job']
                ?? $data['data']['commandName']
                ?? $data['data']['command']
                ?? null;
        } catch (\JsonException) {
            return null;
        }
    }

    public function resolvePayloadData(): array
    {
        if (blank($this->payload)) {
            return [];
        }

        try {
            return json_decode($this->payload, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }
    }
}
