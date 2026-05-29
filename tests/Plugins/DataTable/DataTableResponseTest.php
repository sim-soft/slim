<?php

declare(strict_types=1);

namespace Simsoft\Slim\Tests\Plugins\DataTable;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Simsoft\Slim\Plugins\DataTable\DataTableActionButton;
use Simsoft\Slim\Plugins\DataTable\DataTableResponse;

class DataTableResponseTest extends TestCase
{
    #[Test]
    public function defaultBodyStructure(): void
    {
        $dt = new DataTableResponse();
        $result = $dt->toArray();

        $this->assertArrayHasKey('draw', $result);
        $this->assertArrayHasKey('recordsTotal', $result);
        $this->assertArrayHasKey('recordsFiltered', $result);
        $this->assertArrayHasKey('data', $result);
        $this->assertSame(0, $result['draw']);
        $this->assertSame(0, $result['recordsTotal']);
        $this->assertSame(0, $result['recordsFiltered']);
        $this->assertSame([], $result['data']);
    }

    #[Test]
    public function setParamsSetsDrawValue(): void
    {
        $dt = new DataTableResponse();
        $dt->setParams(['draw' => '3', 'start' => '0', 'length' => '10']);

        $result = $dt->toArray();
        $this->assertSame(3, $result['draw']);
    }

    #[Test]
    public function setParamsSetsStartAndLength(): void
    {
        $dt = new DataTableResponse();
        $dt->setParams(['draw' => '1', 'start' => '20', 'length' => '10']);

        $this->assertSame(20, $dt->getStart());
        $this->assertSame(10, $dt->getLength());
    }

    #[Test]
    public function getPageReturnsFirstPageWhenStartIsZero(): void
    {
        $dt = new DataTableResponse();
        $dt->setParams(['draw' => '1', 'start' => '0', 'length' => '10']);

        $this->assertSame(1, $dt->getPage());
    }

    #[Test]
    public function getPageCalculatesCorrectly(): void
    {
        $dt = new DataTableResponse();
        $dt->setParams(['draw' => '1', 'start' => '20', 'length' => '10']);

        $this->assertSame(3, $dt->getPage());
    }

    #[Test]
    public function getPageForSecondPage(): void
    {
        $dt = new DataTableResponse();
        $dt->setParams(['draw' => '1', 'start' => '10', 'length' => '10']);

        $this->assertSame(2, $dt->getPage());
    }

    #[Test]
    public function setParamsWithSortOnFirstDraw(): void
    {
        $dt = new DataTableResponse();
        $dt->setParams([
            'draw' => '1',
            'start' => '0',
            'length' => '10',
            'order' => [['column' => 0, 'dir' => 'desc']],
            'columns' => [['name' => 'created_at']],
        ], firstDrawSort: true);

        $this->assertSame('created_at', $dt->getSortAttribute());
        $this->assertSame('DESC', $dt->getSortDirection());
    }

    #[Test]
    public function setParamsWithSortOnSubsequentDraw(): void
    {
        $dt = new DataTableResponse();
        $dt->setParams([
            'draw' => '2',
            'start' => '0',
            'length' => '10',
            'order' => [['column' => 1, 'dir' => 'asc']],
            'columns' => [['name' => 'id'], ['name' => 'name']],
        ], firstDrawSort: false);

        $this->assertSame('name', $dt->getSortAttribute());
        $this->assertSame('ASC', $dt->getSortDirection());
    }

    #[Test]
    public function setParamsNoSortOnFirstDrawWhenDisabled(): void
    {
        $dt = new DataTableResponse();
        $dt->setParams([
            'draw' => '1',
            'start' => '0',
            'length' => '10',
            'order' => [['column' => 0, 'dir' => 'desc']],
            'columns' => [['name' => 'created_at']],
        ], firstDrawSort: false);

        $this->assertNull($dt->getSortAttribute());
    }

    #[Test]
    public function setParamsWithEmptyColumnName(): void
    {
        $dt = new DataTableResponse();
        $dt->setParams([
            'draw' => '2',
            'start' => '0',
            'length' => '10',
            'order' => [['column' => 0, 'dir' => 'asc']],
            'columns' => [['name' => '']],
        ]);

        $this->assertNull($dt->getSortAttribute());
        $this->assertSame('ASC', $dt->getSortDirection());
    }

    #[Test]
    public function setTotalRecordsSetsRecordsAndFiltered(): void
    {
        $dt = new DataTableResponse();
        $dt->setTotalRecords(100);

        $result = $dt->toArray();
        $this->assertSame(100, $result['recordsTotal']);
        $this->assertSame(100, $result['recordsFiltered']);
    }

    #[Test]
    public function setTotalFilteredOverridesFilteredCount(): void
    {
        $dt = new DataTableResponse();
        $dt->setTotalRecords(100)->setTotalFiltered(50);

        $result = $dt->toArray();
        $this->assertSame(100, $result['recordsTotal']);
        $this->assertSame(50, $result['recordsFiltered']);
    }

    #[Test]
    public function addRowAddsDataToResponse(): void
    {
        $dt = new DataTableResponse();
        $dt->addRow(['id' => 1, 'name' => 'John']);

        $result = $dt->toArray();
        $this->assertCount(1, $result['data']);
        $this->assertSame(['id' => 1, 'name' => 'John'], $result['data'][0]);
    }

    #[Test]
    public function addRowMultipleRows(): void
    {
        $dt = new DataTableResponse();
        $dt->addRow(['id' => 1, 'name' => 'John']);
        $dt->addRow(['id' => 2, 'name' => 'Jane']);

        $result = $dt->toArray();
        $this->assertCount(2, $result['data']);
    }

    #[Test]
    public function addRowWithFormatter(): void
    {
        $dt = new DataTableResponse();
        $dt->addRow(['id' => 1, 'name' => 'john'], function (array $data) {
            $data['name'] = ucfirst($data['name']);
            return $data;
        });

        $result = $dt->toArray();
        $this->assertSame('John', $result['data'][0]['name']);
    }

    #[Test]
    public function addRowWithEnabledActionButtons(): void
    {
        $button = new DataTableActionButton('Edit', true);
        $button->url('/edit/1');

        $dt = new DataTableResponse();
        $dt->addRow(['id' => 1, 'actions' => [$button]]);

        $result = $dt->toArray();
        $this->assertCount(1, $result['data'][0]['actions']);
        $this->assertSame(['label' => 'Edit', 'url' => '/edit/1'], $result['data'][0]['actions'][0]);
    }

    #[Test]
    public function addRowWithDisabledActionButtonsRemovesThem(): void
    {
        $enabledButton = new DataTableActionButton('Edit', true);
        $enabledButton->url('/edit/1');

        $disabledButton = new DataTableActionButton('Delete', false);
        $disabledButton->url('/delete/1');

        $dt = new DataTableResponse();
        $dt->addRow(['id' => 1, 'actions' => [$enabledButton, $disabledButton]]);

        $result = $dt->toArray();
        // Only enabled button should remain
        $actions = array_values($result['data'][0]['actions']);
        $this->assertCount(1, $actions);
        $this->assertSame('Edit', $actions[0]['label']);
    }

    #[Test]
    public function setErrorAddsErrorToBody(): void
    {
        $dt = new DataTableResponse();
        $dt->setError('Something went wrong');

        $result = $dt->toArray();
        $this->assertArrayHasKey('error', $result);
        $this->assertSame('Something went wrong', $result['error']);
    }

    #[Test]
    public function toJsonReturnsValidJson(): void
    {
        $dt = new DataTableResponse();
        $dt->setTotalRecords(5);
        $dt->addRow(['id' => 1]);

        $json = $dt->toJson();
        $this->assertJson($json);

        $decoded = json_decode($json, true);
        $this->assertSame(5, $decoded['recordsTotal']);
        $this->assertCount(1, $decoded['data']);
    }

    #[Test]
    public function invokeReturnsArray(): void
    {
        $dt = new DataTableResponse();
        $dt->setTotalRecords(10);

        $result = $dt();
        $this->assertIsArray($result);
        $this->assertSame(10, $result['recordsTotal']);
    }

    #[Test]
    public function toStringReturnsJson(): void
    {
        $dt = new DataTableResponse();
        $dt->setTotalRecords(3);

        $string = (string)$dt;
        $this->assertJson($string);
    }

    #[Test]
    public function setParamsReturnsSelfForChaining(): void
    {
        $dt = new DataTableResponse();
        $result = $dt->setParams(['draw' => '1', 'start' => '0', 'length' => '10']);

        $this->assertSame($dt, $result);
    }

    #[Test]
    public function setTotalRecordsReturnsSelfForChaining(): void
    {
        $dt = new DataTableResponse();
        $result = $dt->setTotalRecords(100);

        $this->assertSame($dt, $result);
    }

    #[Test]
    public function setTotalFilteredReturnsSelfForChaining(): void
    {
        $dt = new DataTableResponse();
        $result = $dt->setTotalFiltered(50);

        $this->assertSame($dt, $result);
    }

    #[Test]
    public function addRowReturnsSelfForChaining(): void
    {
        $dt = new DataTableResponse();
        $result = $dt->addRow(['id' => 1]);

        $this->assertSame($dt, $result);
    }

    #[Test]
    public function setErrorReturnsSelfForChaining(): void
    {
        $dt = new DataTableResponse();
        $result = $dt->setError('error');

        $this->assertSame($dt, $result);
    }

    #[Test]
    public function getSortDirectionDefaultsToAsc(): void
    {
        $dt = new DataTableResponse();
        $this->assertSame('ASC', $dt->getSortDirection());
    }

    #[Test]
    public function setParamsWithoutOrder(): void
    {
        $dt = new DataTableResponse();
        $dt->setParams(['draw' => '1', 'start' => '0', 'length' => '25']);

        $this->assertNull($dt->getSortAttribute());
        $this->assertSame(25, $dt->getLength());
    }

    #[Test]
    public function setParamsWithoutDrawKey(): void
    {
        $dt = new DataTableResponse();
        $dt->setParams(['start' => '0', 'length' => '10']);

        $result = $dt->toArray();
        $this->assertSame(0, $result['draw']);
    }
}
