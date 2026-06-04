<?php

declare(strict_types=1);

namespace App\Households\Infrastructure\Http\Form;

use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * Shared household address field definitions collected identically by both
 * {@see RegisterHouseholdFormType} and {@see UpdateHouseholdAddressFormType}.
 * Composed via `use` at the point of use so the field set — including the WCAG
 * 1.3.5 autocomplete tokens — is declared in exactly one place.
 */
trait AddsHouseholdAddressFields
{
    /**
     * @template T
     *
     * @param FormBuilderInterface<T> $builder
     */
    private function addAddressFields(FormBuilderInterface $builder): void
    {
        $builder
            ->add('street', TextType::class, [
                'label' => 'Street',
                'required' => true,
                'attr' => ['autocomplete' => 'address-line1'],
            ])
            ->add('unit', TextType::class, [
                'label' => 'Unit',
                'required' => false,
                'attr' => ['autocomplete' => 'address-line2'],
            ])
            ->add('city', TextType::class, [
                'label' => 'City',
                'required' => true,
                'attr' => ['autocomplete' => 'address-level2'],
            ])
            ->add('state', TextType::class, [
                'label' => 'State / Province',
                'required' => true,
                'attr' => ['autocomplete' => 'address-level1'],
            ])
            ->add('postalCode', TextType::class, [
                'label' => 'Postal code',
                'required' => true,
                'attr' => ['autocomplete' => 'postal-code'],
            ])
            ->add('country', TextType::class, [
                'label' => 'Country (ISO 3166-1 alpha-2)',
                'required' => true,
                'attr' => ['autocomplete' => 'country'],
            ]);
    }
}
