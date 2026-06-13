-- Add profile fields to users table
ALTER TABLE users
ADD COLUMN first_name VARCHAR(50) NULL AFTER name,
ADD COLUMN second_name VARCHAR(50) NULL AFTER first_name,
ADD COLUMN birth_date DATE NULL,
ADD COLUMN sexuality ENUM('Male', 'Female', 'Other') NULL,
ADD COLUMN nic_no VARCHAR(20) NULL,
ADD COLUMN postal_id_card_no VARCHAR(20) NULL,
ADD COLUMN school_name VARCHAR(150) NULL,
ADD COLUMN grade VARCHAR(20) NULL,
ADD COLUMN school_category ENUM('Government', 'Private') NULL,
ADD COLUMN main_subject VARCHAR(100) NULL,
ADD COLUMN first_appointment_date DATE NULL;
