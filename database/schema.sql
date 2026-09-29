-- database/schema.sql
-- Creates the maintenace dashboard database and its tables

CREATE DATABASE IF NOT EXISTS maintenance_dashboard
    CHARACTER SET utf8mb4 
    -- character set
    COLLATE utf8mb4_unicode_ci; 
    -- sorting and comparison rules like case-insensitive

USE maintenance_dashboard;
-- command after this applies to database

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

-- INT UNSIGNED means only whole numbers, no negatives
-- AUTO_INCREMENT assigns 1,2,3 automatically
-- NOT NULL is the value required, leaving it empty creates an error
-- CHAR is a fixed length & VARCHAR is initial length to max length for variables 
-- SMALLINT UNSIGNED is a small number 
-- PK(id) rows unique identity
-- UK no two vehicles can share a VIN
-- ENGINE=InnoDB is required for foreign keys 

CREATE TABLE maintenance_requests (
    id          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    vehicle_id  INT UNSIGNED    NOT NULL,
    title       VARCHAR(150)    NOT NULL,
    description TEXT            NULL,
    priority    ENUM('low', 'medium', 'high')   NOT NULL DEFAULT 'medium',
    status      ENUM('open', 'in_progress', 'completed') NOT NULL DEFAULT 'open',
    created_at  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP
                                ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_requests_vehicle_id (vehicle_id),
    CONSTRAINT fk_requests_vehicle
        FOREIGN KEY (vehicle_id) REFERENCES vehicles (id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE
)ENGINE=InnoDB; 

-- vehicle_id INT UNSIGNED must match vehicles.id exactly
-- TEXT NULL description is optional
-- ENUM means only these listed values are allowed
-- ON UPDATE CURRENT_TIMESTAMP every time the row changes, the time is updated with the current time
-- KEY idx_requests_vehicle_id is an index on the vehicle_id column, helps us locate the vehicles faster
-- CONSTRAINT fk_requests_vehicle gives the rule a name, for readable errors
-- FOREIGN KEY (vehicle_id) REFERENCES vehicles (id) says every vehicle_id must exist as an id in vehicles
-- ON DELETE RESTRICT, a vehicle can be deleted only if zero rows in maintenance_requests have it's id 
-- ON UPDATE CASCADE means change flows down to the child rows automatically