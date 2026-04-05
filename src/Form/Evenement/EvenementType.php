<?php

namespace App\Form\Evenement;

use App\Entity\Evenement\Evenement;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Image;

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
            ->add('imageFile', FileType::class, [
                'label' => 'Image de l’événement',
                'mapped' => false,
                'required' => false,
                'attr' => ['class' => 'form-control'],
                'constraints' => [
                    new Image([
                        'maxSize' => '5M',
                        'maxSizeMessage' => 'L’image ne doit pas dépasser 5 Mo.',
                        'mimeTypes' => ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
                        'mimeTypesMessage' => 'Veuillez télécharger une image JPG, PNG, WEBP ou GIF.',
                    ]),
                ],
                'help' => 'Téléchargez une image depuis votre ordinateur (max 5 Mo).',
            ])
            ->add('description', TextareaType::class, ['label' => 'Description', 'required' => false, 'attr' => ['class' => 'form-control', 'rows' => 5]])
            ->add('statut', ChoiceType::class, ['label' => 'Statut', 'choices' => ['À venir' => 'A_VENIR', 'En cours' => 'EN_COURS', 'Terminée' => 'TERMINE', 'Annulé' => 'ANNULE'], 'attr' => ['class' => 'form-select']]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Evenement::class]);
    }
}
