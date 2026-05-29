<?php

declare(strict_types=1);

namespace App\Controller;

use App\Ui\CashRegister\MockCashRegisterData;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Presentation-only Cash Register screens. The "Full register" three-pane
 * layout and the "Quick sale" touch layout are two selectable modes of the
 * same surface, switched by the shared mode toggle. Both render from stubbed
 * sample data and perform no backend mutations — a real Transactions context
 * is out of scope for this epic. Lives at src/Controller awaiting a Transactions
 * bounded context, like the other root controllers.
 */
final class CashRegisterController extends AbstractController
{
    public function __construct(private readonly MockCashRegisterData $cashRegisterData)
    {
    }

    #[Route('/cash-register', name: 'cash_register_index', methods: ['GET'])]
    public function full(): Response
    {
        return $this->render('cash_register/full.html.twig', [
            'register' => $this->cashRegisterData->build(),
        ]);
    }

    #[Route('/cash-register/quick', name: 'cash_register_quick_index', methods: ['GET'])]
    public function quick(): Response
    {
        return $this->render('cash_register/quick.html.twig', [
            'quick' => $this->cashRegisterData->buildQuickSale(),
        ]);
    }
}
