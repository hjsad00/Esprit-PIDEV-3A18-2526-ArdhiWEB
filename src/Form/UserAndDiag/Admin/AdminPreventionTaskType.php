<?php

namespace App\Form\UserAndDiag\Admin;

use App\Entity\UserAndDiag\PreventionPlan;
use App\Entity\UserAndDiag\PreventionTask;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AdminPreventionTaskType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('day_offset', IntegerType::class, ['label' => 'Jour (offset)'])
            ->add('task_description', TextType::class, ['label' => 'Description'])
            ->add('status', ChoiceType::class, [
                'label' => 'Statut',
                'choices' => [
                    'Pending' => 'PENDING',
                    'Completed' => 'COMPLETED',
                    'Missed' => 'MISSED',
                ],
                'required' => false,
            ])
            ->add('proof_photo_url', TextType::class, ['label' => 'URL photo preuve', 'required' => false])
            ->add('completed_at', DateTimeType::class, ['label' => 'Complété le', 'widget' => 'single_text', 'required' => false])
            ->add('preventionPlan', EntityType::class, [
                'class' => PreventionPlan::class,
                'choice_label' => 'id',
                'label' => 'Plan de prévention',
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
