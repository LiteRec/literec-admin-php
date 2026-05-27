<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\Http\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

/**
 * Symfony Form type for the LRA-90 Create Purchase Order page.
 *
 * Header fields are `vendorId` (UUID v7) and `facilityCode` (short
 * uppercase code). The `lines` CollectionType wraps
 * {@see PurchaseOrderLineFormType} with `allow_add` / `allow_delete`
 * so the Alpine "Add line" controller can clone the prototype row and
 * deleted rows drop out on submit.
 *
 * Validation here is HTTP-boundary short-circuit only: domain rules
 * still fire when the controller maps the input into the
 * {@see \App\Inventory\Application\Command\CreatePurchaseOrder}
 * command and dispatches it.
 *
 * @extends AbstractType<PurchaseOrderInput>
 */
final class PurchaseOrderFormType extends AbstractType
{
    public const string UUID_V7_PATTERN
        = '/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';

    public const int MAX_FACILITY_CODE_LENGTH = 16;

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('vendorId', TextType::class, [
                'label' => 'Vendor id',
                'required' => true,
                'constraints' => [
                    new NotBlank(),
                    new Regex(
                        pattern: self::UUID_V7_PATTERN,
                        message: 'Vendor id must be a UUID v7.',
                    ),
                ],
            ])
            ->add('facilityCode', TextType::class, [
                'label' => 'Facility code',
                'required' => true,
                'constraints' => [
                    new NotBlank(),
                    new Length(max: self::MAX_FACILITY_CODE_LENGTH),
                ],
            ])
            ->add('lines', CollectionType::class, [
                'entry_type' => PurchaseOrderLineFormType::class,
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'prototype' => true,
                'prototype_name' => '__name__',
                'label' => 'Lines',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => PurchaseOrderInput::class,
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id' => 'inventory_purchase_order',
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'inventory_purchase_order';
    }
}
