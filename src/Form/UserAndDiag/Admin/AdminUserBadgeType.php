<?php

namespace App\Form\UserAndDiag\Admin;

use App\Entity\UserAndDiag\Badge;
use App\Entity\UserAndDiag\User;
use App\Entity\UserAndDiag\UserBadge;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AdminUserBadgeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('user', EntityType::class, [
                'class' => User::class,
                'choice_label' => 'email',
                'label' => 'Utilisateur',
            ])
            ->add('badge', EntityType::class, [
                'class' => Badge::class,
                'choice_label' => 'name',
                'label' => 'Badge',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => UserBadge::class,
        ]);
    }
}
