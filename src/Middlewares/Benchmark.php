<?php

declare(strict_types=1);

namespace Simsoft\Slim\Middlewares;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

/**
 * Benchmark Class
 *
 * Measures request execution time and peak memory usage.
 * Adds X-Response-Time and X-Memory-Peak headers to the response.
 */
class Benchmark
{
    /**
     * Constructor.
     *
     * @param bool $includeMemory Include peak memory usage header.
     * @param string $timeHeader Header name for response time.
     * @param string $memoryHeader Header name for memory usage.
     */
    public function __construct(
        protected bool   $includeMemory = true,
        protected string $timeHeader = 'X-Response-Time',
        protected string $memoryHeader = 'X-Memory-Peak',
    )
    {
    }

    /**
     * Measure execution time and memory usage.
     *
     * @param Request $request
     * @param RequestHandler $handler
     * @return Response
     */
    public function __invoke(Request $request, RequestHandler $handler): Response
    {
        $startTime = hrtime(true);

        $response = $handler->handle($request);

        $elapsed = (hrtime(true) - $startTime) / 1_000_000;
        $response = $response->withHeader($this->timeHeader, sprintf('%.2fms', $elapsed));

        if ($this->includeMemory) {
            $peakMemory = memory_get_peak_usage(true);
            $response = $response->withHeader($this->memoryHeader, $this->formatBytes($peakMemory));
        }

        return $response;
    }

    /**
     * Format bytes into human-readable string.
     *
     * @param int $bytes
     * @return string
     */
    protected function formatBytes(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return sprintf('%.2fMB', $bytes / 1048576);
        }

        return sprintf('%.2fKB', $bytes / 1024);
    }
}
