<?php

declare(strict_types=1);

header(
    'Content-Type: application/json; charset=utf-8'
);


if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}


require_once __DIR__ . "/../../../../config/database.php";



function respond(
    bool $success,
    string $message,
    array $data = [],
    int $statusCode = 200
): never {


    http_response_code($statusCode);


    echo json_encode(
        [
            "success"=>$success,
            "message"=>$message,
            "data"=>$data
        ],
        JSON_UNESCAPED_UNICODE
    );


    exit;

}




/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/


if(empty($_SESSION['staff_id'])){


    respond(
        false,
        "Your session has expired.",
        [],
        401
    );


}




$role =
strtoupper(
    $_SESSION['role'] ?? ''
);



if(!in_array(
    $role,
    ['ADMIN','MANAGER'],
    true
)){


    respond(
        false,
        "Access denied.",
        [],
        403
    );


}





if($_SERVER['REQUEST_METHOD'] !== 'POST'){


    respond(
        false,
        "Invalid request method.",
        [],
        405
    );


}




/*
|--------------------------------------------------------------------------
| Input
|--------------------------------------------------------------------------
*/


$input =
json_decode(
    file_get_contents('php://input'),
    true
);



if(!is_array($input)){


    respond(
        false,
        "Invalid recipe data.",
        [],
        422
    );


}





$itemId =
filter_var(
    $input['item_id'] ?? null,
    FILTER_VALIDATE_INT
);



$inventoryId =
filter_var(
    $input['inventory_id'] ?? null,
    FILTER_VALIDATE_INT
);



$quantity =
filter_var(
    $input['required_quantity'] ?? null,
    FILTER_VALIDATE_FLOAT
);




if(!$itemId || !$inventoryId){


    respond(
        false,
        "Invalid menu or inventory item.",
        [],
        422
    );


}




if(
    $quantity === false
    ||
    $quantity <= 0
){


    respond(
        false,
        "Quantity must be greater than zero.",
        [],
        422
    );


}




try{


/*
|--------------------------------------------------------------------------
| Get Unit Automatically
|--------------------------------------------------------------------------
*/


    $inventoryStatement =
    $pdo->prepare("

        SELECT
            unit_type
        FROM inventory_items
        WHERE inventory_id = :inventory_id

    ");



    $inventoryStatement->execute([

        ':inventory_id'=>$inventoryId

    ]);



    $inventory =
    $inventoryStatement
    ->fetch(PDO::FETCH_ASSOC);





    if(!$inventory){


        throw new RuntimeException(
            "Inventory item not found."
        );


    }





    $unitType =
    $inventory['unit_type'];






/*
|--------------------------------------------------------------------------
| Duplicate Check
|--------------------------------------------------------------------------
*/


    $check =
    $pdo->prepare("

        SELECT
            menu_item_inventory_id
        FROM menu_item_inventory
        WHERE item_id = :item_id
        AND inventory_id = :inventory_id

    ");



    $check->execute([

        ':item_id'=>$itemId,

        ':inventory_id'=>$inventoryId

    ]);




    if($check->fetch()){


        throw new RuntimeException(
            "This ingredient already exists."
        );


    }





/*
|--------------------------------------------------------------------------
| Insert Recipe
|--------------------------------------------------------------------------
*/


    $statement =
    $pdo->prepare("

        INSERT INTO menu_item_inventory
        (
            item_id,
            inventory_id,
            required_quantity,
            unit_type
        )

        VALUES
        (
            :item_id,
            :inventory_id,
            :quantity,
            :unit_type
        )

    ");




    $statement->execute([


        ':item_id'=>$itemId,


        ':inventory_id'=>$inventoryId,


        ':quantity'=>$quantity,


        ':unit_type'=>$unitType


    ]);





    respond(

        true,

        "Ingredient added successfully.",

        [

            "id" =>
            (int)$pdo->lastInsertId()

        ],

        201

    );



}
catch(RuntimeException $e){


    respond(

        false,

        $e->getMessage(),

        [],

        422

    );


}
catch(Throwable $e){


    error_log(
        $e->getMessage()
    );


    respond(

        false,

        "Unable to add ingredient.",

        [],

        500

    );


}