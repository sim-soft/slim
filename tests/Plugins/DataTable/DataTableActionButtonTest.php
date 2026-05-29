<?php

declare(strict_types=1);

namespace Simsoft\Slim\Tests\Plugins\DataTable;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Simsoft\Slim\Plugins\DataTable\DataTableActionButton;

class DataTableActionButtonTest extends TestCase
{
    #[Test]
    public function constructorWithLabel(): void
    {
        $button = new DataTableActionButton('Edit');
        $result = $button->toArray();

        $this->assertSame('Edit', $result['label']);
    }

    #[Test]
    public function constructorWithNullLabel(): void
    {
        $button = new DataTableActionButton(null);
        $result = $button->toArray();

        $this->assertSame([], $result);
    }

    #[Test]
    public function constructorDefaultsToEnabled(): void
    {
        $button = new DataTableActionButton('Test');
        $this->assertTrue($button->isEnabled());
    }

    #[Test]
    public function constructorWithDisabled(): void
    {
        $button = new DataTableActionButton('Test', false);
        $this->assertFalse($button->isEnabled());
    }

    #[Test]
    public function labelSetsLabel(): void
    {
        $button = new DataTableActionButton();
        $button->label('Delete');

        $result = $button->toArray();
        $this->assertSame('Delete', $result['label']);
    }

    #[Test]
    public function labelReturnsSelfForChaining(): void
    {
        $button = new DataTableActionButton();
        $result = $button->label('Test');

        $this->assertSame($button, $result);
    }

    #[Test]
    public function titleSetsTitle(): void
    {
        $button = new DataTableActionButton('Edit');
        $button->title('Edit User');

        $result = $button->toArray();
        $this->assertSame('Edit User', $result['title']);
    }

    #[Test]
    public function titleReturnsSelfForChaining(): void
    {
        $button = new DataTableActionButton();
        $result = $button->title('Test');

        $this->assertSame($button, $result);
    }

    #[Test]
    public function urlSetsUrl(): void
    {
        $button = new DataTableActionButton('Edit');
        $button->url('/users/1/edit');

        $result = $button->toArray();
        $this->assertSame('/users/1/edit', $result['url']);
    }

    #[Test]
    public function urlReturnsSelfForChaining(): void
    {
        $button = new DataTableActionButton();
        $result = $button->url('/test');

        $this->assertSame($button, $result);
    }

    #[Test]
    public function confirmSetsConfirm(): void
    {
        $button = new DataTableActionButton('Delete');
        $button->confirm('Are you sure?');

        $result = $button->toArray();
        $this->assertSame('Are you sure?', $result['confirm']);
    }

    #[Test]
    public function confirmReturnsSelfForChaining(): void
    {
        $button = new DataTableActionButton();
        $result = $button->confirm('Sure?');

        $this->assertSame($button, $result);
    }

    #[Test]
    public function fluentChaining(): void
    {
        $button = new DataTableActionButton();
        $button->label('Delete')
            ->title('Delete User')
            ->url('/users/1/delete')
            ->confirm('Are you sure you want to delete this user?');

        $result = $button->toArray();
        $this->assertSame('Delete', $result['label']);
        $this->assertSame('Delete User', $result['title']);
        $this->assertSame('/users/1/delete', $result['url']);
        $this->assertSame('Are you sure you want to delete this user?', $result['confirm']);
    }

    #[Test]
    public function toArrayReturnsNullableArray(): void
    {
        $button = new DataTableActionButton(null);
        $result = $button->toArray();

        // With no attributes set, returns empty array
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    #[Test]
    public function isEnabledReturnsTrue(): void
    {
        $button = new DataTableActionButton('Test', true);
        $this->assertTrue($button->isEnabled());
    }

    #[Test]
    public function isEnabledReturnsFalse(): void
    {
        $button = new DataTableActionButton('Test', false);
        $this->assertFalse($button->isEnabled());
    }

    #[Test]
    public function toArrayContainsOnlySetAttributes(): void
    {
        $button = new DataTableActionButton('Edit');
        $button->url('/edit');

        $result = $button->toArray();
        $this->assertArrayHasKey('label', $result);
        $this->assertArrayHasKey('url', $result);
        $this->assertArrayNotHasKey('title', $result);
        $this->assertArrayNotHasKey('confirm', $result);
    }
}
