<?php

declare(strict_types=1);

namespace App\Tests\Architecture;

use PHPat\Selector\Selector;
use PHPat\Test\Attributes\TestRule;
use PHPat\Test\Builder\Rule;
use PHPat\Test\PHPat;

/**
 * Domain events are immutable value objects named for something that
 * already happened (DDD section: "Name domain events in past tense
 * (UserRegistered, MembershipRenewed, TransactionRecorded), declare them
 * final with readonly properties").
 *
 * The naming regex requires a past-participle token — the regular `ed`
 * ending, or one of the irregular participles already in use (`Sent`,
 * `Split`, `Withdrawn`) — optionally followed by a capitalised
 * prepositional tail (`ToHousehold`, `FromGroup`, `Into`, `Off`, `In`,
 * `Out`). It is an allow-list, not a general English past-tense checker;
 * add a new irregular participle here deliberately when one is needed,
 * the same way the three above were.
 */
final class DomainEventShapeRule
{
    private const DOMAIN_EVENT_NAMESPACE = '/^App\\\\[A-Za-z]+\\\\Domain\\\\Event$/';
    private const PAST_TENSE_EVENT_NAME =
        '/^App\\\\[A-Za-z]+\\\\Domain\\\\Event\\\\[A-Z][A-Za-z]*?(ed|Sent|Split|Withdrawn)([A-Z][a-z]+)*$/';

    #[TestRule]
    public function domain_events_are_readonly(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace(self::DOMAIN_EVENT_NAMESPACE, true))
            ->should()
            ->beReadonly()
            ->because('CLAUDE.md: domain events are declared final with readonly properties.');
    }

    #[TestRule]
    public function domain_events_are_named_in_the_past_tense(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace(self::DOMAIN_EVENT_NAMESPACE, true))
            ->should()
            ->beNamed(self::PAST_TENSE_EVENT_NAME, true)
            ->because('CLAUDE.md: domain events are named in past tense.');
    }
}
