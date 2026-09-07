<?php

declare(strict_types=1);

namespace Simsoft\Slim\Tests\Middlewares;

use Psr\Log\AbstractLogger;

/**
 * A PSR-3 logger that records what it was told, for assertions.
 *
 * The signature is deliberately untyped. This package supports psr/log
 * ^1.1 || ^2.0 || ^3.0, and the interface is not the same across them: 1.x
 * declares log($level, $message, array $context = []) with no types at all,
 * while 3.x declares log($level, string|Stringable $message, ...): void.
 *
 * Widening a parameter is always allowed, so leaving $level and $message
 * untyped satisfies both. Adding the ": void" return type is likewise allowed
 * against 1.x, which declares no return type, and is required by 3.x. Typing
 * $message as string|Stringable would match 3.x but is a narrowing against
 * 1.x, and PHP rejects it with a fatal error.
 */
class CollectingLogger extends AbstractLogger
{
    /** @var array<int, string> Messages received, in order. */
    public array $messages = [];

    /** @var array<int, string> Levels received, in order. */
    public array $levels = [];

    /** @var array<int, array<string, mixed>> Context arrays received, in order. */
    public array $contexts = [];

    /**
     * @param mixed $level
     * @param mixed $message
     * @param array<string, mixed> $context
     */
    public function log($level, $message, array $context = []): void
    {
        $this->levels[] = (string)$level;
        $this->messages[] = (string)$message;
        $this->contexts[] = $context;
    }
}
