<?php

namespace App\Form\Parcelles_Cultures\Type;

use App\DTO\Parcelles_Cultures\IrrigationDTO;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class IrrigationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('date', DateType::class, [
                'label' => 'Date',
                'widget' => 'single_text',
                'required' => true,
                'format' => 'yyyy-MM-dd'
            ])
            ->add('temperature_min', NumberType::class, [
                'label' => 'Température min (°C)',
                'scale' => 2,
                'required' => true,
                'attr' => ['step' => 0.01]
            ])
            ->add('temperature_max', NumberType::class, [
                'label' => 'Température max (°C)',
                'scale' => 2,
                'required' => true,
                'attr' => ['step' => 0.01]
            ])
            ->add('precipitations', NumberType::class, [
                'label' => 'Précipitations (mm)',
                'scale' => 2,
                'required' => true,
                'attr' => ['min' => 0, 'step' => 0.01]
            ])
            ->add('humidite', NumberType::class, [
                'label' => 'Humidité (%)',
                'scale' => 2,
                'required' => true,
                'attr' => ['min' => 0, 'max' => 100, 'step' => 0.01]
            ])
            ->add('kc', NumberType::class, [
                'label' => 'Coefficient Kc',
                'scale' => 2,
                'required' => true,
                'attr' => ['step' => 0.01]
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => IrrigationDTO::class,
        ]);
    }
}
