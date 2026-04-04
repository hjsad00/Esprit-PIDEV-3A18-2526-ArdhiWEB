<?php

namespace App\Form\UserAndDiag\Admin;

use App\Entity\UserAndDiag\Offre;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AdminOffreType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom')
            ->add('description')
            ->add('prix_mensuel')
            ->add('avantages')
            ->add('couleur_primaire')
            ->add('couleur_secondaire')
            ->add('est_active')
            ->add('est_recommandee')
            ->add('date_creation')
            ->add('diagnostics_par_heure')
            ->add('acces_traitement')
            ->add('acces_plan_traitement')
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Offre::class,
        ]);
    }
}
