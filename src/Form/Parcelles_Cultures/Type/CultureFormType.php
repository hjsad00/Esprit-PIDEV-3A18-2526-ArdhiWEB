<?php

namespace App\Form\Parcelles_Cultures\Type;

use App\DTO\Parcelles_Cultures\CultureDTO;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CultureFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom_culture', TextType::class, [
                'label' => 'Nom de la culture',
                'required' => true
            ])
            ->add('type_culture', ChoiceType::class, [
                'label' => 'Type de culture',
                'choices' => [
                    'Blé' => 'ble',
                    'Orge' => 'orge',
                    'Maïs' => 'mais',
                    'Tomate' => 'tomate',
                    'Oignon' => 'oignon',
                    'Pomme de terre' => 'pomme_de_terre',
                    'Laitue' => 'laitue',
                    'Carotte' => 'carotte',
                    'Autre' => 'autre',
                ],
                'required' => true
            ])
            ->add('saison', ChoiceType::class, [
                'label' => 'Saison',
                'choices' => [
                    'Printemps' => 'printemps',
                    'Été' => 'ete',
                    'Automne' => 'automne',
                    'Hiver' => 'hiver',
                ],
                'required' => true
            ])
            ->add('date_plantation', DateType::class, [
                'label' => 'Date de plantation',
                'widget' => 'single_text',
                'required' => true,
                'format' => 'yyyy-MM-dd'
            ])
            ->add('date_recolte_prevue', DateType::class, [
                'label' => 'Date de récolte prévue',
                'widget' => 'single_text',
                'required' => true,
                'format' => 'yyyy-MM-dd'
            ])
            ->add('surface_utilisee', NumberType::class, [
                'label' => 'Surface utilisée (ha)',
                'scale' => 2,
                'required' => true,
                'attr' => ['min' => 0, 'step' => 0.01]
            ])
            ->add('rendement_estime', NumberType::class, [
                'label' => 'Rendement estimé (kg/ha)',
                'scale' => 2,
                'required' => true,
                'attr' => ['min' => 0.01, 'step' => 0.01]
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => CultureDTO::class,
        ]);
    }
}
