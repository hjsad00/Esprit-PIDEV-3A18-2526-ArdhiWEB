<?php

namespace App\Form\UserAndDiag\Admin;

use App\Entity\UserAndDiag\Diagnostic;
use App\Entity\UserAndDiag\TreatmentPlan;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AdminTreatmentPlanType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('start_date', DateTimeType::class, ['label' => 'Date début', 'widget' => 'single_text', 'required' => false])
            ->add('status', ChoiceType::class, [
                'label' => 'Statut',
                'choices' => [
                    'Active' => 'ACTIVE',
                    'Completed' => 'COMPLETED',
                    'Abandoned' => 'ABANDONED',
                ],
                'required' => false,
            ])
            ->add('diagnostic', EntityType::class, [
                'class' => Diagnostic::class,
                'choice_label' => 'id',
                'label' => 'Diagnostic',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => TreatmentPlan::class,
        ]);
    }
}
