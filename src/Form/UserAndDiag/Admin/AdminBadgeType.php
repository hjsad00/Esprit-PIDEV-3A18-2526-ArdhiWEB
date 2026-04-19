<?php

namespace App\Form\UserAndDiag\Admin;

use App\Entity\UserAndDiag\Badge;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

class AdminBadgeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, ['label' => 'Nom'])
            ->add('description', TextareaType::class, ['label' => 'Description', 'required' => false])
            ->add('iconFile', FileType::class, [
                'label' => 'Upload Icône/Image',
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new File(['maxSize' => '2M', 'mimeTypes' => ['image/jpeg', 'image/png', 'image/svg+xml', 'image/webp']])
                ],
            ])
            ->add('condition_type', ChoiceType::class, [
                'label' => 'Type Condition',
                'choices' => [
                    'Diagnostic' => 'DIAGNOSTIC',
                    'Points' => 'POINTS',
                    'Healthy Plants' => 'HEALTHY_PLANTS',
                    'Solution' => 'SOLUTION',
                ],
                'required' => false,
            ])
            ->add('threshold', IntegerType::class, ['label' => 'Seuil', 'required' => false])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Badge::class,
        ]);
    }
}
