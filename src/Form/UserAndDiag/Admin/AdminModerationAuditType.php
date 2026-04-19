<?php
namespace App\Form\UserAndDiag\Admin;

use App\Entity\UserAndDiag\ModerationAudit;
use App\Entity\UserAndDiag\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AdminModerationAuditType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('moderator', EntityType::class, [
                'class' => User::class,
                'choice_label' => 'email',
                'label' => 'Modérateur',
                'attr' => ['class' => 'form-select']
            ])
            ->add('targetUser', EntityType::class, [
                'class' => User::class,
                'choice_label' => 'email',
                'label' => 'Utilisateur Cible',
                'attr' => ['class' => 'form-select']
            ])
            ->add('action', TextType::class, [
                'label' => 'Action'
            ])
            ->add('reason', TextType::class, [
                'label' => 'Raison',
                'required' => false
            ])
            ->add('related_post_id', IntegerType::class, [
                'required' => false
            ])
            ->add('related_comment_id', IntegerType::class, [
                'required' => false
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ModerationAudit::class,
        ]);
    }
}
