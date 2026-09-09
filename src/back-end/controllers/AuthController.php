<?php

require_once __DIR__ . "/../../../config/database.php";
require_once __DIR__ . "/../models/Staff.php";

class AuthController
{
    private $staff;

    public function __construct($pdo)
    {
        $this->staff = new Staff($pdo);
    }

    public function login()
    {
        session_start();
        header("Content-Type: application/json");

        $username = trim($_POST["username"] ?? "");
        $password = $_POST["password"] ?? "";

        if ($username === "" || $password === "") {
            echo json_encode([
                "success" => false,
                "message" => "Please enter username and password."
            ]);
            return;
        }

        $user = $this->staff->findByUsername($username);

        if (!$user) {
            echo json_encode([
                "success" => false,
                "message" => "Username not found."
            ]);

            return;
        }

        // Check account lock
        if ($user["locked_until"] !== null) {

        if (strtotime($user["locked_until"]) > time()) {

        echo json_encode([
            "success" => false,
            "message" => "Account locked. Please try again later."
        ]);

        return;
    }
}


// Check password
$validPassword = password_verify($password, $user["password"]);

if (!$validPassword && $password !== $user["password"]) {


    $attempts = $user["failed_attempts"] + 1;


    // Lock after 3 wrong attempts
    if ($attempts >= 3) {

        $lockedUntil = date(
            "Y-m-d H:i:s",
            strtotime("+5 minutes")
        );


        $this->staff->updateLoginAttempt(
            $user["staff_id"],
            0,
            $lockedUntil
        );


        echo json_encode([
            "success" => false,
            "message" => "Account temporarily locked.Please try again later."
        ]);

        return;

    }


    $this->staff->updateLoginAttempt(
        $user["staff_id"],
        $attempts
    );


    echo json_encode([
        "success" => false,
        "message" => 
        "Wrong password."
    ]);

    return;



// correct password
    $this->staff->resetLoginAttempt(
    $user["staff_id"]
   );
            echo json_encode([
                "success" => false,
                "message" => "Wrong password."
            ]);
            return;
        }

        if ((int) $user["is_active"] !== 1) {
            echo json_encode([
                "success" => false,
                "message" => "Your account is waiting for administrator approval."
            ]);

            return;
        }

        $_SESSION["staff_id"] = $user["staff_id"];
        $_SESSION["name"] = $user["name"];
        $_SESSION["role"] = $user["role"];

        echo json_encode([
            "success" => true,
            "message" => "Login successful.",
            "role" => $user["role"]
        ]);
    }
}
