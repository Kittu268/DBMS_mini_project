# ✈️ Airline Reservation System (PHP + MySQL)

A complete Airline Reservation System built using **PHP, MySQL, HTML, CSS, JavaScript, and Bootstrap**.  
Includes a full **Admin Dashboard**, **SQL Workbench**, **ERD Viewer**, **AI SQL Assistant**, and a complete **Flight Booking Module** for end‑users.

---

## 🚀 Features

### 👤 User Side
- Search available flights  
- Real‑time seat availability  
- Book tickets for any flight leg  
- Automatic fare calculation  
- Payment gateway simulation  
- Automatic redirect to **View Reservations** after payment  
- View & cancel bookings  
- Beautiful UI with animated airplane & cloud backgrounds  

---

### 🔧 Admin Panel (Advanced)
✔ Modern Glass UI Dashboard  
✔ Manage Flights, Legs, Airports, Airplanes, Seats  
✔ SQL Workbench (Editor + Results + Export)  
✔ SQL Beautifier & Autocomplete  
✔ **AI SQL Assistant**  
✔ Schema Browser  
✔ ERD Diagram Viewer  
✔ SQL History Log  
✔ Export CSV, JSON  
✔ Dynamic SQL Insert Generators  
✔ Available Flights SQL CRUD Generator  
✔ Full CRUD on all airline database tables  

---

## 🏗️ Tech Stack

| Layer | Technology |
|-------|------------|
| Frontend | HTML, CSS, Bootstrap 5, JavaScript |
| Backend | PHP (Procedural + Prepared Statements) |
| Database | MySQL / MariaDB |
| Admin Panel | Custom CSS (Glass UI), JS, SQL APIs |
| Tools | AJAX, Clipboard API, JSON Metadata |

---

## 📦 Project Structure

```
airline_project/
│
├── admin/
│   ├── dashboard.php
│   ├── flights.php
│   ├── reservations.php
│   ├── sql.php
│   ├── help.php
│   └── ... admin tools
│
├── includes/
│   ├── background.php
│   ├── background_elements.php
│   └── auth_check.php
│
├── assets/
│   ├── bootstrap.min.css
│   ├── js/
│   └── images/
│
├── tcpdf_min/         # PDF Ticket Generator
├── images/
├── chat_history/
│
├── index.php
├── available_flights.php
├── make_reservation.php
├── reservation.php
├── view_reservations.php
├── cancel_reservation.php
├── payment.php
├── payment_process.php
├── payment_success.php
├── ticket.php
│
├── airline.sql        # Database
└── README.md
```

---

## 🛢️ Database Schema (Main Tables)

### **flight**
- Flight_number (PK)
- Airline
- Duration

### **flight_leg**
- Flight_number (FK)
- Leg_no (PK)
- Departure_airport_code  
- Arrival_airport_code  
- Departure_time  
- Arrival_time  

### **leg_instance**
- Flight_number (FK)
- Leg_no (FK)
- Date  
- Airplane_id  
- Number_of_available_seats  

### **reservation**
- reservation_id (PK)
- Flight_number  
- Leg_no  
- Date  
- Seat_no  
- Customer_name  
- Email  
- payment_status  
- fare  

### **fare**
- Flight_number  
- seat_class  
- base_fare  

### **seat**
- Airplane_id  
- Seat_no  
- class_id  

---

## ⚙️ Installation (Local Setup)

### **Step 1 — Install XAMPP**
Download from: https://www.apachefriends.org

---

### **Step 2 — Clone Repository**
```bash
git clone https://github.com/Kittu268/DBMS_mini_project.git
```

Or download ZIP from GitHub.

---

### **Step 3 — Import Database**
1. Open **phpMyAdmin**
2. Create database:  
   ```
   airline
   ```
3. Import:
   ```
   airline.sql
   ```

---

### **Step 4 — Configure DB Connection**
Edit **db.php**:

```php
$host = "localhost";
$user = "root";
$pass = "";
$db   = "airline";
```

---

### **Step 5 — Run Project**

User side:
```
http://localhost/airline_project/
```

Admin panel:
```
http://localhost/airline_project/admin/
```

---

## 📘 Additional Features

### ✔ PDF Ticket Generation
Auto‑generated e‑ticket using **TCPDF**, including:
- Passenger details  
- Flight info  
- Seat info  
- QR Code  
- Issue timestamp  

---

### ✔ AI SQL Assistant (Admin)
- Fix broken SQL  
- Generate CRUD queries  
- Describe tables  
- Generate joins  
- Optimize queries  

---

## 💡 Future Enhancements (Optional)
- Add JWT‑based API backend  
- Add real payment integration (Razorpay/Stripe)  
- Add dynamic seat map UI  
- Add flight search filters  
- Add email sending & OTP  

---

## 🤝 Contributing
Pull requests are welcome!  
For major changes, open an issue first.

---

## 📜 License
This project is open-source under the **MIT License**.

---

✈️ **Airline Reservation System — Developed with ❤️ by Kittu268**
