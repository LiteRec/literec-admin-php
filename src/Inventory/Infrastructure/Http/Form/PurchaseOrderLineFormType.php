<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\Http\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

/**
 * Inner-row form type for each line inside the LRA-90 Create Purchase
 * Order page. Mirrors the LRA-89 Combo row pattern so the Alpine
 * "Add line" controller can clone the `data-prototype` row.
 *
 * @extends AbstractType<PurchaseOrderLineInput>
 */
final class PurchaseOrderLineFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('itemId', TextType::class, [
                'label' => 'Inventory item id',
                'required' => true,
                'constraints' => [
                    new NotBlank(),
                    new Regex(
                        pattern: PurchaseOrderFormType::UUID_V7_PATTERN,
                        message: 'Item id must be a UUID v7.',
                    ),
                ],
            ])
            ->add('orderedQuantityUnits', IntegerType::class, [
                'label' => 'Ordered quantity',
                'required' => true,
                'constraints' => [
                    new NotBlank(),
                    new GreaterThanOrEqual(1),
                ],
            ])
            ->add('costPerUnitCents', IntegerType::class, [
                'label' => 'Cost per unit (cents)',
                'required' => true,
                'constraints' => [
                    new NotBlank(),
                    new GreaterThanOrEqual(0),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => PurchaseOrderLineInput::class,
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'inventory_purchase_order_line';
    }
}
