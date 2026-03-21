-- =====================================================
-- 003_create_test_user_custom_password.sql
-- Crea usuario "test" con clave personalizada (bcrypt)
-- IMPORTANTE: reemplaza __BCRYPT_HASH__ por tu hash real
-- =====================================================

START TRANSACTION;

SET @business_id := 2;
SET @admin_user_id := 7;
SET @new_username := 'test';
SET @new_email := 'test.admin@local.test';
SET @preferred_admin_role := 'Admin#2';
SET @password_hash := '__BCRYPT_HASH__';

SET @existing_user_id := (
  SELECT id FROM users
  WHERE username = @new_username
    AND business_id = @business_id
  LIMIT 1
);

-- Crear solo si no existe
INSERT INTO users (
  user_type, surname, first_name, last_name, username, email, password, language,
  contact_no, address, remember_token, business_id, available_at, paused_at,
  max_sales_discount_percent, allow_login, status, is_enable_service_staff_pin,
  service_staff_pin, crm_contact_id, is_cmmsn_agnt, cmmsn_percent, selected_contacts,
  dob, gender, marital_status, blood_group, contact_number, alt_number, family_number,
  fb_link, twitter_link, social_media_1, social_media_2, permanent_address,
  current_address, guardian_name, custom_field_1, custom_field_2, custom_field_3,
  custom_field_4, bank_details, id_proof_name, id_proof_number, deleted_at, created_at, updated_at
)
SELECT
  u.user_type,
  'TEST',
  'Test',
  'Admin',
  @new_username,
  @new_email,
  @password_hash,
  'es',
  u.contact_no,
  u.address,
  NULL,
  u.business_id,
  NULL,
  NULL,
  u.max_sales_discount_percent,
  1,
  'active',
  0,
  NULL,
  NULL,
  0,
  0.00,
  0,
  u.dob,
  u.gender,
  u.marital_status,
  u.blood_group,
  u.contact_number,
  u.alt_number,
  u.family_number,
  u.fb_link,
  u.twitter_link,
  u.social_media_1,
  u.social_media_2,
  u.permanent_address,
  u.current_address,
  u.guardian_name,
  u.custom_field_1,
  u.custom_field_2,
  u.custom_field_3,
  u.custom_field_4,
  u.bank_details,
  u.id_proof_name,
  u.id_proof_number,
  NULL,
  NOW(),
  NOW()
FROM users u
WHERE u.id = @admin_user_id
  AND @existing_user_id IS NULL
LIMIT 1;

SET @new_user_id := COALESCE(
  @existing_user_id,
  (SELECT id FROM users WHERE username = @new_username AND business_id = @business_id LIMIT 1)
);

-- Si ya existe, actualiza clave y email del usuario test
UPDATE users
SET email = @new_email,
    password = @password_hash,
    allow_login = 1,
    status = 'active',
    updated_at = NOW()
WHERE id = @new_user_id;

SET @role_id := (
  SELECT r.id
  FROM roles r
  WHERE r.business_id = @business_id
    AND r.name = @preferred_admin_role
  LIMIT 1
);

INSERT INTO roles (name, guard_name, business_id, is_default, is_service_staff, created_at, updated_at)
SELECT CONCAT('ADMIN_SQL#', @business_id), 'web', @business_id, 0, 0, NOW(), NOW()
WHERE @role_id IS NULL;

SET @role_id := COALESCE(
  @role_id,
  (SELECT id FROM roles WHERE business_id = @business_id AND name = CONCAT('ADMIN_SQL#', @business_id) LIMIT 1)
);

INSERT IGNORE INTO role_has_permissions (permission_id, role_id)
SELECT p.id, @role_id
FROM permissions p;

INSERT IGNORE INTO model_has_roles (role_id, model_type, model_id)
VALUES (@role_id, 'App\\User', @new_user_id);

COMMIT;

SELECT id, username, email, business_id, status
FROM users
WHERE id = @new_user_id;
