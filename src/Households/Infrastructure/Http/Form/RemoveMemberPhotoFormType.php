<?php

declare(strict_types=1);

namespace App\Households\Infrastructure\Http\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Symfony Form type backing the Profile card's Remove photo button
 * (LRA-207). See {@see RemoveMemberPhotoInput} for why a fieldless form
 * exists at all.
 *
 * @extends AbstractType<RemoveMemberPhotoInput>
 */
final class RemoveMemberPhotoFormType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => RemoveMemberPhotoInput::class,
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id' => 'remove_member_photo',
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'remove_member_photo';
    }
}
