-- ==========================================================================
-- Migration 002 — adds an optional photo to each rental item.
-- Only needed for databases created from an older db.sql.
-- If you get "Duplicate column name 'image'", it was already applied.
-- ==========================================================================

ALTER TABLE inventory_items
  ADD COLUMN image VARCHAR(255) NULL AFTER total_quantity;
