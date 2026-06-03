-- Drop the foreign key constraint from saturday_special_schedules table
ALTER TABLE saturday_special_schedules DROP FOREIGN KEY saturday_special_schedules_ibfk_1;

-- Optional: Also modify the user_id column to VARCHAR if it's currently INT
ALTER TABLE saturday_special_schedules MODIFY COLUMN user_id VARCHAR(255);
