<?php

declare(strict_types=1);

namespace App\Households\Infrastructure\Http\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Image;

/**
 * Symfony Form type backing the Profile card photo upload (LRA-207).
 *
 * The Image constraint validates via PHP core's getimagesize() only —
 * detectCorrupted is deliberately left off because no gd/imagick
 * extension is installed in this runtime (see the FrankenPHP image),
 * and enabling it would throw on every upload.
 *
 * @extends AbstractType<UploadMemberPhotoInput>
 */
final class UploadMemberPhotoFormType extends AbstractType
{
    private const int MAX_DIMENSION = 4096;

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('photo', FileType::class, [
            'label' => 'Photo',
            'required' => true,
            'attr' => [
                'accept' => 'image/jpeg,image/png,image/webp',
            ],
            'constraints' => [
                new Image(
                    maxSize: '2M',
                    mimeTypes: ['image/jpeg', 'image/png', 'image/webp'],
                    maxWidth: self::MAX_DIMENSION,
                    maxHeight: self::MAX_DIMENSION,
                ),
            ],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => UploadMemberPhotoInput::class,
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id' => 'upload_member_photo',
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'upload_member_photo';
    }
}
