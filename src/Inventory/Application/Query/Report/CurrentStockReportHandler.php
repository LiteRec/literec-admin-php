<?php

declare(strict_types=1);

namespace App\Inventory\Application\Query\Report;

use App\Inventory\Application\Query\Port\InventoryReadModel;
use Generator;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Query handler for the LRA-91 Current Stock report card + CSV export.
 *
 * The paginated read goes through the query bus via `__invoke()`; the
 * CSV streamer calls {@see streamCsvRows()} directly because routing a
 * `Generator` through the bus would buffer the entire export behind the
 * envelope stamp and defeat the streaming contract.
 */
#[AsMessageHandler(bus: 'query.bus')]
final readonly class CurrentStockReportHandler
{
    public function __construct(private InventoryReadModel $readModel)
    {
    }

    public function __invoke(CurrentStockReport $query): CurrentStockReportPage
    {
        return $this->readModel->currentStock($query);
    }

    /**
     * @return Generator<int, list<string>, mixed, void>
     */
    public function streamCsvRows(CurrentStockReport $criteria): Generator
    {
        return $this->readModel->streamCurrentStock($criteria);
    }
}
