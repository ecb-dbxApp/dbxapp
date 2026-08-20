<?php

/**
 * Ermittelt Browser-, Plattform-, Geräte- und Clientmerkmale einer Anfrage.
 */
class dbxBrowser {
    public $_device = 'desktop';
	public $_agent    = '';
	public $_language ='';
	public $_name     = '';
	public $_version  = '';
	public $_platform = '';
	public $_aol    = 0;
	public $_mobile = 0;
	public $_ipad   = 0;
	public $_robot  = 0;
	public $_width  = 0;
	public $_height = 0;
	public $_cookie = 0;
	public $_iframe = 0;
	public $_js     = 0;
	public $_java   = 0;
	public $_flasch = 0;
	public $_host   = 'host';
	public $_ip     = 'ip';
	private $_is_robot=0;


	public function init() {

		$this->_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : "";

		$this->get_ip();
		$this->get_host($this->_ip);

		$this->check_language();
		$this->check_platform();
		$this->check_browsers();
		$this->check_for_size();
		$this->check_for_int_pad();
		$this->check_for_robot();
		$this->check_mobile();
	}

	public function __construct() {
		$this->init();
	}


	public function is_browser($browser_name) {
		return (0 == strcasecmp($this->_name, trim($browser_name)));
	}

	/* =====================================================
	 * MOBILE (NEU & STABIL)
	 * ===================================================== */
	public function check_mobile() {

		$ua = strtolower($this->_agent);

		$is_mobile = 0;
		$device = 'desktop';

		if ($ua) {

			/* =========================================
			* 1. TABLETS
			* ========================================= */
			if (
				strpos($ua, 'ipad') !== false ||
				(strpos($ua, 'android') !== false && strpos($ua, 'mobile') === false) ||
				strpos($ua, 'tablet') !== false ||
				strpos($ua, 'kindle') !== false ||
				strpos($ua, 'silk') !== false
			) {
				$is_mobile = 1;
				$device = 'tablet';
				$this->_ipad = 1;
			}

			/* =========================================
			* 2. PHONES
			* ========================================= */
			elseif (
				strpos($ua, 'iphone') !== false ||
				strpos($ua, 'ipod') !== false ||
				strpos($ua, 'android') !== false ||
				strpos($ua, 'blackberry') !== false ||
				strpos($ua, 'bb10') !== false ||
				strpos($ua, 'windows phone') !== false ||
				strpos($ua, 'opera mini') !== false ||
				strpos($ua, 'opera mobi') !== false
			) {
				$is_mobile = 1;
				$device = 'phone';
			}

			/* =========================================
			* 3. GENERISCH MOBILE
			* ========================================= */
			elseif (
				strpos($ua, 'mobile') !== false ||
				strpos($ua, 'mobi') !== false
			) {
				$is_mobile = 1;
				$device = 'phone';
			}

			/* =========================================
			* 4. FALLBACK (Touch/WebKit Geräte)
			* ========================================= */
			elseif (
				strpos($ua, 'webkit') !== false &&
				(
					strpos($ua, 'touch') !== false ||
					strpos($ua, 'phone') !== false
				)
			) {
				$is_mobile = 1;
				$device = 'phone';
			}
		}

		// State setzen (kompatibel!)
		$this->_mobile = $is_mobile;
		$this->_device = $device;

		dbx()->set_system_var('dbx_is_mobile',$is_mobile);
		dbx()->set_system_var('dbx_device',$device);

		return $is_mobile;
	}


	/* =====================================================
	 * IP
	 * ===================================================== */
	public function get_ip() {

		$candidates = array();

		foreach (array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR') as $key) {
			if (empty($_SERVER[$key])) {
				continue;
			}

			foreach (explode(',', (string) $_SERVER[$key]) as $part) {
				$candidates[] = $part;
			}
		}

		$this->_ip = '0.0.0.0';

		foreach ($candidates as $candidate) {
			$ip = $this->normalize_ip($candidate);
			if ($ip !== '') {
				$this->_ip = $ip;
				break;
			}
		}
	}

	protected function normalize_ip($ip) {
		$ip = trim((string) $ip);

		if ($ip === '' || strtolower($ip) === 'unknown') {
			return '';
		}

		if (str_starts_with($ip, '[')) {
			$end = strpos($ip, ']');
			if ($end !== false) {
				$ip = substr($ip, 1, $end - 1);
			}
		} elseif (substr_count($ip, ':') === 1 && preg_match('/^([0-9.]+):[0-9]+$/', $ip, $m)) {
			$ip = $m[1];
		}

		// IPv6 localhost auf IPv4 localhost normalisieren
		if ($ip === '::1' || $ip === '0:0:0:0:0:0:0:1') {
			return '127.0.0.1';
		}

		// IPv4-mapped IPv6 normalisieren, z.B. ::ffff:127.0.0.1
		if (preg_match('/^::ffff:(\d+\.\d+\.\d+\.\d+)$/i', $ip, $m)) {
			$ip = $m[1];
		}

		return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '';
	}


	protected function get_host($ip) {
		$this->_host = '';

		$ip = trim((string) $ip);

		if ($ip === '127.0.0.1' || $ip === '::1') {
			$this->_host = 'localhost';
			return;
		}

		$this->_host = $ip;
	}


	protected function check_language() {
		if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
			$this->_language = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2);
		}
	}


	protected function check_for_int_pad() {
		if (stripos($this->_agent, 'ipad') !== false) {
			$this->_ipad = 1;
		}
	}


	/* =====================================================
	 * ROBOT (VEREINFACHT & REALISTISCH)
	 * ===================================================== */
	protected function check_for_robot() {

		$ua = strtolower($this->_agent);

		if (!$ua) return;

		if (strpos($ua, 'bot') !== false ||
			strpos($ua, 'crawl') !== false ||
			strpos($ua, 'spider') !== false ||
			strpos($ua, 'slurp') !== false ||
			strpos($ua, 'google') !== false) {

			$this->_robot = 1;
		}

		if ($this->_is_robot) {
			$this->_robot = 1;
		}
	}


	protected function check_for_size() {
		if (isset($_COOKIE["dbx_win_size"])) {
			$size = explode("X", $_COOKIE["dbx_win_size"]);
			if (isset($size[0])) $this->_width = (int)$size[0];
			if (isset($size[1])) $this->_height = (int)$size[1];
		}
	}


	/* =====================================================
	 * BROWSER (STOP BEIM ERSTEN TREFFER)
	 * ===================================================== */
	protected function check_browsers() {

		if ($this->check_browser_google_bot()) return 1;
		if ($this->check_browser_slurp()) return 1;
		if ($this->check_browser_internet_explorer()) return 1;

		if ($this->check_browser_firefox()) return 1;

		// Chrome vor Safari!
		if ($this->check_browser_chrome()) return 1;
		if ($this->check_browser_android()) return 1;
		if ($this->check_browser_safari()) return 1;

		if ($this->check_browser_opera()) return 1;

		if ($this->check_browseri_phone()) return 1;
		if ($this->check_browseri_pod()) return 1;
		if ($this->check_browser_black_berry()) return 1;

		if ($this->check_browser_mozilla()) return 1;

		return 0;
	}


	protected function check_browser_google_bot() {
		if (preg_match('/googlebot/i',$this->_agent)) {
			$this->_name = "GOOGLEBOT";
			$this->_is_robot = 1;
			return 1;
		}
		return 0;
	}


	protected function check_browser_slurp() {
		if (preg_match('/slurp/i',$this->_agent)) {
			$this->_name = "SLURP";
			$this->_is_robot = 1;
			return 1;
		}
		return 0;
	}


	protected function check_browser_internet_explorer() {
		if (preg_match('/msie|trident/i',$this->_agent)) {
			$this->_name = "IE";
			return 1;
		}
		return 0;
	}


	protected function check_browser_firefox() {
		if (preg_match('/firefox/i',$this->_agent)) {
			$this->_name = "FIREFOX";
			return 1;
		}
		return 0;
	}


	protected function check_browser_chrome() {
		if (preg_match('/chrome/i',$this->_agent) && !preg_match('/edg|opr/i',$this->_agent)) {
			$this->_name = "CHROME";
			return 1;
		}
		return 0;
	}


	protected function check_browser_safari() {
		if (preg_match('/safari/i',$this->_agent) && !preg_match('/chrome/i',$this->_agent)) {
			$this->_name = "SAFARI";
			return 1;
		}
		return 0;
	}


	protected function check_browser_opera() {
		if (preg_match('/opera|opr/i',$this->_agent)) {
			$this->_name = "OPERA";
			return 1;
		}
		return 0;
	}


	protected function check_browser_android() {
		if (preg_match('/android/i',$this->_agent)) {
			$this->_name = "ANDROID";
			$this->_mobile = 1;
			return 1;
		}
		return 0;
	}


	protected function check_browseri_phone() {
		if (preg_match('/iphone/i',$this->_agent)) {
			$this->_name = "IPHONE";
			$this->_mobile = 1;
			return 1;
		}
		return 0;
	}


	protected function check_browseri_pod() {
		if (preg_match('/ipod/i',$this->_agent)) {
			$this->_name = "IPOD";
			$this->_mobile = 1;
			return 1;
		}
		return 0;
	}


	protected function check_browser_black_berry() {
		if (preg_match('/blackberry/i',$this->_agent)) {
			$this->_name = "BLACKBERRY";
			$this->_mobile = 1;
			return 1;
		}
		return 0;
	}


	protected function check_browser_mozilla() {
		if (preg_match('/mozilla/i',$this->_agent)) {
			$this->_name = "MOZILLA";
			return 1;
		}
		return 0;
	}


	protected function check_platform() {

		if (preg_match('/iphone/i', $this->_agent)) $this->_platform = "IPHONE";
		elseif (preg_match('/ipad/i', $this->_agent)) $this->_platform = "IPAD";
		elseif (preg_match('/android/i', $this->_agent)) $this->_platform = "ANDROID";
		elseif (preg_match('/win/i', $this->_agent)) $this->_platform = "WINDOWS";
		elseif (preg_match('/mac/i', $this->_agent)) $this->_platform = "APPLE";
		elseif (preg_match('/linux/i', $this->_agent)) $this->_platform = "LINUX";

		return $this->_platform;
	}
}
