<?php

declare(strict_types=1);

namespace App\Households\Infrastructure\Http\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\EqualTo;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Symfony Form type backing the Anonymize Member confirmation dialog
 * (LRA-212). Confirmation is server-enforced, not just client-side: the
 * `expected_confirmation` option (the member's current full name, supplied
 * by the controller) is checked with an {@see EqualTo} constraint on the
 * server regardless of what the dialog's Alpine scope did in the browser.
 *
 * A Form is used — rather than the bare `hx-post` pattern of the purchase
 * order lifecycle buttons — because this needs the Form's built-in CSRF
 * check (`token_id: submit`, stateless).
 *
 * @extends AbstractType<AnonymizeMemberInput>
 */
final class AnonymizeMemberFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('confirmation', TextType::class, [
                'label' => "Type the member's full name to confirm",
                'required' => true,
                'constraints' => [
                    new NotBlank(),
                    new EqualTo(
                        value: $options['expected_confirmation'],
                        message: "Type the member's full name exactly as shown to confirm.",
                    ),
                ],
            ])
            ->add('acknowledged', CheckboxType::class, [
                'label' => 'I understand this cannot be undone.',
                'required' => false,
                'constraints' => [
                    new IsTrue(message: 'You must acknowledge that this action cannot be undone.'),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => AnonymizeMemberInput::class,
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id' => 'submit',
        ]);
        $resolver->setRequired('expected_confirmation');
        $resolver->setAllowedTypes('expected_confirmation', 'string');
    }

    public function getBlockPrefix(): string
    {
        return 'anonymize_member';
    }
}
