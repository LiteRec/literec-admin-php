<?php

declare(strict_types=1);

namespace App\Users\Infrastructure\Http\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Symfony Form type backing the forced "set a new password" page (LRA-213).
 *
 * A single RepeatedType field asks for the new password twice; the minimum
 * length (8) matches app:create-user's MINIMUM_PASSWORD_LENGTH so the two
 * password-setting paths enforce the same rule.
 *
 * @extends AbstractType<EstablishPasswordInput>
 */
final class EstablishPasswordFormType extends AbstractType
{
    private const int MINIMUM_PASSWORD_LENGTH = 8;

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('newPassword', RepeatedType::class, [
            'type' => PasswordType::class,
            'invalid_message' => 'The password fields must match.',
            'first_options' => ['label' => 'New password'],
            'second_options' => ['label' => 'Confirm new password'],
            'constraints' => [
                new NotBlank(),
                new Length(min: self::MINIMUM_PASSWORD_LENGTH),
            ],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => EstablishPasswordInput::class,
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id' => 'establish_password',
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'establish_password';
    }
}
