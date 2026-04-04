<?php

namespace App\Form\UserAndDiag\Admin;

use App\Entity\UserAndDiag\PreventionPlan;
use App\Entity\UserAndDiag\PreventionTask;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AdminPreventionTaskType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('day_offset')
            ->add('task_description')
            ->add('status')
            ->add('proof_photo_url')
            ->add('completed_at')
            ->add('preventionPlan', EntityType::class, [
                'class' => PreventionPlan::class,
                'choice_label' => 'id',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => PreventionTask::class,
        ]);
    }
}
