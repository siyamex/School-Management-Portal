-- Fix timetable schema to match code expectations
-- Add columns for easier time slot management

ALTER TABLE timetables 
ADD COLUMN day VARCHAR(20) AFTER day_of_week,
ADD COLUMN time_slot VARCHAR(20) AFTER end_time;

-- Copy data from day_of_week to day (capitalize)
UPDATE timetables SET day = CONCAT(UPPER(SUBSTRING(day_of_week, 1, 1)), SUBSTRING(day_of_week, 2));

-- Create time_slot from start_time and end_time
UPDATE timetables SET time_slot = CONCAT(DATE_FORMAT(start_time, '%H:%i'), '-', DATE_FORMAT(end_time, '%H:%i'));
