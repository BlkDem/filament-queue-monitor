<?php

namespace Kilo\FilamentQueueMonitor\Tests\Unit;

use Kilo\FilamentQueueMonitor\QueueMonitor\DTO\FailedJobInfo;
use Kilo\FilamentQueueMonitor\QueueMonitor\DTO\JobInfo;

describe('Security', function () {
    it('safely parses malicious JSON payload', function () {
        $maliciousPayload = json_encode([
            'uuid' => '<script>alert(1)</script>',
            'job' => 'App\\Jobs\\TestJob',
            'data' => ['command' => '<img src=x onerror=alert(1)>'],
        ]);

        $job = new JobInfo(
            id: 1,
            uuid: '<script>alert(1)</script>',
            queue: 'default',
            job: 'App\\Jobs\\TestJob',
            payload: $maliciousPayload,
        );

        expect(e($job->resolveJobClass()))->toBe('App\\Jobs\\TestJob')
            ->and($job->payload)->toBe($maliciousPayload);
    });

    it('safely parses exception with sensitive data', function () {
        $sensitiveException = 'Error: DB password is "supersecret" at /app/config/database.php:42';

        $job = new FailedJobInfo(
            id: 1,
            uuid: 'test-uuid',
            connection: 'database',
            queue: 'default',
            payload: '{"job":"TestJob","data":{"commandName":"TestJob"}}',
            exception: $sensitiveException,
            failedAt: now(),
        );

        expect($job->exception)->toBe($sensitiveException)
            ->and(e($job->exception))->not->toContain('<script>');
    });

    it('handles malformed JSON payload gracefully', function () {
        $job = new JobInfo(
            id: 1,
            uuid: null,
            queue: 'default',
            job: 'Unknown',
            payload: 'not valid json',
        );

        expect($job->resolveJobClass())->toBeNull()
            ->and($job->resolvePayloadData())->toBe([]);
    });

    it('handles empty payload gracefully', function () {
        $job = new JobInfo(
            id: 1,
            uuid: null,
            queue: 'default',
            job: 'Test',
            payload: '',
        );

        expect($job->resolveJobClass())->toBeNull()
            ->and($job->resolvePayloadData())->toBe([]);
    });

    it('escapes HTML in payload output', function () {
        $payload = json_encode(['job' => '<script>alert("xss")</script>']);
        $escaped = e($payload);

        expect($escaped)->not->toContain('<script>')
            ->and($escaped)->toContain('&lt;script&gt;');
    });

    it('handles deeply nested payload', function () {
        $nestedPayload = json_encode([
            'uuid' => 'test',
            'job' => 'Test',
            'data' => ['command' => serialize(['nested' => str_repeat('x', 10000)])],
        ]);

        $job = new JobInfo(
            id: 1,
            uuid: null,
            queue: 'default',
            job: 'Test',
            payload: $nestedPayload,
        );

        expect($job->resolvePayloadData())->toHaveKey('uuid')
            ->and($job->resolveJobClass())->toBe('Test');
    });
});
