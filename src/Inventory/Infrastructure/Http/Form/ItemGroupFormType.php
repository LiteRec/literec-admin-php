<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\Http\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

/**
 * Symfony Form type backing the LRA-89 "Create Group" / "Edit Group"
 * HTMX dialog.
 *
 * Domain validation happens inside the value-object constructors
 * (ItemGroupName, PosColor, FacilityCode, FacilityScope) when the
 * controller maps the input into the command DTOs; the constraints
 * here exist only to short-circuit obvious garbage at the HTTP
 * boundary before the bus is dispatched.
 *
 * @extends AbstractType<ItemGroupFormInput>
 */
final class ItemGroupFormType extends AbstractType
{
    public const string POS_COLOR_PATTERN = '/^#[0-9a-fA-F]{6}$/';

    public const string FACILITY_CODE_MAX_LENGTH_MESSAGE = 'Facility code may be at most 16 characters.';

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Group name',
                'required' => true,
                'constraints' => [
                    new NotBlank(),
                    new Length(max: 80),
                ],
            ])
            ->add('colorHex', TextType::class, [
                'label' => 'Color',
                'required' => true,
                'constraints' => [
                    new NotBlank(),
                    new Regex(
                        pattern: self::POS_COLOR_PATTERN,
                        message: 'Color must be a #RRGGBB hex value.',
                    ),
                ],
            ])
            ->add('scope', ChoiceType::class, [
                'label' => 'Scope',
                'required' => true,
                'choices' => [
                    'All facilities' => ItemGroupFormInput::SCOPE_ALL,
                    'Specific facility' => ItemGroupFormInput::SCOPE_FACILITY,
                ],
            ])
            ->add('facilityCode', TextType::class, [
                'label' => 'Facility code (required when scope = facility)',
                'required' => false,
                'constraints' => [
                    new Length(max: 16, maxMessage: self::FACILITY_CODE_MAX_LENGTH_MESSAGE),
                ],
                'empty_data' => null,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ItemGroupFormInput::class,
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id' => 'inventory_item_group',
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'inventory_item_group';
    }
}
