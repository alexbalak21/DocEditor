-- Migration 008 — Add template_id to documents
-- Stores which template pack was used to create the document.
-- Backfilled from document type for all existing rows.

ALTER TABLE documents
    ADD COLUMN template_id VARCHAR(50) NOT NULL DEFAULT 'invoice' AFTER status;

-- Backfill existing documents
UPDATE documents SET template_id = 'invoice'       WHERE type = 'INVOICE';
UPDATE documents SET template_id = 'quote'         WHERE type = 'QUOTE';
UPDATE documents SET template_id = 'credit-note'   WHERE type = 'CREDIT NOTE';
UPDATE documents SET template_id = 'delivery-note' WHERE type = 'DELIVERY NOTE';
UPDATE documents SET template_id = 'proforma'      WHERE type = 'PROFORMA';

-- Index for filtering by template
ALTER TABLE documents ADD INDEX idx_template (template_id);
