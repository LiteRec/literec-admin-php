<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\Http\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Symfony Form type backing the LRA-87 "Take Inventory" bulk grid.
 *
 * One field — `lines` — holds the per-row inputs. Rows are pre-built
 * by the controller from the {@see \App\Inventory\Application\Query\ListInventory}
 * projection, so the form rejects additions and deletions: an operator
 * cannot inject a new row or hide one by tampering with the markup.
 *
 * @extends AbstractType<TakeInventoryInput>
 */
final class TakeInventoryFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('lines', CollectionType::class, [
                'entry_type' => TakeInventoryLineFormType::class,
                'allow_add' => false,
                'allow_delete' => false,
                'prototype' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => TakeInventoryInput::class,
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id' => 'take_inventory',
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'take_inventory';
    }
}
