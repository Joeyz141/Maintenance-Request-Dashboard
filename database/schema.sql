-- database/schema.sql
-- Creates the maintenace dashboard database and its tables

CREATE DATABASE IF NOT EXISTS maintenance_dashboard
    CHARACTER SET utf8mb4 
    --character set
    COLLATE utf8mb4_unicode_ci; 
    --sorting and comparison rules like case-insensitive

USE maintenance_dashboard;
--command after this applies to database

CREATE TABLE vehicles (
    id          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    vin         CHAR(17)        NOT NULL,
    make        VARCHAR(50)     NOT NULL,
    model       VARCHAR(50)     NOT NULL,
    model_year  SMALLINT UNSIGNED NOT NULL,
    created_at TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_vehicles_vin (vin)
    )ENGINE = InnoDB;

--INT UNSIGNED means only whole numbers, no negatives
--AUTO_INCREMENT assigns 1,2,3 automatically
--NOT NULL is the value required, leaving it empty creates an error
--CHAR is a fixed length & VARCHAR is a fixed length for variables 
--SMALLINT UNSIGNED is a small number 
--PK(id) rows unique identity
--UK no two vehicles can share a VIN
--ENGINE=InnoDB is required for foreign keys 