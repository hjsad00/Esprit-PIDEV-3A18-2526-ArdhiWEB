<?php

namespace App\Form\Parcelles_Cultures;

use App\Entity\Parcelles_Cultures\Parcelle;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class ParceleType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('surface', NumberType::class, [
                'label' => 'Surface (hectares)',
                'attr' => ['step' => '0.01', 'min' => '0.01']
            ])
            ->add('localisation', TextType::class, [
                'label' => 'Localisation',
                'attr' => ['placeholder' => 'Région, commune, etc.']
            ])
            ->add('type_sol', ChoiceType::class, [
                'label' => 'Type de sol',
                'choices' => [
                    'Argile' => 'Argile',
                    'Sable' => 'Sable',
                    'Limon' => 'Limon',
                    'Tourbe' => 'Tourbe',
                    'Calcaire' => 'Calcaire'
                ]
            ])
            ->add('systeme_irrigation', ChoiceType::class, [
                'label' => 'Système d\'irrigation',
                'choices' => [
                    'Goutte à goutte' => 'Goutte à goutte',
                    'Aspersion' => 'Aspersion',
                    'Rainage' => 'Rainage',
                    'Manuel' => 'Manuel'
                ]
            ])
            ->add('statut', ChoiceType::class, [
                'label' => 'Statut',
                'choices' => [
                    'Active' => 'Active',
                    'Inactive' => 'Inactive',
                    'En repos' => 'En repos'
                ]
            ])
            ->add('latitude', NumberType::class, [
                'label' => 'Latitude',
                'attr' => ['step' => '0.00001']
            ])
            ->add('longitude', NumberType::class, [
                'label' => 'Longitude',
                'attr' => ['step' => '0.00001']
            ]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Parcelle::class
        ]);
    }
}
