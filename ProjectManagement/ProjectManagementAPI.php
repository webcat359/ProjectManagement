<?php

# Constants
define( 'PLUGIN_PM_EST',		0 );
define( 'PLUGIN_PM_DONE',		1 );
define( 'PLUGIN_PM_TODO',		2 );
define( 'PLUGIN_PM_REMAINING',	3 );
define( 'PLUGIN_PM_DIFF',		4 );

define( 'PLUGIN_PM_WORKTYPE_TOTAL',	100 );

# Enums

class Action {
	const UPDATE = 0;
	const INSERT = 1;
	const INSERT_OR_UPDATE = 2;
	const DELETE = 3;
}

/**
 * Converts the specified amount of minutes to a string formatted as 'HH:MM'.
 * @param float $p_minutes an amount of minutes.
 * @param bool $p_allow_blanks when true is passed, empty $p_minutes return a blank string,
 * otherwise, empty $p_minutes return the literal string '0:00'.
 * @return string the amount of minutes formatted as 'HH:MM'.
 */
function minutes_to_time( $p_minutes, $p_allow_blanks = true ) {
	if ( $p_minutes == 0 ) {
		if ( $p_allow_blanks ) {
			return null;
		} else {
			return '00:00';
		}
	}
	
	$t_hours = str_pad( floor( abs($p_minutes) / 60 ), 2, '0', STR_PAD_LEFT );
	$t_minutes = str_pad( abs($p_minutes) % 60, 2, '0', STR_PAD_RIGHT );
	
	if ( $p_minutes < 0 ) {
		$t_sign = '-';
	}
	
	return $t_sign . $t_hours . ':' . $t_minutes;
}

/**
 * Parses the specified $p_time string and converts it to an amount of minutes.
 * @param string $p_time a string in the form of 'HH', 'HH:MM' or 'DD:HH:MM'.
 * @param bool $p_throw_error_on_invalid_input true to throw an error on invalid input, false to return null.
 * @param bool $p_allow_negative allows conversion of negative values.
 * @throws ERROR_PLUGIN_PM_INVALID_TIME_INPUT thrown upon invalid input when $p_throw_error_on_invalid_input is set to true.
 * @return the amount of minutes represented by the specified $p_time string.
 */
function time_to_minutes( $p_time, $p_allow_negative = true, $p_throw_error_on_invalid_input = true ) {
	if ( $p_time == '0') {
		return 0;
	} else if ( empty ( $p_time ) ) {
		return null;
	}
	
	$t_time_array = explode( ':', $p_time );
	
	foreach ( $t_time_array as $t_value ) {
		if ( !is_numeric( $t_value ) || ( $t_value < 0 && !$p_allow_negative ) ) {
			if ( $p_throw_error_on_invalid_input ) {
				trigger_error( ERROR_CUSTOM_FIELD_INVALID_VALUE, E_USER_ERROR );
			} else {
				return null;
			}
		}
	}
	
	$t_minutes;
	if ( count( $t_time_array ) == 3 ) {
		# User entered DD:HH:MM
		$t_minutes += abs($t_time_array[0]) * 24 * 60;
		$t_minutes += abs($t_time_array[1]) * 60;
		$t_minutes += abs($t_time_array[2]);
	} else if ( count( $t_time_array ) == 2 ) {
		# User entered HH:MM
		$t_minutes += abs($t_time_array[0]) * 60;
		$t_minutes += abs($t_time_array[1]);
	} else if ( count( $t_time_array ) == 1 ) {
		# User entered HH
		$t_minutes += abs($t_time_array[0]) * 60;
	} else {
		if ( $p_throw_error_on_invalid_input || ( $t_value < 0 && !$p_allow_negative ) ) {
			trigger_error( ERROR_CUSTOM_FIELD_INVALID_VALUE, E_USER_ERROR );
		} else {
			return null;
		}
	}
	
	if ( $p_allow_negative && strstr( $p_time, '-' ) ) {
		$t_minutes *= -1;
	}
	
	return $t_minutes;
}

/**
 * Update the work of the specified $p_bug_id.
 * @return number number of affected rows.
 */
function set_work( $p_bug_id, $p_work_type, $p_minutes_type, $p_minutes, $p_book_date, $p_action ) {
	$t_rows_affected = 0;
	$t_table = plugin_table('work');
	$t_user_id = auth_get_current_user_id();
	$t_timestamp = time();
	
	if ( $p_action == ACTION::UPDATE || $p_action == ACTION::INSERT_OR_UPDATE ) {
		#Update and check for rows affected
		$t_query = "UPDATE $t_table SET minutes = $p_minutes, timestamp = $t_timestamp, user_id = $t_user_id, book_date= $p_book_date
				WHERE bug_id = $p_bug_id AND work_type = $p_work_type AND minutes_type = $p_minutes_type";
		db_query_bound( $t_query );
		$t_rows_affected = db_affected_rows();
	}
	if ( $p_action == ACTION::INSERT || ( $p_action == ACTION::INSERT_OR_UPDATE && $t_rows_affected == 0 )) {
		#Insert and check for rows affected
		$t_query = "INSERT INTO $t_table ( bug_id, user_id, work_type, minutes_type, 
					minutes, book_date, timestamp ) 
					VALUES ( $p_bug_id, $t_user_id, $p_work_type, $p_minutes_type,
					$p_minutes, $p_book_date, $t_timestamp )";
		db_query_bound( $t_query );
		$t_rows_affected = db_affected_rows();
	} 
	else if ( $p_action == ACTION::DELETE ) {
		#Delete and check for rows affected
		$t_query = "DELETE FROM $t_table WHERE bug_id = $p_bug_id AND work_type= $p_work_type AND minutes_type= $p_minutes_type";
		db_query_bound( $t_query );
		$t_rows_affected = db_affected_rows();
	}
	
	return $t_rows_affected;
}

/**
 * Returns the first day of the current month, or when specified,
 * the current month added (or substracted) with $p_add_months months.
 * @param int $p_add_months Optional. The amount of months to add or substract from the current month.
 * @param string $p_format Optional. The format of the date to return. Default is 'd/m/Y'.
 * @return string the first day of the month, formated as $p_format.
 */
function first_day_of_month( $p_add_months = 0, $p_format = 'd/m/Y' ) {
	return date( $p_format, mktime( 0, 0, 0, date('m') + $p_add_months, 1 ) );
}

/**
 * Returns the last day of the current month, or when specified,
 * the current month added (or substracted) with $p_add_months months.
 * @param int $p_add_months Optional. The amount of months to add or substract from the current month.
 * @param string $p_format Optional. The format of the date to return. Default is 'd/m/Y'.
 * @return string the last day of the month, formated as $p_format.
 */
function last_day_of_month( $p_add_months = 0, $p_format = 'd/m/Y' ) {
	return date( $p_format, mktime( 0, 0, 0, date('m') + $p_add_months + 1, 0 ) );
}

/**
 * Returns an array of key value pairs containing the key of the specified $p_enum_string
 * and the translated label as its value.
 * @param string $p_enum_string the enum string (without trailing 'enum_string'
 */
function get_translated_assoc_array_for_enum( $p_enum_string ) {
	$t_untranslated = MantisEnum::getAssocArrayIndexedByValues( config_get( $p_enum_string . '_enum_string' ) );
	$t_translated = array();
	foreach ( $t_untranslated as $t_key => $t_value ) {
		$t_translated[$t_key] = get_enum_element( $p_enum_string, $t_key );
	}
	return $t_translated;
}

/**
 * Locale-aware floatval
 * @link http://www.php.net/manual/en/function.floatval.php#92563
 * @param string $floatString
 * @return number
 */
function parse_float( $p_floatstring ){
	$t_locale_info = localeconv();
	$p_floatstring = str_replace( $t_locale_info["mon_thousands_sep"] , "", $p_floatstring );
	$p_floatstring = str_replace( $t_locale_info["mon_decimal_point"] , ".", $p_floatstring );
	return floatval( $p_floatstring );
}

/**
 * Formats the specified $p_decimal as '100 000,00'
 * @param float $p_decimal
 * @return string
 */
function format( $p_decimal ) {
	return number_format( round( $p_decimal, 2 ), 2, ',', ' ' );
}

/**
 * Convert a string array in the form of array( 'key' => 'val', key1 => val2,... ) to a php array.
 * Only works with this format of arrays!
 * @todo duplicated here from adm_config_report.php, should be moved to helper class or something imo.
 * @param complex $p_value
 * @return the array
 */
function string_to_array( $p_value ) {
	$t_value = array();
	$t_full_string = trim( $p_value );
	if ( preg_match('/array[\s]*\((.*)\)/s', $t_full_string, $t_match ) === 1 ) {
		// we have an array here
		$t_values = explode( ',', trim( $t_match[1] ) );
		foreach ( $t_values as $key => $value ) {
			if ( !trim( $value ) ) {
				continue;
			}
			$t_split = explode( '=>', $value, 2 );
			if ( count( $t_split ) == 2 ) {
				// associative array
				$t_new_key = constant_replace( trim( $t_split[0], " \t\n\r\0\x0B\"'" ) );
				$t_new_value = constant_replace( trim( $t_split[1], " \t\n\r\0\x0B\"'" ) );
				$t_value[ $t_new_key ] = $t_new_value;
			} else {
				// regular array
				$t_value[ $key ] = constant_replace( trim( $value, " \t\n\r\0\x0B\"'" ) );
			}
		}
	}
	return $t_value;
}

/**
 * Check if the passed string is a constant and return its value
 * @todo duplicated here from adm_config_report.php, should be moved to helper class or something imo.
 */
function constant_replace( $p_name ) {
	$t_result = $p_name;
	if ( is_string( $p_name ) && defined( $p_name ) ) {
		// we have a constant
		$t_result = constant( $p_name );
	}
	return $t_result;
}

?>