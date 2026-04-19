<?php
namespace App\Form\UserAndDiag\Admin;

use App\Entity\UserAndDiag\CommunityReport;
use App\Entity\UserAndDiag\User;
use App\Entity\UserAndDiag\CommunityPost;
use App\Entity\UserAndDiag\CommunityComment;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AdminCommunityReportType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('reporter', EntityType::class, [
                'class' => User::class,
                'choice_label' => 'email',
                'label' => 'Reporter',
                'attr' => ['class' => 'form-select']
            ])
            ->add('post', EntityType::class, [
                'class' => CommunityPost::class,
                'choice_label' => 'title',
                'required' => false,
                'label' => 'Post (Optionnel)',
                'attr' => ['class' => 'form-select']
            ])
            ->add('comment', EntityType::class, [
                'class' => CommunityComment::class,
                'choice_label' => 'content',
                'required' => false,
                'label' => 'Commentaire (Optionnel)',
                'attr' => ['class' => 'form-select']
            ])
            ->add('reason', TextType::class, [
                'label' => 'Raison',
                'attr' => ['class' => 'form-control']
            ])
            ->add('is_resolved', CheckboxType::class, [
                'label' => 'Résolu ?',
                'required' => false,
                'attr' => ['class' => 'form-check-input']
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => CommunityReport::class,
        ]);
    }
}
