<?php

namespace App\Form\Evenement;

use App\Entity\Evenement\Evenement;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class EvenementType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('titre', TextType::class, ['label' => 'Titre', 'attr' => ['class' => 'form-control']])
            ->add('type', ChoiceType::class, ['label' => 'Type', 'choices' => ['Foire' => 'FOIRE', 'Formation' => 'FORMATION', 'Conférence' => 'CONFERENCE', 'Atelier' => 'ATELIER'], 'attr' => ['class' => 'form-select'], 'placeholder' => '-- Sélectionner --'])
            ->add('lieu', TextType::class, ['label' => 'Lieu', 'attr' => ['class' => 'form-control']])
            ->add('dateDebut', DateType::class, ['label' => 'Date de début', 'widget' => 'single_text', 'attr' => ['class' => 'form-control']])
            ->add('dateFin', DateType::class, ['label' => 'Date de fin', 'widget' => 'single_text', 'attr' => ['class' => 'form-control']])
            ->add('nombrePlacesMax', IntegerType::class, ['label' => 'Nombre de places', 'attr' => ['class' => 'form-control', 'min' => 1]])
            ->add('organisateur', TextType::class, ['label' => 'Organisateur', 'attr' => ['class' => 'form-control']])
            ->add('description', TextareaType::class, ['label' => 'Description', 'required' => false, 'attr' => ['class' => 'form-control', 'rows' => 5]])
            ->add('statut', ChoiceType::class, ['label' => 'Statut', 'choices' => ['À venir' => 'A_VENIR', 'En cours' => 'EN_COURS', 'Terminé' => 'TERMINE', 'Annulé' => 'ANNULE'], 'attr' => ['class' => 'form-select']]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Evenement::class]);
    }
}
