<?php

namespace App\Controller\Marketplace\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;

#[Route('/marketplace/admin')]
#[IsGranted('ROLE_ADMIN')]
class AdminReclamationController extends AbstractController
{
    #[Route('/reclamations', name: 'admin_marketplace_reclamations')]
    public function reclamations(): Response
    {
        return $this->render('Marketplace/admin/reclamations.html.twig');
    }
}
