<?php

namespace App\Form\UserAndDiag\Admin;

use App\Entity\UserAndDiag\TreatmentPlan;
use App\Entity\UserAndDiag\TreatmentTask;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AdminTreatmentTaskType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('treatmentPlan', EntityType::class, [
                'class' => TreatmentPlan::class,
                'choice_label' => 'id',
                'label' => 'Plan de traitement',
            ])
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
            ->add('tech_x', NumberType::class, ['label' => 'Tech X', 'required' => false, 'scale' => 2])
            ->add('tech_y', NumberType::class, ['label' => 'Tech Y', 'required' => false, 'scale' => 2])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => TreatmentTask::class,
        ]);
    }
}
