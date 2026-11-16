ALTER TABLE visitors_registration
    ADD COLUMN authorized_visitor_id INT(11) NULL AFTER pdl_id;

CREATE INDEX idx_visitors_registration_authorized_visitor_id
    ON visitors_registration (authorized_visitor_id);

UPDATE visitors_registration vr
JOIN pdl_authorized_visitors pav
    ON pav.pdl_id = vr.pdl_id
   AND LOWER(TRIM(pav.visitor_fname)) = LOWER(TRIM(vr.firstname))
   AND LOWER(TRIM(pav.visitor_lname)) = LOWER(TRIM(vr.lastname))
   AND LOWER(TRIM(COALESCE(pav.visitor_mname, ''))) = LOWER(TRIM(COALESCE(vr.middlename, '')))
   AND LOWER(TRIM(COALESCE(pav.visitor_suffix, ''))) = LOWER(TRIM(COALESCE(vr.suffix, '')))
SET vr.authorized_visitor_id = pav.id
WHERE vr.authorized_visitor_id IS NULL;
