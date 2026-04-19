<?php

namespace App\Form\UserAndDiag\Admin;

use App\Entity\UserAndDiag\CommunityComment;
use App\Entity\UserAndDiag\CommunityPost;
use App\Entity\UserAndDiag\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

class AdminCommunityPostType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, ['label' => 'Titre'])
            ->add('description', TextareaType::class, ['label' => 'Description', 'required' => false])
            ->add('imageFile', FileType::class, [
                'label' => 'Remplacer l\'image (Upload)',
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new File([
                        'maxSize' => '5M',
                        'mimeTypes' => ['image/jpeg', 'image/png', 'image/webp'],
                        'mimeTypesMessage' => 'Veuillez uploader une image valide (JPG, PNG, WebP).',
                    ])
                ],
            ])
            ->add('created_at', DateTimeType::class, ['label' => 'Date création', 'widget' => 'single_text', 'required' => false])
            ->add('likes', IntegerType::class, ['label' => 'Likes', 'required' => false])
            ->add('dislikes', IntegerType::class, ['label' => 'Dislikes', 'required' => false])
            ->add('is_resolved', CheckboxType::class, ['label' => 'Résolu', 'required' => false])
            ->add('user', EntityType::class, [
                'class' => User::class,
                'choice_label' => 'email',
                'label' => 'Utilisateur',
            ])
            ->add('solutionComment', EntityType::class, [
                'class' => CommunityComment::class,
                'choice_label' => 'id',
                'label' => 'Commentaire solution',
                'required' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => CommunityPost::class,
        ]);
    }
}
