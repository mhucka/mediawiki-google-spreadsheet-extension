<?php

$wgExtensionFunctions[] = "wf_google_spreadsheet_include";
$wgExtensionCredits['other'][] = array(
    'name'        => 'GoogleSpreadsheetAccess',
    'author'      => 'Michael Hucka',
    'url'         => 'foo',
    'description' => 'Return content from a Google Spreadsheet.',
);

function wf_google_spreadsheet_include() {
    global $wgParser;
    $wgParser->setHook( "gscellvalue", "render_gscellvalue" );
}

$sheet_ids = array(
    "SBMLLevel3Packages" => "0ApbKgxVhXxVydG15WXlIT0JacHhwc0FPemV6bE1aQXc",
);

/*
 * This is called automatically by the MediaWiki parser extension system.
 * It takes an argument that indicates how to look up a row based on an
 * exact column value string match, then once the row is found, the column
 * value (in that row) to be returned. 
 *
 * <gscellvalue sheet="A" find="B" return="C">
 *
 * where:
 *   A = identifier for the sheet (see below)
 *   B = exact string to look for in column 1 -- this finds a row
 *   D = column whose value from that row is to be returned
 *
 * The spreadsheet is identified by a name; this is mapped to an actual
 * Google spreadsheet key via an internal array above.  This 
 * indirection is for security reasons, to avoid wiki users being able
 * to inject content from arbitrary spreadsheets that they control.
 */
function render_gscellvalue( $input , $argv, &$parser ) {
    global $sheet_ids;
    $sheet_key = "";
    $find      = "";
    $retcol    = "";
    $wikitext  = isset($argv["wikitext"]);

    if (!isset($argv["sheet"])) {
        return "ERROR: &lt;gscellvalue&gt; is missing 'sheet' attribute.";
    } elseif ( !array_key_exists($argv["sheet"], $sheet_ids) ) {
        return "ERROR: unknown sheet name '" . $argv["sheet"] . "'";
    } else {
        $sheet_key = $sheet_ids[$argv["sheet"]];
    }

    if (!isset($argv["find"])) {
        return "ERROR: &lt;gscellvalue&gt; is missing 'find' attribute.";
    } else {
        $find = $argv["find"];
    }

    if (!isset($argv["return"])) {
        return "ERROR: &lt;gscellvalue&gt; is missing 'return' attribute.";
    } else {
        $retcol = $argv["return"];
    }

    $common = "https://spreadsheets.google.com/tq?key=" . $sheet_key . "&tq=";

    // Step 1: get the column names.
    // This assumes the first row of the table consists of the column names.
    // This first row is assumed to be defined as a header row.  When the table
    // has a header row defined, the GS api returns a table with an element
    // called "cols" that 

    // We then have to convert the numerical index into what Google uses for
    // column IDs, which is A, B, ..., Z, AA, AB, AC, ...

    $query    = $common . "limit%201";
    $row_data = query_gs($query);

    $column_count = count($row_data);
    $index = 0;
    for (; $index < $column_count; $index++) { 
        if ($row_data[$index]->v == $retcol) break; 
    }    
    if ($index == $column_count) { 
        return "ERROR: could not find a column named '" . $retcol . "'"; 
    } 

    $chars  = "ABCDEFGHIJKLMNOPQRSTUVWXYZ";
    $scale  = intval($index/26);
    $prefix = ($scale > 0) ? $chars[$scale - 1] : "";
    $col    = $prefix . $chars[$index % 26];

    // Step 2: get the value requested. 

    $query = $common . rawurlencode("select " . $col . " where A = '" . $find . "'");
    $cell_data = query_gs($query);
    if (is_null($cell_data)) {
        return "empty";
    }

    $output = $cell_data[0]->v;
    if ($wikitext) {
        $parsedText = $parser->parse($output, $parser->mTitle, $parser->mOptions,
                                     false, false);
        return $parsedText->getText();
    } else {
        return $output;
    }
}

// query syntax described at
// https://developers.google.com/chart/interactive/docs/querylanguage

function query_gs ($query) {
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
    }

    $rows = $parsed->table->rows;
    if (is_null($rows) || !is_array($rows)) {
        return "ERROR: table returned from Google Spreadsheets lacks rows.";
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
