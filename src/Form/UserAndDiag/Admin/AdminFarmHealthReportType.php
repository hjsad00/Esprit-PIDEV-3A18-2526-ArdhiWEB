<?php

namespace App\Form\UserAndDiag\Admin;

use App\Entity\UserAndDiag\FarmHealthReport;
use App\Entity\UserAndDiag\FarmHealthScan;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AdminFarmHealthReportType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('health_score')
            ->add('biodiversity_score')
            ->add('llava_analysis')
            ->add('generated_at')
            ->add('scan', EntityType::class, [
                'class' => FarmHealthScan::class,
                'choice_label' => 'id',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => FarmHealthReport::class,
        ]);
    }
}
