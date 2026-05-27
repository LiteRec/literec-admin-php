<?php

declare(strict_types=1);

namespace App\Tests\Unit\Inventory\Infrastructure\Http\Form;

use App\Inventory\Infrastructure\Http\Form\ReceiveStockFormType;
use App\Inventory\Infrastructure\Http\Form\ReceiveStockInput;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\PreloadedExtension;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\Validator\Validation;

/**
 * Unit tests for the LRA-87 Receive Stock form type. Exercises the
 * field-level constraint shape only — XOR cost normalisation and
 * domain-exception mapping live in the controller and are covered by
 * the functional suite.
 */
#[Small]
#[AllowMockObjectsWithoutExpectations]
final class ReceiveStockFormTypeTest extends TypeTestCase
{
    private const string FACILITY_MAIN = 'MAIN';
    private const string FACILITY_LAB = 'LAB';

    /** @return array<int, \Symfony\Component\Form\FormExtensionInterface> */
    protected function getExtensions(): array
    {
        $validator = Validation::createValidator();

        return [
            new ValidatorExtension($validator),
            new PreloadedExtension([new ReceiveStockFormType()], []),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function defaultFacilityChoices(): array
    {
        return [
            self::FACILITY_MAIN => self::FACILITY_MAIN,
            self::FACILITY_LAB => self::FACILITY_LAB,
        ];
    }

    #[Test]
    #[TestDox('A valid submission with per-unit cost binds cleanly to the input DTO.')]
    public function valid_submission_binds_to_input(): void
    {
        $input = new ReceiveStockInput();
        $form = $this->factory->create(ReceiveStockFormType::class, $input, [
            ReceiveStockFormType::FACILITY_CHOICES_OPTION => $this->defaultFacilityChoices(),
        ]);

        $form->submit([
            'facilityCode' => self::FACILITY_MAIN,
            'quantityUnits' => '10',
            'costPerUnitCents' => '250',
            'totalCostCents' => '',
            'comment' => 'opening stock',
        ]);

        self::assertTrue($form->isSynchronized());
        self::assertTrue($form->isValid(), 'Form should be valid: ' . (string) $form->getErrors(true));
        self::assertSame(self::FACILITY_MAIN, $input->facilityCode);
        self::assertSame(10, $input->quantityUnits);
        self::assertSame(250, $input->costPerUnitCents);
        self::assertNull($input->totalCostCents);
        self::assertSame('opening stock', $input->comment);
    }

    #[Test]
    #[TestDox('Missing facility selection produces a validation error.')]
    public function missing_facility_is_rejected(): void
    {
        $input = new ReceiveStockInput();
        $form = $this->factory->create(ReceiveStockFormType::class, $input, [
            ReceiveStockFormType::FACILITY_CHOICES_OPTION => $this->defaultFacilityChoices(),
        ]);

        $form->submit([
            'facilityCode' => '',
            'quantityUnits' => '1',
            'costPerUnitCents' => '100',
        ]);

        self::assertFalse($form->isValid());
        self::assertGreaterThan(0, $form->get('facilityCode')->getErrors()->count());
    }

    #[Test]
    #[TestDox('Quantity must be at least 1 — zero is rejected.')]
    public function zero_quantity_is_rejected(): void
    {
        $input = new ReceiveStockInput();
        $form = $this->factory->create(ReceiveStockFormType::class, $input, [
            ReceiveStockFormType::FACILITY_CHOICES_OPTION => $this->defaultFacilityChoices(),
        ]);

        $form->submit([
            'facilityCode' => self::FACILITY_MAIN,
            'quantityUnits' => '0',
            'costPerUnitCents' => '100',
        ]);

        self::assertFalse($form->isValid());
        self::assertGreaterThan(0, $form->get('quantityUnits')->getErrors()->count());
    }

    #[Test]
    #[TestDox('A facility code not in the provided choices is rejected.')]
    public function unknown_facility_choice_is_rejected(): void
    {
        $input = new ReceiveStockInput();
        $form = $this->factory->create(ReceiveStockFormType::class, $input, [
            ReceiveStockFormType::FACILITY_CHOICES_OPTION => $this->defaultFacilityChoices(),
        ]);

        $form->submit([
            'facilityCode' => 'GHOST',
            'quantityUnits' => '5',
            'costPerUnitCents' => '100',
        ]);

        self::assertFalse($form->isValid());
        self::assertGreaterThan(0, $form->get('facilityCode')->getErrors()->count());
    }

    #[Test]
    #[TestDox('A comment longer than the max length is rejected.')]
    public function over_length_comment_is_rejected(): void
    {
        $input = new ReceiveStockInput();
        $form = $this->factory->create(ReceiveStockFormType::class, $input, [
            ReceiveStockFormType::FACILITY_CHOICES_OPTION => $this->defaultFacilityChoices(),
        ]);

        $form->submit([
            'facilityCode' => self::FACILITY_MAIN,
            'quantityUnits' => '1',
            'costPerUnitCents' => '100',
            'comment' => str_repeat('a', ReceiveStockFormType::MAX_COMMENT_LENGTH + 1),
        ]);

        self::assertFalse($form->isValid());
        self::assertGreaterThan(0, $form->get('comment')->getErrors()->count());
    }
}
