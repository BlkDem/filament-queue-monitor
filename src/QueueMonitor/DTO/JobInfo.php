<?php

namespace BlkDem\FilamentQueueMonitor\QueueMonitor\DTO;

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
            $jobData = is_array($data['data'] ?? null) ? $data['data'] : [];

            $name = $data['displayName']
                ?? $data['job']
                ?? $jobData['commandName']
                ?? null;

            return is_string($name) ? $name : null;
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
            $data = json_decode($this->payload, true, 512, JSON_THROW_ON_ERROR);

            return is_array($data) ? $data : [];
        } catch (\JsonException) {
            return [];
        }
    }
}
