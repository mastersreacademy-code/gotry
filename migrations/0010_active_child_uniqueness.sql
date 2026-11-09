-- Prevent a parent from creating multiple active interests for the same child
-- and activity, even when selecting different trial sessions. Historical
-- duplicate rows are retained; these guards only block new duplicates.
CREATE TRIGGER IF NOT EXISTS interest_active_child_insert
BEFORE INSERT ON interests
WHEN NEW.status='interested'
BEGIN
 SELECT RAISE(ABORT,'child already registered for this activity')
 WHERE EXISTS(
  SELECT 1 FROM interests i
  WHERE i.activity_id=NEW.activity_id AND i.parent_id=NEW.parent_id
    AND lower(trim(i.child_name))=lower(trim(NEW.child_name))
    AND i.status='interested'
 );
END;
CREATE TRIGGER IF NOT EXISTS interest_active_child_update
BEFORE UPDATE OF status,child_name,activity_id,parent_id ON interests
WHEN NEW.status='interested'
BEGIN
 SELECT RAISE(ABORT,'child already registered for this activity')
 WHERE EXISTS(
  SELECT 1 FROM interests i
  WHERE i.id<>OLD.id AND i.activity_id=NEW.activity_id AND i.parent_id=NEW.parent_id
    AND lower(trim(i.child_name))=lower(trim(NEW.child_name))
    AND i.status='interested'
 );
END;
