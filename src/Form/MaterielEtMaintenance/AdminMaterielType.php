<?php

namespace App\Form\MaterielEtMaintenance;

use App\Entity\MaterielEtMaintenance\Materiel;
use App\Entity\UserAndDiag\User;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Constraints\File;

class AdminMaterielType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('userEntity', EntityType::class, [
                'class' => User::class,
                'choice_label' => function (User $user) {
                    return $user->getPrenom() . ' ' . $user->getNom() . ' (' . $user->getEmail() . ')';
                },
                'label' => 'Agriculteur propriétaire',
                'placeholder' => '-- Sélectionner un utilisateur --',
                'mapped' => false,
                'required' => true,
                'query_builder' => function (EntityRepository $er) {
                    return $er->createQueryBuilder('u')
                        ->where("u.role != 'ADMIN'")
                        ->orderBy('u.nom', 'ASC');
                },
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Veuillez sélectionner un agriculteur.']),
                ],
            ])
            ->add('nom', TextType::class, [
                'label' => 'Nom du matériel',
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Le nom du matériel est obligatoire.']),
                    new Assert\Length([
                        'min' => 3,
                        'max' => 50,
                        'minMessage' => 'Le nom doit faire au moins {{ limit }} caractères.',
                        'maxMessage' => 'Le nom ne peut pas dépasser {{ limit }} caractères.',
                    ]),
                ],
            ])
            ->add('type', ChoiceType::class, [
                'label' => 'Type',
                'choices' => [
                    'Tracteur' => 'Tracteur',
                    'Moissonneuse' => 'Moissonneuse',
                    'Semoir' => 'Semoir',
                    'Pulvérisateur' => 'Pulvérisateur',
                    'Charrue' => 'Charrue',
                    'Herse' => 'Herse',
                    'Autre' => 'Autre',
                ],
                'required' => false,
                'placeholder' => '-- Type --',
            ])
            ->add('etat', ChoiceType::class, [
                'label' => 'État',
                'choices' => [
                    'Neuf' => 'Neuf',
                    'Bon' => 'Bon',
                    'Moyen' => 'Moyen',
                    'En panne' => 'En panne',
                    'En maintenance' => 'En maintenance',
                ],
                'required' => false,
                'placeholder' => '-- État --',
            ])
            ->add('dateAchat', DateType::class, [
                'label' => "Date d'achat",
                'widget' => 'single_text',
                'required' => false,
            ])
            ->add('image', FileType::class, [
                'label' => 'Image (JPG, PNG)',
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new File([
                        'maxSize' => '5M',
                        'mimeTypes' => ['image/jpeg', 'image/png', 'image/webp'],
                        'mimeTypesMessage' => 'Format invalide (JPEG, PNG, WEBP requis)',
                    ]),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Materiel::class,
        ]);
    }
}
