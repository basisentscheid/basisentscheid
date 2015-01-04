-- SQL statements to upgrade an existing database
-- add new lines at the bottom
-- leave one blank line between migration steps

ALTER TABLE period DROP online_voting;
ALTER TABLE issue ADD finished TIMESTAMPTZ DEFAULT NULL NULL;

ALTER TABLE issue ADD votingmode_admin BOOLEAN DEFAULT FALSE  NOT NULL;

CREATE TABLE seen (
    argument INT NOT NULL,
    member INT NOT NULL,
    PRIMARY KEY (argument, member)
);
ALTER TABLE seen ADD FOREIGN KEY (comment) REFERENCES comment (id);
ALTER TABLE seen ADD FOREIGN KEY (member) REFERENCES member (id);

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

ALTER TABLE argument RENAME TO comment;
ALTER SEQUENCE argument_id_seq RENAME TO comment_id_seq;
ALTER TABLE ratings RENAME COLUMN argument TO comment;
ALTER TABLE seen RENAME COLUMN argument TO comment;
ALTER TABLE comment RENAME COLUMN side TO rubric;
ALTER TABLE ratings RENAME TO rating;

ALTER TABLE member RENAME COLUMN public_id TO realname;

ALTER TABLE member RENAME COLUMN entitled TO eligible;

ALTER TABLE member ADD verified BOOL DEFAULT false NOT NULL;

ALTER TABLE comment ADD session TEXT DEFAULT '' NOT NULL;
ALTER TABLE comment ALTER COLUMN member DROP NOT NULL;
ALTER TABLE rating ADD session TEXT DEFAULT '' NOT NULL;
ALTER TABLE rating DROP CONSTRAINT ratings_pkey;
ALTER TABLE rating ALTER COLUMN member DROP NOT NULL;

ALTER TYPE issue_state ADD VALUE 'entry' AFTER 'admission';
UPDATE issue SET state='entry' WHERE state='admission';
ALTER TABLE issue ALTER COLUMN state SET DEFAULT 'entry';

ALTER TABLE proposal ADD activity INT DEFAULT 0 NOT NULL;

ALTER TABLE notify ADD comment BOOL DEFAULT false NOT NULL;

ALTER TABLE proposal ADD CONSTRAINT "proposal_state" CHECK (
    ( state = 'draft'     AND submitted ISNULL  AND admitted ISNULL  AND cancelled ISNULL ) OR
    ( state = 'submitted' AND submitted NOTNULL AND admitted ISNULL  AND cancelled ISNULL ) OR
    ( state = 'admitted'  AND submitted NOTNULL AND admitted NOTNULL AND cancelled ISNULL ) OR
    ( state = 'done'      AND                                            cancelled NOTNULL ) OR
    ( state = 'revoked'   AND                                            cancelled NOTNULL ) OR
    ( state = 'cancelled' AND                                            cancelled NOTNULL )
);
ALTER TABLE issue ADD CONSTRAINT "issue_state" CHECK (
    ( state = 'entry'       AND debate_started ISNULL  AND preparation_started ISNULL  AND voting_started ISNULL  AND counting_started ISNULL  AND finished ISNULL  ) OR
    ( state = 'debate'      AND debate_started NOTNULL AND preparation_started ISNULL  AND voting_started ISNULL  AND counting_started ISNULL  AND finished ISNULL  ) OR
    ( state = 'preparation' AND debate_started NOTNULL AND preparation_started NOTNULL AND voting_started ISNULL  AND counting_started ISNULL  AND finished ISNULL  ) OR
    ( state = 'voting'      AND debate_started NOTNULL AND preparation_started NOTNULL AND voting_started NOTNULL AND counting_started ISNULL  AND finished ISNULL  ) OR
    ( state = 'counting'    AND debate_started NOTNULL AND preparation_started NOTNULL AND voting_started NOTNULL AND counting_started NOTNULL AND finished ISNULL  ) OR
    ( state = 'finished'    AND debate_started NOTNULL AND preparation_started NOTNULL                                                         AND finished NOTNULL ) OR
      state = 'cancelled'
);
ALTER TABLE issue ADD CONSTRAINT "clear" CHECK (
    (state IN ('entry', 'debate', 'preparation', 'voting', 'counting') AND clear ISNULL AND cleared ISNULL ) OR
    (state IN ('finished', 'cancelled')                               AND (clear NOTNULL != cleared NOTNULL) )
);

CREATE TABLE session (
    session_id  TEXT                         NOT NULL PRIMARY KEY,
    last_active TIMESTAMPTZ DEFAULT now()    NOT NULL,
    data        TEXT        DEFAULT ''::text NOT NULL
);

ALTER TABLE comment ALTER COLUMN session DROP NOT NULL;
ALTER TABLE comment ALTER COLUMN session SET DEFAULT NULL ;
UPDATE comment SET session=NULL;
ALTER TABLE comment ADD FOREIGN KEY (session) REFERENCES session (session_id) ON UPDATE RESTRICT ON DELETE SET NULL;
ALTER TABLE rating ALTER COLUMN session DROP NOT NULL;
ALTER TABLE rating ALTER COLUMN session SET DEFAULT NULL ;
UPDATE rating SET session=NULL;
ALTER TABLE rating ADD FOREIGN KEY (session) REFERENCES session (session_id) ON UPDATE RESTRICT ON DELETE SET NULL;

ALTER TYPE proposal_state ADD VALUE 'cancelled_admin' BEFORE 'cancelled';
ALTER TYPE proposal_state ADD VALUE 'cancelled_debate' BEFORE 'cancelled';
ALTER TYPE proposal_state ADD VALUE 'cancelled_interval' BEFORE 'cancelled';
ALTER TABLE proposal DROP CONSTRAINT "proposal_state";
UPDATE proposal SET state='cancelled_interval' WHERE state='cancelled' AND cancelled > submitted + interval '6 months';
UPDATE proposal SET state='cancelled_debate'   WHERE state='cancelled';
ALTER TABLE proposal ADD  CONSTRAINT "proposal_state" CHECK (
    ( state = 'draft'              AND submitted ISNULL  AND admitted ISNULL  AND cancelled ISNULL ) OR
    ( state = 'submitted'          AND submitted NOTNULL AND admitted ISNULL  AND cancelled ISNULL ) OR
    ( state = 'admitted'           AND submitted NOTNULL AND admitted NOTNULL AND cancelled ISNULL ) OR
    ( state = 'revoked'            AND                                            cancelled NOTNULL ) OR
    ( state = 'cancelled_interval' AND                                            cancelled NOTNULL ) OR
    ( state = 'cancelled_debate'   AND                                            cancelled NOTNULL ) OR
    ( state = 'cancelled_admin'    AND                                            cancelled NOTNULL )
);

ALTER TABLE proposal ADD annotation TEXT DEFAULT '' NOT NULL;

ALTER TABLE ngroup ADD minimum_quorum_votingmode INT DEFAULT 25 NOT NULL;
UPDATE ngroup SET minimum_quorum_votingmode = minimum_population / 20;

ALTER TABLE notify ADD new_draft boolean DEFAULT false NOT NULL;

DROP TABLE test_dbtableadmin;

CREATE SEQUENCE ngroup_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;
ALTER SEQUENCE ngroup_id_seq OWNED BY ngroup.id;
ALTER TABLE ONLY ngroup ALTER COLUMN id SET DEFAULT nextval('ngroup_id_seq'::regclass);
ALTER SEQUENCE ngroup_id_seq RESTART WITH 10000;
