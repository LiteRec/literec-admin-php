<?php

declare(strict_types=1);

namespace App\Households\Infrastructure\Http\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

/**
 * Symfony Form type backing the merge-confirmation dialog submission
 * (LRA-208). `duplicateMemberId` arrives as a hidden field set by the
 * confirm dialog (populated from the Member Lookup selection);
 * `acknowledged` is the destructive-action confirmation checkbox.
 *
 * @extends AbstractType<MergeMembersInput>
 */
final class MergeMembersFormType extends AbstractType
{
    private const string UUID_V7_REGEX
        = '/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/';

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('duplicateMemberId', HiddenType::class, [
                'constraints' => [
                    new NotBlank(message: 'Select a duplicate member to merge.'),
                    new Regex(
                        pattern: self::UUID_V7_REGEX,
                        message: 'Select a duplicate member to merge.',
                    ),
                ],
            ])
            ->add('duplicateHouseholdId', HiddenType::class, [
                'constraints' => [
                    new NotBlank(message: 'Select a duplicate member to merge.'),
                    new Regex(
                        pattern: self::UUID_V7_REGEX,
                        message: 'Select a duplicate member to merge.',
                    ),
                ],
            ])
            ->add('acknowledged', CheckboxType::class, [
                'label' => 'I understand this merge cannot be undone.',
                'required' => false,
                'constraints' => [
                    new IsTrue(message: 'Acknowledge that this merge cannot be undone.'),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => MergeMembersInput::class,
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id' => 'merge_members',
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'merge_members';
    }
}
