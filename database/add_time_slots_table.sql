-- Create time_slots table for managing school periods
CREATE TABLE IF NOT EXISTS time_slots (
    id INT PRIMARY KEY AUTO_INCREMENT,
    slot_name VARCHAR(50) NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    slot_type ENUM('class', 'break') DEFAULT 'class',
    sort_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_active (is_active),
    INDEX idx_order (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default time slots
INSERT INTO time_slots (slot_name, start_time, end_time, slot_type, sort_order) VALUES
('Period 1', '08:00:00', '08:45:00', 'class', 1),
('Period 2', '08:45:00', '09:30:00', 'class', 2),
('Period 3', '09:30:00', '10:15:00', 'class', 3),
('Break', '10:15:00', '10:30:00', 'break', 4),
('Period 4', '10:30:00', '11:15:00', 'class', 5),
('Period 5', '11:15:00', '12:00:00', 'class', 6),
('Period 6', '12:00:00', '12:45:00', 'class', 7),
('Lunch', '12:45:00', '13:30:00', 'break', 8),
('Period 7', '13:30:00', '14:15:00', 'class', 9),
('Period 8', '14:15:00', '15:00:00', 'class', 10);
