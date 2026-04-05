<?php

namespace App\Form\UserAndDiag\Admin;

use App\Entity\UserAndDiag\CommunityComment;
use App\Entity\UserAndDiag\CommunityPost;
use App\Entity\UserAndDiag\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AdminCommunityCommentType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('content', TextareaType::class, ['label' => 'Contenu'])
            ->add('created_at', DateTimeType::class, ['label' => 'Date création', 'widget' => 'single_text', 'required' => false])
            ->add('likes', IntegerType::class, ['label' => 'Likes', 'required' => false])
            ->add('dislikes', IntegerType::class, ['label' => 'Dislikes', 'required' => false])
            ->add('is_solution', CheckboxType::class, ['label' => 'Solution', 'required' => false])
            ->add('post', EntityType::class, [
                'class' => CommunityPost::class,
                'choice_label' => 'title',
                'label' => 'Post',
            ])
            ->add('user', EntityType::class, [
                'class' => User::class,
                'choice_label' => 'email',
                'label' => 'Utilisateur',
            ])
            ->add('parentComment', EntityType::class, [
                'class' => CommunityComment::class,
                'choice_label' => 'id',
                'label' => 'Commentaire parent',
                'required' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => CommunityComment::class,
        ]);
    }
}
