<?php

declare(strict_types=1);

namespace Simsoft\Slim\Middlewares;

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
     */
    public function __construct(string $storagePath = '')
    {
        $this->storagePath = $storagePath !== '' ? $storagePath : sys_get_temp_dir() . '/slim-rate-limit';

        if (!is_dir($this->storagePath)) {
            mkdir($this->storagePath, 0755, true);
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

        $handle = fopen($file, 'c+');
        if ($handle === false) {
            return ['count' => 1, 'expires' => $now + $windowSeconds];
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
