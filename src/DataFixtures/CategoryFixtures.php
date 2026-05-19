<?php

namespace App\DataFixtures;

use App\Entity\Category;
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
            ['name' => 'Salaire', 'type' => CategoryType::INCOME],
            ['name' => 'Freelance', 'type' => CategoryType::INCOME],
            ['name' => 'Investissement', 'type' => CategoryType::INCOME],
            ['name' => 'Dividendes', 'type' => CategoryType::INCOME],
            ['name' => 'Allocations', 'type' => CategoryType::INCOME],
            ['name' => 'Cadeaux', 'type' => CategoryType::INCOME],
            ['name' => 'Autres revenus', 'type' => CategoryType::INCOME],

            // EXPENSES
            ['name' => 'Logement', 'type' => CategoryType::EXPENSE],
            ['name' => 'Alimentation', 'type' => CategoryType::EXPENSE],
            ['name' => 'Transport', 'type' => CategoryType::EXPENSE],
            ['name' => 'Santé', 'type' => CategoryType::EXPENSE],
            ['name' => 'Loisirs', 'type' => CategoryType::EXPENSE],
            ['name' => 'Shopping', 'type' => CategoryType::EXPENSE],
            ['name' => 'Abonnements', 'type' => CategoryType::EXPENSE],
            ['name' => 'Restaurants', 'type' => CategoryType::EXPENSE],
            ['name' => 'Voyages', 'type' => CategoryType::EXPENSE],
            ['name' => 'Éducation', 'type' => CategoryType::EXPENSE],
            ['name' => 'Assurances', 'type' => CategoryType::EXPENSE],
            ['name' => 'Impôts', 'type' => CategoryType::EXPENSE],
            ['name' => 'Animaux', 'type' => CategoryType::EXPENSE],
            ['name' => 'Famille', 'type' => CategoryType::EXPENSE],
            ['name' => 'Autres dépenses', 'type' => CategoryType::EXPENSE],
        ];

        foreach ($categories as $categoryData) {
            $category = new Category();
            $category->setName($categoryData['name']);
            $category->setType($categoryData['type']);
            $manager->persist($category);
        }

        $manager->flush();
    }
}