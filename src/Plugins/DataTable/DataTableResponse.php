<?php

declare(strict_types=1);

namespace Simsoft\Slim\Plugins\DataTable;

/**
 * DataTableResponse class.
 */
class DataTableResponse
{
    /** @var array<string, mixed> Response body. */
    protected array $body = [
        'draw' => 0,
        'recordsTotal' => 0,
        'recordsFiltered' => 0,
        'data' => [],
    ];

    /** @var int Start index */
    protected int $start = 0;

    /** @var int Length of a returned result. */
    protected int $length = 10;

    /** @var string|null Sort attribute name. */
    protected ?string $sortAttribute = null;

    /** @var string Sort direction. */
    protected string $sortDirection = 'ASC';

    /**
     * Set request params.
     *
     * @param array<string, mixed> $attributes
     * @param bool $firstDrawSort
     * @return $this
     */
    public function setParams(array $attributes = [], bool $firstDrawSort = true): static
    {
        if (array_key_exists('draw', $attributes)) {
            $this->body['draw'] = intVal($attributes['draw']);
        }

        $this->start = intVal($attributes['start']);
        $this->length = intVal($attributes['length']);

        if (isset($attributes['order']) && ($firstDrawSort || $this->body['draw'] > 1)) {
            $this->sortAttribute = empty($attributes['columns'][$attributes['order'][0]['column']]['name'])
                ? null
                : $attributes['columns'][$attributes['order'][0]['column']]['name'];
            $this->sortDirection = $this->sortAttribute
                ? (empty($attributes['order'][0]['dir']) ? $this->sortDirection : strtoupper($attributes['order'][0]['dir']))
                : $this->sortDirection;
        }

        return $this;
    }

    /**
     * Get start value.
     *
     * @return int
     */
    public function getStart(): int
    {
        return $this->start;
    }

    /**
     * Get length value.
     *
     * @return int
     */
    public function getLength(): int
    {
        return $this->length;
    }

    /**
     * Get the current page.
     *
     * @return int
     */
    public function getPage(): int
    {
        if ($this->getStart() === 0) {
            return 1;
        }

        return ($this->getStart() / $this->getLength()) + 1;
    }

    /**
     * Get sort attribute.
     *
     * @return string|null
     */
    public function getSortAttribute(): ?string
    {
        return $this->sortAttribute;
    }

    /**
     * Get sort direction.
     *
     * @return string
     */
    public function getSortDirection(): string
    {
        return $this->sortDirection;
    }

    /**
     * Set total records.
     *
     * @param int $totalRecords
     * @return $this
     */
    public function setTotalRecords(int $totalRecords): static
    {
        $this->body['recordsTotal'] = $totalRecords;
        $this->body['recordsFiltered'] = $totalRecords;
        return $this;
    }

    /**
     * Set total filtered.
     *
     * @param int $totalFiltered
     * @return $this
     */
    public function setTotalFiltered(int $totalFiltered): static
    {
        $this->body['recordsFiltered'] = $totalFiltered;
        return $this;
    }

    /**
     * Add data to the response.
     *
     * @param array<string, mixed> $data
     * @param callable|null $formatter Callable formatter that must return a string.
     * @return $this
     */
    public function addRow(array $data, ?callable $formatter = null): static
    {
        if (isset($data['actions']) && is_array($data['actions'])) {
            /** @var DataTableActionButton $action */
            foreach ($data['actions'] as $key => $action) {
                if ($action->isEnabled()) {
                    $data['actions'][$key] = $action->toArray();
                    continue;
                }
                unset($data['actions'][$key]);
            }
        }

        $this->body['data'][] = $formatter === null ? $data : $formatter($data);
        return $this;
    }

    /**
     * Set an error message to the response.
     *
     * @param string $error
     * @return $this
     */
    public function setError(string $error): static
    {
        $this->body['error'] = $error;
        return $this;
    }

    /**
     * Get a response body as an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->body;
    }

    /**
     * Get response body as JSON format.
     *
     * @return string
     */
    public function toJson(): string
    {
        return (string)json_encode($this->body);
    }

    /**
     * Return as an array if invoked.
     *
     * @return array<string, mixed>
     */
    public function __invoke(): array
    {
        return $this->toArray();
    }

    /**
     * Return as a JSON string.
     *
     * @return string
     */
    public function __toString(): string
    {
        return $this->toJson();
    }
}
