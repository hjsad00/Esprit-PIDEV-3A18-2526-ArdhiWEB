<?php

namespace App\Form\Parcelles_Cultures\Type;

use App\DTO\Parcelles_Cultures\RoiDTO;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class RoiFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('surface_ha', NumberType::class, [
                'label' => 'Surface exploitable (ha)',
                'required' => true,
                'attr' => ['min' => 0.01, 'step' => 0.01]
            ])
            ->add('rendement', NumberType::class, [
                'label' => 'Rendement estimé (kg/ha)',
                'required' => true,
                'attr' => ['min' => 0.01, 'step' => 0.01]
            ])
            ->add('prix_vente', NumberType::class, [
                'label' => 'Prix de vente estimé (DT)',
                'required' => true,
                'attr' => ['min' => 0.01, 'step' => 0.01]
            ])
            ->add('jours_canicule', IntegerType::class, [
                'label' => 'Jours de canicule',
                'required' => true,
                'attr' => ['min' => 0]
            ])
            ->add('jours_excespluie', IntegerType::class, [
                'label' => 'Jours d\'excès de pluie',
                'required' => true,
                'attr' => ['min' => 0]
            ])
            ->add('jours_gel', IntegerType::class, [
                'label' => 'Jours de gel',
                'required' => true,
                'attr' => ['min' => 0]
            ])
            ->add('cout_semences', NumberType::class, [
                'label' => 'Coût des semences (DT)',
                'required' => true,
                'attr' => ['min' => 0, 'step' => 0.01]
            ])
            ->add('cout_engrais', NumberType::class, [
                'label' => 'Coût des engrais (DT)',
                'required' => true,
                'attr' => ['min' => 0, 'step' => 0.01]
            ])
            ->add('cout_main_oeuvre', NumberType::class, [
                'label' => 'Coût de la main d\'œuvre (DT)',
                'required' => true,
                'attr' => ['min' => 0, 'step' => 0.01]
            ])
            ->add('cout_irrigation', NumberType::class, [
                'label' => 'Coût de l\'irrigation (DT)',
                'required' => true,
                'attr' => ['min' => 0, 'step' => 0.01]
            ])
            ->add('cout_autres', NumberType::class, [
                'label' => 'Autres coûts (DT)',
                'required' => true,
                'attr' => ['min' => 0, 'step' => 0.01]
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => RoiDTO::class,
        ]);
    }
}
