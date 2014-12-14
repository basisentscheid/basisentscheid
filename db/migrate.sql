-- SQL statements to upgrade an existing database
-- add new lines at the bottom

--
ALTER TABLE period DROP online_voting;
ALTER TABLE issue ADD finished TIMESTAMPTZ DEFAULT NULL NULL;
--
ALTER TABLE issue ADD votingmode_admin BOOLEAN DEFAULT FALSE  NOT NULL;
--
CREATE TABLE seen (
    argument INT NOT NULL,
    member INT NOT NULL,
    PRIMARY KEY (argument, member)
);
ALTER TABLE seen ADD FOREIGN KEY (comment) REFERENCES comment (id);
ALTER TABLE seen ADD FOREIGN KEY (member) REFERENCES member (id);
--
ALTER TABLE admins RENAME TO admin;
ALTER TABLE members RENAME TO member;
ALTER TABLE ngroups RENAME TO ngroup;
ALTER TABLE members_ngroups RENAME TO member_ngroup;
ALTER TABLE areas RENAME TO area;
ALTER TABLE issues RENAME TO issue;
ALTER TABLE periods RENAME TO period;
ALTER TABLE supporters RENAME TO supporter;
ALTER TABLE drafts RENAME TO draft;
ALTER TABLE ballots RENAME TO ballot;
ALTER TABLE arguments RENAME TO argument;
ALTER TABLE offlinevoters RENAME TO offlinevoter;
ALTER TABLE participants RENAME TO participant;
ALTER TABLE proposals RENAME TO proposal;
ALTER TABLE test_dbtableadmins RENAME TO test_dbtableadmin;
ALTER TABLE vote_tokens RENAME TO vote_token;
ALTER TABLE vote_votes RENAME TO vote_vote;
ALTER TABLE votingmode_tokens RENAME TO votingmode_token;
ALTER TABLE votingmode_votes RENAME TO votingmode_vote;
ALTER SEQUENCE admins_id_seq RENAME TO admin_id_seq;
ALTER SEQUENCE areas_id_seq RENAME TO area_id_seq;
ALTER SEQUENCE arguments_id_seq RENAME TO argument_id_seq;
ALTER SEQUENCE ballots_id_seq RENAME TO ballot_id_seq;
ALTER SEQUENCE drafts_id_seq RENAME TO draft_id_seq;
ALTER SEQUENCE groups_id_seq RENAME TO group_id_seq;
ALTER SEQUENCE issues_id_seq RENAME TO issue_id_seq;
ALTER SEQUENCE members_id_seq RENAME TO member_id_seq;
ALTER SEQUENCE periods_id_seq RENAME TO period_id_seq;
ALTER SEQUENCE proposals_id_seq RENAME TO proposal_id_seq;
--
ALTER TABLE argument RENAME TO comment;
ALTER SEQUENCE argument_id_seq RENAME TO comment_id_seq;
ALTER TABLE ratings RENAME COLUMN argument TO comment;
ALTER TABLE seen RENAME COLUMN argument TO comment;
ALTER TABLE comment RENAME COLUMN side TO rubric;
ALTER TABLE ratings RENAME TO rating;
--




