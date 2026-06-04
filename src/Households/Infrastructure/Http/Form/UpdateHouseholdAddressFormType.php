<?php

declare(strict_types=1);

namespace App\Households\Infrastructure\Http\Form;

use Symfony\Component\Form\AbstractType;
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
    use AddsHouseholdAddressFields;

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->addAddressFields($builder);
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
