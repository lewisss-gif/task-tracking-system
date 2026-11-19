-- ============================================================
-- MIGRATION: Task assignment response workflow
-- Adds accept / flag-as-pending / reject support to tasks.
-- Run this once on an existing hub_pm_system database.
-- ============================================================

USE hub_pm_system;

ALTER TABLE tasks
    ADD COLUMN assignment_status ENUM('Pending', 'Accepted', 'Rejected') NOT NULL DEFAULT 'Pending' AFTER status,
    ADD COLUMN assignment_responded_at TIMESTAMP NULL AFTER assignment_status,
    ADD COLUMN rejection_reason TEXT NULL AFTER assignment_responded_at;

-- Work that has already started or finished is treated as accepted.
UPDATE tasks
SET assignment_status = 'Accepted',
    assignment_responded_at = COALESCE(completed_at, updated_at)
WHERE status IN ('In Progress', 'Completed');
