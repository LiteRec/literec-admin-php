<?php

declare(strict_types=1);

namespace App\Households\Infrastructure\Http\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Symfony Form type backing the Address sub-card edit form (LRA-44).
 *
 * Surface matches the six fields on the household's
 * {@see \App\Households\Domain\ValueObject\Address} VO and on the
 * {@see \App\Households\Application\Command\UpdateHouseholdAddress} command.
 *
 * Note that the form posts to the household-scoped endpoint (no member id)
 * because the address lives on the household aggregate; every member in
 * the household shares it.
 *
 * @extends AbstractType<UpdateHouseholdAddressInput>
 */
final class UpdateHouseholdAddressFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
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

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => UpdateHouseholdAddressInput::class,
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id' => 'update_household_address',
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'update_household_address';
    }
}
