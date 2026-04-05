<?php

namespace App\Controller\Marketplace\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;

#[Route('/marketplace/admin')]
#[IsGranted('ROLE_ADMIN')]
class AdminCouponController extends AbstractController
{
    #[Route('/coupons', name: 'admin_marketplace_coupons')]
    public function coupons(): Response
    {
        return $this->render('Marketplace/admin/coupons.html.twig');
    }
}
