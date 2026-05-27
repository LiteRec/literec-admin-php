<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\Http\Form;

use App\Catalog\Domain\ListingKind;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

/**
 * Symfony Form type backing the LRA-86 "New Item" / "Edit Item" HTMX
 * dialog (templates/inventory/item/_dialog.html.twig). One form shape
 * handles both flows — the controller chooses the action URL and
 * dispatches the matching command(s).
 *
 * Domain validation happens inside the value-object constructors
 * (PosColor, ListingCode, VendorId, ReorderThreshold) when the
 * controller maps the input into the command DTOs; the constraints
 * here exist only to short-circuit obvious garbage at the HTTP boundary
 * before the bus is dispatched.
 *
 * The Vendor field is a free-text UUID v7 input for now because the
 * Inventory context does not yet ship a vendor-listing read model that
 * could back a `<select>`. A follow-up ticket can upgrade it to a
 * ChoiceType once that endpoint exists.
 *
 * @extends AbstractType<InventoryItemInput>
 */
final class InventoryItemFormType extends AbstractType
{
    public const string POS_COLOR_PATTERN = '/^#[0-9a-fA-F]{6}$/';

    public const string CODE_PATTERN = '/^[A-Z0-9_-]+$/i';

    public const string UUID_V7_PATTERN
        = '/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/';

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Name',
                'required' => true,
                'constraints' => [
                    new NotBlank(),
                    new Length(max: 200),
                ],
            ])
            ->add('code', TextType::class, [
                'label' => 'Code',
                'required' => true,
                'constraints' => [
                    new NotBlank(),
                    new Length(max: 50),
                    new Regex(pattern: self::CODE_PATTERN, message: 'Use letters, digits, underscore or hyphen only.'),
                ],
            ])
            ->add('kind', ChoiceType::class, [
                'label' => 'Kind',
                'choices' => self::kindChoices(),
                'placeholder' => 'Select…',
                'required' => true,
            ])
            ->add('vendorId', TextType::class, [
                'label' => 'Primary vendor (UUID, optional)',
                'required' => false,
                'constraints' => [
                    new Regex(
                        pattern: self::UUID_V7_PATTERN,
                        message: 'Vendor id must be a UUID v7.',
                    ),
                ],
                'empty_data' => null,
            ])
            ->add('posColorHex', TextType::class, [
                'label' => 'POS button color',
                'required' => true,
                'constraints' => [
                    new NotBlank(),
                    new Regex(
                        pattern: self::POS_COLOR_PATTERN,
                        message: 'POS color must be a #RRGGBB hex value.',
                    ),
                ],
            ])
            ->add('ledgerAccount', TextType::class, [
                'label' => 'Ledger account',
                'required' => true,
                'constraints' => [
                    new NotBlank(),
                    new Length(max: 100),
                ],
            ])
            ->add('taxApply', CheckboxType::class, [
                'label' => 'Apply tax',
                'required' => false,
            ])
            ->add('taxIncludedInFee', CheckboxType::class, [
                'label' => 'Tax included in fee',
                'required' => false,
            ])
            ->add('feeAmountCents', IntegerType::class, [
                'label' => 'Base fee (cents)',
                'required' => true,
                'constraints' => [
                    new GreaterThanOrEqual(0),
                ],
            ])
            ->add('trackInventory', CheckboxType::class, [
                'label' => 'Track inventory',
                'required' => false,
            ])
            ->add('rentable', CheckboxType::class, [
                'label' => 'Rentable',
                'required' => false,
            ])
            ->add('reorderThresholdUnits', IntegerType::class, [
                'label' => 'Reorder threshold (units)',
                'required' => true,
                'constraints' => [
                    new GreaterThanOrEqual(0),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => InventoryItemInput::class,
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id' => 'inventory_item',
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'inventory_item';
    }

    /**
     * @return array<string, string>
     */
    private static function kindChoices(): array
    {
        $choices = [];
        foreach (ListingKind::cases() as $case) {
            $choices[ucfirst(strtolower(str_replace('_', ' ', $case->name)))] = $case->value;
        }
        return $choices;
    }
}
