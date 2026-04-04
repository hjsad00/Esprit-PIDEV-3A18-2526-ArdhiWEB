<?php

namespace App\Form\UserAndDiag\Admin;

use App\Entity\UserAndDiag\Diagnostic;
use App\Entity\UserAndDiag\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AdminDiagnosticType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('date_scan')
            ->add('image_scannee')
            ->add('resultat_ia')
            ->add('confiance')
            ->add('latitude')
            ->add('longitude')
            ->add('location_label')
            ->add('severity')
            ->add('user', EntityType::class, [
                'class' => User::class,
                'choice_label' => 'id',
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
