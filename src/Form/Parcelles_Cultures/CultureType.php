<?php

namespace App\Form\Parcelles_Cultures;

use App\Entity\Parcelles_Cultures\Culture;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;

class CultureType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('nom_culture', TextType::class, [
                'label' => 'Nom de la culture',
                'attr' => ['placeholder' => 'ex: Maïs, Tomate, etc.']
            ])
            ->add('type_culture', ChoiceType::class, [
                'label' => 'Type de culture',
                'choices' => [
                    'Légume' => 'Légume',
                    'Céréale' => 'Céréale',
                    'Fruit' => 'Fruit',
                    'Fourrage' => 'Fourrage',
                    'Légumineuse' => 'Légumineuse'
                ]
            ])
            ->add('saison', ChoiceType::class, [
                'label' => 'Saison',
                'choices' => [
                    'Courte saison 1' => 'Courte saison 1',
                    'Courte saison 2' => 'Courte saison 2',
                    'Grande saison' => 'Grande saison',
                    'Saison sèche' => 'Saison sèche'
                ]
            ])
            ->add('date_plantation', DateType::class, [
                'label' => 'Date de plantation',
                'widget' => 'single_text'
            ])
            ->add('date_recolte_prevue', DateType::class, [
                'label' => 'Date de récolte prévue',
                'widget' => 'single_text'
            ])
            ->add('etat_culture', ChoiceType::class, [
                'label' => 'État de la culture',
                'choices' => [
                    'Plantée' => 'Plantée',
                    'En croissance' => 'En croissance',
                    'Mature' => 'Mature',
                    'Récoltée' => 'Récoltée'
                ]
            ])
            ->add('surface_utilisee', NumberType::class, [
                'label' => 'Surface utilisée (hectares)',
                'attr' => ['step' => '0.01', 'min' => '0.01']
            ])
            ->add('rendement_estime', NumberType::class, [
                'label' => 'Rendement estimé (kg/ha)',
                'attr' => ['step' => '0.1', 'min' => '0.1']
            ]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Culture::class
        ]);
    }
}
