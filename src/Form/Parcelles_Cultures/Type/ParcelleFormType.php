<?php

namespace App\Form\Parcelles_Cultures\Type;

use App\Entity\Parcelles_Cultures\Parcelle;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\ButtonType;
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
                'attr' => [
                    'min' => 0.01,
                    'step' => 0.01,
                    'class' => 'form-control',
                    'placeholder' => 'Ex: 5.5',
                ]
            ])
            ->add('localisation', TextType::class, [
                'label' => 'Localisation',
                'required' => true,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Cliquez sur la carte ou entrez une adresse',
                    'id' => 'localisation-input',
                ]
            ])
            ->add('latitude', NumberType::class, [
                'label' => 'Latitude',
                'required' => false,
                'scale' => 4,
                'attr' => [
                    'id' => 'latitude-input',
                    'class' => 'form-control',
                    'placeholder' => 'Auto',
                    'readonly' => 'readonly',
                ]
            ])
            ->add('longitude', NumberType::class, [
                'label' => 'Longitude',
                'required' => false,
                'scale' => 4,
                'attr' => [
                    'id' => 'longitude-input',
                    'class' => 'form-control',
                    'placeholder' => 'Auto',
                    'readonly' => 'readonly',
                ]
            ])
            ->add('type_sol', ChoiceType::class, [
                'label' => 'Type de sol',
                'choices' => [
                    'Argileux' => 'Argileux',
                    'Sableux' => 'Sableux',
                    'Limoneux' => 'Limoneux',
                    'Calcaire' => 'Calcaire',
                    'Argilo-sableux' => 'Argilo-sableux',
                    'Argilo-limoneux' => 'Argilo-limoneux',
                    'Tourbeux' => 'Tourbeux',
                ],
                'required' => true,
                'attr' => [
                    'class' => 'form-select',
                    'id' => 'typeSol',
                ]
            ])
            ->add('systeme_irrigation', ChoiceType::class, [
                'label' => 'Système d\'irrigation',
                'choices' => [
                    'Goutte-à-goutte' => 'Goutte-à-goutte',
                    'Aspersion' => 'Aspersion',
                    'Gravitaire' => 'Gravitaire',
                    'Pivot' => 'Pivot',
                    'Micro-aspersion' => 'Micro-aspersion',
                    'Pluvial' => 'Pluvial',
                ],
                'required' => true,
                'attr' => [
                    'class' => 'form-select',
                    'id' => 'systemeIrrigation',
                ]
            ])
            ->add('statut', ChoiceType::class, [
                'label' => 'Statut',
                'choices' => [
                    'Active' => 'active',
                    'En repos' => 'repos',
                ],
                'attr' => [
                    'class' => 'form-select',
                ],
            ])
            ->add('iaRecommend', ButtonType::class, [
                'label' => '🤖 Remplissage IA',
                'attr' => [
                    'class' => 'btn btn-success btn-sm mt-2',
                    'id' => 'ia-recommend-btn',
                    'type' => 'button',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Parcelle::class,
        ]);
    }
}
