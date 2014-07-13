<?php
/**
 * If you use mbstring.func_overload, or the server you are running on has it enabled, you are in trouble.
 * 
 * To the question "Should I use multi-byte overloading (mbstring.func_overload)?". user 'gphilip' said it well on this 
 * StackOverflow post: 
 * http://stackoverflow.com/questions/222630/should-i-use-multi-byte-overloading-mbstring-func-overload
 * 
 *   > My answer is: definitely not!
 *   > 
 *   > The problem is that there is no easy way to "reset" 
 *   > str* functions once they are overloaded.
 *   > 
 *   > For some time this can work well with your project,
 *   > but almost surely you will run into an external library
 *   > that uses string functions to, for example, implement a
 *   > binary protocol, and they will fail. They will fail and
 *   > you will spend hours trying to find out why they are
 *   > failing.
 * 
 * This class is a wrapper for string functions, in cases where the mbstring.func_overload tripe have been enabled. 
 * Be warned, use this class ONLY if you have to, as it *will* affect performance a bit. For some functions, a lot, 
 * though that is due to problems in mb_string, not this class.
 * Function calls in PHP are fairly expensive on their own, and if func_overload is enabled, it'll use mb_string 
 * functions exclusively in place of the built-in PHP string, to parse them as 'latin1', which is also expensive, cpu 
 * wise.
 * 
 * PHP, like Java, have length aware strings, meaning the object header knows how long your string is. They are binary 
 * safe, and not null (0x00) terminated.
 * 
 * mb_string functions ignore that, and parse the entirety of the string, to figure out what is what. stelen(string) 
 * simply tells you how many bytes are in it, mb_strlen will parse it, to find multi byte characters, and tell you
 * how many characters there are. That is great for handling multi-byte encoded strings correctly, such as UTF-8, it
 * sucks for binary data handling, as multi-byte sequences are bound to occur by random chance, in any large enough 
 * binary data set.
 *
 * The functions overloaded by mbstring is:
 *  mbstring.func_overload
 *	value	original function		overloaded function
 *		1	mail()					mb_send_mail()
 *
 *		2	strlen()					mb_strlen()
 *		2	strpos()					mb_strpos()
 *		2	strrpos()				mb_strrpos()
 *		2	substr()					mb_substr()
 *		2	strtolower()				mb_strtolower()
 *		2	strtoupper()				mb_strtoupper()
 *		2	substr_count()			mb_substr_count()
 *
 *		4	ereg()					mb_ereg()
 *		4	eregi()					mb_eregi()
 *		4	ereg_replace()			mb_ereg_replace()
 *		4	eregi_replace()			mb_eregi_replace()
 *		4	split()					mb_split()
 *
 * License: GNU LGPL 2.1.
 *
 * @author A. Grandt <php@grandt.com>
 * @copyright 2014 A. Grandt
 * @license GNU LGPL 2.1
 * @version 0.10
 */

namespace com\grandt;

class BinString {

	const VERSION = 0.10;

	private $has_mbstring = FALSE;
	private $has_mb_shadow = FALSE;
	private $has_mb_mail_overload = FALSE;
	private $has_mb_string_overload = FALSE;
	private $has_mb_regex_overload = FALSE;

	/**
	 * mbstring.func_overload has an undocumented feature, to retain access to the original function. 
	 * As it is undocumented, it is uncertain if it'll remain, therefore it's being made an optional.
	 * 
	 * @var boolean 
	 */
	public $USE_MB_ORIG = false;

	function __construct() {
		$this->has_mbstring = extension_loaded('mbstring') || @dl(PHP_SHLIB_PREFIX . 'mbstring.' . PHP_SHLIB_SUFFIX);
		$this->has_mb_shadow = (int) ini_get('mbstring.func_overload');
		$this->has_mb_mail_overload = $this->has_mbstring && ($this->has_mb_shadow & 1);
		$this->has_mb_string_overload = $this->has_mbstring && ($this->has_mb_shadow & 2);
		$this->has_mb_regex_overload = $this->has_mbstring && ($this->has_mb_shadow & 4);
	}

	/**
	 * @link http://php.net/manual/en/function.mail.php
	 */
	public function _mail($to, $subject, $message, $additional_headers = null, $additional_parameters = null) {
		if ($this->has_mb_mail_overload) {
			if ($this->USE_MB_ORIG) {
				return mb_orig_mail($to, $subject, $message, $additional_headers, $additional_parameters);
			}
			$lang = mb_language(); // get current language
			mb_language("en"); // Force encoding to iso8859-1
			$rv = mb_send_mail($to, $subject, $message, $additional_headers, $additional_parameters);
			mb_language($lang);
			return $rv;
		} else {
			return mail($to, $subject, $message, $additional_headers, $additional_parameters);
		}
	}

	/**
	 * @link http://php.net/manual/en/function.strlen.php
	 */
	public function _strlen($string) {
		if ($this->has_mb_string_overload) {
			if ($this->USE_MB_ORIG) {
				return mb_orig_strlen($string);
			}
			return mb_strlen($string, 'latin1');
		} else {
			return strlen($string);
		}
	}

	/**
	 * @link http://php.net/manual/en/function.strpos.php
	 */
	public function _strpos($haystack, $needle, $offset = 0) {
		if ($this->has_mb_string_overload) {
			if ($this->USE_MB_ORIG) {
				return mb_orig_strpos($haystack, $needle, $offset);
			}
			return mb_strpos($haystack, $needle, $offset, 'latin1');
		} else {
			return strpos($haystack, $needle, $offset);
		}
	}

	/**
	 * @link http://php.net/manual/en/function.strrpos.php
	 */
	public function _strrpos($haystack, $needle, $offset = 0) {
		if ($this->has_mb_string_overload) {
			if ($this->USE_MB_ORIG) {
				return mb_orig_strrpos($haystack, $needle, $offset);
			}
			return mb_strrpos($haystack, $needle, $offset, 'latin1');
		} else {
			return strrpos($haystack, $needle, $offset);
		}
	}

	/**
	 * @link http://php.net/manual/en/function.substr.php
	 */
	public function _substr($string, $start, $length = null) {
		if ($this->has_mb_string_overload) {
			if ($this->USE_MB_ORIG) {
				if (func_num_args() == 2) { // Kludgry hack, as PHP substr is lobotomized.
					return mb_orig_substr($string, $start);
				}
				return mb_orig_substr($string, $start, $length);
			}
			if (func_num_args() == 2) { // Kludgry hack, as mb_substr is lobotomized, AND broken.
				return mb_substr($string, $start, mb_strlen($mbStr, 'latin1'), 'latin1');
			}
			return mb_substr($string, $start, $length, 'latin1');
		} else {
				if (func_num_args() == 2) { // Kludgry hack, as PHP substr is lobotomized.
					return substr($string, $start);
				}
			return substr($string, $start, $length);
		}
	}

	/**
	 * @link http://php.net/manual/en/function.strtolower.php
	 */
	public function _strtolower($string) {
		if ($this->has_mb_string_overload) {
			if ($this->USE_MB_ORIG) {
				return mb_orig_strtolower($string);
			}
			return mb_strtolower($string, 'latin1');
		} else {
			return strtolower($string);
		}
	}

	/**
	 * @link http://php.net/manual/en/function.strtoupper.php
	 */
	public function _strtoupper($string) {
		if ($this->has_mb_string_overload) {
			if ($this->USE_MB_ORIG) {
				return mb_orig_strtoupper($string);
			}
			return mb_strtoupper($string, 'latin1');
		} else {
			return strtoupper($string);
		}
	}

	/**
	 * @link http://php.net/manual/en/function.substr_count.php
	 */
	public function _substr_count($haystack, $needle, $offset = 0, $length = null) {
		if ($this->has_mb_string_overload) {
			if ($this->USE_MB_ORIG) {
				if (func_num_args() > 3) { // Kludgry hack, as PHP substr_count is lobotomized.
					return mb_orig_substr_count($haystack, $needle, $offset, $length);
				}
				return mb_orig_substr_count($haystack, $needle, $offset);
			}
			return mb_substr_count($haystack, $needle, 'latin1');
		} else {
			if (func_num_args() > 3) { // Kludgry hack, as PHP substr_count is lobotomized.
				return substr_count($haystack, $needle, $offset, $length);
			}
			return substr_count($haystack, $needle, $offset);
		}
	}

	/**
	 * @link http://php.net/manual/en/function.ereg.php
	 */
	public function _ereg($pattern, $string, array &$regs) {
		if ($this->has_mb_regex_overload) {
			if ($this->USE_MB_ORIG) {
				return mb_orig_ereg($pattern, $string, $regs);
			}
			$enc = mb_regex_encoding(); // get current encoding
			mb_regex_encoding("latin1"); // Force encoding to iso8859-1
			$rv = mb_ereg($pattern, $string, $regs);
			mb_regex_encoding($enc);
			return $rv;
		} else {
			return ereg($pattern, $string, $regs);
		}
	}

	/**
	 * @link http://php.net/manual/en/function.eregi.php
	 */
	public function _eregi($pattern, $string, array &$regs) {
		if ($this->has_mb_regex_overload) {
			if ($this->USE_MB_ORIG) {
				return mb_orig_eregi($pattern, $string, $regs);
			}
			$enc = mb_regex_encoding(); // get current encoding
			mb_regex_encoding("latin1"); // Force encoding to iso8859-1
			$rv = mb_eregi($pattern, $string, $regs);
			mb_regex_encoding($enc);
			return $rv;
		} else {
			return eregi($pattern, $string, $regs);
		}
	}

	/**
	 * @link http://php.net/manual/en/function.ereg_replace.php
	 */
	public function _ereg_replace($pattern, $replacement, $string, $mb_specific_option = "msr") {
		if ($this->has_mb_regex_overload) {
			if ($this->USE_MB_ORIG) {
				return mb_orig_ereg_replace($pattern, $replacement, $string);
			}
			$enc = mb_regex_encoding(); // get current encoding
			mb_regex_encoding("latin1"); // Force encoding to iso8859-1
			$rv = mb_ereg_replace($pattern, $replacement, $string, $mb_specific_option);
			mb_regex_encoding($enc);
			return $rv;
		} else {
			return ereg_replace($pattern, $replacement, $string);
		}
	}

	/**
	 * @link http://php.net/manual/en/function.eregi_replace.php
	 */
	public function _eregi_replace($pattern, $replacement, $string, $mb_specific_option = "msri") {
		if ($this->has_mb_regex_overload) {
			if ($this->USE_MB_ORIG) {
				return mb_orig_eregi_replace($pattern, $replacement, $string);
			}
			$enc = mb_regex_encoding(); // get current encoding
			mb_regex_encoding("latin1"); // Force encoding to iso8859-1
			$rv = mb_eregi_replace($pattern, $replacement, $string, $mb_specific_option);
			mb_regex_encoding($enc);
			return $rv;
		} else {
			return eregi_replace($pattern, $replacement, $string);
		}
	}

	/**
	 * @link http://php.net/manual/en/function.split.php
	 */
	public function _split($pattern, $string, $limit = -1) {
		if ($this->has_mb_regex_overload) {
			if ($this->USE_MB_ORIG) {
				return mb_orig_split($pattern, $string, $limit);
			}
			$enc = mb_regex_encoding(); // get current encoding
			mb_regex_encoding("latin1"); // Force encoding to iso8859-1
			$rv = mb_split($pattern, $string, $limit);
			mb_regex_encoding($enc);
			return $rv;
		} else {
			return split($pattern, $string, $limit);
		}
	}
}
