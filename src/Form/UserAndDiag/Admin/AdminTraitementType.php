<?php

namespace App\Form\UserAndDiag\Admin;

use App\Entity\UserAndDiag\Diagnostic;
use App\Entity\UserAndDiag\Traitement;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AdminTraitementType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('solution_nom', TextType::class, ['label' => 'Nom solution'])
            ->add('description_detaillee', TextareaType::class, ['label' => 'Description détaillée', 'required' => false])
            ->add('type_traitement', ChoiceType::class, [
                'label' => 'Type de traitement',
                'choices' => [
                    'Fongicide' => 'FONGICIDE',
                    'Herbicide' => 'HERBICIDE',
                    'Insecticide' => 'INSECTICIDE',
                    'Bactéricide' => 'BACTERICIDE',
                    'Nématicide' => 'NEMATICIDE',
                    'Virucide' => 'VIRUCIDE',
                    'Nutriment' => 'NUTRIMENT',
                    'Régulateur croissance' => 'REGULATEUR_CROISSANCE',
                    'Autre' => 'AUTRE',
                ],
                'required' => false,
            ])
            ->add('duree_recommandee', TextType::class, ['label' => 'Durée recommandée', 'required' => false])
            ->add('diagnostic', EntityType::class, [
                'class' => Diagnostic::class,
                'choice_label' => 'id',
                'label' => 'Diagnostic',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Traitement::class,
        ]);
    }
}
