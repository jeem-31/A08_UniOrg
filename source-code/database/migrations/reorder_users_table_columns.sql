-- =====================================================
-- REORDER USERS TABLE COLUMNS
-- Strategy: Table Rebuild (PostgreSQL)
-- =====================================================
-- This script will:
-- 1. Create new table with correct column order
-- 2. Copy all data from old table
-- 3. Drop old table with CASCADE
-- 4. Rename new table
-- 5. Recreate all foreign keys and indexes
-- =====================================================

-- STEP 1: Check current column order (run this first to see current state)
-- Uncomment to check:
/*
SELECT 
    column_name,
    ordinal_position,
    data_type,
    is_nullable,
    column_default
FROM 
    information_schema.columns
WHERE 
    table_name = 'users'
    AND table_schema = 'public'
ORDER BY 
    ordinal_position;
*/

-- STEP 2: Create new users table with correct column order
CREATE TABLE users_new (
    -- Primary Key & Core Fields
    id BIGSERIAL PRIMARY KEY,
    department_id BIGINT,
    role VARCHAR(50) NOT NULL DEFAULT 'student',
    
    -- Identification
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    student_id VARCHAR(255) UNIQUE,
    
    -- Basic Information (Name fields)
    first_name VARCHAR(255) NOT NULL,
    middle_name VARCHAR(255),
    middle_initial VARCHAR(10),
    last_name VARCHAR(255) NOT NULL,
    suffix VARCHAR(50),
    
    -- Academic Information
    course VARCHAR(255),
    year_level VARCHAR(50),
    section VARCHAR(50),
    
    -- Profile
    photo VARCHAR(255),
    is_active BOOLEAN NOT NULL DEFAULT true,
    
    -- Password Management
    password_changed_at TIMESTAMP,
    
    -- Remember Token
    remember_token VARCHAR(100),
    
    -- Timestamps (Last columns)
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- STEP 3: Copy all data from old table to new table
INSERT INTO users_new (
    id,
    department_id,
    role,
    email,
    password,
    student_id,
    first_name,
    middle_name,
    middle_initial,
    last_name,
    suffix,
    course,
    year_level,
    section,
    photo,
    is_active,
    password_changed_at,
    remember_token,
    created_at,
    updated_at
)
SELECT 
    id,
    department_id,
    role,
    email,
    password,
    student_id,
    first_name,
    middle_name,
    middle_initial,
    last_name,
    suffix,
    course,
    year_level,
    section,
    photo,
    is_active,
    password_changed_at,
    remember_token,
    created_at,
    updated_at
FROM users;

-- STEP 4: Update sequence to match current max ID
SELECT setval('users_new_id_seq', (SELECT MAX(id) FROM users_new));

-- STEP 5: Drop old table with CASCADE (removes all foreign keys)
DROP TABLE users CASCADE;

-- STEP 6: Rename new table to users
ALTER TABLE users_new RENAME TO users;

-- STEP 7: Rename sequence
ALTER SEQUENCE users_new_id_seq RENAME TO users_id_seq;

-- STEP 8: Recreate Foreign Keys
-- Foreign key to departments table
ALTER TABLE users 
ADD CONSTRAINT users_department_id_foreign 
FOREIGN KEY (department_id) 
REFERENCES departments(id) 
ON DELETE SET NULL;

-- STEP 9: Recreate indexes for performance
CREATE INDEX idx_users_student_id ON users(student_id);
CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_users_role ON users(role);
CREATE INDEX idx_users_department_id ON users(department_id);

-- STEP 10: Recreate foreign keys from other tables that reference users
-- organization_user pivot table
ALTER TABLE organization_user 
ADD CONSTRAINT organization_user_user_id_foreign 
FOREIGN KEY (user_id) 
REFERENCES users(id) 
ON DELETE CASCADE;

-- STEP 11: Verify the new column order
SELECT 
    column_name,
    ordinal_position,
    data_type,
    is_nullable
FROM 
    information_schema.columns
WHERE 
    table_name = 'users'
    AND table_schema = 'public'
ORDER BY 
    ordinal_position;
