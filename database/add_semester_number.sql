-- Add missing semester_number column to semesters table
ALTER TABLE semesters 
ADD COLUMN semester_number INT DEFAULT 1 AFTER semester_name;
