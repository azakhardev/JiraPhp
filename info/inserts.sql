-- 0. USERS
INSERT INTO users (id, username, email, password, created_at) VALUES
(1, 'artem_zacharcenko', 'zaca06@vse.cz', '$2y$10$BgtUyiVjhEQ8mrNh04Le8.EzgFkNggfTiBeOYragvH5D1SgVvyMTW', '2026-04-27 22:32:00');

-- 1. PROJECTS
-- Creating two different projects
INSERT INTO projects (id, created_by, name, description, color_hex) VALUES
(1, 1, 'Web Application Redesign', 'Complete overhaul of the company landing page and user dashboard.', '#3498db'),
(2, 1, 'Home Office Renovation', 'Planning and tracking tasks for painting and new furniture.', '#e67e22');

-- 2. PROJECT MEMBERS
-- Assigning user 1 as admin to both projects
INSERT INTO project_members (project_id, user_id, role) VALUES
(1, 1, 'admin'),
(2, 1, 'admin');

-- 3. STATUSES
-- Project 1 Statuses
INSERT INTO statuses (id, project_id, name) VALUES
(1, 1, 'Backlog'),
(2, 1, 'In Progress'),
(3, 1, 'Code Review'),
(4, 1, 'Done');

-- Project 2 Statuses
INSERT INTO statuses (id, project_id, name) VALUES
(5, 2, 'To Buy'),
(6, 2, 'In Work'),
(7, 2, 'Completed');

-- 4. TAGS
-- Project 1 Tags
INSERT INTO tags (id, project_id, name) VALUES
(1, 1, 'Frontend'),
(2, 1, 'Backend'),
(3, 1, 'Bug');

-- Project 2 Tags
INSERT INTO tags (id, project_id, name) VALUES
(4, 2, 'IKEA'),
(5, 2, 'Urgent');

-- 5. TASKS
-- Tasks for Project 1
INSERT INTO tasks (id, project_id, title, description, assignee_id, reporter_id, status_id, priority_weight, time_spent_minutes, due_date) VALUES
(1, 1, 'Setup React Router', 'Implement navigation for the new dashboard.', 1, 1, 2, 4, 120, '2026-05-15'),
(2, 1, 'Fix API Auth Leak', 'Security patch for the login endpoint.', 1, 1, 1, 5, 0, '2026-05-10'),
(3, 1, 'Write CSS for Buttons', 'Create reusable button components.', NULL, 1, 1, 2, 0, '2026-05-20');

-- Tasks for Project 2
INSERT INTO tasks (id, project_id, title, description, assignee_id, reporter_id, status_id, priority_weight, time_spent_minutes, due_date) VALUES
(4, 2, 'Buy Standing Desk', 'Find a desk that fits the corner.', 1, 1, 5, 3, 45, '2026-06-01');

-- 6. TASK TAGS (Mapping)
INSERT INTO task_tags (task_id, tag_id) VALUES
(1, 1), -- Task 1 is Frontend
(2, 2), -- Task 2 is Backend
(2, 3), -- Task 2 is also a Bug
(4, 4), -- Task 4 is IKEA
(4, 5); -- Task 4 is Urgent

-- 7. COMMENTS
INSERT INTO comments (task_id, user_id, content) VALUES
(1, 1, 'I started working on the main routes today.'),
(2, 1, 'This is a critical security issue, need to finish this ASAP.'),
(4, 1, 'Checked the dimensions, the 160cm desk is too big.');