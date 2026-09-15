<?php

declare(strict_types=1);

namespace App\Households\Application\Event;

use App\Households\Domain\Event\MemberPhotoReleased;
use App\Households\Domain\Exception\MemberPhotoStorageFailed;
use App\Households\Domain\MemberPhotoStorage;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Deletes the storage-layer file for a photo the aggregate no longer
 * references, in response to {@see MemberPhotoReleased} (LRA-207).
 *
 * A single unconditional call: whether the release happened because a new
 * photo replaced an old one ({@see \App\Households\Domain\Event\MemberPhotoAttached})
 * or the member's photo was removed
 * ({@see \App\Households\Domain\Event\MemberPhotoRemoved}), the storage
 * key is orphaned and {@see MemberPhotoStorage::remove()} is idempotent.
 *
 * Runs post-commit (the originating command dispatches with
 * DispatchAfterCurrentBusStamp): the aggregate change is already durable,
 * so a storage failure here is an ops cleanup concern, not a reason to
 * fail the request that triggered it.
 */
#[AsMessageHandler(bus: 'event.bus')]
final class ReleaseMemberPhotoFileHandler
{
    public function __construct(
        private readonly MemberPhotoStorage $storage,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(MemberPhotoReleased $event): void
    {
        try {
            $this->storage->remove($event->storageKey);
        } catch (MemberPhotoStorageFailed $failure) {
            $this->logger->warning('Superseded member photo could not be removed from storage.', [
                'storageKey' => $event->storageKey,
                'exception' => $failure,
            ]);
        }
    }
}
