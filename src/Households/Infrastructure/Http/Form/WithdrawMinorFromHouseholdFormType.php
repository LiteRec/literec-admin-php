<?php

declare(strict_types=1);

namespace App\Households\Infrastructure\Http\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Symfony Form type backing the linked-households list's "Unlink" button
 * (LRA-210). See {@see WithdrawMinorFromHouseholdInput} for why a
 * fieldless form exists at all.
 *
 * @extends AbstractType<WithdrawMinorFromHouseholdInput>
 */
final class WithdrawMinorFromHouseholdFormType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => WithdrawMinorFromHouseholdInput::class,
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id' => 'withdraw_minor_from_household',
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'withdraw_minor_from_household';
    }
}
