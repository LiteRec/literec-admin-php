<?php

declare(strict_types=1);

namespace App\Households\Infrastructure\Http\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Symfony Form type backing the Deactivate Member confirmation dialog
 * (LRA-211).
 *
 * The reason is required: a deactivation staff cannot explain is a support
 * trap the next time someone asks why a member disappeared from the
 * default Users list. Length is capped at 255 to match the
 * `deactivated_reason` column in {@see \App\Households\Domain\HouseholdMember}'s
 * Doctrine mapping.
 *
 * @extends AbstractType<DeactivateMemberInput>
 */
final class DeactivateMemberFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('reason', TextareaType::class, [
            'label' => 'Reason',
            'required' => true,
            'constraints' => [
                new NotBlank(),
                new Length(max: 255),
            ],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => DeactivateMemberInput::class,
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id' => 'deactivate_member',
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'deactivate_member';
    }
}
