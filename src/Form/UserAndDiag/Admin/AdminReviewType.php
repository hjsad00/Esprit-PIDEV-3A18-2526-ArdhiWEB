<?php

namespace App\Form\UserAndDiag\Admin;

use App\Entity\UserAndDiag\Diagnostic;
use App\Entity\UserAndDiag\PreventionPlan;
use App\Entity\UserAndDiag\Review;
use App\Entity\UserAndDiag\TreatmentPlan;
use App\Entity\UserAndDiag\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AdminReviewType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('review_type', ChoiceType::class, [
                'label' => 'Type de revue',
                'choices' => [
                    'Diagnosis' => 'DIAGNOSIS',
                    'Progress' => 'PROGRESS',
                    'Prevention' => 'PREVENTION',
                ],
            ])
            ->add('status', ChoiceType::class, [
                'label' => 'Statut',
                'choices' => [
                    'Pending' => 'PENDING',
                    'In Progress' => 'IN_PROGRESS',
                    'Completed' => 'COMPLETED',
                ],
                'required' => false,
            ])
            ->add('photo_url', TextType::class, ['label' => 'URL Photo', 'required' => false])
            ->add('ai_analysis', TextareaType::class, ['label' => 'Analyse IA', 'required' => false])
            ->add('expert_notes', TextareaType::class, ['label' => 'Notes expert', 'required' => false])
            ->add('expert_verdict', ChoiceType::class, [
                'label' => 'Verdict expert',
                'choices' => [
                    'Continue' => 'CONTINUE',
                    'Healed' => 'HEALED',
                    'Worsened' => 'WORSENED',
                ],
                'required' => false,
            ])
            ->add('expert_disease_name', TextType::class, ['label' => 'Maladie (expert)', 'required' => false])
            ->add('created_at', DateTimeType::class, ['label' => 'Date création', 'widget' => 'single_text', 'required' => false])
            ->add('updated_at', DateTimeType::class, ['label' => 'Date modification', 'widget' => 'single_text', 'required' => false])
            ->add('farmer_response', ChoiceType::class, [
                'label' => 'Réponse agriculteur',
                'choices' => [
                    'Accepted' => 'ACCEPTED',
                    'Rejected' => 'REJECTED',
                    'Acknowledged' => 'ACKNOWLEDGED',
                ],
                'required' => false,
            ])
            ->add('ai_proposed_plan', TextareaType::class, ['label' => 'Plan proposé IA', 'required' => false])
            ->add('diagnostic', EntityType::class, [
                'class' => Diagnostic::class,
                'choice_label' => 'id',
                'label' => 'Diagnostic',
            ])
            ->add('treatment_plan', EntityType::class, [
                'class' => TreatmentPlan::class,
                'choice_label' => 'id',
                'label' => 'Plan de traitement',
                'required' => false,
            ])
            ->add('expert', EntityType::class, [
                'class' => User::class,
                'choice_label' => 'email',
                'label' => 'Expert',
                'required' => false,
            ])
            ->add('prevention_plan', EntityType::class, [
                'class' => PreventionPlan::class,
                'choice_label' => 'id',
                'label' => 'Plan de prévention',
                'required' => false,
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
