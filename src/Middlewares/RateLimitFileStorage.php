<?php

declare(strict_types=1);

namespace Simsoft\Slim\Middlewares;

use Psr\Log\LoggerInterface;

/**
 * RateLimitFileStorage Class
 *
 * File-based rate limit storage with atomic file locking.
 * Suitable for single-server deployments.
 */
class RateLimitFileStorage implements RateLimitStorageInterface
{
    /** @var string Storage directory path. */
    protected string $storagePath;

    /**
     * Constructor.
     *
     * @param string $storagePath Directory for rate limit files. Defaults to system temp directory.
     * @param LoggerInterface|null $logger Logger notified when storage is unavailable.
     * @param bool $failOpen Whether to allow requests through when storage is unavailable.
     *                       True (default) favours availability: a permissions problem or full
     *                       disk will not take the site down, but the limiter stops limiting.
     *                       False favours protection: requests are rejected while storage is
     *                       broken. Prefer false for endpoints where abuse is costlier than
     *                       downtime.
     */
    public function __construct(
        string $storagePath = '',
        protected ?LoggerInterface $logger = null,
        protected bool $failOpen = true,
    ) {
        $this->storagePath = $storagePath !== '' ? $storagePath : sys_get_temp_dir() . '/slim-rate-limit';

        // Suppressed for the same reason as fopen() below: the path may be
        // unusable (already a file, or unwritable), and a raw PHP warning
        // would leak it into the response. increment() reports the failure
        // through the logger and applies the failOpen policy.
        if (!is_dir($this->storagePath)) {
            @mkdir($this->storagePath, 0755, true);
        }
    }

    /**
     * Atomically increment the request count using file locking.
     *
     * @param string $clientId
     * @param int $windowSeconds
     * @return array{count: int, expires: int}
     */
    public function increment(string $clientId, int $windowSeconds): array
    {
        $file = $this->storagePath . '/' . md5($clientId) . '.json';
        $now = time();

        // Suppressed: a failure here is reported through the logger below,
        // and a raw PHP warning would leak the storage path into the response.
        $handle = @fopen($file, 'c+');

        if ($handle === false) {
            // Storage is unreachable (permissions, full disk, exhausted inodes).
            // Whichever way this resolves, it must not be silent: failing open
            // means the limiter has stopped limiting.
            $this->logger?->error('Rate limit storage unavailable; ' . ($this->failOpen ? 'failing open' : 'failing closed'), [
                'file' => $file,
                'failOpen' => $this->failOpen,
            ]);

            return $this->failOpen
                ? ['count' => 1, 'expires' => $now + $windowSeconds]
                : ['count' => PHP_INT_MAX, 'expires' => $now + $windowSeconds];
        }

        flock($handle, LOCK_EX);

        $content = stream_get_contents($handle);
        $data = ($content !== false && $content !== '') ? json_decode($content, true) : null;

        if (!is_array($data) || !isset($data['count'], $data['expires']) || $data['expires'] <= $now) {
            $data = ['count' => 0, 'expires' => $now + $windowSeconds];
        }

        $data['count']++;

        ftruncate($handle, 0);
        rewind($handle);
        fwrite($handle, (string)json_encode($data));
        fflush($handle);
        flock($handle, LOCK_UN);
        fclose($handle);

        return ['count' => (int)$data['count'], 'expires' => (int)$data['expires']];
    }

    /**
     * Remove expired rate limit files.
     *
     * Call periodically (e.g., via cron) to prevent file accumulation.
     *
     * @return int Number of files removed.
     */
    public function cleanup(): int
    {
        $removed = 0;
        $now = time();
        $files = glob($this->storagePath . '/*.json');

        if ($files === false) {
            return 0;
        }

        foreach ($files as $file) {
            $content = file_get_contents($file);
            if ($content === false) {
                continue;
            }

            $data = json_decode($content, true);
            if (!is_array($data) || !isset($data['expires']) || $data['expires'] <= $now) {
                unlink($file);
                $removed++;
            }
        }

        return $removed;
    }
}
