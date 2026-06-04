<?php

declare(strict_types=1);

namespace App\Households\Infrastructure\Http\Form;

use App\Households\Domain\ValueObject\Gender;
use App\Households\Domain\ValueObject\ResidencyStatus;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * Shared primary-member field definitions collected identically by both
 * {@see AddMemberFormType} and {@see RegisterHouseholdFormType}. Composed via
 * `use` at the point of use so the field set — including the WCAG 1.3.5
 * autocomplete tokens and the gender/residency choice maps — is declared in
 * exactly one place rather than duplicated across the two form types.
 */
trait AddsHouseholdMemberFields
{
    /**
     * @template T
     *
     * @param FormBuilderInterface<T> $builder
     */
    private function addPrimaryMemberFields(FormBuilderInterface $builder): void
    {
        $builder
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
                'choices' => self::genderChoices(),
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
            ]);
    }

    /**
     * @return array<string, string>
     */
    private static function genderChoices(): array
    {
        return [
            'Female' => Gender::Female->value,
            'Male' => Gender::Male->value,
            'Other' => Gender::Other->value,
            'Unspecified' => Gender::Unspecified->value,
        ];
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
