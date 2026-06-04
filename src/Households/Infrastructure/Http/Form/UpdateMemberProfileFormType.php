<?php

declare(strict_types=1);

namespace App\Households\Infrastructure\Http\Form;

use App\Households\Domain\ValueObject\Gender;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
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
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstName', TextType::class, [
                'label' => 'First name',
                'required' => true,
                'attr' => ['autocomplete' => 'given-name'],
            ])
            ->add('middleName', TextType::class, [
                'label' => 'Middle name',
                'required' => false,
                'attr' => ['autocomplete' => 'additional-name'],
            ])
            ->add('lastName', TextType::class, [
                'label' => 'Last name',
                'required' => true,
                'attr' => ['autocomplete' => 'family-name'],
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
            ]);
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
