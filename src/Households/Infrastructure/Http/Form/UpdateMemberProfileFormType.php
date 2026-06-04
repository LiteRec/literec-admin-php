<?php

declare(strict_types=1);

namespace App\Households\Infrastructure\Http\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Symfony Form type backing the Profile card edit form (LRA-43).
 *
 * Surface is the subset of member profile fields the card mutates: name
 * parts, date of birth, gender. Email and phone are owned by a separate
 * contact-update flow (see {@see \App\Households\Domain\Household::updateMemberContact()}),
 * so the Profile card edits identity data only — matching the inputs of
 * {@see \App\Households\Application\Command\UpdateMemberProfile}.
 *
 * @extends AbstractType<UpdateMemberProfileInput>
 */
final class UpdateMemberProfileFormType extends AbstractType
{
    use BuildsHouseholdFormFields;

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->addAutocompleteTextField($builder, 'firstName', 'First name', true, 'given-name');
        $this->addAutocompleteTextField($builder, 'middleName', 'Middle name', false, 'additional-name');
        $this->addAutocompleteTextField($builder, 'lastName', 'Last name', true, 'family-name');
        $this->addAutocompleteTextField($builder, 'suffix', 'Suffix', false, 'honorific-suffix');
        $this->addDateOfBirthField($builder);
        $this->addGenderField($builder);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => UpdateMemberProfileInput::class,
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id' => 'update_member_profile',
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'update_member_profile';
    }
}
