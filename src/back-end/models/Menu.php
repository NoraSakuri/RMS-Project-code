<?php

class Menu
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function getAll(): array
    {
        $stmt = $this->pdo->query("
            SELECT
                m.item_id,
                m.name,
                m.category_id,
                c.category_name,
                m.description,
                m.price,
                m.availability
            FROM menu_items m
            INNER JOIN categories c
                ON c.category_id = m.category_id
            ORDER BY m.item_id DESC
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create(
        string $name,
        int $categoryId,
        string $description,
        float $price,
        int $availability
    ): bool {
        $stmt = $this->pdo->prepare("
            INSERT INTO menu_items (
                name,
                category_id,
                category,
                description,
                price,
                availability
            )
            SELECT
                :name,
                c.category_id,
                c.category_name,
                :description,
                :price,
                :availability
            FROM categories c
            WHERE c.category_id = :category_id
              AND c.is_active = 1
        ");

        $stmt->execute([
            ':name' => $name,
            ':category_id' => $categoryId,
            ':description' => $description,
            ':price' => $price,
            ':availability' => $availability
        ]);

        return $stmt->rowCount() > 0;
    }

    public function update(
        int $id,
        string $name,
        int $categoryId,
        string $description,
        float $price,
        int $availability
    ): bool {
        $stmt = $this->pdo->prepare("
            UPDATE menu_items m
            INNER JOIN categories c
                ON c.category_id = :category_id
               AND c.is_active = 1
            SET
                m.name = :name,
                m.category_id = c.category_id,
                m.category = c.category_name,
                m.description = :description,
                m.price = :price,
                m.availability = :availability
            WHERE m.item_id = :item_id
        ");

        return $stmt->execute([
            ':item_id' => $id,
            ':name' => $name,
            ':category_id' => $categoryId,
            ':description' => $description,
            ':price' => $price,
            ':availability' => $availability
        ]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare("
            DELETE FROM menu_items
            WHERE item_id = :item_id
        ");

        return $stmt->execute([
            ':item_id' => $id
        ]);
    }

    public function categoryExists(int $categoryId): bool
    {
        $stmt = $this->pdo->prepare("
            SELECT category_id
            FROM categories
            WHERE category_id = :category_id
              AND is_active = 1
            LIMIT 1
        ");

        $stmt->execute([
            ':category_id' => $categoryId
        ]);

        return (bool) $stmt->fetchColumn();
    }
}