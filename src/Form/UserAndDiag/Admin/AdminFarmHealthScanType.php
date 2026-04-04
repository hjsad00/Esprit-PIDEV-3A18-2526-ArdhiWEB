<?php

namespace App\Form\UserAndDiag\Admin;

use App\Entity\UserAndDiag\FarmHealthScan;
use App\Entity\UserAndDiag\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AdminFarmHealthScanType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('crop_type')
            ->add('planting_date')
            ->add('growth_stage')
            ->add('latitude')
            ->add('longitude')
            ->add('concerns')
            ->add('photo_crops')
            ->add('photo_soil')
            ->add('photo_edges')
            ->add('photo_insects')
            ->add('photo_spacing')
            ->add('photo_overview')
            ->add('scan_date')
            ->add('status')
            ->add('user', EntityType::class, [
                'class' => User::class,
                'choice_label' => 'id',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => FarmHealthScan::class,
        ]);
    }
}
