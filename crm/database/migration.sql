-- Migration: Add Task Status and Project Pipeline Tracking
-- Date: 2026-04-25

-- 1. Enhance Leads table with Task Status tracking
ALTER TABLE leads ADD COLUMN IF NOT EXISTS task_status ENUM('pending', 'done', 'delay') DEFAULT 'pending';
ALTER TABLE leads ADD COLUMN IF NOT EXISTS task_completed_at TIMESTAMP NULL;

-- 2. Create Projects table for Work Pipeline
CREATE TABLE IF NOT EXISTS projects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL,
    lead_id INT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    start_date DATE,
    end_date DATE,
    progress_percent INT DEFAULT 0,
    status VARCHAR(50) DEFAULT 'active',
    current_stage_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 3. Create Project Stages table (Custom Stages)
CREATE TABLE IF NOT EXISTS project_stages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    sort_order INT DEFAULT 0,
    color VARCHAR(20) DEFAULT '#4f46e5',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 4. Create Project Tasks table
CREATE TABLE IF NOT EXISTS project_tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    stage_id INT NOT NULL,
    assigned_employee_id INT,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    due_date DATE,
    status ENUM('pending', 'done') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Note: Default stages are seeded via PHP logic in projects.php or the setup script.
