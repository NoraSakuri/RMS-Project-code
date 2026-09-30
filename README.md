## Restaurant Management System (RMS)
## Project Title
Design and Development of a Web-Based Restaurant Management System for Efficient Restaurant Operations
## Overview
This project is a web-based Restaurant Management System (RMS) developed to support daily restaurant operations in one integrated platform. The system provides role-based access for Admin, Manager, Waiter, Chef, and Cashier users.

The RMS includes functions for user management, menu and category management, customer orders, kitchen operations, billing and payment, inventory, restaurant tables and reservations, and business reports.
## Main Features
### Admin
- Manage staff accounts and approve user registrations  
- Manage all system users and assign or change user roles  
- Activate, deactivate, or update user accounts  
- Manage menu items and categories  
- Manage inventory  
- Manage tables and reservations  
- Manage kitchen and order information  
- Access billing and payment information  
- View and export reports
### Manager
- View dashboard information
- Manage menu items and categories
- Manage inventory
- Manage tables and reservations
- Manage orders and kitchen status
- View and export reports
### Waiter
- View available tables
- Create and update customer orders
- Send orders to the kitchen
- Check order and kitchen status
- Manage table and reservation information where permitted
### Chef
- View incoming kitchen orders
- Accept and prepare orders
- Update preparation status
- Mark orders as ready
### Cashier
- View ready orders
- Generate bills
- Process payments
- View payment history
- View and print invoices
## Technologies Used
- HTML5
- CSS3
- JavaScript
- PHP
- MySQL
- PDO
- XAMPP / Apache
- phpMyAdmin
- Composer
- Dompdf
- PhpSpreadsheet
## Project Structure
```
RMS Project Code/
├── config/
│   ├── config.php
│   └── database.php
├── database/
│   └── rms_db.sql
├── src/
│   ├── front-end/
│   │   ├── admin/
│   │   ├── cashier/
│   │   ├── chef/
│   │   ├── manager/
│   │   ├── waiter/
│   │   ├── components/
│   │   ├── assets/
│   │   └── index.php
│   └── back-end/
│       ├── api/
│       ├── controllers/
│       ├── middleware/
│       └── models/
├── composer.json
├── composer.lock
└── vendor/
```
## System Requirements
- Apache web server
- MySQL or MariaDB
- PHP 8.2 or later
- Composer
- A modern web browser such as Chrome, Edge, Firefox, or Safari
## Installation
1. Copy the project folder into the XAMPP htdocs directory.
2. Start Apache and MySQL from XAMPP.
3. Open phpMyAdmin.
4. Import the database file:
   database/rms_db.sql
5. Check the database settings in:
   config/database.php
6. The default configuration uses:
   - Host: localhost
   - Database: restaurant_management_system
   - Username: root
   - Password: empty
7. From the project directory, install Composer dependencies if required:
composer install
8. Open the system in a browser. For example, if the project folder is named RMS Project Code:
http://localhost/RMS%20Project%20Code/src/front-end/index.php
Default Demonstration Accounts
Role	Username	Password
Admin	Admin	admin123
Manager	Manager	manager123
Chef	Chef	chef123
Waiter	Waiter	waiter123
Cashier	Cashier	cashier123


These accounts are included for local demonstration and testing. Passwords should be changed in a real deployment.
## Registration and Access Control
New staff can register through the registration page. Public registration supports Manager, Waiter, Chef, and Cashier roles. A newly registered account remains inactive until it is approved by an administrator.
The system uses session-based authentication and role-based access control to restrict pages and functions according to the user's role.
## Reports
The reporting module supports restaurant business information such as sales and payment data. Report export functions use:
- Dompdf for PDF output
- PhpSpreadsheet for Excel output
## Database
The project database is named:
restaurant_management_system
The supplied SQL file creates the core database tables and demonstration data.
## Notes
This project was developed for an academic Computing Project and is intended for local demonstration using XAMPP.
Before final submission or deployment, make sure that database/rms_db.sql is synchronized with the final PHP code and that all tables and columns used by the application are included in the SQL file.
## Author
Phyo Sandar Htun
NCC Education Level 5 Diploma in Computing
NVL College