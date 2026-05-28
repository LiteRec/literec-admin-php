<?php

declare(strict_types=1);

namespace App\Tests\Unit\Inventory\Infrastructure\Http\Form;

use App\Catalog\Domain\ListingKind;
use App\Inventory\Infrastructure\Http\Form\InventoryItemFormType;
use App\Inventory\Infrastructure\Http\Form\InventoryItemInput;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\PreloadedExtension;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\Validator\ConstraintValidatorFactory;
use Symfony\Component\Validator\Validation;

/**
 * Validates the LRA-86 form-binding layer in isolation. Constraints
 * here are the first line of defense at the HTTP boundary; the real
 * domain validation lives in the value-object constructors invoked
 * downstream by the command handlers.
 */
#[Small]
#[AllowMockObjectsWithoutExpectations]
final class InventoryItemFormTypeTest extends TypeTestCase
{
    /** Reused literals (SonarCloud php:S1192). */
    private const string POS_COLOR = '#A1B2C3';

    protected function getExtensions(): array
    {
        $validator = Validation::createValidatorBuilder()
            ->setConstraintValidatorFactory(new ConstraintValidatorFactory())
            ->getValidator();

        return [
            new PreloadedExtension([new InventoryItemFormType()], []),
            new ValidatorExtension($validator),
        ];
    }

    #[Test]
    #[TestDox('A fully valid payload binds and is reported as valid.')]
    public function valid_payload_binds_successfully(): void
    {
        $form = $this->factory->create(InventoryItemFormType::class, new InventoryItemInput());
        $form->submit([
            'name' => 'Widget',
            'code' => 'WID-001',
            'kind' => ListingKind::Inventory->value,
            'vendorId' => '',
            'posColorHex' => self::POS_COLOR,
            'ledgerAccount' => '4000',
            'taxApply' => '1',
            'taxIncludedInFee' => '0',
            'feeAmountCents' => '500',
            'trackInventory' => '1',
            'rentable' => '0',
            'reorderThresholdUnits' => '3',
        ]);

        self::assertTrue($form->isSynchronized());
        self::assertTrue($form->isValid(), 'Validation errors: ' . $this->dumpErrors($form));
        $data = $form->getData();
        self::assertInstanceOf(InventoryItemInput::class, $data);
        self::assertSame('Widget', $data->name);
        self::assertSame('WID-001', $data->code);
        self::assertSame(self::POS_COLOR, $data->posColorHex);
    }

    #[Test]
    #[TestDox('Missing required name fails validation.')]
    public function missing_name_is_invalid(): void
    {
        $form = $this->buildAndSubmit(['name' => '']);
        self::assertFalse($form->isValid());
        self::assertTrue($form->get('name')->getErrors()->count() > 0);
    }

    #[Test]
    #[TestDox('Code containing spaces fails the pattern check.')]
    public function code_with_invalid_characters_is_invalid(): void
    {
        $form = $this->buildAndSubmit(['code' => 'has space']);
        self::assertFalse($form->isValid());
        self::assertTrue($form->get('code')->getErrors()->count() > 0);
    }

    #[Test]
    #[TestDox('Name longer than 200 characters fails length constraint.')]
    public function over_long_name_is_invalid(): void
    {
        $form = $this->buildAndSubmit(['name' => str_repeat('x', 201)]);
        self::assertFalse($form->isValid());
        self::assertTrue($form->get('name')->getErrors()->count() > 0);
    }

    #[Test]
    #[TestDox('POS color that is not a 7-char #RRGGBB hex fails validation.')]
    public function invalid_pos_color_hex_is_invalid(): void
    {
        $form = $this->buildAndSubmit(['posColorHex' => 'red']);
        self::assertFalse($form->isValid());
        self::assertTrue($form->get('posColorHex')->getErrors()->count() > 0);
    }

    #[Test]
    #[TestDox('A kind value not in the ListingKind enum fails the choice constraint.')]
    public function unknown_kind_is_invalid(): void
    {
        $form = $this->buildAndSubmit(['kind' => 'NOT_A_REAL_KIND']);
        self::assertFalse($form->isValid());
        self::assertTrue($form->get('kind')->getErrors()->count() > 0);
    }

    /**
     * @param array<string, string> $overrides
     * @return \Symfony\Component\Form\FormInterface<InventoryItemInput>
     */
    private function buildAndSubmit(array $overrides): \Symfony\Component\Form\FormInterface
    {
        $base = [
            'name' => 'Widget',
            'code' => 'WID-001',
            'kind' => ListingKind::Inventory->value,
            'vendorId' => '',
            'posColorHex' => self::POS_COLOR,
            'ledgerAccount' => '4000',
            'taxApply' => '0',
            'taxIncludedInFee' => '0',
            'feeAmountCents' => '500',
            'trackInventory' => '1',
            'rentable' => '0',
            'reorderThresholdUnits' => '3',
        ];

        $form = $this->factory->create(InventoryItemFormType::class, new InventoryItemInput());
        $form->submit(array_replace($base, $overrides));

        return $form;
    }

    /**
     * @param \Symfony\Component\Form\FormInterface<InventoryItemInput> $form
     */
    private function dumpErrors(\Symfony\Component\Form\FormInterface $form): string
    {
        $messages = [];
        foreach ($form->getErrors(true) as $error) {
            $messages[] = $error->getMessage();
        }
        return implode('; ', $messages);
    }
}
