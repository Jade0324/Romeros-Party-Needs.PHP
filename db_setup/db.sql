-- ==========================================================================
-- Romero's Party Needs — database schema + demo data
--
-- Import this file in phpMyAdmin (Import tab) or run:
--   mysql -u root -P 3307 < db_setup/db.sql
--
-- WARNING: re-importing DROPS and recreates every table below, so any
-- bookings/users you created while testing will be reset to the demo data.
-- ==========================================================================

SET NAMES utf8mb4;

CREATE DATABASE IF NOT EXISTS romeros_party_db
  CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE romeros_party_db;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS booking_logs;
DROP TABLE IF EXISTS payments;
DROP TABLE IF EXISTS appointments;
DROP TABLE IF EXISTS booking_services;
DROP TABLE IF EXISTS booking_items;
DROP TABLE IF EXISTS bookings;
DROP TABLE IF EXISTS blocked_dates;
DROP TABLE IF EXISTS services;
DROP TABLE IF EXISTS inventory_items;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS inquiries;
DROP TABLE IF EXISTS login_attempts;
DROP TABLE IF EXISTS users;
SET FOREIGN_KEY_CHECKS = 1;

-- --------------------------------------------------------------------------
-- Accounts. role = 'admin' unlocks the /admin pages.
-- --------------------------------------------------------------------------
CREATE TABLE users (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  full_name   VARCHAR(100) NOT NULL,
  email       VARCHAR(150) NOT NULL UNIQUE,
  phone       VARCHAR(30)  NULL,
  password    VARCHAR(255) NOT NULL,              -- password_hash() output
  role        ENUM('customer','admin') NOT NULL DEFAULT 'customer',
  is_active   TINYINT(1) NOT NULL DEFAULT 1,      -- 0 = deactivated, can't log in
  last_login_at DATETIME NULL,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Failed logins, used to slow down password guessing (see login-form.php).
CREATE TABLE login_attempts (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email        VARCHAR(150) NOT NULL,
  ip_address   VARCHAR(45)  NOT NULL,
  attempted_at DATETIME NOT NULL,
  INDEX idx_attempt_email (email, attempted_at),
  INDEX idx_attempt_ip (ip_address, attempted_at)
) ENGINE=InnoDB;

-- --------------------------------------------------------------------------
-- Inventory: what we physically own and rent out.
-- "Available on a date" is never stored — it's always calculated as
-- total_quantity minus what pending/confirmed bookings on that date hold.
-- --------------------------------------------------------------------------
CREATE TABLE categories (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(60)  NOT NULL,
  slug        VARCHAR(60)  NOT NULL UNIQUE,
  description VARCHAR(255) NULL,
  image       VARCHAR(255) NULL,
  sort_order  INT NOT NULL DEFAULT 0
) ENGINE=InnoDB;

CREATE TABLE inventory_items (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  category_id    INT UNSIGNED NOT NULL,
  name           VARCHAR(100) NOT NULL,
  description    VARCHAR(255) NULL,
  unit           VARCHAR(20)  NOT NULL DEFAULT 'piece',
  price          DECIMAL(10,2) NOT NULL,          -- per unit, per event day
  total_quantity INT UNSIGNED NOT NULL DEFAULT 0,
  image          VARCHAR(255) NULL,
  is_active      TINYINT(1) NOT NULL DEFAULT 1,   -- 0 = hidden from new bookings
  created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_item_category FOREIGN KEY (category_id) REFERENCES categories(id)
) ENGINE=InnoDB;

-- --------------------------------------------------------------------------
-- People-based services (handled by partner South Flaneur Events).
-- daily_capacity = how many events that team can cover on the same date.
-- --------------------------------------------------------------------------
CREATE TABLE services (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name           VARCHAR(100) NOT NULL,
  description    VARCHAR(255) NULL,
  price          DECIMAL(10,2) NOT NULL,
  daily_capacity INT UNSIGNED NOT NULL DEFAULT 1,
  provider       VARCHAR(100) NOT NULL DEFAULT 'South Flaneur Events',
  image          VARCHAR(255) NULL,
  is_active      TINYINT(1) NOT NULL DEFAULT 1,
  created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- --------------------------------------------------------------------------
-- Dates the business closes to new bookings/appointments (holidays,
-- family events, crew already stretched, etc.)
-- --------------------------------------------------------------------------
CREATE TABLE blocked_dates (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  blocked_date DATE NOT NULL UNIQUE,
  reason       VARCHAR(255) NULL,
  created_by   INT UNSIGNED NULL,
  created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_block_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- --------------------------------------------------------------------------
-- Bookings = one event. Rentals and services hang off it.
-- Prices are copied into booking_items/booking_services at booking time so
-- later price changes don't rewrite old bookings.
-- --------------------------------------------------------------------------
CREATE TABLE bookings (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  reference      VARCHAR(20) NOT NULL UNIQUE,
  user_id        INT UNSIGNED NOT NULL,
  event_type     VARCHAR(50)  NOT NULL,
  event_date     DATE NOT NULL,
  setup_time     TIME NOT NULL,                   -- when our crew arrives to set up
  event_start    TIME NOT NULL,
  event_end      TIME NOT NULL,                   -- pickup/teardown starts after this
  venue_address  VARCHAR(255) NOT NULL,
  guest_count    INT UNSIGNED NOT NULL DEFAULT 0,
  notes          TEXT NULL,
  status         ENUM('pending','confirmed','completed','cancelled') NOT NULL DEFAULT 'pending',
  items_total    DECIMAL(10,2) NOT NULL DEFAULT 0,
  services_total DECIMAL(10,2) NOT NULL DEFAULT 0,
  delivery_fee   DECIMAL(10,2) NOT NULL DEFAULT 0,
  total_amount   DECIMAL(10,2) NOT NULL DEFAULT 0,
  admin_notes    TEXT NULL,
  created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_booking_date_status (event_date, status),
  CONSTRAINT fk_booking_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE booking_items (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  booking_id  INT UNSIGNED NOT NULL,
  item_id     INT UNSIGNED NOT NULL,
  quantity    INT UNSIGNED NOT NULL,
  unit_price  DECIMAL(10,2) NOT NULL,
  subtotal    DECIMAL(10,2) NOT NULL,
  CONSTRAINT fk_bi_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
  CONSTRAINT fk_bi_item    FOREIGN KEY (item_id)    REFERENCES inventory_items(id)
) ENGINE=InnoDB;

CREATE TABLE booking_services (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  booking_id  INT UNSIGNED NOT NULL,
  service_id  INT UNSIGNED NOT NULL,
  price       DECIMAL(10,2) NOT NULL,
  CONSTRAINT fk_bs_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
  CONSTRAINT fk_bs_service FOREIGN KEY (service_id) REFERENCES services(id)
) ENGINE=InnoDB;

CREATE TABLE payments (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  booking_id   INT UNSIGNED NOT NULL,
  amount       DECIMAL(10,2) NOT NULL,
  method       ENUM('cash','gcash','bank_transfer') NOT NULL DEFAULT 'cash',
  reference_no VARCHAR(100) NULL,
  notes        VARCHAR(255) NULL,
  paid_at      DATETIME NOT NULL,
  recorded_by  INT UNSIGNED NULL,
  INDEX idx_pay_date (paid_at),
  CONSTRAINT fk_pay_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
  CONSTRAINT fk_pay_user    FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Timeline of everything that happened to a booking (shown to customer + admin).
CREATE TABLE booking_logs (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  booking_id  INT UNSIGNED NOT NULL,
  action      VARCHAR(50)  NOT NULL,
  note        VARCHAR(255) NULL,
  created_by  INT UNSIGNED NULL,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_log_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
  CONSTRAINT fk_log_user    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- --------------------------------------------------------------------------
-- Appointments: ocular/venue visits, planning consultations, showroom
-- visits. Optionally tied to a booking.
-- --------------------------------------------------------------------------
CREATE TABLE appointments (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id          INT UNSIGNED NOT NULL,
  booking_id       INT UNSIGNED NULL,
  purpose          VARCHAR(60) NOT NULL,
  appointment_date DATE NOT NULL,
  appointment_time TIME NOT NULL,
  notes            VARCHAR(255) NULL,
  status           ENUM('scheduled','completed','cancelled') NOT NULL DEFAULT 'scheduled',
  created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_appt_slot (appointment_date, appointment_time, status),
  CONSTRAINT fk_appt_user    FOREIGN KEY (user_id)    REFERENCES users(id),
  CONSTRAINT fk_appt_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- --------------------------------------------------------------------------
-- Contact form messages. user_id is filled in when the sender is logged in.
-- --------------------------------------------------------------------------
CREATE TABLE inquiries (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     INT UNSIGNED NULL,
  name        VARCHAR(100) NOT NULL,
  email       VARCHAR(150) NOT NULL,
  phone       VARCHAR(30)  NULL,
  event_date  DATE NULL,
  inquiry     TEXT NOT NULL,
  status      ENUM('new','replied','closed') NOT NULL DEFAULT 'new',
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_inq_status (status, created_at),
  CONSTRAINT fk_inq_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;


-- ==========================================================================
-- Demo data
-- ==========================================================================

-- Passwords: admin123 / customer123  (change these before going live!)
INSERT INTO users (id, full_name, email, phone, password, role) VALUES
(1, 'Romero Admin',    'admin@romerospartyneeds.com', '0917 000 0000', '$2y$10$4E5ZhIeVKcvVNTFVWEkduurazymGwB2GHlssg4Pg8.eIVVqL2a2ua', 'admin'),
(2, 'Juan Dela Cruz',  'juan@example.com',            '0917 123 4567', '$2y$10$BHPXTBKuUp4fOC576bv8q.Dfpk8gRSKDoSK8eOFJuT6/kRLrTE2aW', 'customer'),
(3, 'Maria Santos',    'maria@example.com',           '0918 765 4321', '$2y$10$BHPXTBKuUp4fOC576bv8q.Dfpk8gRSKDoSK8eOFJuT6/kRLrTE2aW', 'customer');

INSERT INTO categories (id, name, slug, description, image, sort_order) VALUES
(1, 'Tables & chairs', 'tables-chairs', 'Round and rectangular tables with chairs to match, for intimate gatherings or full receptions.', 'assets/img/rpn-tbch-5.jpg', 1),
(2, 'Tents',           'tents',         'Weatherproof tents in a range of sizes, set up and taken down by our crew.',                    'assets/img/rpn-tbch-3.jpg', 2),
(3, 'Signage',         'signage',       'Welcome signs, name backdrops, seating charts and marquee letters.',                           'assets/img/rpn-bd-2.jpg',   3),
(4, 'Catering setups', 'catering',      'Buffet tables, serving stations and linens ready for your caterer.',                           'assets/img/rpn-bd.jpg',     4);

-- Prices are placeholders — update them from Admin > Inventory.
INSERT INTO inventory_items (id, category_id, name, description, unit, price, total_quantity) VALUES
(1,  1, 'Monobloc chair (white)',       'Sturdy plastic chair, great for large crowds.',          'piece', 15.00,   300),
(2,  1, 'Tiffany chair',                'Elegant chair for weddings, debuts and formal events.',  'piece', 85.00,   120),
(3,  1, 'Round table (10-seater)',      '5 ft round banquet table.',                              'piece', 250.00,  30),
(4,  1, 'Rectangular table (6 ft)',     'Long table for buffets, gift tables or family seating.', 'piece', 200.00,  25),
(5,  1, 'Kiddie table & chairs set',    'One small table with four kiddie chairs.',               'set',   150.00,  15),
(6,  1, 'Cocktail high table',          'Standing-height table for receptions and mixers.',       'piece', 300.00,  10),
(7,  2, 'Pop-up tent (3 x 3 m)',        'Quick shade for food stations or registration.',         'piece', 1200.00, 6),
(8,  2, 'Party tent (5 x 10 m)',        'Covers about 50 seated guests.',                         'piece', 4500.00, 3),
(9,  2, 'Grand tent (10 x 20 m)',       'Covers about 150 seated guests. Includes side walls.',   'piece', 12000.00, 1),
(10, 3, 'Welcome sign with easel',      'Personalised welcome board on a wooden easel.',          'piece', 800.00,  6),
(11, 3, 'Name / backdrop signage',      'Custom name or theme signage for the stage backdrop.',   'piece', 2500.00, 3),
(12, 3, 'Seating chart board',          'Guest seating chart printed on foam board.',             'piece', 600.00,  4),
(13, 3, 'LED marquee letter',           '3 ft light-up letters/numbers, priced per letter.',      'letter', 150.00, 30),
(14, 4, 'Buffet table with skirting',   '6 ft table with skirting in your motif colour.',         'piece', 600.00,  10),
(15, 4, 'Chafing dish (stainless)',     'Full-size chafing dish with fuel holder.',               'piece', 250.00,  20),
(16, 4, 'Tablecloth & topper set',      'Cloth plus coloured topper for one table.',              'set',   60.00,   80),
(17, 4, 'Beverage dispenser station',   'Two 5-gallon dispensers on a draped table.',             'set',   350.00,  6);

INSERT INTO services (id, name, description, price, daily_capacity, image) VALUES
(1, 'Event host / emcee',        'Keeps the program on schedule and guests engaged, from welcome to send-off.', 5000.00,  2, 'assets/img/sf-host-2.jpg'),
(2, 'Wedding coordination',      'Full-day coordination so you can be a guest at your own wedding.',            25000.00, 1, 'assets/img/sf-org.jpg'),
(3, 'Party organizer',           'Planning and on-site coordination for birthdays, debuts and anniversaries.',  12000.00, 2, 'assets/img/sf-host.jpg'),
(4, 'On-the-day coordinator',    'One coordinator to run the timeline on the day itself.',                      8000.00,  2, 'assets/img/sf-org.jpg');

INSERT INTO blocked_dates (blocked_date, reason, created_by) VALUES
(DATE_ADD(CURDATE(), INTERVAL 15 DAY), 'Family event — not accepting bookings', 1);

-- Booking 1 + 4 share the same date on purpose: it shows inventory and the
-- host service being shared/used up across two events on one day.
INSERT INTO bookings (id, reference, user_id, event_type, event_date, setup_time, event_start, event_end, venue_address, guest_count, notes, status, items_total, services_total, delivery_fee, total_amount) VALUES
(1, 'RPN-7K2M9Q', 2, 'Birthday',  DATE_ADD(CURDATE(), INTERVAL 6 DAY), '13:00', '17:00', '21:00', 'Barangay Hall, San Isidro', 80, 'Blue and white motif please.', 'confirmed', 10380.00, 5000.00,  1000.00, 16380.00),
(2, 'RPN-4H8T2W', 3, 'Wedding',   DATE_ADD(CURDATE(), INTERVAL 24 DAY), '08:00', '14:00', '22:00', 'Villa Esperanza Garden, Lipa City', 120, 'Garden ceremony, reception under the tent.', 'pending', 27320.00, 30000.00, 1000.00, 58320.00),
(3, 'RPN-9P3D6L', 2, 'Christmas party', DATE_SUB(CURDATE(), INTERVAL 20 DAY), '15:00', '18:00', '23:00', 'Romero residence, Poblacion', 50, NULL, 'completed', 2910.00, 0.00, 1000.00, 3910.00),
(4, 'RPN-2X5R8N', 3, 'Debut',     DATE_ADD(CURDATE(), INTERVAL 6 DAY), '10:00', '18:00', '23:00', 'Casa Rosa Events Place', 100, 'Pink and gold.', 'confirmed', 14250.00, 17000.00, 1000.00, 32250.00);

INSERT INTO booking_items (booking_id, item_id, quantity, unit_price, subtotal) VALUES
(1, 1, 80, 15.00, 1200.00), (1, 3, 8, 250.00, 2000.00), (1, 16, 8, 60.00, 480.00),
(1, 8, 1, 4500.00, 4500.00), (1, 14, 2, 600.00, 1200.00), (1, 15, 4, 250.00, 1000.00),
(2, 2, 120, 85.00, 10200.00), (2, 3, 12, 250.00, 3000.00), (2, 16, 12, 60.00, 720.00),
(2, 10, 1, 800.00, 800.00), (2, 12, 1, 600.00, 600.00), (2, 9, 1, 12000.00, 12000.00),
(3, 1, 50, 15.00, 750.00), (3, 4, 6, 200.00, 1200.00), (3, 16, 6, 60.00, 360.00), (3, 14, 1, 600.00, 600.00),
(4, 2, 100, 85.00, 8500.00), (4, 3, 10, 250.00, 2500.00), (4, 13, 5, 150.00, 750.00), (4, 11, 1, 2500.00, 2500.00);

INSERT INTO booking_services (booking_id, service_id, price) VALUES
(1, 1, 5000.00),
(2, 2, 25000.00), (2, 1, 5000.00),
(4, 3, 12000.00), (4, 1, 5000.00);

INSERT INTO payments (booking_id, amount, method, reference_no, notes, paid_at, recorded_by) VALUES
(1, 5000.00,  'gcash',         'GC-88123401', 'Down payment', DATE_SUB(NOW(), INTERVAL 3 DAY), 1),
(3, 3910.00,  'cash',          NULL,          'Paid in full on setup day', DATE_SUB(NOW(), INTERVAL 20 DAY), 1),
(4, 10000.00, 'bank_transfer', 'BDO-551902',  'Down payment', DATE_SUB(NOW(), INTERVAL 1 DAY), 1);

INSERT INTO booking_logs (booking_id, action, note, created_by, created_at) VALUES
(1, 'created',   'Booking submitted online', 2, DATE_SUB(NOW(), INTERVAL 5 DAY)),
(1, 'confirmed', 'Confirmed by admin',       1, DATE_SUB(NOW(), INTERVAL 4 DAY)),
(1, 'payment',   'Payment of 5,000.00 recorded (gcash)', 1, DATE_SUB(NOW(), INTERVAL 3 DAY)),
(2, 'created',   'Booking submitted online', 3, DATE_SUB(NOW(), INTERVAL 2 DAY)),
(3, 'created',   'Booking submitted online', 2, DATE_SUB(NOW(), INTERVAL 40 DAY)),
(3, 'confirmed', 'Confirmed by admin',       1, DATE_SUB(NOW(), INTERVAL 39 DAY)),
(3, 'payment',   'Payment of 3,910.00 recorded (cash)', 1, DATE_SUB(NOW(), INTERVAL 20 DAY)),
(3, 'completed', 'Event done, items returned', 1, DATE_SUB(NOW(), INTERVAL 19 DAY)),
(4, 'created',   'Booking submitted online', 3, DATE_SUB(NOW(), INTERVAL 3 DAY)),
(4, 'confirmed', 'Confirmed by admin',       1, DATE_SUB(NOW(), INTERVAL 2 DAY)),
(4, 'payment',   'Payment of 10,000.00 recorded (bank_transfer)', 1, DATE_SUB(NOW(), INTERVAL 1 DAY));

INSERT INTO appointments (user_id, booking_id, purpose, appointment_date, appointment_time, notes, status) VALUES
(3, 2, 'Planning consultation',     DATE_ADD(CURDATE(), INTERVAL 5 + (DAYOFWEEK(DATE_ADD(CURDATE(), INTERVAL 5 DAY)) = 1) DAY), '10:30', 'Discuss reception layout.', 'scheduled'),
(2, 1, 'Ocular / venue inspection', DATE_ADD(CURDATE(), INTERVAL 4 + (DAYOFWEEK(DATE_ADD(CURDATE(), INTERVAL 4 DAY)) = 1) DAY), '09:00', 'Check where the tent will go.', 'scheduled');

INSERT INTO inquiries (user_id, name, email, phone, event_date, inquiry, status) VALUES
(NULL, 'Ana Reyes', 'ana.reyes@example.com', '0920 555 1234', DATE_ADD(CURDATE(), INTERVAL 40 DAY), 'Do you deliver to Batangas City? Around 150 guests for a reunion.', 'new'),
(2,    'Juan Dela Cruz', 'juan@example.com', '0917 123 4567', NULL, 'Can I add 20 more chairs to my birthday booking?', 'replied');
