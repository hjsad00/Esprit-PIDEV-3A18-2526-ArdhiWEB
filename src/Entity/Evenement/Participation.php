<?php

namespace App\Entity\Evenement;

use App\Repository\Evenement\ParticipationRepository;
use App\Entity\UserAndDiag\User;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ParticipationRepository::class)]
#[ORM\Table(name: 'participation')]
class Participation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Evenement::class, inversedBy: 'participations')]
    #[ORM\JoinColumn(name: 'id_evenement', referencedColumnName: 'id', nullable: false)]
    private ?Evenement $evenement = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'id_utilisateur', referencedColumnName: 'id', nullable: false)]
    private ?User $utilisateur = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $dateInscription = null;

    #[ORM\Column(type: Types::STRING, length: 50)]
    private ?string $statut = 'CONFIRME';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $commentaire = null;

    #[ORM\Column(type: Types::INTEGER)]
    #[Assert\Range(min: 1, max: 10, notInRangeMessage: 'Le nombre de personnes doit �tre entre {{ min }} et {{ max }}.')]
    private ?int $nombrePersonnes = 1;

    #[ORM\Column(type: Types::INTEGER)]
    #[Assert\Range(min: 0, max: 5, notInRangeMessage: 'La note doit �tre entre {{ min }} et {{ max }}.')]
    private ?int $note = 0;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $avis = null;

    #[ORM\Column(type: Types::BOOLEAN)]
    private ?bool $attestationEnvoyee = false;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $rappelJ3Envoye = false;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $rappelJ1Envoye = false;

    #[ORM\Column(type: Types::STRING, length: 64, nullable: true, unique: true)]
    private ?string $qrCodeToken = null;

    public function __construct()
    {
        $this->dateInscription = new \DateTime();
        $this->statut = 'CONFIRME';
        $this->nombrePersonnes = 1;
        $this->note = 0;
        $this->attestationEnvoyee = false;
        $this->rappelJ3Envoye = false;
        $this->rappelJ1Envoye = false;
        $this->qrCodeToken = bin2hex(random_bytes(16));
    }

    public function getId(): ?int { return $this->id; }

    public function getEvenement(): ?Evenement { return $this->evenement; }
    public function setEvenement(?Evenement $evenement): static { $this->evenement = $evenement; return $this; }

    public function getUtilisateur(): ?User { return $this->utilisateur; }
    public function setUtilisateur(?User $utilisateur): static { $this->utilisateur = $utilisateur; return $this; }

    public function getDateInscription(): ?\DateTimeInterface { return $this->dateInscription; }
    public function setDateInscription(\DateTimeInterface $dateInscription): static { $this->dateInscription = $dateInscription; return $this; }

    public function getStatut(): ?string { return $this->statut; }
    public function setStatut(string $statut): static { $this->statut = $statut; return $this; }

    public function getCommentaire(): ?string { return $this->commentaire; }
    public function setCommentaire(?string $commentaire): static { $this->commentaire = $commentaire; return $this; }

    public function getNombrePersonnes(): ?int { return $this->nombrePersonnes; }
    public function setNombrePersonnes(int $nombrePersonnes): static { $this->nombrePersonnes = $nombrePersonnes; return $this; }

    public function getNote(): ?int { return $this->note; }
    public function setNote(int $note): static { $this->note = $note; return $this; }

    public function getAvis(): ?string { return $this->avis; }
    public function setAvis(?string $avis): static { $this->avis = $avis; return $this; }

    public function isAttestationEnvoyee(): ?bool { return $this->attestationEnvoyee; }
    public function setAttestationEnvoyee(bool $attestationEnvoyee): static { $this->attestationEnvoyee = $attestationEnvoyee; return $this; }

    public function isRappelJ3Envoye(): bool { return $this->rappelJ3Envoye; }
    public function setRappelJ3Envoye(bool $rappelJ3Envoye): static { $this->rappelJ3Envoye = $rappelJ3Envoye; return $this; }

    public function isRappelJ1Envoye(): bool { return $this->rappelJ1Envoye; }
    public function setRappelJ1Envoye(bool $rappelJ1Envoye): static { $this->rappelJ1Envoye = $rappelJ1Envoye; return $this; }

    public function getQrCodeToken(): ?string { return $this->qrCodeToken; }
    public function setQrCodeToken(?string $qrCodeToken): static { $this->qrCodeToken = $qrCodeToken; return $this; }

    /**
     * Helper for QR Code and Mailer services.
     */
    public function getNomComplet(): string
    {
        $user = $this->getUtilisateur();
        if (!$user) return 'Participant #' . $this->id;
        return trim(($user->getNom() ?? '') . ' ' . ($user->getPrenom() ?? ''))
            ?: $user->getEmail();
    }
}
