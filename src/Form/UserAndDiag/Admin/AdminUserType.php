<?php

namespace App\Form\UserAndDiag\Admin;

use App\Entity\UserAndDiag\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;

class AdminUserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $isEdit = $options['is_edit'];

        $builder
            ->add('nom', TextType::class, ['label' => 'Nom'])
            ->add('prenom', TextType::class, ['label' => 'Prénom'])
            ->add('email', TextType::class, [
                'label' => 'Email',
                'attr' => ['autocomplete' => 'off']
            ])
            ->add('role', ChoiceType::class, [
                'label' => 'Rôle',
                'choices' => [
                    'Admin' => 'ADMIN',
                    'Agriculteur' => 'AGRICULTEUR',
                    'Client' => 'CLIENT',
                    'Agronome' => 'AGRONOME',
                ],
            ])
            ->add('phone', TextType::class, ['label' => 'Téléphone', 'required' => false])
            ->add('location', TextType::class, ['label' => 'Localisation', 'required' => false])
            ->add('points', IntegerType::class, ['label' => 'Points', 'required' => false])
            ->add('points_fidelite', NumberType::class, ['label' => 'Points Fidélité', 'required' => false, 'scale' => 2])
            ->add('level', IntegerType::class, ['label' => 'Niveau', 'required' => false])
            ->add('two_factor_enabled', \Symfony\Component\Form\Extension\Core\Type\CheckboxType::class, ['label' => '2FA Activé', 'required' => false])
            ->add('plainPassword', PasswordType::class, [
                'label' => 'Mot de passe' . ($isEdit ? ' (laisser vide pour ne pas changer)' : ''),
                'mapped' => false,
                'required' => !$isEdit,
                'attr' => [
                    'placeholder' => $isEdit ? 'Laisser vide pour garder le mot de passe actuel' : '',
                    'autocomplete' => 'new-password'
                ],
                'constraints' => [
                    new Length([
                        'min' => 6,
                        'minMessage' => 'Le mot de passe doit contenir au moins 6 caractères.',
                        'max' => 4096,
                    ]),
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'is_edit' => false,
        ]);
    }
}
