<?php
namespace App\Form\UserAndDiag\Admin;

use App\Entity\UserAndDiag\UserBlock;
use App\Entity\UserAndDiag\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AdminUserBlockType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('blocker', EntityType::class, [
                'class' => User::class,
                'choice_label' => 'email',
                'label' => 'Bloqueur',
                'attr' => ['class' => 'form-select']
            ])
            ->add('blocked', EntityType::class, [
                'class' => User::class,
                'choice_label' => 'email',
                'label' => 'Bloqué',
                'attr' => ['class' => 'form-select']
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => UserBlock::class,
        ]);
    }
}
