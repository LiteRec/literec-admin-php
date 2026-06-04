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
 * Shared field builders for the household member-entry, profile, and address
 * forms. Composed via `use` at the point of use so the field definitions — and
 * the WCAG 1.3.5 autocomplete tokens — are declared in exactly one place. Each
 * field routes through a small helper so the per-field option array is written
 * once rather than copy-pasted across {@see AddMemberFormType},
 * {@see RegisterHouseholdFormType}, {@see UpdateHouseholdAddressFormType}, and
 * {@see UpdateMemberProfileFormType}.
 */
trait BuildsHouseholdFormFields
{
    /**
     * @template T
     *
     * @param FormBuilderInterface<T> $builder
     */
    private function addPrimaryMemberFields(FormBuilderInterface $builder): void
    {
        $this->addAutocompleteTextField($builder, 'firstName', 'First name', true, 'given-name');
        $this->addAutocompleteTextField($builder, 'lastName', 'Last name', true, 'family-name');
        $this->addAutocompleteTextField($builder, 'middleName', 'Middle name', false, 'additional-name');
        $this->addAutocompleteTextField($builder, 'suffix', 'Suffix', false, 'honorific-suffix');
        $this->addDateOfBirthField($builder);
        $this->addGenderField($builder);
        $builder->add('email', EmailType::class, [
            'label' => 'Email',
            'required' => true,
            'attr' => ['autocomplete' => 'email'],
        ]);
        $this->addAutocompleteTextField($builder, 'phone', 'Phone', true, 'tel');
        $builder->add('residencyStatusCode', ChoiceType::class, [
            'label' => 'Residency status',
            'choices' => self::residencyChoices(),
            'placeholder' => 'Select…',
            'required' => true,
        ]);
        $builder->add('memberCode', TextType::class, [
            'label' => 'Member code',
            'required' => false,
            'help' => 'Leave blank to allocate automatically.',
        ]);
    }

    /**
     * @template T
     *
     * @param FormBuilderInterface<T> $builder
     */
    private function addAddressFields(FormBuilderInterface $builder): void
    {
        $this->addAutocompleteTextField($builder, 'street', 'Street', true, 'address-line1');
        $this->addAutocompleteTextField($builder, 'unit', 'Unit', false, 'address-line2');
        $this->addAutocompleteTextField($builder, 'city', 'City', true, 'address-level2');
        $this->addAutocompleteTextField($builder, 'state', 'State / Province', true, 'address-level1');
        $this->addAutocompleteTextField($builder, 'postalCode', 'Postal code', true, 'postal-code');
        $this->addAutocompleteTextField($builder, 'country', 'Country (ISO 3166-1 alpha-2)', true, 'country');
    }

    /**
     * Adds a single text field carrying a WCAG 1.3.5 autocomplete token.
     *
     * @template T
     *
     * @param FormBuilderInterface<T> $builder
     */
    private function addAutocompleteTextField(
        FormBuilderInterface $builder,
        string $name,
        string $label,
        bool $required,
        string $autocomplete,
    ): void {
        $builder->add($name, TextType::class, [
            'label' => $label,
            'required' => $required,
            'attr' => ['autocomplete' => $autocomplete],
        ]);
    }

    /**
     * @template T
     *
     * @param FormBuilderInterface<T> $builder
     */
    private function addDateOfBirthField(FormBuilderInterface $builder): void
    {
        $builder->add('dobIso', DateType::class, [
            'label' => 'Date of birth',
            'widget' => 'single_text',
            'html5' => true,
            'input' => 'string',
            'format' => 'yyyy-MM-dd',
            'required' => true,
            'attr' => ['autocomplete' => 'bday'],
        ]);
    }

    /**
     * @template T
     *
     * @param FormBuilderInterface<T> $builder
     */
    private function addGenderField(FormBuilderInterface $builder): void
    {
        $builder->add('genderCode', ChoiceType::class, [
            'label' => 'Gender',
            'choices' => self::genderChoices(),
            'placeholder' => 'Select…',
            'required' => true,
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
