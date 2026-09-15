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
        $photo = ProfilePhoto::of($storageKey, $format, $this->clock->now());

        $household->attachMemberPhoto($memberId, $photo, $this->clock);
        $this->households->save($household);

        foreach ($household->releaseEvents() as $event) {
            $this->eventBus->dispatch($event, [new DispatchAfterCurrentBusStamp()]);
        }
    }
}
