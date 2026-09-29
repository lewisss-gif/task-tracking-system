-- ============================================================
-- PROJECT MANAGEMENT SYSTEM - DATABASE SCHEMA
-- Nairobi Innovators Hub
-- ============================================================

CREATE DATABASE IF NOT EXISTS hub_pm_system;
USE hub_pm_system;

-- ============================================================
-- TABLE: ROLES
-- ============================================================
CREATE TABLE roles (
    role_id INT PRIMARY KEY AUTO_INCREMENT,
    role_name VARCHAR(50) NOT NULL UNIQUE,
    permissions TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert default roles
INSERT INTO roles (role_name, permissions) VALUES 
('Administrator', 'all'),
('Project Manager', 'create_project,edit_project,delete_project,assign_tasks,view_reports,manage_milestones'),
('Team Member', 'view_tasks,update_own_tasks,add_comments,view_dashboard');

-- ============================================================
-- TABLE: USERS
-- ============================================================
CREATE TABLE users (
    user_id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    role_id INT NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(role_id)
);

-- Insert default admin (password: admin123)
INSERT INTO users (username, email, password_hash, full_name, role_id) VALUES 
('admin', 'admin@hub.com', '$2y$12$WMCxDqDDypK6/L0cHEacWeRGCTNhrTqHL4pbXFClCS3UjUm5btch6', 'System Administrator', 1);

-- ============================================================
-- TABLE: PROJECTS
-- ============================================================
CREATE TABLE projects (
    project_id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    manager_id INT NOT NULL,
    status ENUM('Planning', 'Active', 'On Hold', 'Completed', 'Cancelled') DEFAULT 'Planning',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (manager_id) REFERENCES users(user_id)
);

-- ============================================================
-- TABLE: MILESTONES
-- ============================================================
CREATE TABLE milestones (
    milestone_id INT PRIMARY KEY AUTO_INCREMENT,
    project_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    due_date DATE NOT NULL,
    status ENUM('Pending', 'In Progress', 'Completed', 'Overdue') DEFAULT 'Pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(project_id) ON DELETE CASCADE
);

-- ============================================================
-- TABLE: TASKS
-- ============================================================
CREATE TABLE tasks (
    task_id INT PRIMARY KEY AUTO_INCREMENT,
    project_id INT NOT NULL,
    milestone_id INT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    status ENUM('Pending', 'In Progress', 'Completed', 'Blocked') DEFAULT 'Pending',
    priority ENUM('Low', 'Medium', 'High', 'Critical') DEFAULT 'Medium',
    assigned_to INT NULL,
    created_by INT NOT NULL,
    due_date DATE,
    completed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(project_id) ON DELETE CASCADE,
    FOREIGN KEY (milestone_id) REFERENCES milestones(milestone_id) ON DELETE SET NULL,
    FOREIGN KEY (assigned_to) REFERENCES users(user_id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(user_id)
);

-- ============================================================
-- TABLE: COMMENTS
-- ============================================================
CREATE TABLE comments (
    comment_id INT PRIMARY KEY AUTO_INCREMENT,
    task_id INT NOT NULL,
    user_id INT NOT NULL,
    content TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (task_id) REFERENCES tasks(task_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id)
);

-- ============================================================
-- TABLE: NOTIFICATIONS
-- ============================================================
CREATE TABLE notifications (
    notification_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    message TEXT NOT NULL,
    type ENUM('Task Assigned', 'Deadline Approaching', 'Task Overdue', 'Status Update', 'Milestone Alert', 'Project Update') NOT NULL,
    reference_id INT,
    reference_type VARCHAR(50),
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- ============================================================
-- TABLE: ACTIVITY LOGS
-- ============================================================
CREATE TABLE activity_logs (
    log_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(50) NOT NULL,
    entity_id INT NOT NULL,
    old_value TEXT,
    new_value TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id)
);

-- ============================================================
-- SAMPLE DATA FOR TESTING
-- ============================================================

-- Sample users (passwords are all 'password123' hashed)
INSERT INTO users (username, email, password_hash, full_name, role_id) VALUES
('john.manager', 'john@hub.com', '$2y$12$8Ow45m1gFjHUMecNFx20XOb8myHMyU0DHvkfw3gj2RrEA7cGHGKs.', 'John Kamau', 2),
('sarah.dev', 'sarah@hub.com', '$2y$12$8Ow45m1gFjHUMecNFx20XOb8myHMyU0DHvkfw3gj2RrEA7cGHGKs.', 'Sarah Wanjiku', 3),
('mike.dev', 'mike@hub.com', '$2y$12$8Ow45m1gFjHUMecNFx20XOb8myHMyU0DHvkfw3gj2RrEA7cGHGKs.', 'Mike Odhiambo', 3),
('lisa.dev', 'lisa@hub.com', '$2y$12$8Ow45m1gFjHUMecNFx20XOb8myHMyU0DHvkfw3gj2RrEA7cGHGKs.', 'Lisa Akinyi', 3);

-- Sample project
INSERT INTO projects (title, description, start_date, end_date, manager_id, status) VALUES
('Mobile App Development', 'Develop a cross-platform mobile application for hub members', '2026-03-01', '2026-05-31', 2, 'Active'),
('Website Redesign', 'Redesign the hub website with modern UI/UX', '2026-03-15', '2026-04-30', 2, 'Active');

-- Sample milestones
INSERT INTO milestones (project_id, title, due_date, status) VALUES
(1, 'UI/UX Design Complete', '2026-03-15', 'Completed'),
(1, 'Core Features Development', '2026-04-15', 'In Progress'),
(1, 'Testing & QA', '2026-05-15', 'Pending'),
(2, 'Wireframes Complete', '2026-03-25', 'Completed'),
(2, 'Frontend Development', '2026-04-20', 'In Progress');

-- Sample tasks
INSERT INTO tasks (project_id, milestone_id, title, description, status, priority, assigned_to, created_by, due_date) VALUES
(1, 1, 'Create Login Page', 'Design and implement login UI with validation', 'Completed', 'High', 3, 2, '2026-03-12'),
(1, 1, 'User Authentication API', 'Implement JWT-based authentication', 'In Progress', 'Critical', 4, 2, '2026-03-18'),
(1, 2, 'Dashboard Components', 'Build main dashboard widgets and charts', 'Pending', 'Medium', 5, 2, '2026-03-20'),
(1, 2, 'API Integration', 'Connect frontend to backend APIs', 'Pending', 'High', 3, 2, '2026-03-25'),
(2, 4, 'Homepage Wireframe', 'Design homepage layout in Figma', 'Completed', 'Medium', 5, 2, '2026-03-20'),
(2, 5, 'Responsive Navigation', 'Build mobile-responsive navigation bar', 'In Progress', 'High', 4, 2, '2026-03-28');

-- Sample comments
INSERT INTO comments (task_id, user_id, content) VALUES
(2, 2, 'Please use bcrypt for password hashing'),
(2, 4, 'Working on it. Will update by tomorrow'),
(3, 2, 'Make sure the dashboard is mobile responsive');

-- Sample notifications
INSERT INTO notifications (user_id, message, type, reference_id, reference_type) VALUES
(3, 'You have been assigned a new task: Create Login Page', 'Task Assigned', 1, 'task'),
(4, 'You have been assigned a new task: User Authentication API', 'Task Assigned', 2, 'task'),
(4, 'Task "User Authentication API" is due soon!', 'Deadline Approaching', 2, 'task');

-- Sample activity logs
INSERT INTO activity_logs (user_id, action, entity_type, entity_id, new_value) VALUES
(2, 'created_project', 'project', 1, 'Mobile App Development'),
(2, 'created_task', 'task', 1, 'Create Login Page'),
(3, 'updated_status', 'task', 1, 'Completed');
