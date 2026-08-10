<?php

class Category
{
    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    public function getAll()
    {
        $stmt = $this->pdo->query("
            SELECT * FROM categories
            ORDER BY category_id DESC
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create($categoryName, $isActive)
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO categories (category_name, is_active)
            VALUES (?, ?)
        ");

        return $stmt->execute([$categoryName, $isActive]);
    }

    public function update($id, $categoryName, $isActive)
    {
        $stmt = $this->pdo->prepare("
            UPDATE categories
            SET category_name = ?, is_active = ?
            WHERE category_id = ?
        ");

        return $stmt->execute([$categoryName, $isActive, $id]);
    }

    public function delete($id)
    {
        $stmt = $this->pdo->prepare("
            DELETE FROM categories
            WHERE category_id = ?
        ");

        return $stmt->execute([$id]);
    }
}