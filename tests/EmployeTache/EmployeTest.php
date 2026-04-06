<?php

namespace App\Tests\EmployeTache;

use App\Entity\EmployeTache\Employe;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class EmployeTest extends KernelTestCase
{
    private ?EntityManagerInterface $entityManager;
    private ?ValidatorInterface $validator;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = static::getContainer()->get('doctrine')->getManager();
        $this->validator = static::getContainer()->get(ValidatorInterface::class);
    }

    public function testControleSaisieInvalide(): void
    {
        $employe = new Employe();
        // Validation Assert : on donne un nom vide et un email mal formatté
        $employe->setNom('');
        $employe->setPrenom('TestPrenom');
        $employe->setEmail('mauvais_email');
        
        $errors = $this->validator->validate($employe);
        
        // On s'attend à au minimum 2 erreurs (NotBlank pour le nom et Email pour le format d'email)
        $this->assertGreaterThanOrEqual(2, count($errors), 'Il devrait y avoir des erreurs de validation (Nom vide, Email invalide).');
    }

    public function testAjouterEmploye(): int
    {
        $employe = new Employe();
        $employe->setNom('NomTest');
        $employe->setPrenom('PrenomTest');
        
        // Génération d'un email unique pour éviter l'erreur de contrainte UniqueEntity sur la BDD lors des exécutions sucessives
        $emailUnique = 'test' . uniqid() . '@domaine.com';
        $employe->setEmail($emailUnique);
        $employe->setPoste('Ingénieur');
        $employe->setTelephone('12345678');
        $employe->setActif(true);

        // Validation avant ajout pour s'assurer que notre entité remplit les conditions de base
        $errors = $this->validator->validate($employe);
        $this->assertCount(0, $errors, 'L\'entité doit être valide avant persistance.');

        $this->entityManager->persist($employe);
        $this->entityManager->flush();

        $id = $employe->getId();
        $this->assertNotNull($id, 'L\'employé doit avoir un ID après insertion dans la base.');

        // On retourne l'ID pour le tester au passage (via depends)
        return $id;
    }

    /**
     * @depends testAjouterEmploye
     */
    public function testModifierEmploye(int $id): int
    {
        $employe = $this->entityManager->getRepository(Employe::class)->find($id);
        $this->assertNotNull($employe, 'L\'employé fraîchement ajouté doit être trouvé en base.');

        $employe->setNom('NomModifie');
        $this->entityManager->flush();

        $employeModifie = $this->entityManager->getRepository(Employe::class)->find($id);
        $this->assertEquals('NomModifie', $employeModifie->getNom(), 'Le nom de l\'employé a bien été mis à jour.');

        return $id;
    }

    /**
     * @depends testModifierEmploye
     */
    public function testSupprimerEmploye(int $id): void
    {
        $employe = $this->entityManager->getRepository(Employe::class)->find($id);
        $this->assertNotNull($employe, 'L\'employé à supprimer doit exister.');

        $this->entityManager->remove($employe);
        $this->entityManager->flush();

        $employeSupprime = $this->entityManager->getRepository(Employe::class)->find($id);
        $this->assertNull($employeSupprime, 'L\'employé a bien été supprimé et n\'est plus en requête.');
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->entityManager->close();
        $this->entityManager = null;
        $this->validator = null;
    }
}
