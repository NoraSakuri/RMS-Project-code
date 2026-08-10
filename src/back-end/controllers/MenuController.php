<?php

require_once __DIR__ . "/../models/Menu.php";

class MenuController
{
    private Menu $menu;

    public function __construct(PDO $pdo)
    {
        $this->menu = new Menu($pdo);
    }

    public function getMenus(): array
    {
        return $this->menu->getAll();
    }

    public function createMenu(array $data): bool
    {
        return $this->menu->create(
            $data['name'],
            $data['category_id'],
            $data['description'],
            $data['price'],
            $data['availability']
        );
    }

    public function updateMenu(int $id, array $data): bool
    {
        return $this->menu->update(
            $id,
            $data['name'],
            $data['category_id'],
            $data['description'],
            $data['price'],
            $data['availability']
        );
    }

    public function deleteMenu(int $id): bool
    {
        return $this->menu->delete($id);
    }

    public function categoryExists(int $categoryId): bool
    {
        return $this->menu->categoryExists($categoryId);
    }
}