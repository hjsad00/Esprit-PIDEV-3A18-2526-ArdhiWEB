<?php
namespace App\Form\UserAndDiag\Admin;

use App\Entity\UserAndDiag\DiagNotification;
use App\Entity\UserAndDiag\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AdminDiagNotificationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('user', EntityType::class, [
                'class' => User::class,
                'choice_label' => 'email',
                'label' => 'Utilisateur',
                'attr' => ['class' => 'form-select']
            ])
            ->add('type', TextType::class, [
                'label' => 'Type de Notification',
                'attr' => ['class' => 'form-control']
            ])
            ->add('message', TextareaType::class, [
                'label' => 'Message',
                'attr' => ['class' => 'form-control', 'rows' => 3]
            ])
            ->add('isRead', CheckboxType::class, [
                'label' => 'Lu ?',
                'required' => false,
                'attr' => ['class' => 'form-check-input']
            ])
            ->add('relatedEntityId', IntegerType::class, [
                'required' => false,
                'label' => 'ID Entité Associée',
                'attr' => ['class' => 'form-control']
            ])
            ->add('relatedEntityType', TextType::class, [
                'required' => false,
                'label' => 'Type Entité Associée',
                'attr' => ['class' => 'form-control']
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => DiagNotification::class,
        ]);
    }
}
