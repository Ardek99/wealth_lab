<?php

namespace App\DataFixtures;

use App\Entity\Category;
use App\Enum\BudgetGroupType;
use App\Enum\CategoryType;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Override;

class CategoryFixtures extends Fixture
{
    #[Override]
    public function load(ObjectManager $manager): void
    {
        $categories = [
            // INCOME
            ['name' => 'Salaire', 'type' => CategoryType::INCOME,],
            ['name' => 'Freelance', 'type' => CategoryType::INCOME],
            ['name' => 'Investissement', 'type' => CategoryType::INCOME],
            ['name' => 'Dividendes', 'type' => CategoryType::INCOME],
            ['name' => 'Allocations', 'type' => CategoryType::INCOME],
            ['name' => 'Remboursements', 'type' => CategoryType::INCOME],
            ['name' => 'Cadeaux', 'type' => CategoryType::INCOME],
            ['name' => 'Autres revenus', 'type' => CategoryType::INCOME],

            // EXPENSES
            ['name' => 'Logement', 'type' => CategoryType::EXPENSE, 'budgetGroup' => BudgetGroupType::NEEDS],
            ['name' => 'Alimentation', 'type' => CategoryType::EXPENSE, 'budgetGroup' => BudgetGroupType::NEEDS],
            ['name' => 'Électricité, Eau, Gaz', 'type' => CategoryType::EXPENSE, 'budgetGroup' => BudgetGroupType::NEEDS],
            ['name' => 'Téléphone, Internet', 'type' => CategoryType::EXPENSE, 'budgetGroup' => BudgetGroupType::NEEDS],
            ['name' => 'Crédits, Prêts', 'type' => CategoryType::EXPENSE, 'budgetGroup' => BudgetGroupType::NEEDS],
            ['name' => 'Frais bancaires', 'type' => CategoryType::EXPENSE, 'budgetGroup' => BudgetGroupType::NEEDS],
            ['name' => 'Assurances', 'type' => CategoryType::EXPENSE, 'budgetGroup' => BudgetGroupType::NEEDS],
            ['name' => 'Transport', 'type' => CategoryType::EXPENSE, 'budgetGroup' => BudgetGroupType::NEEDS],
            ['name' => 'Santé', 'type' => CategoryType::EXPENSE, 'budgetGroup' => BudgetGroupType::NEEDS],
            ['name' => 'Impôts', 'type' => CategoryType::EXPENSE, 'budgetGroup' => BudgetGroupType::NEEDS],
            ['name' => 'Loisirs', 'type' => CategoryType::EXPENSE, 'budgetGroup' => BudgetGroupType::WANTS],
            ['name' => 'Shopping', 'type' => CategoryType::EXPENSE, 'budgetGroup' => BudgetGroupType::WANTS],
            ['name' => 'Abonnements', 'type' => CategoryType::EXPENSE, 'budgetGroup' => BudgetGroupType::WANTS],
            ['name' => 'Restaurants', 'type' => CategoryType::EXPENSE, 'budgetGroup' => BudgetGroupType::WANTS],
            ['name' => 'Voyages', 'type' => CategoryType::EXPENSE, 'budgetGroup' => BudgetGroupType::WANTS],
            ['name' => 'Animaux', 'type' => CategoryType::EXPENSE, 'budgetGroup' => BudgetGroupType::WANTS],
            ['name' => 'Famille', 'type' => CategoryType::EXPENSE, 'budgetGroup' => BudgetGroupType::WANTS],
            ['name' => 'Autres dépenses', 'type' => CategoryType::EXPENSE, 'budgetGroup' => BudgetGroupType::WANTS],
        ];

        foreach ($categories as $categoryData) {
            $category = new Category();
            $category->setName($categoryData['name']);
            $category->setType($categoryData['type']);
            if (isset($categoryData['budgetGroup'])) {
                $category->setBudgetGroup($categoryData['budgetGroup']);
            }
            $manager->persist($category);
        }

        $manager->flush();
    }
}