-- Fix UTF-8 encoding for all tables in ceylonstudyhub database
-- Run this in phpMyAdmin or MySQL command line

-- Set database default charset
ALTER DATABASE ceylonstudyhub CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Fix questions table
ALTER TABLE questions CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE questions MODIFY question_text TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Fix answer_options table
ALTER TABLE answer_options CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE answer_options MODIFY option_text TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Fix papers table
ALTER TABLE papers CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE papers MODIFY title VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Fix users table
ALTER TABLE users CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE users MODIFY name VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE users MODIFY email VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Fix all other tables
ALTER TABLE attempts CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE responses CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE paper_access CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE teacher_student CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE payments CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE messages CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Verify the changes
SHOW TABLE STATUS WHERE Name LIKE '%';
