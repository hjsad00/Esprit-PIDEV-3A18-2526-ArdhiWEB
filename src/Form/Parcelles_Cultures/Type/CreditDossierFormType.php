<?php

namespace App\Form\Parcelles_Cultures\Type;

use App\DTO\Parcelles_Cultures\CreditDossierDTO;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CreditDossierFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('duree_annees', IntegerType::class, [
                'label' => 'Durée du crédit (années)',
                'required' => true,
                'attr' => ['min' => 1, 'max' => 25]
            ])
            ->add('prixVente', NumberType::class, [
                'label' => 'Prix de vente estimé (DT/kg)',
                'scale' => 2,
                'required' => true,
                'attr' => ['min' => 0, 'step' => 0.01]
            ])
            ->add('coutSemences', NumberType::class, [
                'label' => 'Coût des semences (DT)',
                'scale' => 2,
                'required' => true,
                'attr' => ['min' => 0, 'step' => 0.01]
            ])
            ->add('coutEngrais', NumberType::class, [
                'label' => 'Coût des engrais (DT)',
                'scale' => 2,
                'required' => true,
                'attr' => ['min' => 0, 'step' => 0.01]
            ])
            ->add('coutMainOeuvre', NumberType::class, [
                'label' => 'Coût main d\'œuvre (DT)',
                'scale' => 2,
                'required' => true,
                'attr' => ['min' => 0, 'step' => 0.01]
            ])
            ->add('coutIrrigation', NumberType::class, [
                'label' => 'Coût irrigation (DT)',
                'scale' => 2,
                'required' => true,
                'attr' => ['min' => 0, 'step' => 0.01]
            ])
            ->add('coutAutres', NumberType::class, [
                'label' => 'Autres coûts (DT)',
                'scale' => 2,
                'required' => true,
                'attr' => ['min' => 0, 'step' => 0.01]
            ])
            ->add('score_rentabilite', NumberType::class, [
                'label' => 'Score rentabilité (0-10)',
                'scale' => 2,
                'required' => true,
                'attr' => ['min' => 0, 'max' => 10, 'step' => 0.01]
            ])
            ->add('score_stabilite_climat', NumberType::class, [
                'label' => 'Score stabilité climat (0-10)',
                'scale' => 2,
                'required' => true,
                'attr' => ['min' => 0, 'max' => 10, 'step' => 0.01]
            ])
            ->add('score_diversification', NumberType::class, [
                'label' => 'Score diversification (0-10)',
                'scale' => 2,
                'required' => true,
                'attr' => ['min' => 0, 'max' => 10, 'step' => 0.01]
            ])
            ->add('score_historique', NumberType::class, [
                'label' => 'Score historique (0-10)',
                'scale' => 2,
                'required' => true,
                'attr' => ['min' => 0, 'max' => 10, 'step' => 0.01]
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => CreditDossierDTO::class,
        ]);
    }
}
