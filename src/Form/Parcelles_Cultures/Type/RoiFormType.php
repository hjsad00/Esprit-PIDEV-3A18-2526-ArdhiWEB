<?php

namespace App\Form\Parcelles_Cultures\Type;

use App\DTO\Parcelles_Cultures\RoiDTO;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\RangeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Positive;
use Symfony\Component\Validator\Constraints\Range;

class RoiFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('parcelle', \Symfony\Bridge\Doctrine\Form\Type\EntityType::class, [
                'class' => \App\Entity\Parcelles_Cultures\Parcelle::class,
                'label' => 'Parcelle à analyser',
                'choices' => $options['user_parcelles'],
                'choice_label' => function (\App\Entity\Parcelles_Cultures\Parcelle $p) {
                    return sprintf("#%d - %s (%s ha)", $p->getId(), $p->getLocalisation(), $p->getSurface());
                },
                'choice_attr' => function (\App\Entity\Parcelles_Cultures\Parcelle $p) {
                    return ['data-surface' => $p->getSurface()];
                },
                'placeholder' => 'Choisir une parcelle',
                'required' => true,
                'attr' => ['class' => 'form-select'],
                'constraints' => [new NotBlank()]
            ])
            ->add('surface_ha', NumberType::class, [
                'label' => 'Surface exploitable (ha)',
                'required' => true,
                'attr' => ['min' => 0.01, 'step' => 0.01],
                'constraints' => [new NotBlank(), new Positive()]
            ])
            ->add('rendement', NumberType::class, [
                'label' => 'Rendement estimé (kg/ha)',
                'required' => true,
                'attr' => ['min' => 0.01, 'step' => 0.01],
                'constraints' => [new NotBlank(), new Positive()]
            ])
            ->add('prix_vente', NumberType::class, [
                'label' => 'Prix de vente estimé (DT)',
                'required' => true,
                'attr' => ['min' => 0.01, 'step' => 0.01],
                'constraints' => [new NotBlank(), new Positive()]
            ])
            ->add('duree_pret', RangeType::class, [
                'label' => 'Durée du prêt souhaitée (années)',
                'required' => true,
                'attr' => [
                    'min' => 1, 
                    'max' => 25, 
                    'step' => 1,
                    'class' => 'form-range'
                ],
                'constraints' => [
                    new NotBlank(),
                    new Range(['min' => 1, 'max' => 25])
                ]
            ])
            ->add('jours_canicule', IntegerType::class, [
                'label' => 'Jours de canicule',
                'required' => true,
                'attr' => ['min' => 0],
                'constraints' => [new Range(['min' => 0, 'max' => 100])]
            ])
            ->add('jours_excespluie', IntegerType::class, [
                'label' => 'Jours d\'excès de pluie',
                'required' => true,
                'attr' => ['min' => 0],
                'constraints' => [new Range(['min' => 0, 'max' => 365])]
            ])
            ->add('jours_gel', IntegerType::class, [
                'label' => 'Jours de gel',
                'required' => true,
                'attr' => ['min' => 0],
                'constraints' => [new Range(['min' => 0, 'max' => 100])]
            ])
            ->add('cout_semences', NumberType::class, [
                'label' => 'Coût des semences (DT)',
                'required' => true,
                'attr' => ['min' => 0, 'step' => 0.01],
                'constraints' => [new Range(['min' => 0])]
            ])
            ->add('cout_engrais', NumberType::class, [
                'label' => 'Coût des engrais (DT)',
                'required' => true,
                'attr' => ['min' => 0, 'step' => 0.01],
                'constraints' => [new Range(['min' => 0])]
            ])
            ->add('cout_main_oeuvre', NumberType::class, [
                'label' => 'Coût de la main d\'œuvre (DT)',
                'required' => true,
                'attr' => ['min' => 0, 'step' => 0.01],
                'constraints' => [new Range(['min' => 0])]
            ])
            ->add('cout_irrigation', NumberType::class, [
                'label' => 'Coût de l\'irrigation (DT)',
                'required' => true,
                'attr' => ['min' => 0, 'step' => 0.01],
                'constraints' => [new Range(['min' => 0])]
            ])
            ->add('cout_autres', NumberType::class, [
                'label' => 'Autres coûts (DT)',
                'required' => true,
                'attr' => ['min' => 0, 'step' => 0.01],
                'constraints' => [new Range(['min' => 0])]
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => RoiDTO::class,
            'user_parcelles' => [],
        ]);
    }
}
