--
-- PostgreSQL database dump
--

SET statement_timeout = 0;
SET lock_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SET check_function_bodies = false;
SET client_min_messages = warning;

SET search_path = public, pg_catalog;

--
-- Name: issue_state; Type: TYPE; Schema: public; Owner: -
--

CREATE TYPE issue_state AS ENUM (
    'admission',
    'debate',
    'preparation',
    'voting',
    'counting',
    'finished',
    'cancelled'
);


--
-- Name: notify_interest; Type: TYPE; Schema: public; Owner: -
--

CREATE TYPE notify_interest AS ENUM (
    'all',
    'ngroups',
    'participant',
    'supporter',
    'proponent'
);


--
-- Name: period_state; Type: TYPE; Schema: public; Owner: -
--

CREATE TYPE period_state AS ENUM (
    'ballot_application',
    'ballot_assignment',
    'ballot_preparation'
);


--
-- Name: proposal_state; Type: TYPE; Schema: public; Owner: -
--

CREATE TYPE proposal_state AS ENUM (
    'cancelled',
    'revoked',
    'done',
    'draft',
    'submitted',
    'admitted'
);


--
-- Name: TYPE proposal_state; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON TYPE proposal_state IS 'order is used for display';


--
-- Name: rubric; Type: TYPE; Schema: public; Owner: -
--

CREATE TYPE rubric AS ENUM (
    'pro',
    'contra',
    'discussion'
);


SET default_tablespace = '';

SET default_with_oids = false;

--
-- Name: admin; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE admin (
    id integer NOT NULL,
    username character varying(32) NOT NULL,
    password character varying(123) NOT NULL
);


--
-- Name: admin_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE admin_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: admin_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE admin_id_seq OWNED BY admin.id;


--
-- Name: area; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE area (
    id integer NOT NULL,
    ngroup integer NOT NULL,
    name character varying(64) NOT NULL,
    participants integer DEFAULT 0 NOT NULL
);


--
-- Name: COLUMN area.participants; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN area.participants IS 'cache';


--
-- Name: area_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE area_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: area_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE area_id_seq OWNED BY area.id;


--
-- Name: ballot; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE ballot (
    id integer NOT NULL,
    period integer NOT NULL,
    name text NOT NULL,
    agents text NOT NULL,
    opening time with time zone NOT NULL,
    voters integer DEFAULT 0 NOT NULL,
    approved boolean DEFAULT false NOT NULL,
    ngroup integer NOT NULL
);


--
-- Name: COLUMN ballot.voters; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN ballot.voters IS 'cache';


--
-- Name: ballot_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE ballot_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ballot_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE ballot_id_seq OWNED BY ballot.id;


--
-- Name: comment; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE comment (
    id integer NOT NULL,
    proposal integer NOT NULL,
    parent integer,
    rubric rubric NOT NULL,
    title text NOT NULL,
    content text NOT NULL,
    rating integer DEFAULT 0 NOT NULL,
    member integer NOT NULL,
    created timestamp with time zone DEFAULT now() NOT NULL,
    updated timestamp with time zone,
    removed boolean DEFAULT false NOT NULL
);


--
-- Name: comment_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE comment_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: comment_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE comment_id_seq OWNED BY comment.id;


--
-- Name: cron_lock; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE cron_lock (
    pid integer NOT NULL
);


--
-- Name: draft; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE draft (
    id integer NOT NULL,
    proposal integer NOT NULL,
    title text NOT NULL,
    content text NOT NULL,
    reason text NOT NULL,
    author integer,
    created timestamp with time zone DEFAULT now() NOT NULL
);


--
-- Name: COLUMN draft.author; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN draft.author IS 'NULL = admin';


--
-- Name: draft_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE draft_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: draft_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE draft_id_seq OWNED BY draft.id;


--
-- Name: ngroup; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE ngroup (
    id integer NOT NULL,
    parent integer,
    name text NOT NULL,
    active boolean DEFAULT false NOT NULL,
    minimum_population integer DEFAULT 500 NOT NULL
);


--
-- Name: group_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE group_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: group_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE group_id_seq OWNED BY ngroup.id;


--
-- Name: issue; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE issue (
    id integer NOT NULL,
    period integer,
    area integer NOT NULL,
    votingmode_demanders integer DEFAULT 0 NOT NULL,
    votingmode_reached boolean DEFAULT false,
    votingmode_admin boolean DEFAULT false NOT NULL,
    debate_started timestamp with time zone,
    preparation_started timestamp with time zone,
    voting_started timestamp with time zone,
    counting_started timestamp with time zone,
    finished timestamp with time zone,
    clear date,
    cleared timestamp with time zone,
    state issue_state DEFAULT 'admission'::issue_state NOT NULL
);


--
-- Name: issue_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE issue_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: issue_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE issue_id_seq OWNED BY issue.id;


--
-- Name: member; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE member (
    id integer NOT NULL,
    invite character(24) NOT NULL,
    invite_expiry timestamp with time zone NOT NULL,
    created timestamp with time zone DEFAULT now() NOT NULL,
    activated timestamp with time zone,
    username character varying(32),
    password character varying(123),
    password_reset_code character(24),
    password_reset_code_expiry timestamp with time zone,
    realname text DEFAULT ''::text NOT NULL,
    profile text DEFAULT ''::text NOT NULL,
    eligible boolean DEFAULT false NOT NULL,
    mail text,
    mail_unconfirmed text,
    mail_code character(16),
    mail_code_expiry timestamp with time zone,
    mail_lock_expiry timestamp with time zone,
    fingerprint text DEFAULT ''::text NOT NULL,
    hide_help text DEFAULT ''::text NOT NULL
);


--
-- Name: COLUMN member.fingerprint; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN member.fingerprint IS 'PGP fingerprint for email encryption';


--
-- Name: member_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE member_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: member_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE member_id_seq OWNED BY member.id;


--
-- Name: member_ngroup; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE member_ngroup (
    member integer NOT NULL,
    ngroup integer NOT NULL
);


--
-- Name: notify; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE notify (
    member integer NOT NULL,
    interest notify_interest NOT NULL,
    new_proposal boolean DEFAULT false NOT NULL,
    admitted boolean DEFAULT false NOT NULL,
    submitted boolean DEFAULT false NOT NULL,
    debate boolean DEFAULT false NOT NULL,
    voting boolean DEFAULT false NOT NULL,
    finished boolean DEFAULT false NOT NULL
);


--
-- Name: offlinevoter; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE offlinevoter (
    period integer NOT NULL,
    member integer NOT NULL,
    ballot integer,
    agent boolean DEFAULT false NOT NULL
);


--
-- Name: COLUMN offlinevoter.ballot; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN offlinevoter.ballot IS 'NULL = postal voting';


--
-- Name: participant; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE participant (
    member integer NOT NULL,
    area integer NOT NULL,
    activated date DEFAULT ('now'::text)::date NOT NULL
);


--
-- Name: period; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE period (
    id integer NOT NULL,
    ngroup integer NOT NULL,
    debate timestamp with time zone NOT NULL,
    preparation timestamp with time zone NOT NULL,
    voting timestamp with time zone NOT NULL,
    counting timestamp with time zone NOT NULL,
    ballot_voting boolean NOT NULL,
    ballot_assignment timestamp with time zone,
    ballot_preparation timestamp with time zone,
    state period_state DEFAULT 'ballot_application'::period_state NOT NULL,
    postage boolean DEFAULT false NOT NULL
);


--
-- Name: COLUMN period.postage; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN period.postage IS 'postage for postal voting has started';


--
-- Name: period_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE period_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: period_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE period_id_seq OWNED BY period.id;


--
-- Name: proposal; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE proposal (
    id integer NOT NULL,
    issue integer NOT NULL,
    title text NOT NULL,
    content text NOT NULL,
    reason text NOT NULL,
    submitted timestamp with time zone,
    supporters integer DEFAULT 0 NOT NULL,
    quorum_reached boolean DEFAULT false NOT NULL,
    admission_decision text,
    admitted timestamp with time zone,
    cancelled timestamp with time zone,
    revoke timestamp with time zone,
    state proposal_state DEFAULT 'draft'::proposal_state NOT NULL,
    yes integer,
    no integer,
    abstention integer,
    score integer,
    accepted boolean
);


--
-- Name: COLUMN proposal.supporters; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN proposal.supporters IS 'cache';


--
-- Name: COLUMN proposal.revoke; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN proposal.revoke IS 'date to revoke proposal if it has then not the required number of proponents';


--
-- Name: proposal_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE proposal_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: proposal_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE proposal_id_seq OWNED BY proposal.id;


--
-- Name: rating; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE rating (
    comment integer NOT NULL,
    member integer NOT NULL,
    score integer DEFAULT 0 NOT NULL
);


--
-- Name: seen; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE seen (
    comment integer NOT NULL,
    member integer NOT NULL
);


--
-- Name: supporter; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE supporter (
    proposal integer NOT NULL,
    member integer NOT NULL,
    anonymous boolean DEFAULT false NOT NULL,
    proponent text,
    created date DEFAULT ('now'::text)::date NOT NULL,
    proponent_confirmed boolean DEFAULT false NOT NULL
);


--
-- Name: test_dbtableadmin; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE test_dbtableadmin (
    id integer NOT NULL,
    manualorder integer DEFAULT 0 NOT NULL,
    text text NOT NULL,
    area text NOT NULL,
    "int" integer NOT NULL,
    "boolean" boolean NOT NULL,
    dropdown integer NOT NULL
);


--
-- Name: test_dbtableadmin_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE test_dbtableadmin_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: test_dbtableadmin_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE test_dbtableadmin_id_seq OWNED BY test_dbtableadmin.id;


--
-- Name: vote_token; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE vote_token (
    member integer NOT NULL,
    issue integer NOT NULL,
    token character(8) NOT NULL
);


--
-- Name: vote_vote; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE vote_vote (
    token character(8) NOT NULL,
    vote text,
    votetime timestamp with time zone DEFAULT now() NOT NULL
);


--
-- Name: votingmode_token; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE votingmode_token (
    member integer NOT NULL,
    issue integer NOT NULL,
    token character(8) NOT NULL
);


--
-- Name: votingmode_vote; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE votingmode_vote (
    token character(8) NOT NULL,
    demand boolean NOT NULL,
    votetime timestamp with time zone DEFAULT now() NOT NULL
);


--
-- Name: id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY admin ALTER COLUMN id SET DEFAULT nextval('admin_id_seq'::regclass);


--
-- Name: id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY area ALTER COLUMN id SET DEFAULT nextval('area_id_seq'::regclass);


--
-- Name: id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY ballot ALTER COLUMN id SET DEFAULT nextval('ballot_id_seq'::regclass);


--
-- Name: id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY comment ALTER COLUMN id SET DEFAULT nextval('comment_id_seq'::regclass);


--
-- Name: id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY draft ALTER COLUMN id SET DEFAULT nextval('draft_id_seq'::regclass);


--
-- Name: id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY issue ALTER COLUMN id SET DEFAULT nextval('issue_id_seq'::regclass);


--
-- Name: id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY member ALTER COLUMN id SET DEFAULT nextval('member_id_seq'::regclass);


--
-- Name: id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY period ALTER COLUMN id SET DEFAULT nextval('period_id_seq'::regclass);


--
-- Name: id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY proposal ALTER COLUMN id SET DEFAULT nextval('proposal_id_seq'::regclass);


--
-- Name: id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY test_dbtableadmin ALTER COLUMN id SET DEFAULT nextval('test_dbtableadmin_id_seq'::regclass);


--
-- Name: admins_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY admin
    ADD CONSTRAINT admins_pkey PRIMARY KEY (id);


--
-- Name: admins_username_key; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY admin
    ADD CONSTRAINT admins_username_key UNIQUE (username);


--
-- Name: areas_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY area
    ADD CONSTRAINT areas_pkey PRIMARY KEY (id);


--
-- Name: ballot_voting_demanders_token_key; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY votingmode_token
    ADD CONSTRAINT ballot_voting_demanders_token_key UNIQUE (token);


--
-- Name: ballots_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY ballot
    ADD CONSTRAINT ballots_pkey PRIMARY KEY (id);


--
-- Name: comments_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY comment
    ADD CONSTRAINT comments_pkey PRIMARY KEY (id);


--
-- Name: drafts_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY draft
    ADD CONSTRAINT drafts_pkey PRIMARY KEY (id);


--
-- Name: groups_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY ngroup
    ADD CONSTRAINT groups_pkey PRIMARY KEY (id);


--
-- Name: issues_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY issue
    ADD CONSTRAINT issues_pkey PRIMARY KEY (id);


--
-- Name: members_groups_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY member_ngroup
    ADD CONSTRAINT members_groups_pkey PRIMARY KEY (member, ngroup);


--
-- Name: members_invite_key; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY member
    ADD CONSTRAINT members_invite_key UNIQUE (invite);


--
-- Name: members_password_reset_code_key; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY member
    ADD CONSTRAINT members_password_reset_code_key UNIQUE (password_reset_code);


--
-- Name: members_username_key; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY member
    ADD CONSTRAINT members_username_key UNIQUE (username);


--
-- Name: notify_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY notify
    ADD CONSTRAINT notify_pkey PRIMARY KEY (member, interest);


--
-- Name: offline_demanders_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY votingmode_token
    ADD CONSTRAINT offline_demanders_pkey PRIMARY KEY (member, issue);


--
-- Name: participants_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY participant
    ADD CONSTRAINT participants_pkey PRIMARY KEY (member, area);


--
-- Name: periods_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY period
    ADD CONSTRAINT periods_pkey PRIMARY KEY (id);


--
-- Name: proposals_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY proposal
    ADD CONSTRAINT proposals_pkey PRIMARY KEY (id);


--
-- Name: ratings_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY rating
    ADD CONSTRAINT ratings_pkey PRIMARY KEY (comment, member);


--
-- Name: seen_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY seen
    ADD CONSTRAINT seen_pkey PRIMARY KEY (comment, member);


--
-- Name: supporters_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY supporter
    ADD CONSTRAINT supporters_pkey PRIMARY KEY (proposal, member);


--
-- Name: test_dbtableadmin_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY test_dbtableadmin
    ADD CONSTRAINT test_dbtableadmin_pkey PRIMARY KEY (id);


--
-- Name: users_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY member
    ADD CONSTRAINT users_pkey PRIMARY KEY (id);


--
-- Name: vote_tokens_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY vote_token
    ADD CONSTRAINT vote_tokens_pkey PRIMARY KEY (member, issue);


--
-- Name: vote_tokens_token_key; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY vote_token
    ADD CONSTRAINT vote_tokens_token_key UNIQUE (token);


--
-- Name: voters_ballot_member_key; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY offlinevoter
    ADD CONSTRAINT voters_ballot_member_key UNIQUE (ballot, member);


--
-- Name: voters_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY offlinevoter
    ADD CONSTRAINT voters_pkey PRIMARY KEY (period, member);


--
-- Name: areas_ngroup_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY area
    ADD CONSTRAINT areas_ngroup_fkey FOREIGN KEY (ngroup) REFERENCES ngroup(id) ON UPDATE RESTRICT ON DELETE RESTRICT;


--
-- Name: ballot_voting_demanders_votes_token_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY votingmode_vote
    ADD CONSTRAINT ballot_voting_demanders_votes_token_fkey FOREIGN KEY (token) REFERENCES votingmode_token(token) ON UPDATE RESTRICT ON DELETE CASCADE;


--
-- Name: ballots_ngroup_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY ballot
    ADD CONSTRAINT ballots_ngroup_fkey FOREIGN KEY (ngroup) REFERENCES ngroup(id) ON UPDATE RESTRICT ON DELETE RESTRICT;


--
-- Name: ballots_period_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY ballot
    ADD CONSTRAINT ballots_period_fkey FOREIGN KEY (period) REFERENCES period(id) ON UPDATE RESTRICT ON DELETE CASCADE;


--
-- Name: comments_member_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY comment
    ADD CONSTRAINT comments_member_fkey FOREIGN KEY (member) REFERENCES member(id) ON UPDATE RESTRICT ON DELETE RESTRICT;


--
-- Name: comments_proposal_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY comment
    ADD CONSTRAINT comments_proposal_fkey FOREIGN KEY (proposal) REFERENCES proposal(id) ON UPDATE RESTRICT ON DELETE CASCADE;


--
-- Name: drafts_author_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY draft
    ADD CONSTRAINT drafts_author_fkey FOREIGN KEY (author) REFERENCES member(id) ON UPDATE RESTRICT ON DELETE RESTRICT;


--
-- Name: drafts_proposal_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY draft
    ADD CONSTRAINT drafts_proposal_fkey FOREIGN KEY (proposal) REFERENCES proposal(id) ON UPDATE RESTRICT ON DELETE CASCADE;


--
-- Name: issues_area_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY issue
    ADD CONSTRAINT issues_area_fkey FOREIGN KEY (area) REFERENCES area(id) ON UPDATE RESTRICT ON DELETE RESTRICT;


--
-- Name: issues_period_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY issue
    ADD CONSTRAINT issues_period_fkey FOREIGN KEY (period) REFERENCES period(id) ON UPDATE RESTRICT ON DELETE RESTRICT;


--
-- Name: members_ngroups_member_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY member_ngroup
    ADD CONSTRAINT members_ngroups_member_fkey FOREIGN KEY (member) REFERENCES member(id) ON UPDATE RESTRICT ON DELETE CASCADE;


--
-- Name: notify_member_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY notify
    ADD CONSTRAINT notify_member_fkey FOREIGN KEY (member) REFERENCES member(id) ON UPDATE RESTRICT ON DELETE CASCADE;


--
-- Name: offlinedemanders_issue_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY votingmode_token
    ADD CONSTRAINT offlinedemanders_issue_fkey FOREIGN KEY (issue) REFERENCES issue(id) ON UPDATE RESTRICT ON DELETE CASCADE;


--
-- Name: offlinedemanders_user_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY votingmode_token
    ADD CONSTRAINT offlinedemanders_user_fkey FOREIGN KEY (member) REFERENCES member(id) ON UPDATE RESTRICT ON DELETE CASCADE;


--
-- Name: participants_area_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY participant
    ADD CONSTRAINT participants_area_fkey FOREIGN KEY (area) REFERENCES area(id) ON UPDATE RESTRICT ON DELETE CASCADE;


--
-- Name: participants_member_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY participant
    ADD CONSTRAINT participants_member_fkey FOREIGN KEY (member) REFERENCES member(id) ON UPDATE RESTRICT ON DELETE CASCADE;


--
-- Name: periods_ngroup_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY period
    ADD CONSTRAINT periods_ngroup_fkey FOREIGN KEY (ngroup) REFERENCES ngroup(id) ON UPDATE RESTRICT ON DELETE RESTRICT;


--
-- Name: proposals_issue_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY proposal
    ADD CONSTRAINT proposals_issue_fkey FOREIGN KEY (issue) REFERENCES issue(id) ON UPDATE RESTRICT ON DELETE CASCADE;


--
-- Name: ratings_comment_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY rating
    ADD CONSTRAINT ratings_comment_fkey FOREIGN KEY (comment) REFERENCES comment(id) ON UPDATE RESTRICT ON DELETE CASCADE;


--
-- Name: ratings_member_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY rating
    ADD CONSTRAINT ratings_member_fkey FOREIGN KEY (member) REFERENCES member(id) ON UPDATE RESTRICT ON DELETE RESTRICT;


--
-- Name: seen_comment_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY seen
    ADD CONSTRAINT seen_comment_fkey FOREIGN KEY (comment) REFERENCES comment(id);


--
-- Name: seen_member_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY seen
    ADD CONSTRAINT seen_member_fkey FOREIGN KEY (member) REFERENCES member(id);


--
-- Name: supporters_proposal_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY supporter
    ADD CONSTRAINT supporters_proposal_fkey FOREIGN KEY (proposal) REFERENCES proposal(id) ON UPDATE RESTRICT ON DELETE CASCADE;


--
-- Name: supporters_user_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY supporter
    ADD CONSTRAINT supporters_user_fkey FOREIGN KEY (member) REFERENCES member(id) ON UPDATE RESTRICT ON DELETE CASCADE;


--
-- Name: vote_token_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY vote_vote
    ADD CONSTRAINT vote_token_fkey FOREIGN KEY (token) REFERENCES vote_token(token) ON UPDATE RESTRICT ON DELETE CASCADE;


--
-- Name: voters_ballot_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY offlinevoter
    ADD CONSTRAINT voters_ballot_fkey FOREIGN KEY (ballot) REFERENCES ballot(id) ON UPDATE RESTRICT ON DELETE CASCADE;


--
-- Name: voters_member_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY offlinevoter
    ADD CONSTRAINT voters_member_fkey FOREIGN KEY (member) REFERENCES member(id) ON UPDATE RESTRICT ON DELETE CASCADE;


--
-- Name: voters_period_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY offlinevoter
    ADD CONSTRAINT voters_period_fkey FOREIGN KEY (period) REFERENCES period(id) ON UPDATE RESTRICT ON DELETE CASCADE;


--
-- PostgreSQL database dump complete
--

