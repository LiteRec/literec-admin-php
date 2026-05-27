<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\Http\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

/**
 * Inner-row form type for each component inside the LRA-89 Combo
 * modal. Rendered both as a server-side row for existing components and
 * as a `data-prototype` template that the Alpine "Add component"
 * controller clones on demand.
 *
 * @extends AbstractType<ComboComponentInput>
 */
final class ComboComponentFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('componentItemId', TextType::class, [
                'label' => 'Component item id',
                'required' => true,
                'constraints' => [
                    new NotBlank(),
                    new Regex(
                        pattern: ComboFormType::UUID_V7_PATTERN,
                        message: 'Component item id must be a UUID v7.',
                    ),
                ],
            ])
            ->add('quantityPerCombo', IntegerType::class, [
                'label' => 'Quantity per combo',
                'required' => true,
                'constraints' => [
                    new NotBlank(),
                    new GreaterThanOrEqual(1),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ComboComponentInput::class,
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'inventory_combo_component';
    }
}
