USE aikaa_crm;

-- Seed a test company
INSERT INTO companies (name, subdomain, plan, status) VALUES 
('Aikaa Demo', 'demo', 'pro', 'active');

-- Get the ID of the inserted company (assuming it's 1)
SET @company_id = LAST_INSERT_ID();

-- Seed a test admin user (password: admin123)
-- Using a placeholder for password_hash which we will verify in PHP
INSERT INTO users (company_id, name, email, password, role, status) VALUES 
(@company_id, 'Admin User', 'admin@aikocrm.com', '$2y$10$nRQ/mUVD9KpxzU/Y.Yv8Ou2R6y.G3gP8VvW2hG6pA9R5zXy8V0yqG', 'admin', 'active');

-- Seed some test leads
INSERT INTO leads (company_id, name, mobile, email, category, source, status) VALUES 
(@company_id, 'John Smith', '9876543210', 'john@example.com', 'hot', 'Facebook', 'new'),
(@company_id, 'Sarah Jenkins', '8765432109', 'sarah@example.com', 'warm', 'Website', 'in_progress'),
(@company_id, 'Mike Ross', '7654321098', 'mike@example.com', 'cold', 'Referral', 'lost'),
(@company_id, 'Harvey Specter', '6543210987', 'harvey@example.com', 'hot', 'Ads', 'won');
