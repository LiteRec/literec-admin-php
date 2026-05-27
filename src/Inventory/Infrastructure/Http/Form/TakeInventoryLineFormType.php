<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\Http\Form;

use App\Inventory\Domain\ValueObject\StockAdjustmentReason;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;

/**
 * Symfony Form type for one row of the LRA-87 "Take Inventory" bulk
 * grid. The full grid is composed via {@see TakeInventoryFormType}
 * (a CollectionType of these).
 *
 * The reason ChoiceType is required: false at the form level because
 * a no-variance row legitimately leaves it blank; the controller
 * enforces "reason required when actual != expected" so the validation
 * message can mention the row's listing code.
 *
 * @extends AbstractType<TakeInventoryLineInput>
 */
final class TakeInventoryLineFormType extends AbstractType
{
    public const int MAX_REASON_NOTE_LENGTH = 200;

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('itemId', HiddenType::class, [
                'constraints' => [
                    new NotBlank(),
                ],
            ])
            ->add('listingCode', HiddenType::class, [
                'constraints' => [
                    new NotBlank(),
                ],
            ])
            ->add('expected', HiddenType::class, [
                'constraints' => [
                    new NotNull(),
                ],
            ])
            ->add('actual', IntegerType::class, [
                'label' => 'Counted',
                'required' => true,
                'constraints' => [
                    new NotNull(message: 'Enter the counted quantity.'),
                    new GreaterThanOrEqual(value: 0, message: 'Counted quantity cannot be negative.'),
                ],
            ])
            ->add('reason', ChoiceType::class, [
                'label' => 'Reason',
                'choices' => self::reasonChoices(),
                'placeholder' => '—',
                'required' => false,
            ])
            ->add('reasonNote', TextType::class, [
                'label' => 'Note',
                'required' => false,
                'constraints' => [
                    new Length(max: self::MAX_REASON_NOTE_LENGTH),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => TakeInventoryLineInput::class,
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'take_inventory_line';
    }

    /**
     * @return array<string, string>
     */
    private static function reasonChoices(): array
    {
        $choices = [];
        foreach (StockAdjustmentReason::cases() as $case) {
            $choices[ucfirst(strtolower($case->name))] = $case->value;
        }
        return $choices;
    }
}
