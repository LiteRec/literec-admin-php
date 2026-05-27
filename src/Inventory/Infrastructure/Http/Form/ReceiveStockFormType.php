<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\Http\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\GreaterThan;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Symfony Form type backing the LRA-87 "Receive Stock" HTMX dialog
 * (templates/inventory/receive/_dialog.html.twig).
 *
 * The facility list is injected by the controller via the
 * {@see self::FACILITY_CHOICES_OPTION} option; the placeholder enforces
 * that the operator picks a facility before submitting. Cost is captured
 * as either per-unit or total cents — the XOR constraint between them
 * is enforced by the controller (which has the quantity available to
 * derive whichever value the operator did not provide).
 *
 * @extends AbstractType<ReceiveStockInput>
 */
final class ReceiveStockFormType extends AbstractType
{
    public const string FACILITY_CHOICES_OPTION = 'facility_choices';

    public const int MAX_COMMENT_LENGTH = 500;

    private const string LABEL_FACILITY = 'Facility';

    private const string LABEL_QUANTITY = 'Quantity (units)';

    private const string LABEL_COST_PER_UNIT = 'Cost per unit (cents)';

    private const string LABEL_TOTAL_COST = 'Total cost (cents)';

    private const string LABEL_COMMENT = 'Comment (optional)';

    private const string PLACEHOLDER_FACILITY = 'Select a facility';

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var array<string, string> $facilityChoices */
        $facilityChoices = $options[self::FACILITY_CHOICES_OPTION];

        $builder
            ->add('facilityCode', ChoiceType::class, [
                'label' => self::LABEL_FACILITY,
                'choices' => $facilityChoices,
                'placeholder' => self::PLACEHOLDER_FACILITY,
                'required' => true,
                'constraints' => [
                    new NotBlank(message: 'Choose a facility.'),
                ],
            ])
            ->add('quantityUnits', IntegerType::class, [
                'label' => self::LABEL_QUANTITY,
                'required' => true,
                'constraints' => [
                    new NotBlank(message: 'Enter a quantity.'),
                    new GreaterThan(value: 0, message: 'Quantity must be at least 1.'),
                ],
            ])
            ->add('costPerUnitCents', IntegerType::class, [
                'label' => self::LABEL_COST_PER_UNIT,
                'required' => false,
                'constraints' => [
                    new GreaterThanOrEqual(value: 0, message: 'Cost cannot be negative.'),
                ],
            ])
            ->add('totalCostCents', IntegerType::class, [
                'label' => self::LABEL_TOTAL_COST,
                'required' => false,
                'constraints' => [
                    new GreaterThanOrEqual(value: 0, message: 'Cost cannot be negative.'),
                ],
            ])
            ->add('comment', TextareaType::class, [
                'label' => self::LABEL_COMMENT,
                'required' => false,
                'constraints' => [
                    new Length(max: self::MAX_COMMENT_LENGTH),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ReceiveStockInput::class,
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id' => 'receive_stock',
        ]);
        $resolver->setRequired(self::FACILITY_CHOICES_OPTION);
        $resolver->setAllowedTypes(self::FACILITY_CHOICES_OPTION, 'array');
    }

    public function getBlockPrefix(): string
    {
        return 'receive_stock';
    }
}
