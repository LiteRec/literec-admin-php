<?php

declare(strict_types=1);

namespace App\Tests\Unit\Households\Infrastructure\Storage;

use App\Households\Domain\MemberPhotoStorage;
use App\Households\Infrastructure\Storage\InMemoryMemberPhotoStorage;
use App\Tests\Support\Trait\MemberPhotoStorageContractCases;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[Small]
final class InMemoryMemberPhotoStorageContractTest extends TestCase
{
    use MemberPhotoStorageContractCases;

    private InMemoryMemberPhotoStorage $storage;

    protected function setUp(): void
    {
        $this->storage = new InMemoryMemberPhotoStorage();
    }

    protected function storage(): MemberPhotoStorage
    {
        return $this->storage;
    }
}
