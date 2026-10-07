-- deploy/db/03-demo-data.sql
-- Extra fictional demo rows for the live site (runs after schema.sql and seed.sql),
-- so the list, filters and charts have something to show. All VINs are fake.

USE maintenance_dashboard;

INSERT INTO vehicles (vin, make, model, model_year) VALUES
  ('TESTVIN0000000004', 'Tesla',     'Model 3',  2024),
  ('TESTVIN0000000005', 'Chevrolet', 'Silverado', 2021),
  ('TESTVIN0000000006', 'Ford',      'Transit',  2023);

INSERT INTO maintenance_requests (vehicle_id, title, description, priority, status) VALUES
  (4, 'Software update failed',        'Infotainment shows update error 0x21.',      'medium', 'open'),
  (4, 'Cabin air filter replacement',  NULL,                                         'low',    'completed'),
  (5, 'Check engine light',            'Light came on during highway driving.',      'high',   'in_progress'),
  (5, 'Transmission slipping',         'Slips between 2nd and 3rd gear when cold.',  'high',   'open'),
  (6, 'Sliding door sticks',           'Passenger-side door needs force to close.',  'medium', 'in_progress'),
  (6, 'Rotate tires',                  NULL,                                         'low',    'open'),
  (2, 'Battery replacement',           'Slow crank in the mornings.',                'medium', 'completed'),
  (3, 'AC blowing warm air',           'Possible refrigerant leak.',                 'high',   'open');
