<?php

declare(strict_types=1);

namespace App\Households\Infrastructure\Storage;

use App\Households\Domain\Exception\MemberPhotoStorageFailed;
use App\Households\Domain\MemberPhotoStorage;
use App\Households\Domain\ValueObject\ImageFormat;
use App\Households\Domain\ValueObject\MemberId;
use SplFileInfo;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\Filesystem\Filesystem;
use Throwable;

/**
 * Local-filesystem adapter for the {@see MemberPhotoStorage} port
 * (LRA-207). Writes under $rootDir — production wires this to
 * `var/uploads/member-photos/` (the only writable path under `/app` in
 * production), never under `public/`, so photos are only reachable
 * through the authenticated {@see \App\Households\Infrastructure\Http\Controller\MemberPhotoController}.
 *
 * This is local/ephemeral storage; an S3-backed adapter behind the same
 * port is out of scope for this ticket.
 */
final class LocalMemberPhotoStorage implements MemberPhotoStorage
{
    use ResolvesMemberPhotoPath;

    public function __construct(
        private readonly string $rootDir,
        private readonly Filesystem $filesystem = new Filesystem(),
    ) {
    }

    public function store(MemberId $memberId, ImageFormat $format, string $sourcePath): string
    {
        $storageKey = sprintf('%s/%s.%s', $memberId->value, bin2hex(random_bytes(16)), $format->extension());
        $destination = $this->resolvePath($this->rootDir, $storageKey);

        try {
            $this->filesystem->mkdir(dirname($destination));
            $this->filesystem->copy($sourcePath, $destination, true);
        } catch (IOExceptionInterface) {
            throw MemberPhotoStorageFailed::writing($storageKey);
        }

        return $storageKey;
    }

    public function readable(string $storageKey): SplFileInfo
    {
        $path = $this->resolvePath($this->rootDir, $storageKey);

        if (!$this->filesystem->exists($path) || !is_file($path)) {
            throw MemberPhotoStorageFailed::notFound($storageKey);
        }

        return new SplFileInfo($path);
    }

    public function remove(string $storageKey): void
    {
        try {
            $this->filesystem->remove($this->resolvePath($this->rootDir, $storageKey));
        } catch (Throwable) {
            throw MemberPhotoStorageFailed::removing($storageKey);
        }
    }
}
