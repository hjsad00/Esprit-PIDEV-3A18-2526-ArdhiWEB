<?php

namespace App\Form\MaterielEtMaintenance;

use App\Entity\MaterielEtMaintenance\Reclamation;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ReclamationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('description', TextareaType::class, [
                'label' => 'Explication du retard de maintenance',
                'attr' => [
                    'rows' => 6,
                    'placeholder' => '',
                    'class' => 'form-control'
                ],
            ])
            ->add('urgence', ChoiceType::class, [
                'label' => "Niveau d'urgence",
                'choices' => [
                    'Normale' => 'normale',
                    '🔴 Urgente' => 'urgente',
                ],
                'attr' => ['class' => 'form-select'],
                'expanded' => false,
                'multiple' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Reclamation::class,
        ]);
    }
}
