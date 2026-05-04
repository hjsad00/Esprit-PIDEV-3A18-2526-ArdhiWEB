<?php

namespace App\Service\EmployeTache;

use App\Entity\EmployeTache\Employe;

/**
 * Service métier pour la gestion des règles de validation de l'entité Employe.
 *
 * Ce service valide les règles métier de manière isolée (sans base de données),
 * ce qui le rend parfaitement testable via des tests unitaires PHPUnit (TestCase).
 */
class EmployeManager
{
    /** Types de contrat autorisés dans le système. */
    public const TYPES_CONTRAT_VALIDES = ['CDI', 'CDD', 'Intérim'];

    /**
     * Valide toutes les règles métier d'un employé.
     *
     * @param Employe $employe L'entité à valider
     * @return bool true si toutes les règles sont respectées
     *
     * @throws \InvalidArgumentException si une règle métier est violée
     */
    public function valider(Employe $employe): bool
    {
        $this->validerNom($employe->getNom());
        $this->validerPrenom($employe->getPrenom());
        $this->validerEmail($employe->getEmail());
        $this->validerTelephone($employe->getTelephone());
        $this->validerSalaireJournalier($employe->getSalaireJournalier());
        $this->validerTypeContrat($employe->getTypeContrat());

        return true;
    }

    // ─── Règles métier individuelles ──────────────────────────────────────────

    /**
     * Règle 1 : Le nom est obligatoire (non vide).
     *
     * @throws \InvalidArgumentException
     */
    public function validerNom(?string $nom): void
    {
        if (empty(trim((string) $nom))) {
            throw new \InvalidArgumentException('Le nom de l\'employé est obligatoire.');
        }
    }

    /**
     * Règle 2 : Le prénom est obligatoire (non vide).
     *
     * @throws \InvalidArgumentException
     */
    public function validerPrenom(?string $prenom): void
    {
        if (empty(trim((string) $prenom))) {
            throw new \InvalidArgumentException('Le prénom de l\'employé est obligatoire.');
        }
    }

    /**
     * Règle 3 : L'email doit être valide (format RFC).
     *
     * @throws \InvalidArgumentException
     */
    public function validerEmail(?string $email): void
    {
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('L\'adresse email de l\'employé est invalide.');
        }
    }

    /**
     * Règle 4 : Le téléphone doit contenir exactement 8 chiffres.
     *
     * @throws \InvalidArgumentException
     */
    public function validerTelephone(?string $telephone): void
    {
        if (empty($telephone) || !preg_match('/^[0-9]{8}$/', $telephone)) {
            throw new \InvalidArgumentException('Le téléphone doit contenir exactement 8 chiffres.');
        }
    }

    /**
     * Règle 5 : Le salaire journalier ne peut pas être négatif.
     *
     * @throws \InvalidArgumentException
     */
    public function validerSalaireJournalier(float $salaire): void
    {
        if ($salaire < 0) {
            throw new \InvalidArgumentException('Le salaire journalier ne peut pas être négatif.');
        }
    }

    /**
     * Règle 6 : Le type de contrat doit être parmi les valeurs autorisées.
     *
     * @throws \InvalidArgumentException
     */
    public function validerTypeContrat(string $typeContrat): void
    {
        if (!in_array($typeContrat, self::TYPES_CONTRAT_VALIDES, true)) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Le type de contrat "%s" est invalide. Valeurs autorisées : %s.',
                    $typeContrat,
                    implode(', ', self::TYPES_CONTRAT_VALIDES)
                )
            );
        }
    }
}
