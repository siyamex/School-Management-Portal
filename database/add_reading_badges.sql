-- Add reading badge system tables

-- Create reading_badges table for badge templates
CREATE TABLE IF NOT EXISTS reading_badges (
    id INT PRIMARY KEY AUTO_INCREMENT,
    badge_name VARCHAR(100) NOT NULL,
    badge_description TEXT,
    badge_icon VARCHAR(255), -- Path to uploaded SVG/PNG
    pages_threshold INT DEFAULT 0, -- Minimum pages to earn (0 = no requirement)
    books_threshold INT DEFAULT 0, -- Minimum books to earn (0 = no requirement)
    created_by INT, -- Teacher who created
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES teachers(id) ON DELETE SET NULL,
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create student_reading_badges table for awarded badges
CREATE TABLE IF NOT EXISTS student_reading_badges (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT NOT NULL,
    badge_id INT NOT NULL,
    awarded_by INT, -- Teacher who awarded
    awarded_date DATE NOT NULL,
    reason TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (badge_id) REFERENCES reading_badges(id) ON DELETE CASCADE,
    FOREIGN KEY (awarded_by) REFERENCES teachers(id) ON DELETE SET NULL,
    UNIQUE KEY unique_student_badge (student_id, badge_id),
    INDEX idx_student (student_id),
    INDEX idx_badge (badge_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default reading badges
INSERT INTO reading_badges (badge_name, badge_description, badge_icon, pages_threshold, books_threshold) VALUES
('Book Worm', 'Read 1000 pages', NULL, 1000, 0),
('Reading Star', 'Complete 10 books', NULL, 0, 10),
('Page Turner', 'Read 500 pages', NULL, 500, 0),
('Bookworm Junior', 'Read 100 pages', NULL, 100, 0),
('Reading Champion', 'Complete 25 books', NULL, 0, 25);
