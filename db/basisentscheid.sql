--
-- PostgreSQL database dump
--

SET statement_timeout = 0;
SET lock_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SET check_function_bodies = false;
SET client_min_messages = warning;

--
-- Name: plpgsql; Type: EXTENSION; Schema: -; Owner: -
--

CREATE EXTENSION IF NOT EXISTS plpgsql WITH SCHEMA pg_catalog;


--
-- Name: EXTENSION plpgsql; Type: COMMENT; Schema: -; Owner: -
--

COMMENT ON EXTENSION plpgsql IS 'PL/pgSQL procedural language';


SET search_path = public, pg_catalog;

--
-- Name: argument_side; Type: TYPE; Schema: public; Owner: -
--

CREATE TYPE argument_side AS ENUM (
    'pro',
    'contra'
);


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
    'cleared',
    'cancelled'
);


--
-- Name: proposal_state; Type: TYPE; Schema: public; Owner: -
--

CREATE TYPE proposal_state AS ENUM (
    'draft',
    'submitted',
    'admitted',
    'revoked',
    'cancelled',
    'done'
);


SET default_tablespace = '';

SET default_with_oids = false;

--
-- Name: admins; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE admins (
    id integer NOT NULL,
    username character varying(32) NOT NULL,
    password character varying(123) NOT NULL
);


--
-- Name: admins_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE admins_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: admins_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE admins_id_seq OWNED BY admins.id;


--
-- Name: areas; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE areas (
    id integer NOT NULL,
    name character varying(64) NOT NULL,
    participants integer DEFAULT 0 NOT NULL
);


--
-- Name: COLUMN areas.participants; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN areas.participants IS 'cache';


--
-- Name: areas_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE areas_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: areas_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE areas_id_seq OWNED BY areas.id;


--
-- Name: arguments; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE arguments (
    id integer NOT NULL,
    proposal integer NOT NULL,
    parent integer,
    created timestamp with time zone DEFAULT now() NOT NULL,
    member integer NOT NULL,
    title text NOT NULL,
    content text NOT NULL,
    plus integer DEFAULT 0 NOT NULL,
    minus integer DEFAULT 0 NOT NULL,
    side argument_side NOT NULL
);


--
-- Name: arguments_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE arguments_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: arguments_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE arguments_id_seq OWNED BY arguments.id;


--
-- Name: ballots; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE ballots (
    id integer NOT NULL,
    name text NOT NULL,
    period integer NOT NULL,
    approved boolean DEFAULT false NOT NULL,
    opening time with time zone NOT NULL,
    voters integer DEFAULT 0 NOT NULL,
    agents text NOT NULL
);


--
-- Name: COLUMN ballots.voters; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN ballots.voters IS 'cache';


--
-- Name: ballots_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE ballots_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ballots_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE ballots_id_seq OWNED BY ballots.id;


--
-- Name: issues; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE issues (
    id integer NOT NULL,
    period integer,
    area integer NOT NULL,
    secret_demanders integer DEFAULT 0 NOT NULL,
    secret_reached boolean DEFAULT false,
    state issue_state DEFAULT 'admission'::issue_state NOT NULL,
    clear date
);


--
-- Name: issues_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE issues_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: issues_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE issues_id_seq OWNED BY issues.id;


--
-- Name: members; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE members (
    id integer NOT NULL,
    auid character(36) NOT NULL,
    username character varying(32)
);


--
-- Name: members_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE members_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: members_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE members_id_seq OWNED BY members.id;


--
-- Name: offline_demanders; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE offline_demanders (
    member integer NOT NULL,
    issue integer NOT NULL
);


--
-- Name: participants; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE participants (
    member integer NOT NULL,
    area integer NOT NULL,
    activated date DEFAULT ('now'::text)::date NOT NULL
);


--
-- Name: periods; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE periods (
    id integer NOT NULL,
    debate timestamp with time zone NOT NULL,
    preparation timestamp with time zone NOT NULL,
    voting timestamp with time zone NOT NULL,
    counting timestamp with time zone NOT NULL,
    online boolean NOT NULL,
    secret boolean NOT NULL
);


--
-- Name: periods_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE periods_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: periods_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE periods_id_seq OWNED BY periods.id;


--
-- Name: proposals; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE proposals (
    id integer NOT NULL,
    title text NOT NULL,
    content text NOT NULL,
    reason text NOT NULL,
    issue integer NOT NULL,
    supporters integer DEFAULT 0 NOT NULL,
    quorum_reached boolean DEFAULT false NOT NULL,
    admission_decision text,
    state proposal_state DEFAULT 'submitted'::proposal_state NOT NULL,
    submitted date DEFAULT now() NOT NULL,
    proponents text NOT NULL
);


--
-- Name: COLUMN proposals.supporters; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN proposals.supporters IS 'cache';


--
-- Name: proposals_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE proposals_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: proposals_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE proposals_id_seq OWNED BY proposals.id;


--
-- Name: ratings; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE ratings (
    argument integer NOT NULL,
    member integer NOT NULL,
    positive boolean NOT NULL
);


--
-- Name: supporters; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE supporters (
    proposal integer NOT NULL,
    member integer NOT NULL,
    created date DEFAULT ('now'::text)::date NOT NULL
);


--
-- Name: voters; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE voters (
    member integer NOT NULL,
    ballot integer NOT NULL,
    agent boolean DEFAULT false NOT NULL,
    period integer NOT NULL
);


--
-- Name: id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY admins ALTER COLUMN id SET DEFAULT nextval('admins_id_seq'::regclass);


--
-- Name: id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY areas ALTER COLUMN id SET DEFAULT nextval('areas_id_seq'::regclass);


--
-- Name: id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY arguments ALTER COLUMN id SET DEFAULT nextval('arguments_id_seq'::regclass);


--
-- Name: id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY ballots ALTER COLUMN id SET DEFAULT nextval('ballots_id_seq'::regclass);


--
-- Name: id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY issues ALTER COLUMN id SET DEFAULT nextval('issues_id_seq'::regclass);


--
-- Name: id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY members ALTER COLUMN id SET DEFAULT nextval('members_id_seq'::regclass);


--
-- Name: id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY periods ALTER COLUMN id SET DEFAULT nextval('periods_id_seq'::regclass);


--
-- Name: id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY proposals ALTER COLUMN id SET DEFAULT nextval('proposals_id_seq'::regclass);


--
-- Name: admins_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY admins
    ADD CONSTRAINT admins_pkey PRIMARY KEY (id);


--
-- Name: admins_username_key; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY admins
    ADD CONSTRAINT admins_username_key UNIQUE (username);


--
-- Name: areas_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY areas
    ADD CONSTRAINT areas_pkey PRIMARY KEY (id);


--
-- Name: arguments_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY arguments
    ADD CONSTRAINT arguments_pkey PRIMARY KEY (id);


--
-- Name: ballots_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY ballots
    ADD CONSTRAINT ballots_pkey PRIMARY KEY (id);


--
-- Name: issues_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY issues
    ADD CONSTRAINT issues_pkey PRIMARY KEY (id);


--
-- Name: members_auid_key; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY members
    ADD CONSTRAINT members_auid_key UNIQUE (auid);


--
-- Name: members_username_key; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY members
    ADD CONSTRAINT members_username_key UNIQUE (username);


--
-- Name: offline_demanders_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY offline_demanders
    ADD CONSTRAINT offline_demanders_pkey PRIMARY KEY (member, issue);


--
-- Name: participants_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY participants
    ADD CONSTRAINT participants_pkey PRIMARY KEY (member, area);


--
-- Name: periods_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY periods
    ADD CONSTRAINT periods_pkey PRIMARY KEY (id);


--
-- Name: proposals_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY proposals
    ADD CONSTRAINT proposals_pkey PRIMARY KEY (id);


--
-- Name: ratings_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY ratings
    ADD CONSTRAINT ratings_pkey PRIMARY KEY (argument, member);


--
-- Name: supporters_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY supporters
    ADD CONSTRAINT supporters_pkey PRIMARY KEY (proposal, member);


--
-- Name: users_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY members
    ADD CONSTRAINT users_pkey PRIMARY KEY (id);


--
-- Name: voters_member_period_key; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY voters
    ADD CONSTRAINT voters_member_period_key UNIQUE (member, period);


--
-- Name: voters_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY voters
    ADD CONSTRAINT voters_pkey PRIMARY KEY (member, ballot);


--
-- Name: arguments_member_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY arguments
    ADD CONSTRAINT arguments_member_fkey FOREIGN KEY (member) REFERENCES members(id) ON UPDATE RESTRICT ON DELETE RESTRICT;


--
-- Name: arguments_proposal_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY arguments
    ADD CONSTRAINT arguments_proposal_fkey FOREIGN KEY (proposal) REFERENCES proposals(id) ON DELETE CASCADE;


--
-- Name: ballots_period_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY ballots
    ADD CONSTRAINT ballots_period_fkey FOREIGN KEY (period) REFERENCES periods(id) ON DELETE CASCADE;


--
-- Name: issues_area_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY issues
    ADD CONSTRAINT issues_area_fkey FOREIGN KEY (area) REFERENCES areas(id) ON DELETE RESTRICT;


--
-- Name: issues_period_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY issues
    ADD CONSTRAINT issues_period_fkey FOREIGN KEY (period) REFERENCES periods(id) ON DELETE RESTRICT;


--
-- Name: offlinedemanders_issue_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY offline_demanders
    ADD CONSTRAINT offlinedemanders_issue_fkey FOREIGN KEY (issue) REFERENCES issues(id) ON DELETE CASCADE;


--
-- Name: offlinedemanders_user_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY offline_demanders
    ADD CONSTRAINT offlinedemanders_user_fkey FOREIGN KEY (member) REFERENCES members(id) ON DELETE CASCADE;


--
-- Name: participants_area_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY participants
    ADD CONSTRAINT participants_area_fkey FOREIGN KEY (area) REFERENCES areas(id) ON DELETE CASCADE;


--
-- Name: participants_member_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY participants
    ADD CONSTRAINT participants_member_fkey FOREIGN KEY (member) REFERENCES members(id) ON DELETE CASCADE;


--
-- Name: proposals_issue_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY proposals
    ADD CONSTRAINT proposals_issue_fkey FOREIGN KEY (issue) REFERENCES issues(id) ON DELETE CASCADE;


--
-- Name: ratings_argument_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY ratings
    ADD CONSTRAINT ratings_argument_fkey FOREIGN KEY (argument) REFERENCES arguments(id) ON DELETE CASCADE;


--
-- Name: ratings_member_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY ratings
    ADD CONSTRAINT ratings_member_fkey FOREIGN KEY (member) REFERENCES members(id) ON UPDATE RESTRICT ON DELETE RESTRICT;


--
-- Name: supporters_proposal_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY supporters
    ADD CONSTRAINT supporters_proposal_fkey FOREIGN KEY (proposal) REFERENCES proposals(id) ON DELETE CASCADE;


--
-- Name: supporters_user_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY supporters
    ADD CONSTRAINT supporters_user_fkey FOREIGN KEY (member) REFERENCES members(id) ON DELETE CASCADE;


--
-- Name: voters_ballot_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY voters
    ADD CONSTRAINT voters_ballot_fkey FOREIGN KEY (ballot) REFERENCES ballots(id) ON DELETE CASCADE;


--
-- Name: voters_member_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY voters
    ADD CONSTRAINT voters_member_fkey FOREIGN KEY (member) REFERENCES members(id) ON DELETE CASCADE;


--
-- Name: voters_period_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY voters
    ADD CONSTRAINT voters_period_fkey FOREIGN KEY (period) REFERENCES periods(id) ON DELETE CASCADE;


--
-- PostgreSQL database dump complete
--

