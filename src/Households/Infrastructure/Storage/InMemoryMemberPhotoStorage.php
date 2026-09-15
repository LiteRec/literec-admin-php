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

/**
 * In-memory-for-tests adapter for the {@see MemberPhotoStorage} port.
 * Used by unit/functional tests so they never touch the real
 * `var/uploads/member-photos` directory.
 *
 * Files are still copied to real bytes under a scratch directory, because
 * {@see readable()} must return a real {@see SplFileInfo} for
 * {@see \Symfony\Component\HttpFoundation\BinaryFileResponse} to stream —
 * an array of raw bytes would not satisfy the port.
 *
 * The scratch directory is shared across every instance in the PHP
 * process (a `static` property, not a constructor-time random path):
 * Symfony's functional test client reboots the kernel — and therefore
 * rebuilds the container, constructing a fresh instance of this class —
 * before every request, so a photo uploaded in one request must still be
 * readable by a later request's fresh instance within the same test. A
 * `register_shutdown_function` removes the directory when the PHPUnit
 * process ends.
 */
final class InMemoryMemberPhotoStorage implements MemberPhotoStorage
{
    use ResolvesMemberPhotoPath;

    private static ?string $sharedScratchDir = null;

    private readonly string $scratchDir;

    public function __construct(private readonly Filesystem $filesystem = new Filesystem())
    {
        $this->scratchDir = self::sharedScratchDir($this->filesystem);
    }

    public function store(MemberId $memberId, ImageFormat $format, string $sourcePath): string
    {
        $storageKey = sprintf('%s/%s.%s', $memberId->value, bin2hex(random_bytes(16)), $format->extension());
        $destination = $this->resolvePath($this->scratchDir, $storageKey);

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
        $path = $this->resolvePath($this->scratchDir, $storageKey);

        if (!is_file($path)) {
            throw MemberPhotoStorageFailed::notFound($storageKey);
        }

        return new SplFileInfo($path);
    }

    public function remove(string $storageKey): void
    {
        $this->filesystem->remove($this->resolvePath($this->scratchDir, $storageKey));
    }

    private static function sharedScratchDir(Filesystem $filesystem): string
    {
        if (self::$sharedScratchDir === null) {
            $dir = sys_get_temp_dir() . '/member-photo-storage-' . bin2hex(random_bytes(8));
            $filesystem->mkdir($dir);

            register_shutdown_function(static function () use ($filesystem, $dir): void {
                $filesystem->remove($dir);
            });

            self::$sharedScratchDir = $dir;
        }

        return self::$sharedScratchDir;
    }
}
