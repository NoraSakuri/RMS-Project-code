<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . "/../../../../config/database.php";

function jsonResponse(
    bool $success,
    string $message,
    array $data = [],
    int $statusCode = 200
): never {
    http_response_code($statusCode);

    echo json_encode(
        array_merge(
            [
                'success' => $success,
                'message' => $message
            ],
            $data
        ),
        JSON_UNESCAPED_UNICODE
    );

    exit;
}

/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/

if (empty($_SESSION['staff_id'])) {
    jsonResponse(
        false,
        'Your session has expired. Please log in again.',
        [],
        401
    );
}

$role = strtoupper((string) ($_SESSION['role'] ?? ''));

if (!in_array($role, ['WAITER', 'ADMIN', 'MANAGER'], true)) {
    jsonResponse(
        false,
        'You do not have permission to create orders.',
        [],
        403
    );
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(
        false,
        'Invalid request method.',
        [],
        405
    );
}

/*
|--------------------------------------------------------------------------
| Read request
|--------------------------------------------------------------------------
*/

$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);

if (!is_array($input)) {
    jsonResponse(
        false,
        'Invalid order data.',
        [],
        422
    );
}

$tableId = filter_var(
    $input['table_id'] ?? null,
    FILTER_VALIDATE_INT
);

$customerNote = trim(
    (string) ($input['customer_note'] ?? '')
);

$items = $input['items'] ?? [];

if (!$tableId || $tableId < 1) {
    jsonResponse(
        false,
        'Please select a valid table.',
        [],
        422
    );
}

if (!is_array($items) || empty($items)) {
    jsonResponse(
        false,
        'Please add at least one menu item.',
        [],
        422
    );
}

if (mb_strlen($customerNote) > 1000) {
    jsonResponse(
        false,
        'Customer note is too long.',
        [],
        422
    );
}

try {
    $pdo->beginTransaction();

    /*
    |--------------------------------------------------------------------------
    | Validate table
    |--------------------------------------------------------------------------
    */

    $tableStatement = $pdo->prepare("
        SELECT
            table_id,
            table_number,
            table_status
        FROM rms_tables
        WHERE table_id = :table_id
        FOR UPDATE
    ");

    $tableStatement->execute([
        ':table_id' => $tableId
    ]);

    $table = $tableStatement->fetch(PDO::FETCH_ASSOC);

    if (!$table) {
        throw new RuntimeException(
            'Selected table was not found.'
        );
    }

    $tableStatus = strtoupper(
        (string) $table['table_status']
    );

    if (
        !in_array(
            $tableStatus,
            ['AVAILABLE', 'RESERVED'],
            true
        )
    ) {
        throw new RuntimeException(
            'Selected table is not available.'
        );
    }

    if ($tableStatus === 'CLEANING') {
        throw new RuntimeException(
            'Selected table is currently being cleaned.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Validate menu items and calculate total
    |--------------------------------------------------------------------------
    */

    $menuStatement = $pdo->prepare("
        SELECT
            item_id,
            name,
            price,
            availability
        FROM menu_items
        WHERE item_id = :item_id
        LIMIT 1
    ");

    $validatedItems = [];
    $subtotal = 0.0;

    foreach ($items as $item) {
        $itemId = filter_var(
            $item['item_id'] ?? null,
            FILTER_VALIDATE_INT
        );

        $quantity = filter_var(
            $item['quantity'] ?? null,
            FILTER_VALIDATE_INT
        );

        if (!$itemId || !$quantity || $quantity < 1) {
            throw new RuntimeException(
                'One or more order items are invalid.'
            );
        }

        if ($quantity > 99) {
            throw new RuntimeException(
                'Maximum quantity per item is 99.'
            );
        }

        $menuStatement->execute([
            ':item_id' => $itemId
        ]);

        $menuItem = $menuStatement->fetch(PDO::FETCH_ASSOC);

        if (!$menuItem) {
            throw new RuntimeException(
                'A selected menu item was not found.'
            );
        }

        if (!(bool) $menuItem['availability']) {
            throw new RuntimeException(
                $menuItem['name'] . ' is currently unavailable.'
            );
        }

        $price = (float) $menuItem['price'];
        $itemSubtotal = $price * $quantity;

        $subtotal += $itemSubtotal;

        $validatedItems[] = [
            'item_id' => (int) $menuItem['item_id'],
            'name' => (string) $menuItem['name'],
            'quantity' => $quantity,
            'price' => $price,
            'subtotal' => $itemSubtotal
        ];
    }

    $taxAmount = round($subtotal * 0.05, 2);
    $discountAmount = 0.0;
    $totalAmount = $subtotal + $taxAmount - $discountAmount;

    /*
|--------------------------------------------------------------------------
| Calculate required inventory
|--------------------------------------------------------------------------
*/

    $inventoryRequirements = [];

    $recipeStatement = $pdo->prepare("
    SELECT
        mii.inventory_id,
        mii.required_quantity,
        mii.unit_type,
        i.item_name,
        i.quantity,
        i.unit_type AS inventory_unit
    FROM menu_item_inventory mii
    INNER JOIN inventory_items i
        ON i.inventory_id = mii.inventory_id
    WHERE mii.item_id = :item_id
    FOR UPDATE
");

    foreach ($validatedItems as $item) {
        $recipeStatement->execute([
            ':item_id' => $item['item_id']
        ]);

        $recipeIngredients = $recipeStatement->fetchAll(
            PDO::FETCH_ASSOC
        );

        if (empty($recipeIngredients)) {
            throw new RuntimeException(
                'Inventory setup is missing for '
                    . $item['name']
                    . '. Please contact the administrator.'
            );
        }

        foreach ($recipeIngredients as $ingredient) {

            $recipeUnit = strtoupper(
                (string) $ingredient['unit_type']
            );

            $inventoryUnit = strtoupper(
                (string) $ingredient['inventory_unit']
            );

            if ($recipeUnit !== $inventoryUnit) {
                throw new RuntimeException(
                    'Unit mismatch for '
                        . $ingredient['item_name']
                        . '. Recipe unit is '
                        . $recipeUnit
                        . ' but inventory unit is '
                        . $inventoryUnit
                        . '.'
                );
            }

            $inventoryId = (int) $ingredient['inventory_id'];

            $requiredAmount =
                (float) $ingredient['required_quantity']
                * (int) $item['quantity'];

            if (!isset($inventoryRequirements[$inventoryId])) {
                $inventoryRequirements[$inventoryId] = [
                    'inventory_id' => $inventoryId,
                    'item_name' => $ingredient['item_name'],
                    'available_quantity' =>
                    (float) $ingredient['quantity'],
                    'required_quantity' => 0.0,
                    'inventory_unit' =>
                    $ingredient['inventory_unit'],
                    'recipe_unit' =>
                    $ingredient['unit_type']
                ];
            }

            $inventoryRequirements[$inventoryId]['required_quantity'] += $requiredAmount;
        }
    }

    /*
|--------------------------------------------------------------------------
| Validate inventory stock
|--------------------------------------------------------------------------
*/

    foreach ($inventoryRequirements as $requirement) {
        if (
            $requirement['available_quantity']
            < $requirement['required_quantity']
        ) {
            throw new RuntimeException(
                'Insufficient stock for '
                    . $requirement['item_name']
                    . '. Required: '
                    . $requirement['required_quantity']
                    . ' '
                    . $requirement['inventory_unit']
                    . ', Available: '
                    . $requirement['available_quantity']
                    . ' '
                    . $requirement['inventory_unit']
                    . '.'
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Create order
    |--------------------------------------------------------------------------
    */

    $orderStatement = $pdo->prepare("
        INSERT INTO orders (
            table_id,
            staff_id,
            customer_note,
            status,
            chef_action,
            subtotal,
            tax_amount,
            discount_amount,
            total_amount
        ) VALUES (
            :table_id,
            :staff_id,
            :customer_note,
            'PENDING',
            'PENDING',
            :subtotal,
            :tax_amount,
            :discount_amount,
            :total_amount
        )
    ");

    $orderStatement->execute([
        ':table_id' => $tableId,
        ':staff_id' => (int) $_SESSION['staff_id'],
        ':customer_note' => $customerNote !== ''
            ? $customerNote
            : null,
        ':subtotal' => $subtotal,
        ':tax_amount' => $taxAmount,
        ':discount_amount' => $discountAmount,
        ':total_amount' => $totalAmount
    ]);

    $orderId = (int) $pdo->lastInsertId();

    /*
    |--------------------------------------------------------------------------
    | Create order details
    |--------------------------------------------------------------------------
    */

    $detailStatement = $pdo->prepare("
        INSERT INTO order_details (
            order_id,
            item_id,
            quantity,
            price,
            subtotal
        ) VALUES (
            :order_id,
            :item_id,
            :quantity,
            :price,
            :subtotal
        )
    ");

    foreach ($validatedItems as $item) {
        $detailStatement->execute([
            ':order_id' => $orderId,
            ':item_id' => $item['item_id'],
            ':quantity' => $item['quantity'],
            ':price' => $item['price'],
            ':subtotal' => $item['subtotal']
        ]);
    }

    /*
|--------------------------------------------------------------------------
| Deduct inventory stock
|--------------------------------------------------------------------------
*/

    $deductInventoryStatement = $pdo->prepare("
    UPDATE inventory_items
    SET quantity = quantity - :required_quantity
    WHERE inventory_id = :inventory_id
      AND quantity >= :required_quantity
");

    foreach ($inventoryRequirements as $requirement) {
        $deductInventoryStatement->execute([
            ':required_quantity' =>
            $requirement['required_quantity'],

            ':inventory_id' =>
            $requirement['inventory_id']
        ]);

        if ($deductInventoryStatement->rowCount() !== 1) {
            throw new RuntimeException(
                'Unable to deduct inventory for '
                    . $requirement['item_name']
                    . '. Available stock may have changed.'
            );
        }
    }

    /*
|--------------------------------------------------------------------------
| Record stock-out transactions
|--------------------------------------------------------------------------
*/

    $transactionStatement = $pdo->prepare("
    INSERT INTO inventory_transactions (
        inventory_id,
        order_id,
        transaction_type,
        quantity,
        unit_type,
        note
    ) VALUES (
        :inventory_id,
        :order_id,
        'STOCK_OUT',
        :quantity,
        :unit_type,
        :note
    )
");

    foreach ($inventoryRequirements as $requirement) {
        $transactionStatement->execute([
            ':inventory_id' =>
            $requirement['inventory_id'],

            ':order_id' =>
            $orderId,

            ':quantity' =>
            $requirement['required_quantity'],

            ':unit_type' =>
            $requirement['inventory_unit'],

            ':note' =>
            'Stock deducted for order ORD-'
                . str_pad(
                    (string) $orderId,
                    5,
                    '0',
                    STR_PAD_LEFT
                )
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Mark table occupied
    |--------------------------------------------------------------------------
    */

    $updateTableStatement = $pdo->prepare("
        UPDATE rms_tables
        SET table_status = 'OCCUPIED'
        WHERE table_id = :table_id
    ");

    $updateTableStatement->execute([
        ':table_id' => $tableId
    ]);

    $pdo->commit();

    $orderNumber = 'ORD-' . str_pad(
        (string) $orderId,
        5,
        '0',
        STR_PAD_LEFT
    );

    jsonResponse(
        true,
        'Order sent to kitchen successfully.',
        [
            'order_id' => $orderId,
            'order_number' => $orderNumber,
            'table_number' => $table['table_number'],
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'total_amount' => $totalAmount
        ],
        201
    );
} catch (RuntimeException $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    jsonResponse(
        false,
        $exception->getMessage(),
        [],
        422
    );
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log(
        'Create order error: ' . $exception->getMessage()
    );

    jsonResponse(
        false,
        'Unable to create order. Please try again.',
        [],
        500
    );
}
