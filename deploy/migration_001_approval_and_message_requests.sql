-- Migration 001 — non-destructive, additive only.
-- Adds: lecturer approval workflow + private message-request inbox.
-- Safe to run on an already-populated database (existing rows get the
-- DEFAULT, which preserves current behaviour: everyone already-approved,
-- every existing conversation already-accepted).

-- Lecturers sign up in a "pending" state and must be approved by their HOD.
-- Everyone else defaults to "approved" so nothing else changes.
ALTER TABLE users
  ADD COLUMN approval_status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'approved' AFTER is_active;

-- A direct message from a stranger lands in the recipient's "requests"
-- tray (status='pending') instead of their inbox, until they accept it.
-- The initiator's own participant row is 'accepted'. Existing rows default
-- to 'accepted' so current conversations stay in the inbox.
ALTER TABLE conversation_participants
  ADD COLUMN status ENUM('pending','accepted') NOT NULL DEFAULT 'accepted' AFTER last_read_at;
