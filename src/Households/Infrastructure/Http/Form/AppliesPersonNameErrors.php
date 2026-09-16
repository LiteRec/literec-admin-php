<?php

declare(strict_types=1);

namespace App\Households\Infrastructure\Http\Form;

use App\Households\Domain\Exception\InvalidPersonName;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;

/**
 * Maps an {@see InvalidPersonName} onto the offending firstName/lastName
 * field when one is identifiable, otherwise onto the form root. Extracted
 * from {@see \App\Households\Infrastructure\Http\Controller\MemberDetailController}
 * (LRA-209) so {@see \App\Households\Infrastructure\Http\Controller\SplitMemberController}
 * does not duplicate the same mapping — the SonarCloud new-code
 * duplication gate is 3%. The Profile card edit flow that originally
 * carried this trait moved to
 * {@see \App\Households\Infrastructure\Http\Controller\MemberProfileCardController}
 * (LRA-235), which is now its other consumer.
 */
trait AppliesPersonNameErrors
{
    /**
     * The exception message is the only signal available — by convention
     * it mentions "first name" or "last name" — so the mapping stays
     * heuristic and safely falls back to a form-level error.
     *
     * @template TData
     *
     * @param FormInterface<TData> $form
     */
    private function applyNameErrorToForm(FormInterface $form, InvalidPersonName $exception): void
    {
        $message = $exception->getMessage();
        $lower = strtolower($message);

        if (str_contains($lower, 'first name') && $form->has('firstName')) {
            $form->get('firstName')->addError(new FormError($message));

            return;
        }

        if (str_contains($lower, 'last name') && $form->has('lastName')) {
            $form->get('lastName')->addError(new FormError($message));

            return;
        }

        $form->addError(new FormError($message));
    }
}
