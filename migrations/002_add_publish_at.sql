ALTER TABLE documents ADD COLUMN publish_at TEXT;

CREATE INDEX idx_documents_publish_at ON documents(publish_at);
