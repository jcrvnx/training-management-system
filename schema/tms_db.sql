-- Create the database
CREATE DATABASE IF NOT EXISTS tms_db;
USE tms_db;

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'user') DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Trainings table
CREATE TABLE IF NOT EXISTS trainings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(100) NOT NULL,
    description TEXT,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    location VARCHAR(100),
    max_participants INT DEFAULT 20,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Enrollments table (junction table for many-to-many)
CREATE TABLE IF NOT EXISTS enrollments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    training_id INT NOT NULL,
    enrolled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (training_id) REFERENCES trainings(id) ON DELETE CASCADE,
    UNIQUE KEY (user_id, training_id)
);

-- Sample Data: Insert admin user
INSERT INTO users (full_name, email, password, role) VALUES 
('Admin User', 'admin@example.com', 'admin123', 'admin');

-- Sample Data: Insert regular users
INSERT INTO users (full_name, email, password) VALUES 
('John Doe', 'john@example.com', 'password123'),
('Jane Smith', 'jane@example.com', 'password123'),
('Robert Johnson', 'robert@example.com', 'password123'),
('Emily Davis', 'emily@example.com', 'password123');

-- Sample Data: Insert trainings
INSERT INTO trainings (title, description, start_date, end_date, location, max_participants) VALUES 
('PHP Basics', 'Introduction to PHP programming language', '2023-06-01', '2023-06-05', 'Room 101', 15),
('Web Development Fundamentals', 'HTML, CSS, and JavaScript basics', '2023-06-15', '2023-06-20', 'Room 102', 20),
('Database Design', 'SQL and database normalization', '2023-07-01', '2023-07-03', 'Room 201', 12),
('Bootstrap Framework', 'Responsive design with Bootstrap', '2023-07-15', '2023-07-17', 'Room 301', 25);

-- Sample Data: Insert enrollments
INSERT INTO enrollments (user_id, training_id) VALUES 
(2, 1), -- John in PHP Basics
(3, 1), -- Jane in PHP Basics
(2, 2), -- John in Web Development
(3, 2), -- Jane in Web Development
(4, 3), -- Robert in Database Design
(5, 4); -- Emily in Bootstrap Framework 