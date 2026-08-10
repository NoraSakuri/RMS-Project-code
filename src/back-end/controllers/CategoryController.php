<?php

require_once __DIR__ . "/../models/Category.php";

class CategoryController
{
    private $category;

    public function __construct($pdo)
    {
        $this->category = new Category($pdo);
    }

    public function getCategories()
    {
        return $this->category->getAll();
    }

    public function createCategory($data)
    {
        return $this->category->create(
            $data["category_name"],
            $data["is_active"]
        );
    }

    public function updateCategory($id, $data)
    {
        return $this->category->update(
            $id,
            $data["category_name"],
            $data["is_active"]
        );
    }

    public function deleteCategory($id)
    {
        return $this->category->delete($id);
    }
}