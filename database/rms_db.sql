CREATE DATABASE IF NOT EXISTS restaurant_management_system;
USE restaurant_management_system;

CREATE TABLE staff (
    staff_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('ADMIN','MANAGER','CHEF','WAITER','CASHIER') NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE rms_tables (
    table_id INT AUTO_INCREMENT PRIMARY KEY,
    table_number INT UNIQUE NOT NULL,
    capacity INT NOT NULL,
    table_status ENUM('AVAILABLE','OCCUPIED','RESERVED') DEFAULT 'AVAILABLE'
);

CREATE TABLE menu_items (
    item_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    category VARCHAR(50) NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL,
    availability BOOLEAN DEFAULT TRUE
);

CREATE TABLE inventory_items (
    inventory_id INT AUTO_INCREMENT PRIMARY KEY,
    item_name VARCHAR(100) NOT NULL,
    quantity DECIMAL(10,2) NOT NULL,
    minimum_stock DECIMAL(10,2) NOT NULL,
    supplier_name VARCHAR(100),
    unit_type ENUM('GRAM','KILOGRAM','MILLILITER','LITER','PIECE') NOT NULL,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE menu_item_inventory (
    menu_item_inventory_id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    inventory_id INT NOT NULL,
    required_quantity DECIMAL(10,2) NOT NULL,
    unit_type ENUM('GRAM','KILOGRAM','MILLILITER','LITER','PIECE') NOT NULL,
    FOREIGN KEY (item_id) REFERENCES menu_items(item_id) ON DELETE CASCADE,
    FOREIGN KEY (inventory_id) REFERENCES inventory_items(inventory_id) ON DELETE CASCADE
);

CREATE TABLE orders (
    order_id INT AUTO_INCREMENT PRIMARY KEY,
    table_id INT NOT NULL,
    order_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('PENDING','CONFIRMED','PREPARING','READY','COMPLETED','CANCELLED') DEFAULT 'PENDING',
    chef_action ENUM('PENDING','ACCEPTED','PREPARING','READY','COMPLETED','CANCELLED') DEFAULT 'PENDING',
    total_amount DECIMAL(10,2) DEFAULT 0,
    FOREIGN KEY (table_id) REFERENCES rms_tables(table_id)
);

CREATE TABLE order_details (
    order_detail_id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    item_id INT NOT NULL,
    quantity INT NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(order_id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES menu_items(item_id)
);

CREATE TABLE payments (
    payment_id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    payment_method ENUM('CASH','CREDIT_CARD') NOT NULL,
    amount_paid DECIMAL(10,2) NOT NULL,
    payment_status ENUM('PENDING','PAID','FAILED','REFUNDED') DEFAULT 'PENDING',
    payment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(order_id)
);

CREATE TABLE invoices (
    invoice_id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    payment_id INT,
    invoice_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    subtotal DECIMAL(10,2) NOT NULL,
    tax_amount DECIMAL(10,2) DEFAULT 0,
    discount_amount DECIMAL(10,2) DEFAULT 0,
    final_total DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(order_id),
    FOREIGN KEY (payment_id) REFERENCES payments(payment_id)
);

CREATE TABLE reservations (
    reservation_id INT AUTO_INCREMENT PRIMARY KEY,
    table_id INT NOT NULL,
    staff_id INT NOT NULL,
    customer_name VARCHAR(100) NOT NULL,
    number_of_guests INT NOT NULL,
    reservation_date DATETIME NOT NULL,
    reservation_status ENUM('PENDING','CONFIRMED','CANCELLED','COMPLETED') DEFAULT 'PENDING',
    FOREIGN KEY (table_id) REFERENCES rms_tables(table_id),
    FOREIGN KEY (staff_id) REFERENCES staff(staff_id)
);

CREATE TABLE inventory_alerts (
    alert_id INT AUTO_INCREMENT PRIMARY KEY,
    inventory_id INT NOT NULL,
    minimum_qty DECIMAL(10,2) NOT NULL,
    alert_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('OPEN','CLOSED') DEFAULT 'OPEN',
    FOREIGN KEY (inventory_id) REFERENCES inventory_items(inventory_id)
);

INSERT INTO staff (name, username, password, role) VALUES
('Admin User', 'Admin', 'admin123', 'ADMIN'),
('Manager User', 'Manager', 'manager123', 'MANAGER'),
('Chef User', 'Chef', 'chef123', 'CHEF'),
('Waiter User', 'Waiter', 'waiter123', 'WAITER'),
('Cashier User', 'Cashier', 'cashier123', 'CASHIER');

INSERT INTO rms_tables (table_number, capacity, table_status) VALUES
(1, 4, 'AVAILABLE'),
(2, 4, 'AVAILABLE'),
(3, 6, 'AVAILABLE'),
(4, 2, 'AVAILABLE'),
(5, 8, 'AVAILABLE');

INSERT INTO menu_items (name, category, description, price, availability) VALUES
('Fried Rice', 'Rice', 'Chicken fried rice', 5000, TRUE),
('Noodle Soup', 'Noodle', 'Hot noodle soup', 4500, TRUE),
('Chicken Curry', 'Curry', 'Myanmar chicken curry', 7000, TRUE),
('Tea', 'Drink', 'Hot tea', 1500, TRUE),
('Coffee', 'Drink', 'Hot coffee', 2500, TRUE);

INSERT INTO inventory_items (item_name, quantity, minimum_stock, supplier_name, unit_type) VALUES
('Rice', 50, 10, 'Local Supplier', 'KILOGRAM'),
('Chicken', 20, 5, 'Meat Supplier', 'KILOGRAM'),
('Noodle', 30, 8, 'Noodle Supplier', 'KILOGRAM'),
('Tea Powder', 10, 2, 'Drink Supplier', 'KILOGRAM'),
('Coffee Powder', 8, 2, 'Drink Supplier', 'KILOGRAM');