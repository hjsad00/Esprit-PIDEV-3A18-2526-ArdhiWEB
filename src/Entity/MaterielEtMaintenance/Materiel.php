<?php

namespace App\Entity\MaterielEtMaintenance;

use App\Repository\MaterielEtMaintenance\MaterielRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: MaterielRepository::class)]
#[ORM\Table(name: 'materiel')]
class Materiel
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_materiel', type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'user_id', type: 'integer')]
    private ?int $userId = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le nom du matériel est obligatoire.')]
    private ?string $nom = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $dateAchat = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $frequenceMaintenanceMois = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $derniereMaintenance = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $dateProchaineMaintenance = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $type = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $etat = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $image = null;

    #[ORM\OneToMany(mappedBy: 'materiel', targetEntity: Maintenance::class, orphanRemoval: true)]
    private Collection $maintenances;

    public function __construct()
    {
        $this->maintenances = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUserId(): ?int
    {
        return $this->userId;
    }

    public function setUserId(int $userId): self
    {
        $this->userId = $userId;

        return $this;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(?string $nom): self
    {
        $this->nom = $nom;

        return $this;
    }

    public function getDateAchat(): ?\DateTimeInterface
    {
        return $this->dateAchat;
    }

    public function setDateAchat(?\DateTimeInterface $dateAchat): self
    {
        $this->dateAchat = $dateAchat;

        return $this;
    }

    public function getFrequenceMaintenanceMois(): ?int
    {
        return $this->frequenceMaintenanceMois;
    }

    public function setFrequenceMaintenanceMois(?int $frequenceMaintenanceMois): self
    {
        $this->frequenceMaintenanceMois = $frequenceMaintenanceMois;

        return $this;
    }

    public function getDerniereMaintenance(): ?\DateTimeInterface
    {
        return $this->derniereMaintenance;
    }

    public function setDerniereMaintenance(?\DateTimeInterface $derniereMaintenance): self
    {
        $this->derniereMaintenance = $derniereMaintenance;

        return $this;
    }

    public function getDateProchaineMaintenance(): ?\DateTimeInterface
    {
        return $this->dateProchaineMaintenance;
    }

    public function setDateProchaineMaintenance(?\DateTimeInterface $dateProchaineMaintenance): self
    {
        $this->dateProchaineMaintenance = $dateProchaineMaintenance;

        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(?string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getEtat(): ?string
    {
        return $this->etat;
    }

    public function setEtat(?string $etat): self
    {
        $this->etat = $etat;

        return $this;
    }

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function setImage(?string $image): self
    {
        $this->image = $image;

        return $this;
    }

    /**
     * @return Collection<int, Maintenance>
     */
    public function getMaintenances(): Collection
    {
        return $this->maintenances;
    }

    public function addMaintenance(Maintenance $maintenance): self
    {
        if (!$this->maintenances->contains($maintenance)) {
            $this->maintenances->add($maintenance);
            $maintenance->setMateriel($this);
        }

        return $this;
    }

    public function removeMaintenance(Maintenance $maintenance): self
    {
        if ($this->maintenances->removeElement($maintenance)) {
            // set the owning side to null (unless already changed)
            if ($maintenance->getMateriel() === $this) {
                $maintenance->setMateriel(null);
            }
        }

        return $this;
    }
}
