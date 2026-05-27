<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\Http\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

/**
 * Symfony Form type backing the LRA-89 "Create Combo" / "Edit Combo"
 * HTMX dialog. The controller chooses the action URL and dispatches
 * either {@see \App\Inventory\Application\Command\DefineCombo} or
 * {@see \App\Inventory\Application\Command\UpdateComboComponents}.
 *
 * Domain validation happens inside the value-object constructors
 * (ListingId, InventoryItemId, Quantity) when the controller maps the
 * input into the command DTOs; the constraints here exist only to
 * short-circuit obvious garbage at the HTTP boundary before the bus is
 * dispatched.
 *
 * Components is a CollectionType with `allow_add` + `allow_delete` so
 * the Alpine "Add component" controller in the template can clone the
 * `data-prototype` row and so that omitted server-rendered rows are
 * dropped on submit.
 *
 * @extends AbstractType<ComboFormInput>
 */
final class ComboFormType extends AbstractType
{
    public const string UUID_V7_PATTERN
        = '/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('parentListingId', TextType::class, [
                'label' => 'Parent catalog listing id',
                'required' => true,
                'constraints' => [
                    new NotBlank(),
                    new Regex(
                        pattern: self::UUID_V7_PATTERN,
                        message: 'Parent listing id must be a UUID v7.',
                    ),
                ],
            ])
            ->add('components', CollectionType::class, [
                'entry_type' => ComboComponentFormType::class,
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'prototype' => true,
                'prototype_name' => '__name__',
                'label' => 'Components',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ComboFormInput::class,
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id' => 'inventory_combo',
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'inventory_combo';
    }
}
