<?php

namespace App\Form\UserAndDiag\Admin;

use App\Entity\UserAndDiag\FarmHealthReport;
use App\Entity\UserAndDiag\PreventionPlan;
use App\Entity\UserAndDiag\Vulnerability;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AdminPreventionPlanType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title')
            ->add('problem_summary')
            ->add('steps')
            ->add('timeline_days')
            ->add('estimated_cost')
            ->add('expected_outcome')
            ->add('impact_level')
            ->add('status')
            ->add('start_date')
            ->add('created_at')
            ->add('report', EntityType::class, [
                'class' => FarmHealthReport::class,
                'choice_label' => 'id',
            ])
            ->add('vulnerability', EntityType::class, [
                'class' => Vulnerability::class,
                'choice_label' => 'id',
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
