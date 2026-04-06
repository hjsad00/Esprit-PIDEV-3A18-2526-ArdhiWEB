<?php

namespace App\Form\Parcelles_Cultures\Type;

use App\Entity\Parcelles_Cultures\Parcelle;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ParcelleFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('surface', NumberType::class, [
                'label' => 'Surface (hectares)',
                'required' => true,
                'scale' => 2,
                'attr' => ['min' => 0.01, 'step' => 0.01]
            ])
            ->add('localisation', TextType::class, [
                'label' => 'Localisation',
                'required' => true
            ])
            ->add('type_sol', ChoiceType::class, [
                'label' => 'Type de sol',
                'choices' => [
                    'Argileux' => 'argileux',
                    'Calcaire' => 'calcaire',
                    'Limoneux' => 'limoneux',
                    'Sableux' => 'sableux',
                ],
                'required' => true
            ])
            ->add('systeme_irrigation', ChoiceType::class, [
                'label' => 'Système d\'irrigation',
                'choices' => [
                    'Goutte à goutte' => 'goutte_a_goutte',
                    'Aspersion' => 'aspersion',
                    'Submersion' => 'submersion',
                    'Pluvial' => 'pluvial',
                ],
                'required' => true
            ])
            ->add('latitude', NumberType::class, [
                'label' => 'Latitude',
                'required' => false,
                'scale' => 6,
                'attr' => ['placeholder' => 'ex: 36.806389']
            ])
            ->add('longitude', NumberType::class, [
                'label' => 'Longitude',
                'required' => false,
                'scale' => 6,
                'attr' => ['placeholder' => 'ex: 10.182778']
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Parcelle::class,
        ]);
    }
}
