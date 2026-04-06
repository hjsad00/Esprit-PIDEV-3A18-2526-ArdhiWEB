<?php

namespace App\Form\MaterielEtMaintenance;

use App\Entity\MaterielEtMaintenance\Maintenance;
use App\Entity\MaterielEtMaintenance\Materiel;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;
use Doctrine\ORM\EntityRepository;

class MaintenanceType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $userId = $options['user_id'] ?? null;

        $builder
            ->add('materiel', EntityType::class, [
                'class' => Materiel::class,
                'label' => 'Matériel concerné',
                'required' => false,
                'choice_label' => function (Materiel $m) {
                    return $m->getNom() . ' (' . $m->getType() . ')';
                },
                'placeholder' => '-- Sélectionner un matériel --',
                'attr' => ['class' => 'form-select'],
                'query_builder' => function (EntityRepository $er) use ($userId) {
                    $qb = $er->createQueryBuilder('m');
                    if ($userId) {
                        $qb->where('m.userId = :uid')->setParameter('uid', $userId);
                    }
                    return $qb->orderBy('m.nom', 'ASC');
                },
                'constraints' => [
                    new Assert\NotNull(['message' => 'Le matériel est obligatoire.']),
                ],
            ])
            ->add('type_maintenance', ChoiceType::class, [
                'label' => 'Type de maintenance',
                'required' => false,
                'choices' => [
                    'Préventive' => 'preventive',
                    'Corrective' => 'corrective',
                    'Urgente' => 'urgente',
                ],
                'placeholder' => '-- Sélectionner le type --',
                'attr' => ['class' => 'form-select'],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Le type de maintenance est obligatoire.']),
                    new Assert\Choice([
                        'choices' => ['preventive', 'corrective', 'urgente'],
                        'message' => 'Type invalide.',
                    ]),
                ],
            ])
            ->add('date_maintenance', DateType::class, [
                'label' => 'Date de l\'intervention',
                'widget' => 'single_text',
                'required' => false,
                'attr' => ['class' => 'form-control'],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description (optionnel)',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 4,
                    'placeholder' => 'Décrivez les opérations à effectuer...',
                ],
                'constraints' => [
                    new Assert\Length([
                        'max' => 1000,
                        'maxMessage' => 'Maximum 1000 caractères.',
                    ]),
                ],
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
