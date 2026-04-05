<?php

namespace App\Form\UserAndDiag\Admin;

use App\Entity\UserAndDiag\Abonnement;
use App\Entity\UserAndDiag\Offre;
use App\Entity\UserAndDiag\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AdminAbonnementType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('type', TextType::class, ['label' => 'Type', 'required' => false])
            ->add('prix', NumberType::class, ['label' => 'Prix'])
            ->add('date_debut', DateType::class, ['label' => 'Date début', 'widget' => 'single_text', 'required' => false])
            ->add('date_fin', DateType::class, ['label' => 'Date fin', 'widget' => 'single_text', 'required' => false])
            ->add('statut', TextType::class, ['label' => 'Statut', 'required' => false])
            ->add('user', EntityType::class, [
                'class' => User::class,
                'choice_label' => 'email',
                'label' => 'Utilisateur',
                'required' => false,
            ])
            ->add('offre', EntityType::class, [
                'class' => Offre::class,
                'choice_label' => 'nom',
                'label' => 'Offre',
                'required' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Abonnement::class,
        ]);
    }
}
