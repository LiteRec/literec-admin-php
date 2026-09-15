<?php

declare(strict_types=1);

namespace App\Households\Infrastructure\Storage;

use App\Households\Domain\Exception\MemberPhotoStorageFailed;

/**
 * Shared path-resolution logic for {@see MemberPhotoStorage} filesystem
 * adapters (LocalMemberPhotoStorage, InMemoryMemberPhotoStorage):
 * resolves a storage key against a root directory and rejects any key
 * whose resolved path would escape that root — defense in depth
 * alongside {@see \App\Households\Domain\ValueObject\ProfilePhoto::of()}'s
 * ".." rejection, since an adapter cannot assume every caller of the
 * port constructed the key through the domain value object. Extracted
 * to keep both adapters' implementations of this check from drifting
 * apart (SonarCloud php:S1192-style duplication).
 */
trait ResolvesMemberPhotoPath
{
    /**
     * @throws MemberPhotoStorageFailed when $storageKey resolves outside $rootDir.
     */
    private function resolvePath(string $rootDir, string $storageKey): string
    {
        $root = rtrim($rootDir, '/');
        $candidate = $root . '/' . ltrim($storageKey, '/');

        $normalized = $this->normalize($candidate);
        $normalizedRoot = $this->normalize($root);

        if ($normalized !== $normalizedRoot && !str_starts_with($normalized, $normalizedRoot . '/')) {
            throw MemberPhotoStorageFailed::notFound($storageKey);
        }

        return $candidate;
    }

    private function normalize(string $path): string
    {
        $segments = [];
        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                array_pop($segments);
                continue;
            }
            $segments[] = $segment;
        }

        return '/' . implode('/', $segments);
    }
}
