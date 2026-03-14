-- ============================================================
-- Migration: Add assistant_ict and assistant_it roles
-- Run this ONCE on an existing database
-- ============================================================

-- 1. Expand the role ENUM
ALTER TABLE it_staff
  MODIFY COLUMN role ENUM('ict_head','assistant_manager','assistant_ict','sr_it_executive','assistant_it') NOT NULL;

-- 2. Fix Ms. Priya Sharma → now Dr Pakkairaha with assistant_ict role
--    (Run only if old seed data is present)
UPDATE it_staff
  SET name = 'Dr Pakkairaha',
      email = 'pakkairaha@apollouniversity.edu.in',
      role = 'assistant_ict',
      designation = 'Assistant Director of ICT'
  WHERE email = 'priya.sharma@apollouniversity.edu.in';

-- 3. Update existing staff names to match real data
UPDATE it_staff SET name = 'Mr Ashok Kumar', email = 'ashok.kumar@apollouniversity.edu.in', designation = 'Assistant Manager IT'
  WHERE email = 'arun.verma@apollouniversity.edu.in';

UPDATE it_staff SET name = 'Mr K Prasanna', email = 'k.prasanna@apollouniversity.edu.in'
  WHERE email = 'kiran.patel@apollouniversity.edu.in';

UPDATE it_staff SET name = 'Mr K Jagadeesh', email = 'k.jagadeesh@apollouniversity.edu.in'
  WHERE email = 'suresh.reddy@apollouniversity.edu.in';

-- Remove the third Sr IT Executive (Deepa Nair) since only 2 are needed
DELETE FROM it_staff WHERE email = 'deepa.nair@apollouniversity.edu.in';

-- 4. Insert Assistant IT Executives
INSERT INTO it_staff (name, email, password_hash, role, designation, contact) VALUES
  ('Mr Mohan',   'mohan@apollouniversity.edu.in',   '$2y$12$placeholder_hash_replace_me', 'assistant_it', 'Assistant IT Executive', '9876543220'),
  ('Mr Bhargav', 'bhargav@apollouniversity.edu.in', '$2y$12$placeholder_hash_replace_me', 'assistant_it', 'Assistant IT Executive', '9876543221'),
  ('Mr Gopi',    'gopi@apollouniversity.edu.in',    '$2y$12$placeholder_hash_replace_me', 'assistant_it', 'Assistant IT Executive', '9876543222'),
  ('Mr Vijay',   'vijay@apollouniversity.edu.in',   '$2y$12$placeholder_hash_replace_me', 'assistant_it', 'Assistant IT Executive', '9876543223');
