-- ============================================================================
-- FEENION SCHOOL FEES SYSTEM - PARENT ID & COMBINED VOUCHERS MIGRATION
-- ============================================================================
-- This migration transforms the system from student-based to family-based fees
-- Execution Order: STEP 1 → STEP 2 → STEP 3 → STEP 4 → STEP 5
-- ============================================================================

-- ============================================================================
-- STEP 1: CREATE PARENTS TABLE (New formal parents table)
-- ============================================================================
-- This is the master table for parent/family information
-- Previous system stored parent references in users table; now we have dedicated table

CREATE TABLE IF NOT EXISTS `parents` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `parent_name` varchar(255) NOT NULL COMMENT 'Full name of parent/guardian',
  `email` varchar(100) COMMENT 'Parent email address',
  `phone` varchar(20) COMMENT 'Parent phone number',
  `cnic` varchar(20) COMMENT 'National ID (Pakistan CNIC)',
  `address` text COMMENT 'Parent address',
  `branch_id` int(11) NOT NULL COMMENT 'Which branch this parent belongs to',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`branch_id`) REFERENCES `branches`(`id`) ON DELETE RESTRICT,
  INDEX `idx_branch_id` (`branch_id`),
  INDEX `idx_parent_name` (`parent_name`),
  INDEX `idx_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================================
-- STEP 2: CREATE COMBINED_VOUCHERS TABLE (New table for family vouchers)
-- ============================================================================
-- This is KEY: One record per parent per month (not per student!)
-- Replaces the need to print 3 vouchers for 1 parent with 3 children

CREATE TABLE IF NOT EXISTS `combined_vouchers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `parent_id` int(11) NOT NULL COMMENT 'Foreign key to parents table',
  `month_year` varchar(7) NOT NULL COMMENT 'YYYY-MM format (e.g., 2026-09)',
  `total_amount` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Sum of all children fees',
  `amount_paid` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Total paid by parent',
  `discount_amount` decimal(10,2) DEFAULT 0.00 COMMENT 'Family-level discount',
  `discount_reason` varchar(255) COMMENT 'Why discount was applied (Sibling, Bulk, etc)',
  `status` enum('Unpaid','Partially Paid','Paid') DEFAULT 'Unpaid' COMMENT 'Payment status',
  `file_path` varchar(255) COMMENT 'Path to generated PDF voucher',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `branch_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`parent_id`) REFERENCES `parents`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`branch_id`) REFERENCES `branches`(`id`) ON DELETE RESTRICT,
  UNIQUE KEY `unique_parent_month_branch` (`parent_id`, `month_year`, `branch_id`),
  INDEX `idx_parent_id` (`parent_id`),
  INDEX `idx_month_year` (`month_year`),
  INDEX `idx_status` (`status`),
  INDEX `idx_branch_id` (`branch_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================================
-- STEP 3: MODIFY STUDENTS TABLE
-- ============================================================================
-- Update the parent_id foreign key to reference the new parents table
-- (instead of users table)

-- First, add the new index if needed
ALTER TABLE `students` ADD INDEX IF NOT EXISTS `idx_parent_id` (`parent_id`);

-- Note: We'll update the FK constraint AFTER data migration
-- For now, students.parent_id will still reference users.id during transition

-- ============================================================================
-- STEP 4: MODIFY INVOICES TABLE
-- ============================================================================
-- Add columns to link invoices to parents and combined vouchers

ALTER TABLE `invoices` 
  ADD COLUMN IF NOT EXISTS `parent_id` int(11) COMMENT 'Link to parents table (from student)',
  ADD COLUMN IF NOT EXISTS `combined_voucher_id` int(11) COMMENT 'Link to combined_vouchers table';

-- Add foreign keys for new columns
ALTER TABLE `invoices`
  ADD CONSTRAINT `invoices_ibfk_parent_id` FOREIGN KEY IF NOT EXISTS (`parent_id`) 
    REFERENCES `parents`(`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `invoices_ibfk_combined_voucher_id` FOREIGN KEY IF NOT EXISTS (`combined_voucher_id`) 
    REFERENCES `combined_vouchers`(`id`) ON DELETE SET NULL;

-- Add indexes for performance
ALTER TABLE `invoices` ADD INDEX IF NOT EXISTS `idx_parent_id` (`parent_id`);
ALTER TABLE `invoices` ADD INDEX IF NOT EXISTS `idx_combined_voucher_id` (`combined_voucher_id`);

-- ============================================================================
-- STEP 5: MODIFY VOUCHERS TABLE
-- ============================================================================
-- Add columns to track combined vouchers

ALTER TABLE `vouchers`
  ADD COLUMN IF NOT EXISTS `combined_voucher_id` int(11) COMMENT 'Link to combined_vouchers',
  ADD COLUMN IF NOT EXISTS `is_part_of_combined` BOOLEAN DEFAULT FALSE COMMENT 'Is this part of combined voucher?';

-- Add foreign key and index
ALTER TABLE `vouchers`
  ADD CONSTRAINT `vouchers_ibfk_combined_voucher_id` FOREIGN KEY IF NOT EXISTS (`combined_voucher_id`) 
    REFERENCES `combined_vouchers`(`id`) ON DELETE SET NULL;

ALTER TABLE `vouchers` ADD INDEX IF NOT EXISTS `idx_combined_voucher_id` (`combined_voucher_id`);

-- ============================================================================
-- VERIFICATION QUERIES (Run these after each step to verify success)
-- ============================================================================
-- 
-- After Step 1:
--   SELECT COUNT(*) as parent_count FROM parents;
-- 
-- After Step 2:
--   SELECT COUNT(*) as voucher_count FROM combined_vouchers;
--
-- After Step 3-5:
--   SHOW COLUMNS FROM students LIKE 'parent_id';
--   SHOW COLUMNS FROM invoices LIKE 'parent_id';
--   SHOW COLUMNS FROM invoices LIKE 'combined_voucher_id';
--   SHOW COLUMNS FROM vouchers LIKE 'combined_voucher_id';
--
-- ============================================================================

COMMIT;
