<?php

declare(strict_types=1);

namespace App\Users\Application\Command;

use App\Users\Domain\Exception\UsernameAlreadyTaken;
use App\Users\Domain\IdentityGenerator;
use App\Users\Domain\User;
use App\Users\Domain\Users;
use App\Users\Domain\ValueObject\HashedPassword;
use App\Users\Domain\ValueObject\Role;
use App\Users\Domain\ValueObject\UserId;
use App\Users\Domain\ValueObject\Username;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;

#[AsMessageHandler(bus: 'command.bus')]
final class RegisterUserHandler
{
    public function __construct(
        private readonly Users $users,
        private readonly IdentityGenerator $ids,
        private readonly ClockInterface $clock,
        private readonly PasswordHasherFactoryInterface $hashers,
        private readonly MessageBusInterface $eventBus,
    ) {
    }

    public function __invoke(RegisterUser $command): UserId
    {
        $username = Username::of($command->username);

        if ($this->users->existsWithUsername($username)) {
            throw UsernameAlreadyTaken::for($username->value);
        }

        // The named 'users' hasher is configured in security.yaml's
        // password_hashers section; referring to it by name keeps this
        // handler free of any Infrastructure import.
        $hash = $this->hashers->getPasswordHasher('users')
            ->hash($command->plaintextPassword);

        $password = HashedPassword::fromHash($hash);
        $roles = array_map(static fn(string $r): Role => Role::from($r), $command->roles);
        $id = $this->ids->nextUserId();

        $user = User::register($id, $username, $password, $roles, $this->clock);
        $this->users->add($user);

        foreach ($user->releaseEvents() as $event) {
            $this->eventBus->dispatch($event, [new DispatchAfterCurrentBusStamp()]);
        }

        return $id;
    }
}
