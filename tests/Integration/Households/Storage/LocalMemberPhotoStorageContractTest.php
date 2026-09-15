<?php

declare(strict_types=1);

namespace App\Tests\Integration\Households\Storage;

use App\Households\Domain\MemberPhotoStorage;
use App\Households\Infrastructure\Storage\LocalMemberPhotoStorage;
use App\Tests\Support\Trait\MemberPhotoStorageContractCases;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Drives the {@see MemberPhotoStorageContractCases} suite against the
 * real local filesystem, under a fresh temp directory per test so cases
 * stay isolated without needing the Doctrine kernel.
 */
#[Medium]
final class LocalMemberPhotoStorageContractTest extends TestCase
{
    use MemberPhotoStorageContractCases;

    private string $rootDir;
    private LocalMemberPhotoStorage $storage;
    private Filesystem $filesystem;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->rootDir = sys_get_temp_dir() . '/lra207-member-photos-' . bin2hex(random_bytes(8));
        $this->filesystem->mkdir($this->rootDir);
        $this->storage = new LocalMemberPhotoStorage($this->rootDir, $this->filesystem);
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->rootDir);
    }

    protected function storage(): MemberPhotoStorage
    {
        return $this->storage;
    }
}
