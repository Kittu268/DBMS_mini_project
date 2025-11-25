-- airline_updated.sql
-- Rebuild Airline Reservation DB (DDL + sample data)
-- This file is an updated, fixed and extended version of your original airline.sql.
-- Fixes syntax errors, adds cancellations and revenue_log tables, and corrects flight_price seeding query.

SET FOREIGN_KEY_CHECKS = 0;
DROP DATABASE IF EXISTS airline;
CREATE DATABASE airline CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE airline;
SET FOREIGN_KEY_CHECKS = 1;

-- 1) users
CREATE TABLE users (
  id INT PRIMARY KEY AUTO_INCREMENT,
  username VARCHAR(100) NOT NULL,
  email VARCHAR(255) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  is_admin TINYINT(1) DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2) airport
CREATE TABLE airport (
  Airport_code VARCHAR(8) PRIMARY KEY,
  Name VARCHAR(200) NOT NULL,
  City VARCHAR(100) NOT NULL,
  State VARCHAR(100) DEFAULT NULL,
  Country VARCHAR(100) DEFAULT 'India',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3) airplane_type
CREATE TABLE airplane_type (
  Type_name VARCHAR(100) PRIMARY KEY,
  Max_seats INT NOT NULL,
  Company VARCHAR(100),
  description TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4) airplane
CREATE TABLE airplane (
  Airplane_id VARCHAR(50) PRIMARY KEY,
  Total_no_of_seats INT NOT NULL,
  Type_name VARCHAR(100) NOT NULL,
  registration_no VARCHAR(50),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (Type_name) REFERENCES airplane_type(Type_name) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5) seat_class (economy/Business/First)
CREATE TABLE seat_class (
  Seat_class_id INT PRIMARY KEY AUTO_INCREMENT,
  Class_name VARCHAR(50) NOT NULL UNIQUE,
  description VARCHAR(255),
  multiplier DECIMAL(5,2) DEFAULT 1.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6) seat (individual seat per airplane)
CREATE TABLE seat (
  id INT PRIMARY KEY AUTO_INCREMENT,
  Airplane_id VARCHAR(50) NOT NULL,
  Seat_no VARCHAR(10) NOT NULL,
  Seat_class_id INT DEFAULT NULL,
  is_window TINYINT(1) DEFAULT 0,
  is_aisle TINYINT(1) DEFAULT 0,
  CONSTRAINT ux_seat UNIQUE (Airplane_id, Seat_no),
  FOREIGN KEY (Airplane_id) REFERENCES airplane(Airplane_id) ON UPDATE CASCADE ON DELETE CASCADE,
  FOREIGN KEY (Seat_class_id) REFERENCES seat_class(Seat_class_id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7) flight (logical flight identifier)
CREATE TABLE flight (
  Flight_number VARCHAR(30) PRIMARY KEY,
  Airline VARCHAR(120),
  Flight_name VARCHAR(150),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 8) flight_leg (defines ordered legs for a flight)
CREATE TABLE flight_leg (
  id INT PRIMARY KEY AUTO_INCREMENT,
  Flight_number VARCHAR(30) NOT NULL,
  Leg_no INT NOT NULL,
  Departure_airport_code VARCHAR(8) NOT NULL,
  Arrival_airport_code VARCHAR(8) NOT NULL,
  scheduled_duration TIME DEFAULT NULL,
  CONSTRAINT ux_flight_leg UNIQUE (Flight_number, Leg_no),
  FOREIGN KEY (Flight_number) REFERENCES flight(Flight_number) ON UPDATE CASCADE ON DELETE CASCADE,
  FOREIGN KEY (Departure_airport_code) REFERENCES airport(Airport_code) ON UPDATE CASCADE ON DELETE RESTRICT,
  FOREIGN KEY (Arrival_airport_code) REFERENCES airport(Airport_code) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 9) leg_instance (a specific flight leg occurrence on a date)
CREATE TABLE leg_instance (
  id INT PRIMARY KEY AUTO_INCREMENT,
  Flight_number VARCHAR(30) NOT NULL,
  Leg_no INT NOT NULL,
  Date DATE NOT NULL,
  Number_of_available_seats INT DEFAULT 0,
  Airplane_id VARCHAR(50) DEFAULT NULL,
  Departure_airport_code VARCHAR(8),
  Departure_time TIME DEFAULT NULL,
  Arrival_airport_code VARCHAR(8),
  Arrival_time TIME DEFAULT NULL,
  CONSTRAINT ux_leg_instance UNIQUE (Flight_number, Leg_no, Date),
  FOREIGN KEY (Flight_number) REFERENCES flight(Flight_number) ON UPDATE CASCADE ON DELETE CASCADE,
  FOREIGN KEY (Airplane_id) REFERENCES airplane(Airplane_id) ON UPDATE CASCADE ON DELETE SET NULL,
  FOREIGN KEY (Departure_airport_code) REFERENCES airport(Airport_code) ON UPDATE CASCADE ON DELETE SET NULL,
  FOREIGN KEY (Arrival_airport_code) REFERENCES airport(Airport_code) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 10) reservation
CREATE TABLE reservation (
  id INT PRIMARY KEY AUTO_INCREMENT,
  Flight_number VARCHAR(30) NOT NULL,
  Leg_no INT NOT NULL,
  Date DATE NOT NULL,
  Airplane_id VARCHAR(50) DEFAULT NULL,
  Seat_no VARCHAR(10) NOT NULL,
  Customer_name VARCHAR(200) NOT NULL,
  Cphone VARCHAR(50) DEFAULT NULL,
  Email VARCHAR(255) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  reservation_created_at TIMESTAMP NULL,
  cancellation_status VARCHAR(50) DEFAULT NULL,
  fare DECIMAL(10,2) DEFAULT 0.00,
  Airplane_class VARCHAR(50) DEFAULT NULL,
  FOREIGN KEY (Flight_number) REFERENCES flight(Flight_number) ON UPDATE CASCADE ON DELETE CASCADE,
  FOREIGN KEY (Airplane_id) REFERENCES airplane(Airplane_id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 11) fare (base fares per route/seat class)
CREATE TABLE fare (
  fare_id INT PRIMARY KEY AUTO_INCREMENT,
  Flight_number VARCHAR(30) DEFAULT NULL,
  Leg_no INT DEFAULT NULL,
  Seat_class_id INT DEFAULT NULL,
  Base_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  Currency VARCHAR(8) DEFAULT 'INR',
  valid_from DATE DEFAULT NULL,
  valid_to DATE DEFAULT NULL,
  FOREIGN KEY (Flight_number) REFERENCES flight(Flight_number) ON UPDATE CASCADE ON DELETE SET NULL,
  FOREIGN KEY (Seat_class_id) REFERENCES seat_class(Seat_class_id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 12) dynamic_fare (overrides/promotions for date ranges or occupancy)
CREATE TABLE dynamic_fare (
  dynamic_id INT PRIMARY KEY AUTO_INCREMENT,
  fare_id INT NOT NULL,
  Date DATE NOT NULL,
  multiplier DECIMAL(5,2) DEFAULT 1.00,
  reason VARCHAR(255),
  base_fare DECIMAL(10,2) DEFAULT NULL,
  final_fare DECIMAL(10,2) DEFAULT NULL,
  demand_factor DECIMAL(5,2) DEFAULT NULL,
  seat_class VARCHAR(50) DEFAULT NULL,
  flight_number VARCHAR(30) DEFAULT NULL,
  travel_date DATE DEFAULT NULL,
  FOREIGN KEY (fare_id) REFERENCES fare(fare_id) ON UPDATE CASCADE ON DELETE CASCADE,
  UNIQUE (fare_id, Date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 13) payments (transactions)
CREATE TABLE payments (
  payment_id INT PRIMARY KEY AUTO_INCREMENT,
  reservation_id INT DEFAULT NULL,
  Email VARCHAR(255) DEFAULT NULL,
  amount DECIMAL(10,2) NOT NULL,
  currency VARCHAR(8) DEFAULT 'INR',
  status VARCHAR(50) DEFAULT 'pending',
  provider VARCHAR(100) DEFAULT NULL,
  transaction_ref VARCHAR(255) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (reservation_id) REFERENCES reservation(id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 14) payment_price (per fare snapshots / price components)
CREATE TABLE payment_price (
  price_id INT PRIMARY KEY AUTO_INCREMENT,
  fare_id INT DEFAULT NULL,
  reservation_id INT DEFAULT NULL,
  base_amount DECIMAL(10,2) DEFAULT 0.00,
  taxes DECIMAL(10,2) DEFAULT 0.00,
  fee DECIMAL(10,2) DEFAULT 0.00,
  total_amount DECIMAL(10,2) AS (COALESCE(base_amount,0)+COALESCE(taxes,0)+COALESCE(fee,0)) PERSISTENT,
  Currency VARCHAR(8) DEFAULT 'INR',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (fare_id) REFERENCES fare(fare_id) ON UPDATE CASCADE ON DELETE SET NULL,
  FOREIGN KEY (reservation_id) REFERENCES reservation(id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 15) flight_price (pre-calculated quick lookup)
CREATE TABLE IF NOT EXISTS flight_price (
  id INT AUTO_INCREMENT PRIMARY KEY,
  Flight_number VARCHAR(30) NOT NULL,
  price DECIMAL(10,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 16) cancellations (refund tracking)
CREATE TABLE IF NOT EXISTS cancellations (
  id INT PRIMARY KEY AUTO_INCREMENT,
  reservation_id INT NOT NULL,
  refund_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  refund_pct DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  days_left INT NOT NULL DEFAULT 0,
  cancelled_on DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (reservation_id) REFERENCES reservation(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 17) revenue_log (simple accounting ledger)
CREATE TABLE IF NOT EXISTS revenue_log (
  id INT PRIMARY KEY AUTO_INCREMENT,
  reservation_id INT DEFAULT NULL,
  amount DECIMAL(12,2) NOT NULL,
  type VARCHAR(32) NOT NULL, -- 'fare' or 'refund' or others
  created_on DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (reservation_id) REFERENCES reservation(id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Indexes
CREATE INDEX idx_reservation_email ON reservation (Email);
CREATE INDEX idx_leginstance_date ON leg_instance (Date);
CREATE INDEX idx_flight_airline ON flight (Airline);

-- SAMPLE DATA
-- NOTE: Replace {PASSWORD_HASH} with a password_hash() output from PHP, or use signup page to create a user.
INSERT INTO users (username, email, password, is_admin) VALUES
('admin','admin@example.com','{PASSWORD_HASH}',1);

INSERT INTO users (username, email, password, is_admin) VALUES
('mahesh','mahesh@example.com','{PASSWORD_HASH}',0);

-- Airports
INSERT INTO airport (Airport_code, Name, City, State, Country) VALUES
('BLR','Kempegowda Intl','Bengaluru','Karnataka','India'),
('DEL','Indira Gandhi Intl','Delhi','Delhi','India'),
('MYS','Mysuru Airport','Mysuru','Karnataka','India');

-- Airplane types
INSERT INTO airplane_type (Type_name, Max_seats, Company, description) VALUES
('Airbus-A320',180,'Airbus','Narrow-body A320 family'),
('Boeing-737',189,'Boeing','B737 family');

-- Airplanes
INSERT INTO airplane (Airplane_id, Total_no_of_seats, Type_name, registration_no) VALUES
('A320-001',180,'Airbus-A320','VT-AAA'),
('B737-001',189,'Boeing-737','VT-BBB');

-- Seat classes
INSERT INTO seat_class (Class_name, description, multiplier) VALUES
('Economy','Standard economy class',1.00),
('Business','Business class',1.80),
('First','First class',2.50);

-- Seats (a few)
INSERT INTO seat (Airplane_id, Seat_no, Seat_class_id, is_window, is_aisle) VALUES
('A320-001','14A',1,1,0),
('A320-001','14B',1,0,0),
('A320-001','14C',1,0,1),
('B737-001','1A',3,1,0),
('B737-001','1B',3,0,1);

-- Flights
INSERT INTO flight (Flight_number, Airline, Flight_name) VALUES
('AI101','Air India','AI101 BLR-DEL'),
('SJ201','SpiceJet','SJ201 BLR-MYS');

-- Flight legs
INSERT INTO flight_leg (Flight_number, Leg_no, Departure_airport_code, Arrival_airport_code, scheduled_duration) VALUES
('AI101',1,'BLR','DEL','02:30:00'),
('SJ201',1,'BLR','MYS','00:45:00');

-- Leg instances (scheduled occurrences)
INSERT INTO leg_instance (Flight_number, Leg_no, Date, Number_of_available_seats, Airplane_id, Departure_airport_code, Departure_time, Arrival_airport_code, Arrival_time) VALUES
('AI101',1,DATE_ADD(CURDATE(), INTERVAL 2 DAY),150,'A320-001','BLR','10:00:00','DEL','12:30:00'),
('SJ201',1,DATE_ADD(CURDATE(), INTERVAL 3 DAY),100,'B737-001','BLR','15:00:00','MYS','15:45:00');

-- Fare and dynamic fare
INSERT INTO fare (Flight_number, Leg_no, Seat_class_id, Base_price, Currency, valid_from, valid_to) VALUES
('AI101',1,1,3500.00,'INR',CURDATE(),DATE_ADD(CURDATE(), INTERVAL 180 DAY)),
('SJ201',1,1,1200.00,'INR',CURDATE(),DATE_ADD(CURDATE(), INTERVAL 180 DAY));

INSERT INTO dynamic_fare (fare_id, Date, multiplier, reason, base_fare, final_fare) VALUES
(1, DATE_ADD(CURDATE(), INTERVAL 2 DAY), 1.15, 'Weekend demand', 3500.00, ROUND(3500.00 * 1.15,2));

-- Sample reservation (no payment yet)
INSERT INTO reservation (Flight_number, Leg_no, Date, Airplane_id, Seat_no, Customer_name, Cphone, Email, reservation_created_at, fare, Airplane_class) VALUES
('AI101',1,DATE_ADD(CURDATE(), INTERVAL 2 DAY),'A320-001','14A','Test User','9999999999','testuser@example.com', NOW(), 3500.00, 'Economy');

-- Sample payment_price (snapshot)
INSERT INTO payment_price (fare_id, reservation_id, base_amount, taxes, fee, Currency) VALUES
(1, 1, 3500.00, 250.00, 50.00, 'INR');

-- Sample payments
INSERT INTO payments (reservation_id, Email, amount, currency, status, provider, transaction_ref) VALUES
(1, 'testuser@example.com', 3800.00, 'INR', 'completed', 'test_gateway', 'TXN123456');

-- Seed flight_price from fare base prices (corrected query)
INSERT INTO flight_price (Flight_number, price)
SELECT Flight_number, ROUND(MIN(Base_price),2) as min_price
FROM fare
GROUP BY Flight_number
ON DUPLICATE KEY UPDATE price = VALUES(price);

-- Final housekeeping
ANALYZE TABLE flight, reservation, leg_instance, users;

-- Optional: add `name` column to users if not exists (safe alter)
ALTER TABLE users ADD COLUMN IF NOT EXISTS `name` VARCHAR(100) NULL AFTER username;

-- Create view to alias reservation id if needed by old PHP files
CREATE OR REPLACE VIEW reservation_view AS
SELECT id AS reservation_id, * FROM reservation;
