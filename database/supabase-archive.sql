-- Run once in the Supabase SQL editor for archive_surat_metadata.
ALTER TABLE public.archive_surat_metadata
  ADD COLUMN IF NOT EXISTS letter_id integer,
  ADD COLUMN IF NOT EXISTS completion_stage smallint NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS completion_status text NOT NULL DEFAULT 'Belum selesai',
  ADD COLUMN IF NOT EXISTS completion_actor text,
  ADD COLUMN IF NOT EXISTS completion_date date,
  ADD COLUMN IF NOT EXISTS completion_recorded_by text,
  ADD COLUMN IF NOT EXISTS completion_recorded_at timestamptz,
  ADD COLUMN IF NOT EXISTS completion_drive_url text;