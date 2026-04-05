<?php

namespace App\Form\UserAndDiag\Admin;

use App\Entity\UserAndDiag\FarmHealthScan;
use App\Entity\UserAndDiag\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AdminFarmHealthScanType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('crop_type', TextType::class, ['label' => 'Type de culture'])
            ->add('planting_date', DateType::class, ['label' => 'Date plantation', 'widget' => 'single_text'])
            ->add('growth_stage', TextType::class, ['label' => 'Phase de croissance'])
            ->add('latitude', NumberType::class, ['label' => 'Latitude', 'required' => false, 'scale' => 6])
            ->add('longitude', NumberType::class, ['label' => 'Longitude', 'required' => false, 'scale' => 6])
            ->add('concerns', TextareaType::class, ['label' => 'Préoccupations', 'required' => false])
            ->add('photo_crops', TextType::class, ['label' => 'Photo cultures', 'required' => false])
            ->add('photo_soil', TextType::class, ['label' => 'Photo sol', 'required' => false])
            ->add('photo_edges', TextType::class, ['label' => 'Photo bordures', 'required' => false])
            ->add('photo_insects', TextType::class, ['label' => 'Photo insectes', 'required' => false])
            ->add('photo_spacing', TextType::class, ['label' => 'Photo espacement', 'required' => false])
            ->add('photo_overview', TextType::class, ['label' => 'Photo vue générale', 'required' => false])
            ->add('scan_date', DateTimeType::class, ['label' => 'Date scan', 'widget' => 'single_text', 'required' => false])
            ->add('status', ChoiceType::class, [
                'label' => 'Statut',
                'choices' => [
                    'Pending' => 'PENDING',
                    'Processing' => 'PROCESSING',
                    'Completed' => 'COMPLETED',
                    'Failed' => 'FAILED',
                ],
                'required' => false,
            ])
            ->add('user', EntityType::class, [
                'class' => User::class,
                'choice_label' => 'email',
                'label' => 'Utilisateur',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => FarmHealthScan::class,
        ]);
    }
}
