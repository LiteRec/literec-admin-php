<?php

declare(strict_types=1);

namespace App\Users\Application\Command;

use App\Users\Domain\Users;
use App\Users\Domain\ValueObject\HashedPassword;
use App\Users\Domain\ValueObject\UserId;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;

#[AsMessageHandler(bus: 'command.bus')]
final class EstablishPasswordHandler
{
    public function __construct(
        private readonly Users $users,
        private readonly ClockInterface $clock,
        private readonly PasswordHasherFactoryInterface $hashers,
        private readonly MessageBusInterface $eventBus,
    ) {
    }

    public function __invoke(EstablishPassword $command): void
    {
        $user = $this->users->byId(UserId::fromString($command->userId));

        $hash = $this->hashers->getPasswordHasher('users')->hash($command->plaintextPassword);
        $user->establishPassword(HashedPassword::fromHash($hash), $this->clock);
        $this->users->save($user);

        foreach ($user->releaseEvents() as $event) {
            $this->eventBus->dispatch($event, [new DispatchAfterCurrentBusStamp()]);
        }
    }
}
