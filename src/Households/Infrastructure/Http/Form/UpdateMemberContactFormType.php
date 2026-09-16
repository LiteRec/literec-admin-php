<?php

declare(strict_types=1);

namespace App\Households\Infrastructure\Http\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Symfony Form type backing the Contact sub-card edit form (LRA-204).
 *
 * Surface is the pair of contact channels owned by
 * {@see \App\Households\Domain\MemberInHousehold::updateContact()}: email and
 * phone. Both are optional — an empty submitted value maps to `null`
 * (Symfony's default `empty_data` for {@see \Symfony\Component\Form\Extension\Core\Type\EmailType}
 * and {@see TelType}, both of which extend `TextType`), which the command
 * handler treats as "clear this channel".
 *
 * @extends AbstractType<UpdateMemberContactInput>
 */
final class UpdateMemberContactFormType extends AbstractType
{
    use BuildsHouseholdFormFields;

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->addEmailField($builder, false);
        $builder->add('phone', TelType::class, [
            'label' => 'Phone',
            'required' => false,
            'attr' => ['autocomplete' => 'tel'],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => UpdateMemberContactInput::class,
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id' => 'update_member_contact',
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'update_member_contact';
    }
}
