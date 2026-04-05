<?php

namespace App\Form\UserAndDiag\Admin;

use App\Entity\UserAndDiag\CommunityComment;
use App\Entity\UserAndDiag\CommunityLike;
use App\Entity\UserAndDiag\CommunityPost;
use App\Entity\UserAndDiag\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AdminCommunityLikeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('vote_type', ChoiceType::class, [
                'label' => 'Type de vote',
                'choices' => [
                    'Like' => 'LIKE',
                    'Dislike' => 'DISLIKE',
                ],
            ])
            ->add('created_at', DateTimeType::class, ['label' => 'Date création', 'widget' => 'single_text', 'required' => false])
            ->add('user', EntityType::class, [
                'class' => User::class,
                'choice_label' => 'email',
                'label' => 'Utilisateur',
            ])
            ->add('post', EntityType::class, [
                'class' => CommunityPost::class,
                'choice_label' => 'title',
                'label' => 'Post',
                'required' => false,
            ])
            ->add('comment', EntityType::class, [
                'class' => CommunityComment::class,
                'choice_label' => 'id',
                'label' => 'Commentaire',
                'required' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => CommunityLike::class,
        ]);
    }
}
