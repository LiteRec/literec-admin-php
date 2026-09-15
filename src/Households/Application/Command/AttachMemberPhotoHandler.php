<?php

declare(strict_types=1);

namespace App\Households\Application\Command;

use App\Households\Domain\Households;
use App\Households\Domain\MemberPhotoStorage;
use App\Households\Domain\ValueObject\HouseholdId;
use App\Households\Domain\ValueObject\ImageFormat;
use App\Households\Domain\ValueObject\MemberId;
use App\Households\Domain\ValueObject\ProfilePhoto;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;
use Throwable;

#[AsMessageHandler(bus: 'command.bus')]
final class AttachMemberPhotoHandler
{
    public function __construct(
        private readonly Households $households,
        private readonly MemberPhotoStorage $storage,
        private readonly ClockInterface $clock,
        private readonly MessageBusInterface $eventBus,
    ) {
    }

    public function __invoke(AttachMemberPhoto $command): void
    {
        $household = $this->households->findById(HouseholdId::fromString($command->householdId));
        $memberId = MemberId::fromString($command->memberId);

        $format = ImageFormat::fromMimeType($command->mimeType);
        $storageKey = $this->storage->store($memberId, $format, $command->sourcePath);

        try {
            $photo = ProfilePhoto::of($storageKey, $format, $this->clock->now());
            $household->attachMemberPhoto($memberId, $photo, $this->clock);
            $this->households->save($household);
        } catch (Throwable $failure) {
            // The aggregate never took ownership of the key (MemberNotFound,
            // or save() failed after it did), so no MemberPhotoReleased will
            // ever clean it up — remove the orphaned file ourselves.
            $this->storage->remove($storageKey);

            throw $failure;
        }

        foreach ($household->releaseEvents() as $event) {
            $this->eventBus->dispatch($event, [new DispatchAfterCurrentBusStamp()]);
        }
    }
}
