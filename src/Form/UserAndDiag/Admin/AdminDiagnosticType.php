<?php

namespace App\Form\UserAndDiag\Admin;

use App\Entity\UserAndDiag\Diagnostic;
use App\Entity\UserAndDiag\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

class AdminDiagnosticType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('date_scan', DateTimeType::class, ['label' => 'Date scan', 'widget' => 'single_text', 'required' => false])
            ->add('imageFile', FileType::class, [
                'label' => 'Télécharger une nouvelle image',
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new File([
                        'maxSize' => '5M',
                        'mimeTypes' => ['image/jpeg', 'image/png', 'image/webp'],
                    ])
                ],
            ])
            ->add('resultat_ia', TextType::class, ['label' => 'Résultat IA', 'required' => false])
            ->add('confiance', NumberType::class, ['label' => 'Confiance', 'required' => false, 'scale' => 2])
            ->add('latitude', NumberType::class, ['label' => 'Latitude', 'required' => false, 'scale' => 6])
            ->add('longitude', NumberType::class, ['label' => 'Longitude', 'required' => false, 'scale' => 6])
            ->add('location_label', TextType::class, ['label' => 'Localisation', 'required' => false])
            ->add('severity', TextType::class, ['label' => 'Sévérité', 'required' => false])
            ->add('user', EntityType::class, [
                'class' => User::class,
                'choice_label' => 'email',
                'label' => 'Utilisateur',
                'required' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Diagnostic::class,
        ]);
    }
}
