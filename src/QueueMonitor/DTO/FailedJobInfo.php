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
            $jobData = is_array($data['data'] ?? null) ? $data['data'] : [];

            $name = $data['displayName']
                ?? $data['job']
                ?? $jobData['commandName']
                ?? $jobData['command']
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
