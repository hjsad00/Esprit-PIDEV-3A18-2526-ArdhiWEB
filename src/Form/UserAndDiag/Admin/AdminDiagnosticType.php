<?php

namespace App\Form\UserAndDiag\Admin;

use App\Entity\UserAndDiag\Diagnostic;
use App\Entity\UserAndDiag\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AdminDiagnosticType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('date_scan', DateTimeType::class, ['label' => 'Date scan', 'widget' => 'single_text', 'required' => false])
            ->add('image_scannee', TextType::class, ['label' => 'Image scannée', 'required' => false])
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
