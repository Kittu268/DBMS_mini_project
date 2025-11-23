# DBMS_mini_project
# ✈️ Airline Reservation System (PHP + MySQL)

A complete Airline Reservation System developed using **PHP, MySQL, HTML, CSS, JavaScript, and Bootstrap** with a modern **Admin Dashboard**, **SQL Workbench**, **ERD Viewer**, **AI SQL Assistant**, and a full **Flight Booking Module** for users.

---

## 🚀 Features

### 👤 User Side
- Search available flights
- Check seat availability
- Book tickets for any flight leg
- Auto-calculated fare based on class
- Payment success redirection to “View Reservations”
- View & cancel reservations
- Modern UI with animated flight cards

### 🔧 Admin Panel (Advanced)
- Dashboard with statistics
- Manage Flights, Legs, Airports, Airplanes, Seats
- SQL Explorer (Workbench)
- SQL Beautifier + Autocomplete
- AI SQL Assistant (automatically fixes and generates SQL)
- Schema Browser
- ERD Diagram Viewer
- Export Data (CSV / JSON)
- Dynamic SQL Insert Generator
- Available Flights SQL Generator (ADD / UPDATE / DELETE / READ)
- Full CRUD on all tables

---

## 🏗️ Tech Stack

| Layer | Technology |
|------|------------|
| Frontend | HTML, CSS, Bootstrap 5, JavaScript |
| Backend | PHP (Procedural + Prepared Statements) |
| Database | MySQL (XAMPP / MariaDB) |
| Admin Panel UI | Custom CSS + Modern Glass UI |
| Tools | AJAX, Clipboard API, JSON Metadata |

---
    Directory: C:\xampp\htdocs\airline_project


Mode                 LastWriteTime         Length Name
----                 -------------         ------ ----
d-----        23-11-2025  09:32 AM                admin
d-----        19-11-2025  02:50 PM                assets
d-----        20-11-2025  12:08 AM                chat_history
d-----        11-11-2025  11:21 AM                images
d-----        19-11-2025  01:32 AM                includes
d-----        19-11-2025  12:39 AM                tcpdf_min
-a----        23-11-2025  09:46 AM          11704 airline.sql
-a----        19-11-2025  02:47 PM          14513 ai_assistant_api.php
-a----        19-11-2025  02:56 PM           5329 ai_chat.php
-a----        11-11-2025  08:50 AM          10952 all_tables.php
-a----        23-11-2025  09:37 AM           7797 available_flights.php
-a----        20-11-2025  05:01 PM           5741 cancel_reservation.php
-a----        07-11-2025  02:35 AM            108 clear_chat.php
-a----        09-11-2025  10:32 PM          73569 code_appendix.txt
-a----        02-11-2025  06:05 PM           3248 complex_queries.php
-a----        07-11-2025  02:16 AM            461 config.php
-a----        11-11-2025  08:28 PM            296 db.php
-a----        18-11-2025  10:07 PM           4711 global-styles.css
-a----        22-11-2025  01:50 AM           8467 header.php
-a----        19-11-2025  02:59 PM           2750 index.php
-a----        03-11-2025  01:52 PM           1747 insert_row.php
-a----        15-11-2025  07:03 PM           4189 login.php
-a----        03-11-2025  01:57 PM             90 logout.php
-a----        23-11-2025  10:55 AM          24545 make_reservation.php
-a----        23-11-2025  09:49 AM           6733 payment.php
-a----        23-11-2025  12:56 AM           5143 payment_process.php
-a----        23-11-2025  10:58 AM           2216 payment_success.php
-a----        19-11-2025  06:04 PM           3802 profile.php
-a----        12-11-2025  01:14 AM          11316 query_executor.php
-a----        11-11-2025  12:05 PM           6069 reservation.php
-a----        02-11-2025  06:02 PM           2570 reserve.php
-a----        23-11-2025  02:16 AM           1684 signup.php
-a----        23-11-2025  10:40 AM           6681 style.css
-a----        19-11-2025  12:28 AM           4862 ticket.php
-a----        02-11-2025  06:33 PM            879 update_cell.php
-a----        23-11-2025  10:00 AM            966 verify_payment.php
-a----        23-11-2025  11:03 AM           3474 view_reservations.php
for admin 
Mode                 LastWriteTime         Length Name
----                 -------------         ------ ----
d-----        19-11-2025  10:33 PM                assets
d-----        20-11-2025  02:51 AM                includes
d-----        19-11-2025  01:41 PM                partials
-a----        22-11-2025  11:47 PM          14366 analytics.php
-a----        20-11-2025  09:56 PM           2420 cancel_reservation.php
-a----        22-11-2025  02:41 PM           8975 dashboard.php
-a----        19-11-2025  10:06 PM           2981 flights.php
-a----        23-11-2025  11:54 AM          29755 help.php
-a----        19-11-2025  02:15 PM            218 index.php
-a----        19-11-2025  02:16 PM           2914 login.php
-a----        19-11-2025  01:45 PM             98 logout.php
-a----        19-11-2025  01:46 PM            625 README.txt
-a----        20-11-2025  09:53 PM           7644 reservations.php
-a----        23-11-2025  11:44 AM          11808 sql.php
-a----        20-11-2025  12:20 AM           8096 sql_ai.php
-a----        21-11-2025  12:36 PM          24014 sql_ai_assistant.php
-a----        20-11-2025  12:32 AM           6650 sql_data_api.php
-a----        20-11-2025  03:49 PM           5483 sql_erd.php
-a----        20-11-2025  12:37 AM           4513 sql_execute.php
-a----        20-11-2025  12:30 AM           2865 sql_format.php
-a----        19-11-2025  10:39 PM            592 sql_history.log
-a----        20-11-2025  12:51 PM           2593 sql_schema.php
-a----        20-11-2025  12:34 AM           8711 sql_structure_api.php
-a----        21-11-2025  12:31 PM           4027 test.html
-a----        19-11-2025  10:04 PM           2521 users.php


---

## 🛢️ Database Schema (Important Tables)

### `flight`
- Flight_number (PK)
- Airline
- Duration

### `flight_leg`
- Flight_number (FK)
- Leg_no (PK)
- Departure_airport_code
- Arrival_airport_code
- Departure_time
- Arrival_time

### `leg_instance`
- Flight_number (FK)
- Leg_no (FK)
- Date
- Number_of_available_seats
- Airplane_id

### `reservation`
- Reservation_id
- User_id
- Flight_number
- Leg_no
- Date
- Seat_no
- Fare_paid

### `fare`
- Flight_number
- seat_class
- base_fare
- currency

### `seat`
- Airplane_id
- Seat_no
- class_id

---

## ⚙️ Installation (Local)

### Step 1 — Install XAMPP
Download from https://apachefriends.org

### Step 2 — Clone Project
https://github.com/Kittu268/DBMS_mini_prject

### Step 3 — Import Database
1. Open `http://localhost/phpmyadmin`
2. Create database **airline**
3. Import the `airline.sql` file from the project folder

### Step 4 — Configure DB Connection
Edit:db.php


Set:
```php
$host = "localhost";
$user = "root";
$pass = "";
$db   = "airline";

Open:

http://localhost/airline_project/
Default admin URL:

http://localhost/airline_project/admin/
## 🗂️ Project Structure

