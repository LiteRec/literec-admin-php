<?php

declare(strict_types=1);

namespace App\Households\Infrastructure\Http\Form;

use App\Households\Domain\ValueObject\ResidencyStatus;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Symfony Form type backing the Residency sub-card change form (LRA-44).
 *
 * Surface matches the inputs of
 * {@see \App\Households\Application\Command\ChangeMemberResidency}: the
 * target status enum value, an effective-from date (yyyy-MM-dd), and an
 * optional free-text reason recorded on the resulting domain event.
 *
 * @extends AbstractType<ChangeMemberResidencyInput>
 */
final class ChangeMemberResidencyFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
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
            ->add('effectiveFromIso', DateType::class, [
                'label' => 'Effective from',
                'widget' => 'single_text',
                'html5' => true,
                'input' => 'string',
                'format' => 'yyyy-MM-dd',
                'required' => true,
            ])
            ->add('reason', TextType::class, [
                'label' => 'Reason (optional)',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ChangeMemberResidencyInput::class,
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id' => 'change_member_residency',
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'change_member_residency';
    }
}
