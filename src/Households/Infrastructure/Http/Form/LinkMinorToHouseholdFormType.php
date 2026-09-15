<?php

declare(strict_types=1);

namespace App\Households\Infrastructure\Http\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Symfony Form type backing the Household card's "Link Shared Member"
 * action (LRA-210). `memberId` is never user-typed — it is set
 * programmatically from the member lookup dialog's selection payload — but
 * still goes through a Form so the submission gets CSRF protection like
 * every other mutating endpoint in this context.
 *
 * @extends AbstractType<LinkMinorToHouseholdInput>
 */
final class LinkMinorToHouseholdFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('memberId', HiddenType::class, [
            'required' => true,
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => LinkMinorToHouseholdInput::class,
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id' => 'link_minor_to_household',
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'link_minor_to_household';
    }
}
