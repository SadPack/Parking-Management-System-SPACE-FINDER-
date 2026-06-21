# Parking Management System (SPACE FINDER)

### Project Overview

Parking Management System is a web application developed using PHP, MySQL, HTML, CSS, and JavaScript. The project demonstrates complete CRUD operations along with fundamental web application security practices. The system supports two user roles, Customer and Admin, each with their own set of features.

#### CRUD Operations

* Users (customers): create via self-registration, read via admin search, update via account disable/enable, delete via admin
* Vehicles: create via admin vehicle entry, read via admin and customer views, update via marking a vehicle as exited, delete via admin
* Payments: create automatically on vehicle entry, read via admin and customer views, update via customer marking a payment as paid

####Security Features

* Passwords are hashed using bcrypt (PHP password_hash and password_verify), never stored in plaintext
* All database queries use parameterized prepared statements to prevent SQL Injection
* All dynamic output is escaped with htmlspecialchars to prevent Cross-Site Scripting (XSS)
* CSRF tokens are generated per session and verified on every form submission
* Role-Based Access Control is enforced server-side (require_admin and require_customer functions), not just hidden in the interface
* Payment actions verify record ownership before processing, preventing one user from accessing another user's data
* Login attempts are tracked and temporarily locked out after repeated failures
* Session ID is regenerated on every successful login to prevent session fixation
* Disabled accounts are blocked at login even with the correct password

#### Customer Features

* Register, login, and logout
* View currently parked vehicles
* Make a payment for a parking session
* View parking history and payment history

#### Admin Features

* Add a vehicle entry and assign it to a customer and parking slot
* Mark a vehicle as exited, which frees the slot
* Delete vehicle records
* Search, disable, re-enable, or delete customer accounts
* View a dashboard with occupancy, revenue, and recent activity

#### Technologies Used

* PHP
* MySQL
* HTML
* CSS
* JavaScript

#### How to Run the Project

1. Clone or download this project into your XAMPP or WAMP htdocs folder.
2. Start Apache and MySQL.
3. Open phpMyAdmin and run the database/schema.sql file to create the database, tables, and seed data.
4. Open config/db.php and update the database credentials if needed.
5. Open a browser and go to http://localhost/your-project-folder-name/login.php

Demo Admin Account

Email: admin@parking.com
Password: Admin1234

