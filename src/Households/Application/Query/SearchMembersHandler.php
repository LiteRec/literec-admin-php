<?php

declare(strict_types=1);

namespace App\Households\Application\Query;

use App\Households\Application\Query\Port\MemberReadModel;
use App\Households\Application\Query\Port\PageOfMembers;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final class SearchMembersHandler
{
    public function __construct(
        private readonly MemberReadModel $readModel,
    ) {
    }

    public function __invoke(SearchMembers $query): PageOfMembers
    {
        return $this->readModel->search($query->criteria);
    }
}
