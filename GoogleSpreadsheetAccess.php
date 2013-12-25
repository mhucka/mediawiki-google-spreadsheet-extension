<?php
/**
 * ============================================================================
 * @file    GoogleSiteSearch.php
 * @brief   MediaWiki plug-in for accessing values in a Google Spreadsheet
 * @author  Michael Hucka (mhucka@caltech.edu), Caltech
 *
 * See http://mhucka.github.com/google-spreadsheet-mw-plugin for more info.
 *
 * This plug-in was developed as part of the SBML Project (http://sbml.org).
 * Copyright (C) 2012 by the California Institute of Technology, Pasadena, USA.
 *
 * This library is free software; you can redistribute it and/or modify it
 * under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation; either version 2.1 of the License, or any
 * later version.
 * 
 * This software is distributed in the hope that it will be useful, but
 * WITHOUT ANY WARRANTY, WITHOUT EVEN THE IMPLIED WARRANTY OF MERCHANTABILITY
 * OR FITNESS FOR A PARTICULAR PURPOSE.  The software and documentation
 * provided hereunder is on an "as is" basis, and the California Institute of
 * Technology has no obligations to provide maintenance, support, updates,
 * enhancements or modifications.  In no event shall the California Institute
 * of Technology be liable to any party for direct, indirect, special,
 * incidental or consequential damages, including lost profits, arising out
 * of the use of this software and its documentation, even if the California
 * Institute of Technology has been advised of the possibility of such
 * damage.  See the GNU Lesser General Public License for more details.
 * 
 * You should have received a copy of the GNU Lesser General Public License
 * along with this library in the file named "COPYING.txt" included with the
 * software distribution.
 * ============================================================================
 */

/*
 * Standard MediaWiki code to protect against register_globals vulnerabilities.
 * This line must be present before any global variable is referenced.
 */
if (!defined('MEDIAWIKI')) {
    echo("This file is part of MediaWiki. It is not a valid entry point.\n");
    die(-1);
}

global $wgExtensionCredits;
$wgExtensionCredits['other'][] = array(
    'path'        => __FILE__,
    'name'        => 'GoogleSpreadsheetAccess',
    'author'      => 'Michael Hucka (mhucka@caltech.edu)',
    'url'         => 'http://mhucka.github.com/google-spreadsheet-mw-plugin',
    'description' => 'Return values from a Google Spreadsheet.',
    'version'     => '1.0.0',
);

$wgExtensionFunctions[] = "wf_google_spreadsheet_include";

/**
 * Hooks this extension into the MediaWiki parser.
 */
function wf_google_spreadsheet_include() {
    global $wgParser;
    $wgParser->setHook( "gscellvalue", "render_gscellvalue" );
}

/*
 * For security reasons, you must hard-code the spreadsheet key here, and
 * use identifiers in the references in your wiki pages, rather than use
 * the spreadsheet key directly in the wiki pages.  The format of the
 * following array is:
 *
 *    "sheet name" => "Google key for spreadsheet"
 *
 * This indirection is for security reasons, to avoid wiki users being able
 * to inject malicious content from arbitrary spreadsheets that they control.
 */

$sheet_ids = array(
    "SBMLLevel3Packages" => "0ApbKgxVhXxVydG15WXlIT0JacHhwc0FPemV6bE1aQXc",
);

/*
 * Function render_gscellvalue will be called automatically by the MediaWiki
 * parser extension system.  It accepts arguments that indicate a row to find
 * in the spreadsheet, and once the row is found, the column value in that
 * row to be returned.  The approach is relatively simple and relies on one
 * important assumptions about the spreadsheet: that the first row consists
 * of column labels.  References to rows in this extension are to these row
 * labels and NOT to the spreadsheet row ID's; this allows people to reorder
 * the spreadsheet columns without affecting references in MediaWiki pages.
 * String matches are performed in a case-sensitive manner.
 *
 * The syntax is the following:
 *
 * <gscellvalue sheet="S" find="X" in="Y" return="Z" 
 *              prepend="A" append="B" ifempty="C" wikitext bigtable>
 *
 * where the following are required:
 *   S = name for the spreadsheet (see $sheet_ids above)
 *   X = exact string to look for in column "Y", to find a row
 *   Y = label (not ID) of the column in which to search for content "X"
 *   Z = label (not ID) of the column whose value is to be returned
 * and the following arguments are optional:
 *   A = text to prepend to the value returned
 *   B = text to append to the value returned
 *   C = value to return if the cell content is found to be empty
 *   wikitext = keyword indicating content is to be parsed before returning it
 *   bigtable = keyword indicating table is large, so don't read it all at once
 *
 * If a value for the optional argument 'ifempty' is supplied, and the
 * spreadsheet cell to be returned is empty, only the value of 'ifempty' is
 * returned alone, without prepending A or appending B.  Conversely, if a
 * value for 'ifempty' is not supplied, and the spreadsheet cell value is
 * empty, then A and B *will* still be prepended and appended (which means
 * you will get the concatenation "AB" as the returned result).  Single-
 * and double-quote characters will be removed from the resulting string
 * before it is returned or parsed as wikitext; this is necessary so that
 * A and B can be strings with leading and trailing spaces (which you can
 * do by putting quotes around the strings, like this: append="' text'").
 *
 * If the attribute 'wikitext' is supplied, the entire string to be returned
 * is first handed to the MediaWiki parser, and the result of that is what is
 * returned.  The attribute 'wikitext' takes no value.
 *
 * By default, this plug-in will make a single call to Google to get the
 * entire table in one read, then do the cell value lookups internally in
 * this plug-in.  Depending on the size of the spreadsheet, the speed of your
 * server, and the number of uses of <gscellvalue> in a given MediaWiki page,
 * this approach may be slower than doing two separate reads together with
 * using the Google spreadsheets query API.  If the attribute 'bigtable' is
 * supplied, this plug-in will make two separate calls to Google rather than
 * read the whole spreadsheet into memory in one call.
 *
 * Other attributes supplied to gscellvalue are silently ignored.
 */
function render_gscellvalue( $input , $argv, &$parser ) {
    wfProfileIn( "gscellvalue" );

    global $sheet_ids;
    $sheet_key  = "";
    $sheet      = isset($argv["sheet"])   ? $argv["sheet"]   : "";
    $find       = isset($argv["find"])    ? $argv["find"]    : "";
    $find_col   = isset($argv["in"])      ? $argv["in"]      : "";
    $return_col = isset($argv["return"])  ? $argv["return"]  : "";
    $prepend    = isset($argv["prepend"]) ? $argv["prepend"] : "";
    $append     = isset($argv["append"])  ? $argv["append"]  : "";
    $ifempty    = isset($argv["ifempty"]) ? $argv["ifempty"] : "";
    $wikitext   = isset($argv["wikitext"]);
    $bigtable   = isset($argv["bigtable"]);

    if (empty($sheet)) {
        return "ERROR: &lt;gscellvalue&gt; is missing 'sheet' attribute.";
    } elseif ( !array_key_exists($sheet, $sheet_ids) ) {
        return "ERROR: unknown sheet name '" . $sheet . "'";
    } else {
        $sheet_key = $sheet_ids[$sheet];
    }
    if (empty($find)) {
        return "ERROR: &lt;gscellvalue&gt; missing 'find' attribute.";
    }
    if (empty($find_col)) {
        return "ERROR: &lt;gscellvalue&gt; missing 'in' attribute.";
    }
    if (empty($return_col)) {
        return "ERROR: &lt;gscellvalue&gt; missing 'return' attribute.";
    }

    // Let's get this show on the road.  

    if ($bigtable) {
        $data = gscellvalue_bigtable($sheet_key, $find, $find_col, $return_col);
    } else {
        $data = gscellvalue_default($sheet_key, $find, $find_col, $return_col);
    }

    if (is_string($data) && startsWith($data, "ERROR")) {
        return $data;
    } else if (empty($data) && !empty($ifempty)) {
        $data = $ifempty;
    } else {
        $data = $prepend . $data . $append;
        $data = str_replace('"', "", $data);
        $data = str_replace("'", "", $data);
    }

    if ($wikitext) {
        $parsedText = $parser->parse($data, $parser->mTitle,
                                     $parser->mOptions, false, false);
        wfProfileOut( "gscellvalue" );
        return $parsedText->getText();
    } else {
        wfProfileOut( "gscellvalue" );
        return htmlspecialchars($data);
    }
}

function gscellvalue_default($sheet_key, $find, $find_col, $return_col) {
    $query = "https://spreadsheets.google.com/tq?key=" . $sheet_key;

    // Step 1: get the entire table.

    $data  = query_gs($query);
    if (is_string($data) && startsWith($data, "ERROR")) {
        return $data;
    }

    // Step 2: extract the first row, assumed to contain the column names.

    $label_row = extract_row($data, 0);
    if (is_null($label_row)) {
        return "ERROR: could not extract the first row of the spreadsheet.";
    }

    // Step 3: figure out the index of the column we're going to search, and
    // the index of the column whose value we're going to return.

    $num_columns = count($label_row);
    $find_col_index = -1;
    $return_col_index = -1;
    for ($i = 0; $i < $num_columns; $i++) {
        if ($label_row[$i]->v == $find_col)
            $find_col_index = $i;
        if ($label_row[$i]->v == $return_col)
            $return_col_index = $i;
        if ($find_col_index > 0 && $return_col_index > 0)
            break;
    }    
    if ($find_col_index < 0) { 
        return "ERROR: could not find a column named '" . $find_col . "'"; 
    } 
    if ($return_col_index < 0) { 
        return "ERROR: could not find a column named '" . $return_col . "'"; 
    } 

    // Step 3: figure out the row where the entry is located.

    $num_rows = count_data_rows($data);
    $row_found_index = -1;
    for ($i = 0; $i < $num_rows; $i++) {
        $value = cell_value($data, $i, $find_col_index);
        if ($value == $find) {
            $row_found_index = $i;
            break;
        }
    }
    if ($row_found_index < 0) {
        return "ERROR: could not find '" . $find . "' in column '" . $find_col . "'";
    }

    // Step 4: return what we found.

    return cell_value($data, $row_found_index, $return_col_index);
}


function gscellvalue_bigtable($sheet_key, $find, $find_col, $return_col) {
    $common = "https://spreadsheets.google.com/tq?key=" . $sheet_key . "&tq=";

    // Step 1: get the first row, which we assume contains the column names.

    $query = $common . "limit%201";
    $data  = query_gs($query);
    if (is_null($data)) {
        return $data;
    }
    $label_row = extract_row($data, 0);
    if (is_null($label_row) || !is_array($label_row)) {
        return "ERROR: could not extract the first row of the spreadsheet.";
    }

    // Step 2: figure out the ID of the column we're going to search in and
    // the index number of the column whose value we're going to return.
    // (Note: the first is a string identifier, and the second is a number.
    // To get the ID, we have to convert the numerical index into what
    // Google uses for column IDs, which is A, B, ..., Z, AA, AB, AC, ...)

    $num_columns = count($label_row);
    $find_col_id = "";
    $return_col_index = -1;
    for ($i = 0; $i < $num_columns; $i++) {
        if ($label_row[$i]->v == $find_col)
            $find_col_id = index_to_identifier($i);
        if ($label_row[$i]->v == $return_col)
            $return_col_index = $i;
        if ($find_col_id != "" && $return_col_index > 0)
            break;
    }    
    if ($find_col_id == "") { 
        return "ERROR: could not find a column named '" . $find_col . "'"; 
    } 
    if ($return_col_index < 0) { 
        return "ERROR: could not find a column named '" . $return_col . "'"; 
    } 

    // Step 3: get the value requested. 

    $query = $common . rawurlencode("where " . $find_col_id . " = '" . $find . "'");
    $data  = query_gs($query);
    if (is_null($data)) {
        return "ERROR: received empty return from Google spreadsheets.";
    }
    $rows = extract_row($data, 0);
    if (is_null($rows) || !is_array($rows)) {
        return "ERROR: could not find '" . $find . "' in column '" . $find_col . "'";
    }

    // Step 4: return something.

    return $rows[$return_col_index]->v;
}


// query syntax described at
// https://developers.google.com/chart/interactive/docs/querylanguage

function query_gs($query) { /*  */
    $data = do_curl($query);
    if (is_null($data)) {
        return "ERROR: null data returned.";
    }

    // Extract the JSON part of the reply from Google.
    // Example of a reply from Google (the "// Data ..." is part of it!)
    // 
    //   // Data table response
    //   google.visualization.Query.setResponse({"version":"0.6","status":"ok",
    //   "sig":"999999999","table":{"cols":[{"id":"D","label":"",
    //   "type":"string", "pattern":""}],"rows":[{"c":[{"v":"sbml-comp"}]}]}});

    $json = substr($data, strpos($data, "{"), -2);

    // Now turn it into a data array:

    $parsed = json_decode($json);
    if (is_null($parsed)) {
        return "ERROR: unable to parse reply from Google Spreadsheets";
    } elseif (is_null($parsed->status)) {
        return "ERROR: reply from Google Spreadsheets is not in expected form";
    } elseif ($parsed->status == "error") {
        if (is_null($parsed->errors) || !is_array($parsed->errors)) {
            return "ERROR: unknown error returned by Google Spreadsheets";
        } else {
            return "ERROR: " . $parsed->errors[0]->reason;
        }
    } 

    if (is_null($parsed->table)) {
        return "ERROR: reply from Google Spreadsheets lacks a table.";
    } else {
        return $parsed->table;
    }
}


// The value returned will be an array of objects. Each object will
// have a field named "v" whose value is the content of a cell in the
// spreadsheet.  You would access it as, e.g., $c[index]->v.

function extract_row($data, $index) {
    $rows = $data->rows;
    if (!empty($rows) && is_array($rows)) {
        return $rows[$index]->c;
    } else {
        return null;
    }
}


function count_data_rows($data) {
    return count($data->rows);
}


function cell_value($data, $row_index, $col_index) {
    return $data->rows[$row_index]->c[$col_index]->v;
}


function index_to_identifier($index) {
    $chars  = "ABCDEFGHIJKLMNOPQRSTUVWXYZ";
    $scale  = intval($index/26);
    $prefix = ($scale > 0) ? $chars[$scale - 1] : "";
    return $prefix . $chars[$index % 26];
}


function do_curl($query) {
    $ch = curl_init($query);

    curl_setopt($ch, CURLOPT_HEADER,         false);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT,        15);

    wfProfileIn( "gscellvalue_curl" );
    $output = curl_exec($ch);
    if (empty($output)) {
        return "ERROR: encountered network access error";
        curl_close($ch);
    } else {
        curl_close($ch);
        wfProfileOut( "gscellvalue_curl" );
        return $output;
    }
}


// From answer http://stackoverflow.com/a/834355/743730 by "MrHus".

function startsWith($haystack, $needle)
{
    $length = strlen($needle);
    return (substr($haystack, 0, $length) === $needle);
}

?>
