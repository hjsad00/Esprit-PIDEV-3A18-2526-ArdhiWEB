<?php

namespace App\Form\Evenement;

use App\Entity\Evenement\Evenement;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
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
            ->add('titre', TextType::class, [
                'label' => 'Titre',
                'attr'  => ['class' => 'form-control', 'placeholder' => 'Ex: Foire Agricole Printemps 2025'],
            ])
            ->add('type', ChoiceType::class, [
                'label'       => 'Type',
                'choices'     => [
                    'Foire'      => 'FOIRE',
                    'Formation'  => 'FORMATION',
                    'Conférence' => 'CONFERENCE',
                    'Atelier'    => 'ATELIER',
                ],
                'placeholder' => '-- Sélectionner --',
                'attr'        => ['class' => 'form-select'],
            ])
            ->add('lieu', TextType::class, [
                'label' => 'Lieu',
                'attr'  => ['class' => 'form-control', 'placeholder' => 'Ex: Tunis, Ariana, Sousse...'],
            ])
            ->add('dateDebut', DateType::class, [
                'label'  => 'Date de début',
                'widget' => 'single_text',
                'html5'  => true,
                'attr'   => ['class' => 'form-control'],
            ])
            ->add('dateFin', DateType::class, [
                'label'  => 'Date de fin',
                'widget' => 'single_text',
                'html5'  => true,
                'attr'   => ['class' => 'form-control'],
            ])
            ->add('nombrePlacesMax', IntegerType::class, [
                'label' => 'Nombre de places',
                'attr'  => ['class' => 'form-control', 'min' => 1, 'placeholder' => 'Ex: 50'],
            ])
            ->add('description', TextareaType::class, [
                'label'    => 'Description',
                'required' => false,
                'attr'     => [
                    'class'       => 'form-control',
                    'rows'        => 5,
                    'placeholder' => 'Décrivez votre événement...',
                ],
            ])
            ->add('imageFile', FileType::class, [
                'label'       => "Image de l'événement",
                'mapped'      => false,
                'required'    => false,
                'attr'        => ['class' => 'form-control'],
                'constraints' => [
                    new Image([
                        'maxSize'            => '5M',
                        'maxSizeMessage'     => "L'image ne doit pas dépasser 5 Mo.",
                        'mimeTypes'          => ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
                        'mimeTypesMessage'   => 'Veuillez télécharger une image JPG, PNG, WEBP ou GIF.',
                    ]),
                ],
                'help' => 'Formats acceptés : JPG, PNG, WEBP, GIF — max 5 Mo.',
            ])
            ->add('organisateur', HiddenType::class)
        ;
        // ── Fields NOT in the form (set automatically) ──────────────────────
        // organisateur → set in controller from $this->getUser()->getNom().' '.$this->getUser()->getPrenom()
        // statut       → managed automatically by EvenementStatusSyncService
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Evenement::class]);
    }
}
