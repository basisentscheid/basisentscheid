-- SQL statements to upgrade an existing database
-- add new lines at the bottom

--
ALTER TABLE periods DROP online_voting;
ALTER TABLE issues ADD finished TIMESTAMPTZ DEFAULT NULL NULL;
--
ALTER TABLE issues ADD votingmode_admin BOOLEAN DEFAULT FALSE  NOT NULL;
