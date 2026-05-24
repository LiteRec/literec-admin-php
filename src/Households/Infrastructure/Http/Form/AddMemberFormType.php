<?php

declare(strict_types=1);

namespace App\Households\Infrastructure\Http\Form;

use App\Households\Domain\ValueObject\Gender;
use App\Households\Domain\ValueObject\ResidencyStatus;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Symfony Form type backing the "Add Member to Household" HTMX dialog
 * (templates/households/new/_member_dialog.html.twig) introduced in LRA-40.
 *
 * The household id travels in the URL (route parameter), not the form, so
 * an operator cannot accidentally re-target the submission at another
 * household by tampering with hidden inputs. Fields here mirror the
 * primitive shape of
 * {@see \App\Households\Application\Command\AddMemberToHousehold}.
 *
 * @extends AbstractType<AddMemberInput>
 */
final class AddMemberFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstName', TextType::class, [
                'label' => 'First name',
                'required' => true,
            ])
            ->add('lastName', TextType::class, [
                'label' => 'Last name',
                'required' => true,
            ])
            ->add('middleName', TextType::class, [
                'label' => 'Middle name',
                'required' => false,
            ])
            ->add('suffix', TextType::class, [
                'label' => 'Suffix',
                'required' => false,
            ])
            ->add('dobIso', DateType::class, [
                'label' => 'Date of birth',
                'widget' => 'single_text',
                'html5' => true,
                'input' => 'string',
                'format' => 'yyyy-MM-dd',
                'required' => true,
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
            ])
            ->add('phone', TextType::class, [
                'label' => 'Phone',
                'required' => true,
            ])
            ->add('residencyStatusCode', ChoiceType::class, [
                'label' => 'Residency status',
                'choices' => [
                    'Resident' => ResidencyStatus::Resident->value,
                    'Non-resident' => ResidencyStatus::NonResident->value,
                    'Member' => ResidencyStatus::Member->value,
                    'Staff' => ResidencyStatus::Staff->value,
                ],
                'placeholder' => 'Select…',
                'required' => true,
            ])
            ->add('memberCode', TextType::class, [
                'label' => 'Member code',
                'required' => false,
                'help' => 'Leave blank to allocate automatically.',
            ])
            ->add('isPrimary', CheckboxType::class, [
                'label' => 'Mark as primary contact',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => AddMemberInput::class,
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id' => 'add_member_to_household',
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'add_member';
    }
}
