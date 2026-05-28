<?php

declare(strict_types=1);

namespace Simsoft\Slim\Plugins\DataTable;

/**
 * DataTableActionButton class.
 */
class DataTableActionButton
{
    /** @var array<string, string> Action button attributes. */
    protected array $attributes = [];

    /**
     * Constructor.
     *
     * @param string|null $label The button's label.
     * @param bool $enabled Determine if the button is enabled.
     */
    public function __construct(?string $label = null, protected bool $enabled = true)
    {
        if ($label !== null) {
            $this->label($label);
        }
    }

    /**
     * Determine this button is enabled.
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Convert button to array.
     *
     * @return array<string, string>|null
     */
    public function toArray(): ?array
    {
        return $this->attributes;
    }

    /**
     * Set button label.
     *
     * @param string $label
     * @return $this
     */
    public function label(string $label): static
    {
        $this->attributes['label'] = $label;
        return $this;
    }

    /**
     * Set data title or data's name.
     *
     * @param string $title
     * @return $this
     */
    public function title(string $title): static
    {
        $this->attributes['title'] = $title;
        return $this;
    }

    /**
     * Set button URL.
     *
     * @param string $url
     * @return $this
     */
    public function url(string $url): static
    {
        $this->attributes['url'] = $url;
        return $this;
    }

    /**
     * Determine the button need confirmation.
     *
     * @param string $confirm The confirmation title.
     * @return $this
     */
    public function confirm(string $confirm): static
    {
        $this->attributes['confirm'] = $confirm;
        return $this;
    }
}
