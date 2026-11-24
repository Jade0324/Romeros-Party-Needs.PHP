-- ==========================================================================
-- Migration 003 — indexes for the Reports page and the admin dashboard.
-- Only needed for databases created from an older db.sql. Safe for data:
-- it only adds indexes. If you get "Duplicate key name", it was already
-- applied.
-- ==========================================================================

ALTER TABLE payments  ADD INDEX idx_pay_date (paid_at);
ALTER TABLE inquiries ADD INDEX idx_inq_status (status, created_at);
