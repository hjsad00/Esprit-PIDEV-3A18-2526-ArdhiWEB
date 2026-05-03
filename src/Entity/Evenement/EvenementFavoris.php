<?php

namespace App\Entity\Evenement;

use App\Repository\Evenement\EvenementFavorisRepository;
use App\Entity\UserAndDiag\User;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EvenementFavorisRepository::class)]
#[ORM\Table(name: 'evenement_favoris')]
#[ORM\UniqueConstraint(name: 'unique_user_event', columns: ['id_utilisateur', 'id_evenement'])]
class EvenementFavoris
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Evenement::class, inversedBy: 'favoris')]
    #[ORM\JoinColumn(name: 'id_evenement', referencedColumnName: 'id', nullable: false)]
    private ?Evenement $evenement = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'id_utilisateur', referencedColumnName: 'id', nullable: false)]
    private ?User $utilisateur = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $dateAjout = null;

    public function __construct()
    {
        if (array_key_exists('__PHPSTAN_ENTITY_ID_HINT', $_SERVER)) {
            $this->id = 0;
        }
        $this->dateAjout = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }

    public function getEvenement(): ?Evenement { return $this->evenement; }
    public function setEvenement(?Evenement $evenement): static { $this->evenement = $evenement; return $this; }

    public function getUtilisateur(): ?User { return $this->utilisateur; }
    public function setUtilisateur(?User $utilisateur): static { $this->utilisateur = $utilisateur; return $this; }

    public function getDateAjout(): ?\DateTimeInterface { return $this->dateAjout; }
    public function setDateAjout(\DateTimeInterface $dateAjout): static { $this->dateAjout = $dateAjout; return $this; }
}
