<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\Http\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

/**
 * Symfony Form type backing the LRA-89 "Link Item" HTMX dialog.
 *
 * Domain validation happens inside the value-object constructors
 * (InventoryItemId, Quantity, ItemLink) when the controller maps the
 * input into the {@see \App\Inventory\Application\Command\LinkItem}
 * command; the constraints here exist only to short-circuit obvious
 * garbage at the HTTP boundary before the bus is dispatched.
 *
 * @extends AbstractType<ItemLinkFormInput>
 */
final class ItemLinkFormType extends AbstractType
{
    public const string UUID_V7_PATTERN
        = '/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';

    public const string ISO_DATE_PATTERN = '/^\d{4}-\d{2}-\d{2}$/';

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('linkedItemId', TextType::class, [
                'label' => 'Linked item id (UUID v7)',
                'required' => true,
                'constraints' => [
                    new NotBlank(),
                    new Regex(
                        pattern: self::UUID_V7_PATTERN,
                        message: 'Linked item id must be a UUID v7.',
                    ),
                ],
            ])
            ->add('reservedQuantityUnits', IntegerType::class, [
                'label' => 'Reserved quantity (units)',
                'required' => true,
                'constraints' => [
                    new GreaterThanOrEqual(0),
                ],
            ])
            ->add('unlimited', CheckboxType::class, [
                'label' => 'Unlimited',
                'required' => false,
            ])
            ->add('minRequiredUnits', IntegerType::class, [
                'label' => 'Min required',
                'required' => true,
                'constraints' => [
                    new GreaterThanOrEqual(0),
                ],
            ])
            ->add('maxPerPurchaseUnits', IntegerType::class, [
                'label' => 'Max per purchase',
                'required' => true,
                'constraints' => [
                    new GreaterThanOrEqual(0),
                ],
            ])
            ->add('includeUntilIso', TextType::class, [
                'label' => 'Include until (YYYY-MM-DD, optional)',
                'required' => false,
                'constraints' => [
                    new Regex(
                        pattern: self::ISO_DATE_PATTERN,
                        message: 'Include-until must be a YYYY-MM-DD date.',
                    ),
                ],
                'empty_data' => null,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ItemLinkFormInput::class,
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id' => 'inventory_item_link',
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'inventory_item_link';
    }
}
