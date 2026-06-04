<?php

declare(strict_types=1);

namespace App\Households\Infrastructure\Http\Form;

use App\Households\Domain\ValueObject\Gender;
use App\Households\Domain\ValueObject\ResidencyStatus;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
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
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('householdName', TextType::class, [
                'label' => 'Household name',
                'required' => true,
            ])
            ->add('firstName', TextType::class, [
                'label' => 'First name',
                'required' => true,
                'attr' => ['autocomplete' => 'given-name'],
            ])
            ->add('lastName', TextType::class, [
                'label' => 'Last name',
                'required' => true,
                'attr' => ['autocomplete' => 'family-name'],
            ])
            ->add('middleName', TextType::class, [
                'label' => 'Middle name',
                'required' => false,
                'attr' => ['autocomplete' => 'additional-name'],
            ])
            ->add('suffix', TextType::class, [
                'label' => 'Suffix',
                'required' => false,
                'attr' => ['autocomplete' => 'honorific-suffix'],
            ])
            ->add('dobIso', DateType::class, [
                'label' => 'Date of birth',
                'widget' => 'single_text',
                'html5' => true,
                'input' => 'string',
                'format' => 'yyyy-MM-dd',
                'required' => true,
                'attr' => ['autocomplete' => 'bday'],
            ])
            ->add('genderCode', ChoiceType::class, [
                'label' => 'Gender',
                'choices' => [
                    'Female' => Gender::Female->value,
                    'Male' => Gender::Male->value,
                    'Other' => Gender::Other->value,
                    'Unspecified' => Gender::Unspecified->value,
                ],
                'placeholder' => 'Select…',
                'required' => true,
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'required' => true,
                'attr' => ['autocomplete' => 'email'],
            ])
            ->add('phone', TextType::class, [
                'label' => 'Phone',
                'required' => true,
                'attr' => ['autocomplete' => 'tel'],
            ])
            ->add('residencyStatusCode', ChoiceType::class, [
                'label' => 'Residency status',
                'choices' => self::residencyChoices(),
                'placeholder' => 'Select…',
                'required' => true,
            ])
            ->add('memberCode', TextType::class, [
                'label' => 'Member code',
                'required' => false,
                'help' => 'Leave blank to allocate automatically.',
            ])
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
                'label' => 'State',
                'required' => true,
                'attr' => ['autocomplete' => 'address-level1'],
            ])
            ->add('postalCode', TextType::class, [
                'label' => 'Postal code',
                'required' => true,
                'attr' => ['autocomplete' => 'postal-code'],
            ])
            ->add('country', TextType::class, [
                'label' => 'Country',
                'required' => true,
                'attr' => ['autocomplete' => 'country'],
            ]);
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

    /**
     * @return array<string, string>
     */
    private static function residencyChoices(): array
    {
        return [
            'Resident' => ResidencyStatus::Resident->value,
            'Non-resident' => ResidencyStatus::NonResident->value,
            'Member' => ResidencyStatus::Member->value,
            'Staff' => ResidencyStatus::Staff->value,
        ];
    }
}
