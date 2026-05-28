<?php

declare(strict_types=1);

namespace App\Inventory\Application\Query\Report;

use App\Inventory\Application\Query\Port\InventoryReadModel;
use Generator;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Query handler for the LRA-91 Entry Log report card + CSV export.
 *
 * Same routing rationale as {@see CurrentStockReportHandler}: the
 * paginated card uses `__invoke()` through the bus; the CSV export
 * calls {@see streamCsvRows()} directly so the generator stays lazy.
 */
#[AsMessageHandler(bus: 'query.bus')]
final readonly class EntryLogReportHandler
{
    public function __construct(private InventoryReadModel $readModel)
    {
    }

    public function __invoke(EntryLogReport $query): EntryLogPage
    {
        return $this->readModel->entryLog($query);
    }

    /**
     * @return Generator<int, list<string>, mixed, void>
     */
    public function streamCsvRows(EntryLogReport $criteria): Generator
    {
        return $this->readModel->streamEntryLog($criteria);
    }
}
