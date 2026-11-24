-- ==========================================================================
-- Removes the DEMO bookings, customers, appointments, payments, inquiries
-- and blocked dates — for when the site goes live with real customers.
--
-- KEEPS: admin accounts, categories, inventory items and services (edit
-- their prices/quantities in Admin > Inventory and Admin > Services).
--
-- phpMyAdmin: select your database > Import > choose this file.
-- This cannot be undone. Export a backup first if you're unsure.
-- ==========================================================================

DELETE FROM payments;
DELETE FROM booking_logs;
DELETE FROM booking_services;
DELETE FROM booking_items;
DELETE FROM appointments;
DELETE FROM bookings;
DELETE FROM inquiries;
DELETE FROM blocked_dates;
DELETE FROM login_attempts;
DELETE FROM users WHERE role = 'customer';
