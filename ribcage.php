<?php
/*
	Plugin Name: Ribcage
	Plugin URI: http://recordsonribs.com/ribcage/
	Description: Manages and monitors artists, releases and downloads for the Records On Ribs label.
	Version: 1.0
	Author: Alexander Andrews
	Author URI: http://recordsonribs.com
*/
/*
	Copyright 2007  Alexander Andrews  (email : alex@recordsonribs.com)

	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation; either version 2 of the License, or
	(at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program; if not, write to the Free Software
	Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA	
*/

define ("ARTISTS_PLUGIN", "/~music/artists/");

require_once dirname(__FILE__) . '/ribcage-includes/functions.php';
require_once dirname(__FILE__) . '/ribcage-includes/log.php';
require_once dirname(__FILE__) . '/ribcage-includes/track.php';
require_once dirname(__FILE__) . '/ribcage-includes/template.php';

require_once dirname(__FILE__) . '/download.php';
require_once dirname(__FILE__) . '/stream.php';
require_once dirname(__FILE__) . '/player.php';

add_action('template_redirect','ribcage_init');

function ribcage_init (){
	global $wp_query;
	global $artists, $artist, $current_artist;
	global $releases, $release, $current_release;
	global $tracks, $track, $current_track;

	if ( is_ribcage_page () == 0){
		return;
	}
	
	// Artist Index
	if (isset($wp_query->query_vars['artist_index'])) {
		$artists = list_artists_blurb();
		$artist = $artists [$current_artist];

		$load = ribcage_load_template ('artist-index.php');
	}
	
	// Individual Artist (including bio, contact et al)
	if (isset($wp_query->query_vars['artist_slug'])) {
		$artist = get_artist_by_slug ($wp_query->query_vars['artist_slug']);
		
		if (is_artist_page()){
			switch ($wp_query->query_vars['artist_page']) {
				case 'press':
					$releases = list_artist_releases ($artist['artist_id']);
					$load = ribcage_load_template('press.php');
					break;
					
				case 'bio':
					$load  = ribcage_load_template('bio.php');
					break;
					
				default :
					$release = get_release_by_slug ($wp_query->query_vars['artist_page'], TRUE, TRUE);
					$tracks = $release ['release_tracks'];
					$load = ribcage_load_template ('release.php');				
			}
		}
		else {
			$releases = list_artist_releases ($artist['artist_id']);
			$load = ribcage_load_template ('artist.php');
		}
	}
		
	// Releases Index
	if (isset($wp_query->query_vars['release_index'])) {
		$releases = list_recent_releases_blurb();
		$artists = list_artists_blurb();
		
		$load = ribcage_load_template ('release-index.php');
	}
	
	// Downloads
	if (isset($wp_query->query_vars['ribcage_download'])) {
		
		// Download nag/links page.
		if (isset($wp_query->query_vars['release_slug'])) {
			$release = get_release_by_slug ($wp_query->query_vars['release_slug'], FALSE, FALSE);
			$artist = get_artist ($release['release_artist']);
			
			// If we haven't seen the user before, then nag them about the download.
			if (!isset($_COOKIE["ask_donate"])){
				setcookie("ask_donate", "1", time()+3600);
				$load = ribcage_load_template('nag.php');
			}
			
			// If we have seen the user before, then there is a one in five chance they will see the nag.
			elseif (isset($_COOKIE["ask_donate"])) {
				$random = rand(1, 5);
				if ($random == 5) {
					$load = ribcage_load_template('nag.php');
				}
				else {
					$load = ribcage_load_template('download.php');
				}
			}
			
			// If the user has just got back from Paypal congratulate them on their brillance and given them
			// the download. Maybe lower the chance of a nag?
			
		}
		
		// Download whole release.
		if (isset($wp_query->query_vars['release_slug']) && isset($wp_query->query_vars['format'])) {
			$load = download_release ($wp_query->query_vars['release_slug'], $wp_query->query_vars['format']);
		}
		
		// Download individual track.
		if (isset($wp_query->query_vars['track_slug'])) {
			$load = download_track ($wp_query->query_vars['track_slug'], $wp_query->query_vars['format']);
		}
	}
	
		
	// Streams
	if (isset($wp_query->query_vars['ribcage_stream'])) {
		// Stream whole release.
		if (isset($wp_query->query_vars['release_slug'])) {
			$load = stream_release ($wp_query->query_vars['release_slug'],$wp_query->query_vars['stream_format']);
		}
		// Stream individual track.
		if (isset($wp_query->query_vars['track_slug'])) {
			$load = stream_track ($wp_query->query_vars['track_slug']);
		}
	}
	
	if (isset($wp_query->query_vars['ribcage_player']))	{
		$load = show_player($wp_query->query_vars['release_slug']);
	}
	
	
	// Did we get an error by the end of all this? If so let the user know.
	if (is_wp_error($load)) {
		echo $load->get_error_message();
	}
	
	// Don't output anything else.
	die ();
}

function ribcage_load_template ( $filename ) {
	// Dangerous stuff ...sort this out...
	// $filename = basename($filename);
	$template = TEMPLATEPATH ."/ribcage/$filename";
	
	if ( !file_exists($template) )
		$template = dirname(__FILE__)."/templates/$filename";
	
	if ( !file_exists($template) )
		return new WP_Error('template-missing', sprintf(__("Oops! The template file %s could not be found in either the Ribcage template directory or your theme's Ribcage directory.", NRTD), "<code>$filename</code>"));
	
	load_template($template);
}

// If the rewrite rules are regenerated, Add our pretty permalink stuff, redirect it to the correct queryvar
add_action('generate_rewrite_rules', 'ribcage_add_rewrite_rules');

function ribcage_add_rewrite_rules ( $wp_rewrite ) {
	$new_rules = array(
		"(artists)/(.*)/(.*)" => 'index.php?artist_slug='.$wp_rewrite->preg_index(2).'&artist_page='.$wp_rewrite->preg_index(3),
		"(artists)/(.*)" => 'index.php?artist_slug=' . $wp_rewrite->preg_index(2),
		"(artists)" => 'index.php?artist_index=1',
		
		"(releases)" => 'index.php?release_index=1',
			
		"(download)/(track)/(.*)/(.*)" => 'index.php?ribcage_download=1&track_slug='.$wp_rewrite->preg_index(3).'&format='.$wp_rewrite->preg_index(4),
		"(download)/(.*)/(.*)" => 'index.php?ribcage_download=1&release_slug='.$wp_rewrite->preg_index(2).'&format='.$wp_rewrite->preg_index(3),
		"(download)/(.*)" => 'index.php?ribcage_download=1&release_slug='.$wp_rewrite->preg_index(2),
		"(download)" => 'index.php?ribcage_download=1',
		
		"(stream)/(track)/(.*)/(.*)/(.*)" => 'index.php?ribcage_stream=1&track_slug='.$wp_rewrite->preg_index(3).'&stream_format='.$wp_rewrite->preg_index(4),
		"(stream)/(.*)/(.*)" => 'index.php?ribcage_stream=1&release_slug='.$wp_rewrite->preg_index(2).'&stream_format='.$wp_rewrite->preg_index(3),
		"(stream)" => 'index.php?ribcage_stream=1',
		
		"(player)/(.*)" => 'index.php?ribcage_player=1&release_slug='.$wp_rewrite->preg_index(2)
		
	);

	$wp_rewrite->rules = $wp_rewrite->rules + $new_rules;
}

// Add a Query Var, This allows us to access the query var via $wp_query
add_filter('query_vars', 'ribcage_queryvars' );

function ribcage_queryvars ( $qvars ){
	
	// Artist Listings
	$qvars[] = 'artist_index';
	$qvars[] = 'artist_slug';
	$qvars[] = 'artist_id';
	$qvars[] = 'artist_page';
	
	// Release Listings
	$qvars[] = 'release_index';
	
	// Downloads
	$qvars[] = 'ribcage_download';

	// Streams
	$qvars[] = 'ribcage_stream';
	$qvars[] = 'stream_format';
	
	// Releases and Tracks
	$qvars[] = 'release_slug';
	$qvars[] = 'track_slug';
	
	$qvars[] = 'format';
	$qvars[] = 'ribcage_player';

	return $qvars;
}

// Add a Activation hook for the current page:
// NOTE: Broken on win32, see http://trac.wordpress.org/ticket/3002 Fixed on 2.3 (and 2.2.3?)
// register_activation_hook(__FILE__,'test_flush_rules');
add_action('init','ribcage_flush_rules');

function ribcage_flush_rules (){
	// Flush the rewrite rules so that the new rules from this plugin get added, 
	// This should only be done when the rewrite rules are changing, Ie. When this plugin is activated(Or 
	// Deactivated), For simplicity while developing using WP Rewrite, I flush the rules on every page load
	global $wp_rewrite;
	$wp_rewrite->flush_rules();
}

?>