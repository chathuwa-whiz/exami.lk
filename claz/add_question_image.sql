-- Add image column to questions table
ALTER TABLE questions ADD COLUMN image_path VARCHAR(255) NULL AFTER question_text;
