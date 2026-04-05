<?php

namespace App\Form\Evenement;

use App\Entity\Evenement\Participation;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class AvisType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('note', ChoiceType::class, [
                'label'   => 'Note',
                'choices' => [
                    '⭐ 1 — Mauvais'          => 1,
                    '⭐⭐ 2 — Passable'         => 2,
                    '⭐⭐⭐ 3 — Bien'           => 3,
                    '⭐⭐⭐⭐ 4 — Très bien'    => 4,
                    '⭐⭐⭐⭐⭐ 5 — Excellent'  => 5,
                ],
                'placeholder' => '-- Sélectionner une note --',
                'attr'        => ['class' => 'form-select form-select-lg'],
                'label_attr'  => ['class' => 'form-label fw-bold'],
                'constraints' => [new Assert\NotBlank(message: 'Veuillez choisir une note.')],
            ])
            ->add('avis', TextareaType::class, [
                'label'      => 'Votre avis',
                'required'   => false,
                'attr'       => [
                    'class'       => 'form-control',
                    'rows'        => 4,
                    'placeholder' => 'Partagez votre expérience sur cet événement...',
                ],
                'label_attr' => ['class' => 'form-label fw-bold'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Participation::class]);
    }
}
