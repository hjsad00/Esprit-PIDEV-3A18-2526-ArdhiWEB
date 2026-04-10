<?php

namespace App\Controller\EmployeTache;

use App\Entity\EmployeTache\Tache;
use App\Repository\EmployeTache\EmployeRepository;
use App\Service\EmployeTache\TacheRiskService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/employe/tache')]
#[IsGranted('ROLE_AGRICULTEUR')]
class TacheRiskController extends AbstractController
{
    #[Route('/{id}/analyse-ia', name: 'tache_analyse_ia', methods: ['GET'])]
    public function analyse(Tache $tache, TacheRiskService $riskService, EmployeRepository $employeRepo): JsonResponse
    {
        $nomEmploye = "Inconnu";
        if ($tache->getIdEmploye()) {
            $emp = $employeRepo->find($tache->getIdEmploye());
            if ($emp) {
                $nomEmploye = $emp->getNomComplet();
            }
        }

        $resultat = $riskService->analyser($tache, $nomEmploye);

        return new JsonResponse($resultat);
    }
}
