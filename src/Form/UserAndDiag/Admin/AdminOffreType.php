<?php

namespace App\Form\UserAndDiag\Admin;

use App\Entity\UserAndDiag\Offre;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AdminOffreType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, ['label' => 'Nom'])
            ->add('description', TextType::class, ['label' => 'Description', 'required' => false])
            ->add('prix_mensuel', NumberType::class, ['label' => 'Prix mensuel', 'scale' => 2])
            ->add('avantages', TextareaType::class, ['label' => 'Avantages', 'required' => false])
            ->add('couleur_primaire', TextType::class, ['label' => 'Couleur primaire', 'required' => false])
            ->add('couleur_secondaire', TextType::class, ['label' => 'Couleur secondaire', 'required' => false])
            ->add('est_active', CheckboxType::class, ['label' => 'Active', 'required' => false])
            ->add('est_recommandee', CheckboxType::class, ['label' => 'Recommandée', 'required' => false])
            ->add('date_creation', DateTimeType::class, ['label' => 'Date création', 'widget' => 'single_text', 'required' => false])
            ->add('diagnostics_par_heure', IntegerType::class, ['label' => 'Diagnostics/heure', 'required' => false])
            ->add('acces_traitement', CheckboxType::class, ['label' => 'Accès traitement', 'required' => false])
            ->add('acces_plan_traitement', CheckboxType::class, ['label' => 'Accès plan traitement', 'required' => false])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Offre::class,
        ]);
    }
}
