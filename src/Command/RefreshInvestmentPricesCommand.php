<?php

namespace App\Command;

use App\Enum\InvestmentType;
use App\Repository\InvestmentRepository;
use App\Service\PriceFetcherService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:refresh-investment-prices', description: 'Refreshes the prices of all investments.')]
class RefreshInvestmentPricesCommand extends Command
{
    public function __construct(
        private PriceFetcherService $priceFetcherService,
        private InvestmentRepository $investmentRepository,
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $investments = $this->investmentRepository->findAll();

        foreach ($investments as $investment) {
            if (!$investment->getTicker()) {
                continue;
            }
            if ($investment->getType() === InvestmentType::STOCK) {
                $price = $this->priceFetcherService->fetchStockPrice($investment->getTicker());
            } elseif ($investment->getType() === InvestmentType::CRYPTO) {
                $price = $this->priceFetcherService->fetchCryptoPrice($investment->getTicker());
            } elseif ($investment->getType() === InvestmentType::ETF) {
                $price = $this->priceFetcherService->fetchStockPrice($investment->getTicker());
            } else {
                continue;
            }

            if ($price !== null) {
                $investment->setCurrentPrice((string) $price);
                $investment->setCurrentPriceUpdatedAt(new \DateTime());
                $this->entityManager->persist($investment);
            }
        }

        $this->entityManager->flush();

        return Command::SUCCESS;
    }
}