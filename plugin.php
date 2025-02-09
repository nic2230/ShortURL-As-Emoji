<?php
/*
Plugin Name: ShortURLs as Emoji
Plugin URI: http://yourls.org/
Description: Generate URL in a set of four emojis only.
Version: 1.1
Author: Rajendra Rajsri
Author URI: https://github.com/rajendrarajsri
Credit : Ripped from Telematics plugin.
*/

// No direct call
if( !defined( 'YOURLS_ABSPATH' ) ) die();
require_once(__DIR__ . '/vendor/autoload.php');
use SteppingHat\EmojiDetector;

function to_unicode($text) {
    $str = preg_replace_callback(
        "%(?:\xF0[\x90-\xBF][\x80-\xBF]{2} | [\xF1-\xF3][\x80-\xBF]{3} | \xF4[\x80-\x8F][\x80-\xBF]{2})%xs",
        function($emoji){
            $emojiStr = mb_convert_encoding($emoji[0], 'UTF-32', 'UTF-8');
            return strtoupper(preg_replace("/^[0]+/","U+",bin2hex($emojiStr)));
        },
        $text
    );
    return $str;
}

/*
 * Accept listed emojis
 */
yourls_add_filter( 'get_shorturl_charset', 'raj_emojis_in_charset');

function raj_emojis_in_charset($in) {
// Read JSON file
$json = file_get_contents(__DIR__ . '/vendor/emoji_all.json');
//Decode JSON
$json_data = json_decode($json,true);
//Traverse array and get the data for unicode to html
foreach ($json_data as $item_key => $item_value) {
		$items = count($json_data[$item_key])-1 ;
		for ( $loop = 0; $loop < $items; $loop++ ) {
			$new_emoji = $json_data[$item_key][$loop]['emoji'] ;
			$all_emoji = $in.trim($new_emoji) ;
		}
	}
return $all_emoji ;
}

// Only register things if the old third-party plugin is not present
if( function_exists('ozh_random_keyword') ) {
    yourls_add_notice( "<b>Random ShortURLs</b> plugin cannot function unless <b>Random Keywords</b> is removed first." );
} else {
    // filter registration happens conditionally, to avoid conflicts
    // settings action is left out here, as it allows checking settings before deleting the old plugin
    yourls_add_filter( 'random_keyword', 'raj_random_emojis' );
}

// Don't increment sequential keyword tracker
yourls_add_filter( 'get_next_decimal', 'raj_random_keyword_next_decimal' );
function raj_random_keyword_next_decimal( $next ) {
        return ( $next - 1 );
}

//Find emoji from their index code in the file
function raj_find_emoji( $seg ) {
	
$path = (__DIR__ . '/vendor/emoji_set.txt');
$file = fopen($path,'r');
while ($line = fgets($file)) {
  $data = explode(",", $line);
  if ( $seg == trim($data[0])) { 
	$emojicode = trim($data[2]) ;
	break ;
	}
}
fclose($file);	

$json = file_get_contents(__DIR__ . '/vendor/emoji_all.json');
$json_data = json_decode($json,true);
foreach ($json_data as $item_key => $item_value) {
	$items = count($json_data[$item_key])-1 ;
		for ( $loop = 0; $loop < $items; $loop++ ) {
		$item_data = ($json_data[$item_key][$loop]) ;
		$uni_code = strtoupper($json_data[$item_key][$loop]['unicode']) ;
		if ( $uni_code == strtoupper($emojicode) ) {
		$htm_emoji = $json_data[$item_key][$loop]['html'] ;
		$emoji = $json_data[$item_key][$loop]['emoji'] ;
		break ;
		}
	  }
	}
	return ($emoji) ;	
}

/////Auto generated short url
function raj_random_emojis() {

$segment1 = mt_rand(0,255);
$segment2 = mt_rand(0,255);
$segment3 = mt_rand(0,255);
$segment4 = mt_rand(0,255);

$emoji1 = raj_find_emoji($segment1);
$emoji2 = raj_find_emoji($segment2);
$emoji3 = raj_find_emoji($segment3);
$emoji4 = raj_find_emoji($segment4);

$all_emoji = $emoji1.$emoji2.$emoji3.$emoji4 ;
$keyword = to_unicode($all_emoji) ; 
//check for duplicacy
//$keyword = $emoji1 ;
return $keyword;
}

///////////////////////////////////////////////////////////////////////////////////////////////

/*
 * Accepts URLs that are ONLY emojis
 */
yourls_add_filter( 'sanitize_url', 'raj_emojis_sanitize_url' );

function raj_emojis_sanitize_url($unsafe_url) {
  $clean_url = '';
  $detector = new SteppingHat\EmojiDetector\EmojiDetector();
  $detect_emoji = $detector->detect(urldecode($unsafe_url));

  if( sizeof($detect_emoji) > 0 ) {
    foreach ($detect_emoji as $emoji) {
      $clean_url .= $emoji->getEmoji();
    }
    return $clean_url;
  }
  return $unsafe_url;
}

/*
 * filter wrong spacing whoopsies
 * see @link https://github.com/YOURLS/YOURLS/issues/1303
 */
yourls_add_filter( 'sanitize_url', 'fix_long_url' );
function fix_long_url( $url, $unsafe_url ) {
  $search = array ( '%2520', '%2521', '%2522', '%2523', '%2524', '%2525', '%2526', '%2527', '%2528', '%2529', '%252A', '%252B', '%252C', '%252D', '%252E', '%252F', '%253D', '%253F', '%255C', '%255F' );
  $replace = array ( '%20', '%21', '%22', '%23', '%24', '%25', '%26', '%27', '%28', '%29', '%2A', '%2B', '%2C', '%2D', '%2E', '%2F', '%3D', '%3F', '%5C', '%5F' );
  $url = str_ireplace ( $search, $replace ,$url );
  return yourls_apply_filter( 'after_fix_long_url', $url, $unsafe_url );
}



/////Custom entered short url check                   ///////////////

yourls_add_filter( 'shunt_add_new_link', 'raj_limit_keyword_length' );
// Check the keyin format and return an error if not match
function raj_limit_keyword_length( $too_long, $url, $keyword ) {
	$keyin = trim( $keyword );
	$max_keyword_length = 25;
	$keyword_length = strlen($keyin);
	$keyword_format = true;

// only digits and "-" filter
	$keyword_string =  "";
	$digits = array("0", "1", "2", "3", "4", "5", "6", "7", "8", "9");
	for ( $loop = 0; $loop < $keyword_length; $loop++ ) {
	$char = substr( $keyin, $loop, 1);
	if (in_array( $char, $digits )) {
			$keyword_string =  $keyword_string . $char;
			} else {
				$keyword_string =  $keyword_string . "-";
				}
			}
// Four segments of digits
	$segment = explode( "-", $keyword_string );
	if ( count( $segment ) != 4 ) {
			$keyword_format = false;
			}

// Each segment contains three digits and less than 256
	foreach ( $segment as $seg_value )
	{
	$seg_length = strlen($seg_value);
	if (( $seg_length != 3) or ( $seg_value > 255)) {
			  $keyword_format = false;
			  }
   	}
// valid format is true for blank
	if ( $keyword_length < 1 ) $keyword_format = true;

// check if any format mismatch occure
	if (!$keyword_format) {
		$return['status']   = 'fail';
		$return['code']     = 'error:keyword';
		$return['message']  = "Sorry, the keyword " . $keyword . " is not in format or Emoji";
		return yourls_apply_filter( 'add_new_link_keyword_too_long', $return );
							}
	return false;
}
