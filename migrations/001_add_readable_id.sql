ALTER TABLE documents ADD COLUMN readable_id TEXT;

UPDATE documents
SET readable_id = lower(replace(title, ' ', '-')) || '-' || id
WHERE readable_id IS NULL;

CREATE UNIQUE INDEX idx_documents_readable_id ON documents(readable_id);
