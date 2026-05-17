-- Robust insert for TOPTOUR collection task.
-- 1) Auto-detects table prefix.
-- 2) Fills required created_at/updated_at columns.
-- 3) Does not hardcode ID (AUTO_INCREMENT handles it).
-- 4) Reuses destination_id from task #5 when available.

SET @table_name := (
    SELECT table_name
    FROM information_schema.tables
    WHERE table_schema = DATABASE()
      AND table_name LIKE '%toptour_ref_collection_tasks'
    ORDER BY (table_name = 'wp_toptour_ref_collection_tasks') DESC, table_name
    LIMIT 1
);

-- Fail fast when the plugin table does not exist in the selected database.
SELECT IF(@table_name IS NULL, 'ERROR: table %toptour_ref_collection_tasks not found in current DB', CONCAT('Using table: ', @table_name)) AS table_check;

SET @insert_sql := CONCAT(
    'INSERT INTO ', @table_name, ' (',
    'task_title, destination_id, frequency, target_type, target_id, query_text, source_hint, expected_source_type, task_status, priority, notes, created_at, updated_at',
    ') VALUES (',
    '?, ',
    'COALESCE((SELECT destination_id FROM ', @table_name, ' WHERE id = ? LIMIT 1), 0), ',
    '?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW()',
    ')'
);

SET @task_title := 'Demo: Wellness hotel reality signals (reviews + photo evidence)';
SET @source_task_id := 5;
SET @frequency := 'manual';
SET @target_type := 'collection_task';
SET @target_id := 5;
SET @query_text := 'Vyhladajte kandidatske zdroje (candidate_sources), cakajuce nalezy (pending_findings), kandidatov na fotodokumentaciu (photo_evidence_candidates) a rozpory medzi oficialnymi prislubmi a dokazmi od hosti pre wellness hotely.';
SET @source_hint := 'TripAdvisor, Booking.com, Google Reviews, Instagram, YouTube';
SET @expected_source_type := 'mixed';
SET @task_status := 'pending';
SET @priority := 'high';
SET @notes := 'Instructions: Provide concrete URLs and at least 5 photo candidates with observation_summary.';

PREPARE insert_stmt FROM @insert_sql;
EXECUTE insert_stmt USING
    @task_title,
    @source_task_id,
    @frequency,
    @target_type,
    @target_id,
    @query_text,
    @source_hint,
    @expected_source_type,
    @task_status,
    @priority,
    @notes;
DEALLOCATE PREPARE insert_stmt;

SET @new_task_id := LAST_INSERT_ID();
SELECT @new_task_id AS inserted_task_id;

SET @select_sql := CONCAT(
    'SELECT id, task_title, destination_id, frequency, target_type, target_id, expected_source_type, task_status, priority, created_at, updated_at ',
    'FROM ', @table_name, ' WHERE id = ?'
);

PREPARE select_stmt FROM @select_sql;
EXECUTE select_stmt USING @new_task_id;
DEALLOCATE PREPARE select_stmt;
