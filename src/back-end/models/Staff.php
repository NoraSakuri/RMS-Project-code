<?php

class Staff
{
    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    public function findByUsername($username)
    {
        $stmt = $this->pdo->prepare("
        SELECT *
        FROM staff
        WHERE username = ?
        LIMIT 1
    ");

        $stmt->execute([
            $username
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function usernameExists($username)
    {
        $stmt = $this->pdo->prepare("
            SELECT staff_id FROM staff 
            WHERE username = ?
            LIMIT 1
        ");
        $stmt->execute([$username]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function createStaff($name, $username, $password, $role, $isActive)
    {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $this->pdo->prepare("
            INSERT INTO staff (name, username, password, role, is_active)
            VALUES (?, ?, ?, ?, ?)
        ");

        return $stmt->execute([
            $name,
            $username,
            $hashedPassword,
            strtoupper($role),
            $isActive
        ]);
    }

    public function updateLoginAttempt($staff_id, $attempts, $lockedUntil = null)
{
    $stmt = $this->pdo->prepare("
        UPDATE staff
        SET failed_attempts = ?, locked_until = ?
        WHERE staff_id = ?
    ");

    return $stmt->execute([
        $attempts,
        $lockedUntil,
        $staff_id
    ]);
}


    public function resetLoginAttempt($staff_id)
{
    $stmt = $this->pdo->prepare("
        UPDATE staff
        SET failed_attempts = 0,
            locked_until = NULL
        WHERE staff_id = ?
    ");

    return $stmt->execute([
        $staff_id
    ]);
}
}
