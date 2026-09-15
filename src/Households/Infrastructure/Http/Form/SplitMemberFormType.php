<?php

declare(strict_types=1);

namespace App\Households\Infrastructure\Http\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Symfony Form type backing the split-member dialog (LRA-209): the new
 * member's name and contact fields, an optional free-text reason, and
 * the hidden set of transaction references carried over from the
 * Transaction History card's row selection.
 *
 * Date of birth, gender, and residency status are not fields here — they
 * are copied from the source member by
 * {@see \App\Households\Domain\Household::splitMember()}; only identity
 * (name) and contact are entered fresh, since the split creates a
 * distinct person's record.
 *
 * @extends AbstractType<SplitMemberInput>
 */
final class SplitMemberFormType extends AbstractType
{
    use BuildsHouseholdFormFields;

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->addAutocompleteTextField($builder, 'firstName', 'First name', true, 'given-name');
        $this->addAutocompleteTextField($builder, 'lastName', 'Last name', true, 'family-name');
        $this->addAutocompleteTextField($builder, 'middleName', 'Middle name', false, 'additional-name');
        $this->addAutocompleteTextField($builder, 'suffix', 'Suffix', false, 'honorific-suffix');
        $this->addEmailField($builder, false);
        $this->addAutocompleteTextField($builder, 'phone', 'Phone', false, 'tel');

        $builder
            ->add('reason', TextareaType::class, [
                'label' => 'Reason (optional)',
                'required' => false,
            ])
            ->add('transactionIds', CollectionType::class, [
                'entry_type' => HiddenType::class,
                'allow_add' => true,
                'allow_delete' => false,
                'prototype' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => SplitMemberInput::class,
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id' => 'split_member',
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'split_member';
    }
}
