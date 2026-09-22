<?php

namespace Kilo\FilamentQueueMonitor\QueueMonitor\DTO;

class JobInfo
{
    public function __construct(
        public readonly string|int|null $id = null,
        public readonly string|int|null $uuid = null,
        public readonly string $queue,
        public readonly string $job,
        public readonly int $attempts = 0,
        public readonly ?\Illuminate\Support\Carbon $createdAt = null,
        public readonly ?\Illuminate\Support\Carbon $availableAt = null,
        public readonly ?\Illuminate\Support\Carbon $reservedAt = null,
        public readonly string $payload = '',
    ) {}

    public function resolveJobClass(): ?string
    {
        if (blank($this->payload)) {
            return null;
        }

        try {
            $data = json_decode($this->payload, true, 512, JSON_THROW_ON_ERROR);

            return $data['displayName']
                ?? $data['job']
                ?? $data['data']['commandName']
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
