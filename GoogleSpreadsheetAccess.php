<?php
/**
** ============================================================================
** @file    GoogleSiteSearch.php
** @brief   MediaWiki plug-in for accessing values in a Google Spreadsheet
** @author  Michael Hucka (mhucka@caltech.edu), Caltech
**
** See http://mhucka.github.com/google-spreadsheet-mw-plugin for more info.
**
** This plug-in was developed as part of the SBML Project (http://sbml.org).
** Copyright (C) 2012 by the California Institute of Technology, Pasadena, USA.
**
** This library is free software; you can redistribute it and/or modify it
** under the terms of the GNU Lesser General Public License as published by
** the Free Software Foundation.  A copy of the license agreement is provided
** in the file named "LICENSE.txt" included with this software distribution
** and also available online as http://sbml.org/software/libsbml/license.html
** ============================================================================
*/

$wgExtensionFunctions[] = "wf_google_spreadsheet_include";
$wgExtensionCredits['other'][] = array(
    'name'        => 'GoogleSpreadsheetAccess',
    'author'      => 'Michael Hucka (mhucka@caltech.edu)',
    'url'         => 'http://mhucka.github.com/google-spreadsheet-mw-plugin',
    'description' => 'Return values from a Google Spreadsheet.',
);

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
 *
 * The syntax in MediaWiki markup is the following:
 *
 * <gscellvalue sheet="W" find="X" search="Y" return="Z" wikitext>
 *
 * where:
 *   W = name for the sheet (see $sheet_ids above)
 *   X = exact string to look for in column "Y", to find a row
 *   Y = label (not ID) of the column in which to search for content "X"
 *   Z = label (not ID) of the column whose value is to be returned
 *   wikitext = (optional) keyword to indicate content is to be parsed
 *
 * The spreadsheet is identified by a name; this is mapped to an actual
 * Google spreadsheet key via the array $sheet_ids above.
 */
function render_gscellvalue( $input , $argv, &$parser ) {
    global $sheet_ids;
    $sheet_key  = "";
    $find       = "";
    $find_col   = "";
    $return_col = "";
    $wikitext   = isset($argv["wikitext"]);

    if (!isset($argv["sheet"])) {
        return "ERROR: &lt;gscellvalue&gt; is missing 'sheet' attribute.";
    } elseif ( !array_key_exists($argv["sheet"], $sheet_ids) ) {
        return "ERROR: unknown sheet name '" . $argv["sheet"] . "'";
    } else {
        $sheet_key = $sheet_ids[$argv["sheet"]];
    }

    if (!isset($argv["find"])) {
        return "ERROR: &lt;gscellvalue&gt; missing 'find' attribute.";
    } else {
        $find = $argv["find"];
    }

    if (!isset($argv["search"])) {
        return "ERROR: &lt;gscellvalue&gt; missing 'search' attribute.";
    } else {
        $find_col = $argv["search"];
    }


    if (!isset($argv["return"])) {
        return "ERROR: &lt;gscellvalue&gt; missing 'return' attribute.";
    } else {
        $return_col = $argv["return"];
    }

    $common = "https://spreadsheets.google.com/tq?key=" . $sheet_key . "&tq=";

    // Step 1: get the column names.
    // This assumes the first row of the table consists of the column names.
    // We then have to convert the numerical index into what Google uses for
    // column IDs, which is A, B, ..., Z, AA, AB, AC, ...

    $query         = $common . "limit%200";
    $data          = query_gs($query);
    $column_labels = extract_columns($data);
    $column_count  = count($column_labels);

    // Step 2: figure out the ID of the column we're going to search in and
    // the index number of the column whose value we're going to return.

    $find_col_id = "";
    $return_col_index = -1;
    for ($i = 0; $i < $column_count; $i++) {
        if ($column_labels[$i]->label == $find_col)
            $find_col_id = $column_labels[$i]->id;
        if ($column_labels[$i]->label == $return_col)
            $return_col_index = $i;
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
    $rows  = extract_rows($data);
    if (is_null($rows)) {
        return "ERROR: could not find '" . $find . "' in column '" . $find_col . "'";
    }
    $output = $rows[$return_col_index]->v;

    if ($wikitext) {
        $parsedText = $parser->parse($output, $parser->mTitle,
                                     $parser->mOptions, false, false);
        return $parsedText->getText();
    } else {
        return $output;
    }
}

// query syntax described at
// https://developers.google.com/chart/interactive/docs/querylanguage

function query_gs($query) {
    // Without setting the error reporting level up to E_NOTICE, I get "SSL:
    // fatal protocol error" in the logs. I haven't been able to figure out
    // what the cause of the warning is. (It's not due to https:// vs http://.)

    error_reporting(E_NOTICE);
    $data = file_get_contents($query);
    error_reporting(E_WARNING);

    if (is_null($data)) {
        return "ERROR: null data returned.";
    }

    // Example of a reply from Google:
    //   google.visualization.Query.setResponse({"version":"0.6","status":"ok",
    //   "sig":"999999999","table":{"cols":[{"id":"D","label":"",
    //   "type":"string", "pattern":""}],"rows":[{"c":[{"v":"sbml-comp"}]}]}});

    // We extract the JSON part of the string, then try to parse it:

    $json = substr($data, 39, -2);
    $parsed = fromJSON($json);
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


function extract_columns($data) {
    $cols = $data->cols;
    if (is_null($cols) || !is_array($cols)) {
        return "ERROR: reply from Google Spreadsheets lacks 'cols' array.";
    }

    // The value at this point is an array of objects.  Each object will
    // have a field named "id" and another named "label".  You would access
    // each field as, e.g., $cols[index]->label.
    return $cols;
}


function extract_rows($data) {
    $rows = $data->rows;
    if (is_null($rows) || !is_array($rows)) {
        return "ERROR: reply from Google Spreadsheets lacks 'rows' array.";
    } else if (empty($rows)) {
        return null;
    }

    $c = $rows[0]->c;
    if (is_null($c) || !is_array($c)) {
        return "ERROR: table returned from Google Spreadsheets lacks 'c' part.";
    }

    // The value at this point will be an array of objects. Each object will
    // have a field named "v" whose value is the content of a cell in the
    // spreadsheet.  You would access it as, e.g., $c[index]->v.

    return $c;
}


/*

  simplejson - a tiny JSON parser for older PHP versions

  ------------
  
  The main purpose of this is to allow the parsing of JSON encoded strings
  into PHP native structures, or PHP objects encoding into JSON strings. 
  Primary target for this are mature systems running versions of PHP older 
  than 5.2, which provides this functionality. 
  
  The functions are confirmed to work on PHP as old as 4.1.2.
  
  The functions do not care about character encoding and will do nothing
  to magically fix character set issues. They'll work with the data 
  as-provided and won't, for example, (un)escape \u0000 or \x00 characters.
  
  WARNING: Be aware that the string input is being "evaluated" and run by this 
  function with all the implications that includes!
  
  ------------
  
  Copyright (C) 2006 Borgar Thorsteinsson [borgar.undraland.com]
  
  Permission is hereby granted, free of charge, to any person
  obtaining a copy of this software and associated documentation
  files (the "Software"), to deal in the Software without
  restriction, including without limitation the rights to use,
  copy, modify, merge, publish, distribute, sublicense, and/or sell
  copies of the Software, and to permit persons to whom the
  Software is furnished to do so, subject to the following
  conditions:

  The above copyright notice and this permission notice shall be
  included in all copies or substantial portions of the Software.

  THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
  EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES
  OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
  NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
  HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
  WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
  FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR
  OTHER DEALINGS IN THE SOFTWARE.

  ------------

  
*/
/**
 * Parses a JSON string into a PHP variable.
 * @param string $json  The JSON string to be parsed.
 * @param bool $assoc   Optional flag to force all objects into associative arrays.
 * @return mixed        Parsed structure as object or array, or null on parser failure.
 */
function fromJSON ( $json, $assoc = false ) {

  /* by default we don't tolerate ' as string delimiters
     if you need this, then simply change the comments on
     the following lines: */

  // $matchString = '/(".*?(?<!\\\\)"|\'.*?(?<!\\\\)\')/';
  $matchString = '/".*?(?<!\\\\)"/';
  
  // safety / validity test
  $t = preg_replace( $matchString, '', $json );
  $t = preg_replace( '/[,:{}\[\]0-9.\-+Eaeflnr-u \n\r\t]/', '', $t );
  if ($t != '') { return null; }

  // build to/from hashes for all strings in the structure
  $s2m = array();
  $m2s = array();
  preg_match_all( $matchString, $json, $m );
  foreach ($m[0] as $s) {
    $hash       = '"' . md5( $s ) . '"';
    $s2m[$s]    = $hash;
    $m2s[$hash] = str_replace( '$', '\$', $s );  // prevent $ magic
  }
  
  // hide the strings
  $json = strtr( $json, $s2m );
  
  // convert JS notation to PHP notation
  $a = ($assoc) ? '' : '(object) ';
  $json = strtr( $json, 
    array(
      ':' => '=>', 
      '[' => 'array(', 
      '{' => "{$a}array(", 
      ']' => ')', 
      '}' => ')'
    ) 
  );
  
  // remove leading zeros to prevent incorrect type casting
  $json = preg_replace( '~([\s\(,>])(-?)0~', '$1$2', $json );
  
  // return the strings
  $json = strtr( $json, $m2s );

  /* "eval" string and return results. 
     As there is no try statement in PHP4, the trick here 
     is to suppress any parser errors while a function is 
     built and then run the function if it got made. */
  $f = @create_function( '', "return {$json};" );
  $r = ($f) ? $f() : null;

  // free mem (shouldn't really be needed, but it's polite)
  unset( $s2m ); unset( $m2s ); unset( $f );

  return $r;
}

?>
