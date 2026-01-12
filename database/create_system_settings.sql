-- Create system_settings table for application configuration
CREATE TABLE IF NOT EXISTS system_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    setting_type ENUM('text', 'number', 'boolean', 'email', 'url', 'file') DEFAULT 'text',
    setting_group VARCHAR(50) DEFAULT 'general',
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by INT,
    INDEX idx_key (setting_key),
    INDEX idx_group (setting_group),
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default settings
INSERT INTO system_settings (setting_key, setting_value, setting_type, setting_group, description) VALUES
-- General Settings
('school_name', 'School Portal', 'text', 'general', 'Name of the school'),
('school_short_name', 'SP', 'text', 'general', 'Short name or abbreviation'),
('school_tagline', 'Excellence in Education', 'text', 'general', 'School tagline or motto'),
('school_address', '123 Education Street', 'text', 'general', 'School physical address'),
('school_phone', '+1234567890', 'text', 'general', 'School contact phone'),
('school_email', 'info@school.edu', 'email', 'general', 'School contact email'),
('school_website', 'https://school.edu', 'url', 'general', 'School website URL'),

-- Academic Settings
('academic_year_start_month', '9', 'number', 'academic', 'Month when academic year starts (1-12)'),
('periods_per_day', '8', 'number', 'academic', 'Default number of periods per day'),
('class_duration_minutes', '45', 'number', 'academic', 'Default class duration in minutes'),
('passing_percentage', '40', 'number', 'academic', 'Minimum passing percentage'),

-- Attendance Settings
('attendance_grace_period', '15', 'number', 'attendance', 'Grace period in minutes for late arrival'),
('auto_mark_absent', '1', 'boolean', 'attendance', 'Automatically mark students absent if not marked'),

-- System Settings
('timezone', 'UTC', 'text', 'system', 'System timezone'),
('date_format', 'Y-m-d', 'text', 'system', 'Date format for display'),
('items_per_page', '20', 'number', 'system', 'Default items per page in lists'),
('session_timeout', '3600', 'number', 'system', 'Session timeout in seconds'),
('enable_email_notifications', '1', 'boolean', 'system', 'Enable email notifications'),
('enable_sms_notifications', '0', 'boolean', 'system', 'Enable SMS notifications'),

-- Appearance
('primary_color', '#3b82f6', 'text', 'appearance', 'Primary theme color'),
('logo_url', '', 'file', 'appearance', 'School logo URL or path'),
('favicon_url', '', 'file', 'appearance', 'Favicon URL or path');
