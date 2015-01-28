<?php
// Update the database schema from version 29-30
// - delete an old/unused table
//
// The script should assume that it can be interrupted at
// any point, and be able to continue by re-running the script.
// Fatal errors, however, should be allowed to throw exceptions,
// which will be caught by the framework.
// It shouldn't do anything that might take more than a few
// seconds, for systems with low timeout values.
//
// webtrees: Web based Family History software
// Copyright (C) 2014 Greg Roach
//
// This program is free software; you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation; either version 2 of the License, or
// (at your option) any later version.
//
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with this program; if not, write to the Free Software
// Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA

// Originally migrated from PGV, but never used.
try {
	WT_DB::exec("DROP TABLE `##ip_address`");
} catch (PDOException $ex) {
	// Already deleted?
}

// No longer used
WT_DB::exec("DELETE FROM `##user_setting` WHERE setting_name in ('edit_account')");

// https://bugs.launchpad.net/webtrees/+bug/1405672
WT_DB::exec(
	"UPDATE `##site_access_rule` SET user_agent_pattern = 'Mozilla/5.0 (% Konqueror/%'" .
	" WHERE user_agent_pattern='Mozilla/5.0 (compatible; Konqueror/%'"
);

// Update the version to indicate success
WT_Site::setPreference($schema_name, $next_version);
