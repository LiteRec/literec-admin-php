<?php

declare(strict_types=1);

namespace App\Tests\Architecture;

use App\Catalog\Application\Query\FindListingByCodeHandler;
use App\Inventory\Application\Query\GetStockMovementHistoryHandler;
use App\Inventory\Application\Query\Report\CurrentStockReportHandler;
use App\Inventory\Application\Query\Report\EntryLogReportHandler;
use PHPat\Selector\Selector;
use PHPat\Test\Attributes\TestRule;
use PHPat\Test\Builder\Rule;
use PHPat\Test\PHPat;

/**
 * Every *Handler class exposes exactly one public method (CLAUDE.md: "Write
 * one application service per use case ... with a single public method
 * (__invoke or handle)").
 *
 * Four handlers are documented debt, not a baseline file, and are excluded
 * by name until a follow-up ticket extracts a dedicated collaborator:
 * - FindListingByCodeHandler exposes static toSummary()/projectFees(),
 *   called by GetListingDetailHandler and FindListingsByKindHandler.
 * - GetStockMovementHistoryHandler, CurrentStockReportHandler, and
 *   EntryLogReportHandler each expose streamCsvRows(), called directly by
 *   the LRA-91 CSV controllers so the generator stays lazy.
 */
final class HandlersExposeOnePublicMethodRule
{
    #[TestRule]
    public function handlers_have_only_one_public_method(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::classname('/Handler$/', true))
            ->excluding(
                Selector::inNamespace('App\Tests'),
                Selector::classname(FindListingByCodeHandler::class),
                Selector::classname(GetStockMovementHistoryHandler::class),
                Selector::classname(CurrentStockReportHandler::class),
                Selector::classname(EntryLogReportHandler::class),
            )
            ->should()
            ->haveOnlyOnePublicMethod()
            ->because('CLAUDE.md: one application service per use case with a single public method.');
    }
}
