<?php

declare(strict_types=1);

namespace App\Households\Infrastructure\Http\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
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
    use BuildsHouseholdFormFields;

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->addPrimaryMemberFields($builder);

        $builder->add('isPrimary', CheckboxType::class, [
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
