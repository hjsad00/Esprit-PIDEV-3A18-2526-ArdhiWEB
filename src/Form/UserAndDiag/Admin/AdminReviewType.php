<?php

namespace App\Form\UserAndDiag\Admin;

use App\Entity\UserAndDiag\Diagnostic;
use App\Entity\UserAndDiag\PreventionPlan;
use App\Entity\UserAndDiag\Review;
use App\Entity\UserAndDiag\TreatmentPlan;
use App\Entity\UserAndDiag\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AdminReviewType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('review_type')
            ->add('status')
            ->add('photo_url')
            ->add('ai_analysis')
            ->add('expert_notes')
            ->add('expert_verdict')
            ->add('expert_disease_name')
            ->add('created_at')
            ->add('updated_at')
            ->add('farmer_response')
            ->add('ai_proposed_plan')
            ->add('diagnostic', EntityType::class, [
                'class' => Diagnostic::class,
                'choice_label' => 'id',
            ])
            ->add('treatment_plan', EntityType::class, [
                'class' => TreatmentPlan::class,
                'choice_label' => 'id',
            ])
            ->add('expert', EntityType::class, [
                'class' => User::class,
                'choice_label' => 'id',
            ])
            ->add('prevention_plan', EntityType::class, [
                'class' => PreventionPlan::class,
                'choice_label' => 'id',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Review::class,
        ]);
    }
}
