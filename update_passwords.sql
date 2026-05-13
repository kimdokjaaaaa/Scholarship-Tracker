USE scholarship_tracker;
UPDATE users SET password = '$2y$10$Dgm1QM0d.h/1lyJx2Vyd2.WvkCxVUhb/Bc.Fw2R/94blOQYe1zBNa' WHERE username IN ('admin', 'jreyes', 'mcruz');
SELECT username, password FROM users;
