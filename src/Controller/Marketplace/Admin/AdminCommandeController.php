<?php

namespace App\Controller\Marketplace\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;

#[Route('/marketplace/admin')]
#[IsGranted('ROLE_ADMIN')]
class AdminCommandeController extends AbstractController
{
    #[Route('/commandes', name: 'admin_marketplace_commandes')]
    public function commandes(): Response
    {
        return $this->render('Marketplace/admin/commandes.html.twig');
    }
}
