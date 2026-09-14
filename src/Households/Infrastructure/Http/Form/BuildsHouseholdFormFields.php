<?php

declare(strict_types=1);

namespace App\Households\Infrastructure\Http\Form;

use App\Households\Domain\ValueObject\Gender;
use App\Households\Domain\ValueObject\ResidencyStatus;
use App\Households\Domain\ValueObject\Salutation;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
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
    /** Reused literal (SonarCloud php:S1192). */
    private const string PLACEHOLDER_SELECT = 'Select…';

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
        $this->addEmailField($builder, true);
        $this->addAutocompleteTextField($builder, 'phone', 'Phone', true, 'tel');
        $builder->add('residencyStatusCode', ChoiceType::class, [
            'label' => 'Residency status',
            'choices' => self::residencyChoices(),
            'placeholder' => self::PLACEHOLDER_SELECT,
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
     * Adds the email field shared by the primary-member-entry fields and
     * the Contact sub-card (LRA-204). Extracted here so the field
     * definition — including its `email` autocomplete token — is declared
     * once, per the SonarCloud 3% new-code duplication gate.
     *
     * @template T
     *
     * @param FormBuilderInterface<T> $builder
     */
    private function addEmailField(FormBuilderInterface $builder, bool $required): void
    {
        $builder->add('email', EmailType::class, [
            'label' => 'Email',
            'required' => $required,
            'attr' => ['autocomplete' => 'email'],
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
            'placeholder' => self::PLACEHOLDER_SELECT,
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
     * Nickname ("Goes by") field shared by the Profile card edit form
     * (LRA-205) and, once split off, the registration/add-member dialogs.
     *
     * @template T
     *
     * @param FormBuilderInterface<T> $builder
     */
    private function addNicknameField(FormBuilderInterface $builder): void
    {
        $builder->add('nickname', TextType::class, [
            'label' => 'Goes by',
            'required' => false,
            'attr' => ['autocomplete' => 'nickname'],
        ]);
    }

    /**
     * @template T
     *
     * @param FormBuilderInterface<T> $builder
     */
    private function addSalutationField(FormBuilderInterface $builder): void
    {
        $builder->add('salutationCode', ChoiceType::class, [
            'label' => 'Salutation',
            'choices' => self::salutationChoices(),
            'placeholder' => self::PLACEHOLDER_SELECT,
            'required' => false,
            'attr' => ['autocomplete' => 'honorific-prefix'],
        ]);
    }

    /**
     * Height is entered and stored as a whole number of inches (LRA-205).
     * The field renders an HTML5 number input; the form is `novalidate`, so
     * the server (via {@see \App\Households\Domain\ValueObject\Height})
     * owns the actual validation. A non-numeric value fails IntegerType's
     * transformer and lands as a field-level error before the command is
     * ever dispatched.
     *
     * @template T
     *
     * @param FormBuilderInterface<T> $builder
     */
    private function addHeightField(FormBuilderInterface $builder): void
    {
        $builder->add('heightInches', IntegerType::class, [
            'label' => 'Height (inches)',
            'required' => false,
            'attr' => ['min' => 1, 'inputmode' => 'numeric'],
            'invalid_message' => 'Height must be a whole number of inches.',
        ]);
    }

    /**
     * Weight is entered and stored as a whole number of pounds (LRA-205).
     * See {@see self::addHeightField()} for the shared validation rationale.
     *
     * @template T
     *
     * @param FormBuilderInterface<T> $builder
     */
    private function addWeightField(FormBuilderInterface $builder): void
    {
        $builder->add('weightPounds', IntegerType::class, [
            'label' => 'Weight (lbs)',
            'required' => false,
            'attr' => ['min' => 1, 'inputmode' => 'numeric'],
            'invalid_message' => 'Weight must be a whole number of pounds.',
        ]);
    }

    /**
     * @return array<string, string>
     */
    private static function salutationChoices(): array
    {
        return [
            'Mr.' => Salutation::Mr->value,
            'Mrs.' => Salutation::Mrs->value,
            'Ms.' => Salutation::Ms->value,
            'Mx.' => Salutation::Mx->value,
            'Dr.' => Salutation::Dr->value,
            'Rev.' => Salutation::Rev->value,
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
