<?php

# WARNING: This file is publically viewable on the web.
# Do not put private data here.

# Initialize the array. Append to that array to add a throttle
$wmgThrottlingExceptions = array();

# $wmgThrottlingExceptions is an array of arrays of parameters:
#  'from'  => date/time to start raising account creation throttle
#  'to'    => date/time to stop
#
# Optional arguments can be added to set the value or restrict by client IP
# or project dbname. Options are:
#  'value'  => new value for $wgAccountCreationThrottle (default: 50)
#  'IP'     => client IP as given by wfGetIP() or array (default: any IP)
#  'range'  => alternatively, the client IP CIDR ranges or array (default: any range)
#  'dbname' => a $wgDBname or array of dbnames to compare to
#             (eg. enwiki, metawiki, frwikibooks, eswikiversity)
#             (default: any project)
## Add throttling definitions below.

$wmgThrottlingExceptions[] = array( // Bug 58114
	'from'   => '2013-12-06T00:00 +0:00',
	'to'     => '2013-12-07T00:00 +0:00',
	'IP'     => array( '207.159.196.253' ),
	'dbname' => array( 'enwiki' ),
	'value'  => 45,
);
## Add throttling definitions above.

## Helper methods:

/**
 * Determines if an IP address is a list of CIDR a.b.c.d/n ranges.
 *
 * @param string $ip the IP to check
 * @param array $range the IP ranges, each element a range
 *
 * @return Boolean true if the specified adress belongs to the specified range; otherwise, false.
 */
function inIPRanges ( $ip, $ranges ) {
	foreach ( $ranges as $range ) {
		if ( IP::isInRange( $ip, $range ) ) {
			return true;
		}
	}
	return false;
}

# Will eventually raise value when MediaWiki is fully initialized:
$wgExtensionFunctions[] = 'efRaiseAccountCreationThrottle';

/**
 * Helper to easily add a throttling request.
 */
function efRaiseAccountCreationThrottle() {
	global $wmgThrottlingExceptions, $wgDBname;

	foreach ( $wmgThrottlingExceptions as $options ) {
		# Validate entry, skip when it does not apply to our case

		# 1) skip when it does not apply to our database name

		if( isset( $options['dbname'] ) ) {
			if ( is_array( $options['dbname'] ) ) {
				if ( !in_array( $wgDBname, $options['dbname'] ) ) {
					continue;
				}
			} elseif ( $wgDBname != $options['dbname'] ) {
				continue;
			}
		}

		# 2) skip expired entries
		$inTimeWindow = time() >= strtotime( $options['from'] )
				&& time() <= strtotime( $options['to'] );

		if( !$inTimeWindow ) {
			continue;
		}

		# 3) skip when throttle does not apply to the client IP
		if ( isset( $options['IP'] ) ) {
			if ( is_array( $options['IP'] ) && !in_array( wfGetIP(), $options['IP'] ) ) {
				continue;
			} elseif ( wfGetIP() != $options['IP'] ) {
				continue;
			}
		}
		if ( isset ( $options['range'] ) ) {
			//Loads IP class from the MediaWiki code, for IP::isInRange.
			global $IP;
			define( 'MEDIAWIKI', true);
			require_once( "$IP/includes/GlobalFunctions.php" );
			require_once( "$IP/includes/IP.php" );

			//Checks if the IP is in range
			if ( is_array( $options['range'] ) && !inIPRanges( wfGetIP(), $options['range'] ) ) {
				continue;
			} elseif ( !IP::isInRange( wfGetIP(), $options['range'] ) ) {
				continue;
			}
		}

		# Finally) set up the throttle value
		global $wgAccountCreationThrottle;
		if( isset( $options['value'] ) && is_numeric( $options['value'] ) ) {
			$wgAccountCreationThrottle = $options['value'];
		} else {
			$wgAccountCreationThrottle = 50; // Provide some sane default
		}
		return; # No point in proceeding to another entry
	}
}

