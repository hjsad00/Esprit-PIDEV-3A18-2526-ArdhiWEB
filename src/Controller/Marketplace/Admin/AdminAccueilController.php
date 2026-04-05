<?php

namespace App\Controller\Marketplace\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;

#[Route('/marketplace/admin')]
#[IsGranted('ROLE_ADMIN')]
class AdminAccueilController extends AbstractController
{
    #[Route('', name: 'admin_marketplace_accueil')]
    public function accueil(): Response
    {
        return $this->render('Marketplace/admin/accueil.html.twig');
    }
}
