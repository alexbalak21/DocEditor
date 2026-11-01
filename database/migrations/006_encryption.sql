-- Migration 006 — Alter columns to store encrypted ciphertext.
--
-- Encrypted values are base64-encoded strings, longer than plaintext.
-- We change VARCHAR/JSON columns to MEDIUMTEXT so they can hold any ciphertext size.
--
-- IMPORTANT: Run this AFTER generating your APP_KEY and setting it in .env.
-- Run the re-encryption script (database/encrypt_existing.php) immediately after.

-- Drop indexes before changing indexed VARCHAR columns to MEDIUMTEXT.
-- Encrypted values cannot be meaningfully indexed.
SET @drop_documents_customer_index = (
    SELECT IF(
        EXISTS (
            SELECT 1
            FROM information_schema.statistics
            WHERE table_schema = DATABASE()
              AND table_name = 'documents'
              AND index_name = 'idx_customer'
        ),
        'ALTER TABLE documents DROP INDEX idx_customer',
        'SET @migration_noop = 1'
    )
);
PREPARE drop_documents_customer_index FROM @drop_documents_customer_index;
EXECUTE drop_documents_customer_index;
DEALLOCATE PREPARE drop_documents_customer_index;

SET @drop_customers_name_index = (
    SELECT IF(
        EXISTS (
            SELECT 1
            FROM information_schema.statistics
            WHERE table_schema = DATABASE()
              AND table_name = 'customers'
              AND index_name = 'idx_name'
        ),
        'ALTER TABLE customers DROP INDEX idx_name',
        'SET @migration_noop = 1'
    )
);
PREPARE drop_customers_name_index FROM @drop_customers_name_index;
EXECUTE drop_customers_name_index;
DEALLOCATE PREPARE drop_customers_name_index;

-- documents: customer name + data blob
ALTER TABLE documents
    MODIFY COLUMN customer MEDIUMTEXT NOT NULL,
    MODIFY COLUMN data     MEDIUMTEXT NOT NULL;

-- customers: all PII fields
ALTER TABLE customers
    MODIFY COLUMN name    MEDIUMTEXT NOT NULL,
    MODIFY COLUMN address MEDIUMTEXT NOT NULL,
    MODIFY COLUMN city    MEDIUMTEXT NOT NULL,
    MODIFY COLUMN contact MEDIUMTEXT NOT NULL,
    MODIFY COLUMN phone   MEDIUMTEXT NOT NULL,
    MODIFY COLUMN vat     MEDIUMTEXT NOT NULL;
