<?php

namespace Simsoft\Slim\Traits;

use Exception;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

/**
 * ContainerAwareTrait.
 */
trait ContainerAwareTrait
{
    /**
     * Constructor.
     */
    public function __construct(protected ?ContainerInterface $container = null)
    {

    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws Exception
     */
    public function __get(string $id)
    {
        if ($this->container?->has($id)) {
            return $this->container->get($id);
        }

        throw new Exception("Service: '$id' not found.");
    }
}