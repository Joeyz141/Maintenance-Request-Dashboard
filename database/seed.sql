-- database/seed.sql
-- Fictional sample data for local development and testing only

USE maintenance_dashboard;

INSERT INTO vehicles (vin, make, model, model_year) VALUES
  ('TESTVIN0000000001', 'Ford',   'F-150',   2022),
  ('TESTVIN0000000002', 'Toyota', 'Camry',   2023),
  ('TESTVIN0000000003', 'Honda',  'Accord',  2020);

INSERT INTO maintenance_requests (vehicle_id, title, description, priority, status) VALUES
  (1, 'Brake noise when stopping', 'Squealing from front left wheel.',     'high',   'open'),
  (1, 'Oil change due',            NULL,                                   'low',    'completed'),
  (2, 'Tire pressure warning',     'Dashboard light on after cold start.', 'medium', 'in_progress'),
  (3, 'Replace wiper blades',      NULL,                                   'low',    'open');

  -- INSERT INTO adds rows to vehicles
  -- VALUES each set of parentheses is one row