<?php

namespace App\Form\UserAndDiag\Admin;

use App\Entity\UserAndDiag\FarmHealthReport;
use App\Entity\UserAndDiag\FarmHealthScan;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AdminFarmHealthReportType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('health_score', IntegerType::class, ['label' => 'Score santé', 'required' => false])
            ->add('biodiversity_score', IntegerType::class, ['label' => 'Score biodiversité', 'required' => false])
            ->add('llava_analysis', TextareaType::class, ['label' => 'Analyse LLaVA', 'required' => false])
            ->add('generated_at', DateTimeType::class, ['label' => 'Généré le', 'widget' => 'single_text', 'required' => false])
            ->add('scan', EntityType::class, [
                'class' => FarmHealthScan::class,
                'choice_label' => 'id',
                'label' => 'Scan',
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
