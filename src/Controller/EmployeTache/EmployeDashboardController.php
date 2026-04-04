<?php

namespace App\Controller\EmployeTache;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class EmployeDashboardController extends AbstractController
{
    #[Route('/employe-dashboard', name: 'app_employe_dashboard')]
    public function index(): Response
    {
        return $this->render('EmployeTache/viewagriculteure.html.twig');
    }
}
