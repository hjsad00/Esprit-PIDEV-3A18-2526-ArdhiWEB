<?php

namespace App\Form\UserAndDiag\Admin;

use App\Entity\UserAndDiag\FarmHealthReport;
use App\Entity\UserAndDiag\PreventionPlan;
use App\Entity\UserAndDiag\Vulnerability;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AdminPreventionPlanType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, ['label' => 'Titre'])
            ->add('problem_summary', TextareaType::class, ['label' => 'Résumé du problème', 'required' => false])
            ->add('steps', TextareaType::class, ['label' => 'Étapes'])
            ->add('timeline_days', IntegerType::class, ['label' => 'Durée (jours)', 'required' => false])
            ->add('estimated_cost', NumberType::class, ['label' => 'Coût estimé', 'required' => false, 'scale' => 2])
            ->add('expected_outcome', TextareaType::class, ['label' => 'Résultat attendu', 'required' => false])
            ->add('impact_level', ChoiceType::class, [
                'label' => 'Niveau d\'impact',
                'choices' => [
                    'High' => 'HIGH',
                    'Medium' => 'MEDIUM',
                    'Low' => 'LOW',
                ],
                'required' => false,
            ])
            ->add('status', ChoiceType::class, [
                'label' => 'Statut',
                'choices' => [
                    'Active' => 'ACTIVE',
                    'Completed' => 'COMPLETED',
                    'Abandoned' => 'ABANDONED',
                ],
                'required' => false,
            ])
            ->add('start_date', DateType::class, ['label' => 'Date début', 'widget' => 'single_text', 'required' => false])
            ->add('created_at', DateTimeType::class, ['label' => 'Date création', 'widget' => 'single_text', 'required' => false])
            ->add('report', EntityType::class, [
                'class' => FarmHealthReport::class,
                'choice_label' => 'id',
                'label' => 'Rapport',
            ])
            ->add('vulnerability', EntityType::class, [
                'class' => Vulnerability::class,
                'choice_label' => 'id',
                'label' => 'Vulnérabilité',
                'required' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => PreventionPlan::class,
        ]);
    }
}
