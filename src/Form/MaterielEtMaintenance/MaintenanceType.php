<?php

namespace App\Form\MaterielEtMaintenance;

use App\Entity\MaterielEtMaintenance\Maintenance;
use App\Entity\MaterielEtMaintenance\Materiel;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class MaintenanceType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $userId = $options['user_id'] ?? null;

        $builder
            ->add('materiel', EntityType::class, [
                'class' => Materiel::class,
                'choice_label' => 'nom',
                'label' => 'Matériel',
                'query_builder' => function (EntityRepository $er) use ($userId) {
                    if ($userId !== null) {
                        return $er->createQueryBuilder('m')
                            ->where('m.userId = :user')
                            ->setParameter('user', $userId)
                            ->orderBy('m.nom', 'ASC');
                    }
                    return $er->createQueryBuilder('m')->orderBy('m.nom', 'ASC');
                },
            ])
            ->add('type_maintenance', ChoiceType::class, [
                'label' => 'Type de maintenance',
                'choices' => [
                    'Préventive' => 'preventive',
                    'Corrective' => 'corrective',
                    'Urgente' => 'urgente',
                ],
            ])
            ->add('statut_maintenance', ChoiceType::class, [
                'label' => 'Statut',
                'choices' => [
                    'Planifiée' => 'planifiee',
                    'En cours' => 'en_cours',
                    'Terminée' => 'terminee',
                    'Annulée' => 'annulee',
                ],
            ])
            ->add('date_maintenance', DateType::class, [
                'label' => 'Date de la maintenance',
                'widget' => 'single_text',
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description / Rapport',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Maintenance::class,
            'user_id' => null,
        ]);
    }
}
