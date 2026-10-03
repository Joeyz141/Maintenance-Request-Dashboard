-- database/create_app_user.sql
-- Creates the limited database account that the PHP app logs in with.
-- Run as root (phpMyAdmin SQL tab or the mysql client), AFTER schema.sql.
-- Before running: replace CHANGE_ME with the same password you put in .htaccess.
-- Never commit the real password. This file must keep the CHANGE_ME placeholder.

-- Remove any old copy first, so this script can be run again safely (idempotent).
-- DROP USER also removes all of the account's permissions.
DROP USER IF EXISTS 'maintenance_app'@'localhost';

-- The account: can only log in from this computer (localhost).
CREATE USER 'maintenance_app'@'localhost' IDENTIFIED BY 'CHANGE_ME';

-- Least privilege: read and write rows in our database only.
-- No CREATE, ALTER or DROP. Schema changes are done by root.
GRANT SELECT, INSERT, UPDATE, DELETE ON maintenance_dashboard.* TO 'maintenance_app'@'localhost';
