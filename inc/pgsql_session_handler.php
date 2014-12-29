<?
/**
 * PostgreSQL Session Handler for PHP
 *
 * Copyright 2000-2003 Jon Parise <jon@php.net>.  All rights reserved.
 *
 *  Redistribution and use in source and binary forms, with or without
 *  modification, are permitted provided that the following conditions
 *  are met:
 *  1. Redistributions of source code must retain the above copyright
 *     notice, this list of conditions and the following disclaimer.
 *  2. Redistributions in binary form must reproduce the above copyright
 *     notice, this list of conditions and the following disclaimer in the
 *     documentation and/or other materials provided with the distribution.
 *
 *  THIS SOFTWARE IS PROVIDED BY THE AUTHOR AND CONTRIBUTORS ``AS IS'' AND
 *  ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 *  IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
 *  ARE DISCLAIMED.  IN NO EVENT SHALL THE AUTHOR OR CONTRIBUTORS BE LIABLE
 *  FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL
 *  DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS
 *  OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
 *  HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT
 *  LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY
 *  OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF
 *  SUCH DAMAGE.
 *
 * Usage Notes
 * ~~~~~~~~~~~
 * - Create the table structure using the follow schema:
 *      CREATE TABLE session (
 *          session_id  TEXT                         NOT NULL PRIMARY KEY,
 *          last_active TIMESTAMPTZ DEFAULT now()    NOT NULL,
 *          data        TEXT        DEFAULT ''::text NOT NULL
 *      );
 *
 * @version 2.1, 02/10/2003
 *
 * $Id: pgsql_session_handler.php,v 1.33 2003/02/10 16:04:39 jon Exp $
 *
 * http://www.csh.rit.edu/~jon/projects/pgsql_session_handler/
 * @version Modified for Basisentscheid by Magnus Rosenbaum
 * @author  Jon Parise <jon@php.net>
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 * @see inc/classes/Login.php
 */


/* Configure PHP for our custom session handler */
ini_set("session.save_handler", 'user');

/* Register the session handling functions with PHP. */
session_set_save_handler(
	'pgsql_session_open',
	'pgsql_session_close',
	'pgsql_session_read',
	'pgsql_session_write',
	'pgsql_session_destroy',
	'pgsql_session_gc'
);


/**
 * Opens a new session.
 *
 * @return boolean True on success, false on failure.
 */
function pgsql_session_open() {
	return true;
}


/**
 * Closes the current session.
 *
 * @return boolean True on success, false on failure.
 */
function pgsql_session_close() {
	return true;
}


/**
 * Reads the requested session data from the database.
 *
 * @param string  $session_id Unique session ID of the requested entry.
 * @return string  The requested session data.  A failure condition will result in an empty string being returned.
 */
function pgsql_session_read($session_id) {

	/*
     * Attempt to retrieve a row of existing session data.
     *
     * We begin by starting a new transaction.  All of the session-related
     * operations with happen within this transcation.  The transaction will
     * be committed by either session_write() or session_destroy(), depending
     * on which is called.
     *
     * We mark this SELECT statement as FOR UPDATE because it is probable that
     * we will be updating this row later on in session_write(), and performing
     * an exclusive lock on this row for the lifetime of the transaction is
     * desirable.
     */
	$sql = "BEGIN; SELECT data FROM session WHERE session_id=".DB::esc($session_id)." FOR UPDATE;";
	$result = DB::query($sql);

	/*
     * If we were unable to retrieve an existing row of session data, insert a
     * new row.  This ensures that the UPDATE operation in session_write() will
     * succeed.
     */
	if (($result === false) || (pg_num_rows($result) != 1)) {
		$sql = "INSERT INTO session (session_id) VALUES(".DB::esc($session_id).")";
		$result = DB::query($sql);

		/* If the insertion succeeds, return an empty string of data. */
		if (($result !== false) && (pg_affected_rows($result) == 1)) {
			pg_freeresult($result);
			return '';
		}

		/*
         * If the insertion fails, it may be due to a race condition that
         * exists between multiple instances of this session handler in the
         * case where a new session is created by multiple script instances
         * at the same time (as can occur when multiple session-aware frames
         * exist).
         *
         * In this case, we attempt another SELECT operation which will
         * hopefully retrieve the session data inserted by the competing
         * instance.
         */
		$sql = "ROLLBACK; BEGIN; SELECT data FROM session WHERE session_id=".DB::esc($session_id)." FOR UPDATE;";
		$result = DB::query($sql);

		/* If this attempt also fails, give up and return an empty string. */
		if (($result === false) || (pg_num_rows($result) != 1)) {
			pg_freeresult($result);
			return '';
		}
	}

	/* Extract and return the 'data' value from the successful result. */
	$data = pg_fetch_result($result, 0, 'data');
	pg_freeresult($result);

	return $data;
}


/**
 * Writes the provided session data with the requested key to the database.
 *
 * @param string  $session_id Unique session ID of the current entry.
 * @param string  $data       String containing the session data.
 * @return boolean True on success, false on failure.
 */
function pgsql_session_write($session_id, $data) {

	$sql = "UPDATE session SET last_active=now(), data=".DB::esc($data)." WHERE session_id=".DB::esc($session_id)."; COMMIT;";
	$result = DB::query($sql);

	$success = ($result !== false);
	pg_freeresult($result);

	return $success;
}


/**
 * Destroys the requested session.
 *
 * @param string  $session_id Unique session ID of the requested entry.
 * @return boolean True on success, false on failure.
 */
function pgsql_session_destroy($session_id) {

	$sql = "DELETE FROM session WHERE session_id=".DB::esc($session_id)."; COMMIT;";
	$result = DB::query($sql);

	/* A successful deletion query will affect a single row. */
	$success = (($result !== false) && (pg_affected_rows($result) == 1));
	pg_freeresult($result);

	return $success;
}


/**
 * Performs session garbage collection based on the provided lifetime.
 *
 * Sessions that have been inactive longer than $maxlifetime sessions will be
 * deleted.
 *
 * @param integer $maxlifetime Maximum lifetime of a session in seconds.
 * @return boolean True on success, false on failure.
 */
function pgsql_session_gc($maxlifetime) {

	$sql = "DELETE FROM session WHERE last_active < now() - interval '".intval($maxlifetime)." seconds'";

	return DB::query($sql) !== false;
}
