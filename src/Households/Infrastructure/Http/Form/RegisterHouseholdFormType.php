<?php

declare(strict_types=1);

namespace App\Households\Infrastructure\Http\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Symfony Form type backing the "Register Household" HTMX dialog
 * (templates/households/new/_dialog.html.twig) introduced in LRA-40.
 *
 * Fields collected here cover both the household-level address and the
 * primary member's profile, matching the inputs of
 * {@see \App\Households\Application\Command\RegisterHousehold}. Domain
 * validation runs inside the command handler when value objects are
 * constructed; the form supplies only the structural HTTP-binding layer.
 *
 * @extends AbstractType<RegisterHouseholdInput>
 */
final class RegisterHouseholdFormType extends AbstractType
{
    use AddsHouseholdMemberFields;
    use AddsHouseholdAddressFields;

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('householdName', TextType::class, [
            'label' => 'Household name',
            'required' => true,
        ]);

        $this->addPrimaryMemberFields($builder);
        $this->addAddressFields($builder);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => RegisterHouseholdInput::class,
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id' => 'register_household',
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'register_household';
    }
}
