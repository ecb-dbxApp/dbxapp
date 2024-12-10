<?php

	class dbxBrowser {

		public $_agent    = '';
		public $_language ='';
		public $_name     = '';
		public $_version  = '';
		public $_platform = '';
		public $_aol    = 0;
		public $_mobile = 0;
		public $_ipad   = 0;
		public $_robot  = 1;
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
		    $this->checkLanguage();
			$this->checkPlatform();
			$this->checkBrowsers();
			$this->checkForSize();
			$this->checkForIntPad();
			$this->checkForRobot();
			$this->checkMobile();
		}

		public function __construct() {
	     $this->init();
	  }


		public function isBrowser($browserName) { return( 0 == strcasecmp($this->_name, trim($browserName))); }
		protected function setVersion($version) { $this->_version = preg_replace('[^0-9,.,a-z,A-Z]','',$version); }
        protected function setPlatform($os) { $this->_platform = $os; } 

    public function checkMobile() {     // maybe add this check
        $u='HTTP_USER_AGENT';
        if (empty($_SERVER['HTTP_USER_AGENT']) ) {
            $is_mobile = 0;
        } elseif ( strpos($_SERVER[$u], 'Mobile') !== false // (iPhone, iPad, etc.)
            || strpos($_SERVER[$u], 'Android')    !== false
            || strpos($_SERVER[$u], 'Silk/')      !== false
            || strpos($_SERVER[$u], 'Kindle')     !== false
            || strpos($_SERVER[$u], 'BlackBerry') !== false
            || strpos($_SERVER[$u], 'Opera Mini') !== false
            || strpos($_SERVER[$u], 'Opera Mobi') !== false ) {
                $is_mobile = 1;
        } else {
            $is_mobile = 0;
        }
        if (!$is_mobile) {
			$ua = strtolower($_SERVER["HTTP_USER_AGENT"]);
			$is_mobile = is_numeric(strpos($ua, "mobile"));
		}

		if (!$is_mobile) {
				$browser= $this->checkBrowsers();
				$os     = $this->checkPlatform();
			if ($browser=='CHROME' && $os == 'LINUX') $is_mobile=1;
				//dbx_debug("#Browser =($browser) OS=($os) Mobile=($is_mobile)");
		}
		if ($is_mobile) {
				$is_mobile=1;
		} else {
				$is_mobile=0;
		}
		$this->_mobile=$is_mobile;
		dbx_set_SysVar('dbx_is_mobile',$is_mobile);
		return $is_mobile;
    }


    public function get_IP() {
		$ipAddress = '';
		if (isset($_SERVER['HTTP_CLIENT_IP']) && !empty($_SERVER['HTTP_CLIENT_IP'])) {
			// Check if the IP is from shared internet
			$ipAddress = $_SERVER['HTTP_CLIENT_IP'];
		} elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR']) && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
			// Check if the IP is passed from a proxy
			$ipAddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
		} elseif (isset($_SERVER['REMOTE_ADDR']) && !empty($_SERVER['REMOTE_ADDR'])) {
			// Check for the remote IP
			$ipAddress = $_SERVER['REMOTE_ADDR'];
		}
		
		// In case of multiple forwarded IPs, get the first one
		if (strpos($ipAddress, ',') !== false) {
			$ipAddress = explode(',', $ipAddress)[0];
		}
		$this->_ip=trim($ipAddress);
		//dbx_debug ("##IP=($ip)##");
    }




	protected function get_host($ip) {
		// $host=@gethostbyaddr($ip);  // das dauert teilweise sehr lang > 4 Sekunden 
		$host='x';
	   $this->_host=$host;
	}

    protected function checkLanguage() {
       global $_SERVER;
       if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
		     $this->_language=substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2);
		   }
		}


    protected function checkForIntPad() {
		   $name=$this->_name;
		   $os  =$this->_platform;
			 $IntPad = (bool) strpos($name,'Pad');
			 if ($IntPad) $this->_ipad=1;
		}

    protected function checkForRobot() {
		   $name=$this->_name;
		   $host=$this->_host;
		   $ip  =$this->_ip;
		   $os  =$this->_platform;
		   $osn=1;
		   if (!$name && !$os) $osn=0;
		   if ( $name &&  $os) $this->_robot=0;
		   if ($ip == $host && !$osn) $this->_robot=1;
		   if ($this->_is_robot) $this->_robot=1;
		   $ro=strpos("-".$host,"search.");
		   if (!$ro) $ro=strpos("-".$host,"bot.");
		   if (!$ro) $ro=strpos("-".$host,"spider");
		   if (!$ro) $ro=strpos("-".$host,"crawl");
		   if (!$ro) $ro=strpos("-".$host,"kundenserver");
		   if ($ro) $this->_robot=1;
		}

    protected function checkForSize() {
			if(isset($_COOKIE["dbx_win_size"])) {
			  $browser_size=$_COOKIE["dbx_win_size"];
			  $size=explode("X", $browser_size);
        if (isset($size[0])) $this->_width=$size[0];
			  if (isset($size[1]))$this->_height=$size[1];
			}
    }



		/**
		 * Protected routine to determine the browser type
		 * @return boolean 1 if the browser was detected otherwise 0
		 */
		protected function checkBrowsers() {
			$browser= (
				$this->checkBrowserGoogleBot() ||
				$this->checkBrowserSlurp() ||
				$this->checkBrowserInternetExplorer() ||
				$this->checkBrowserShiretoko() ||
				$this->checkBrowserIceCat() ||
				$this->checkBrowserNetscapeNavigator9Plus() ||
				$this->checkBrowserFirefox() ||
				$this->checkBrowserChrome() ||
        		$this->checkBrowserAndroid() ||
				$this->checkBrowserSafari() ||
				$this->checkBrowserOpera() ||
				$this->checkBrowserNetPositive() ||
				$this->checkBrowserFirebird() ||
				$this->checkBrowserGaleon() ||
				$this->checkBrowserKonqueror() ||
				$this->checkBrowserIcab() ||
				$this->checkBrowserOmniWeb() ||
				$this->checkBrowserPhoenix() ||
				$this->checkBrowserWebTv() ||
				$this->checkBrowserAmaya() ||
				$this->checkBrowserLynx() ||
				$this->checkBrowseriPhone() ||
				$this->checkBrowseriPod() ||
        $this->checkBrowserBlackBerry() ||
				$this->checkBrowserW3CValidator() ||
				$this->checkBrowserMozilla() /*  must check last */
				);
			return $browser;
		}

		/**
		 * Determine if the user is using a BlackBerry
		 * @return boolean 1 if the browser is the BlackBerry browser otherwise 0
		 */
		protected function checkBrowserBlackBerry() {
			$retval = 0;
			if( preg_match('/blackberry/i',$this->_agent) ) {
				$aresult = explode("/",stristr($this->_agent,"BlackBerry"));
				$aversion = explode(' ',$aresult[1]);
				$this->setVersion($aversion[0]);
				$this->_name = "BLACKBERRY";
				$this->_mobile=1;
				$retval = 1;
			}
			return $retval;
		}


		/**
		 * Determine if the browser is the GoogleBot or not
		 * @return boolean 1 if the browser is the GoogletBot otherwise 0
		 */
		protected function checkBrowserGoogleBot() {
			$retval = 0;
			if( preg_match('/googlebot/i',$this->_agent) ) {
				$aresult = explode('/',stristr($this->_agent,'googlebot'));
				$aversion = explode(' ',$aresult[1]);
				$this->setVersion(str_replace(';','',$aversion[0]));
				$this->_name = "GOOGLEBOT";
        $this->_is_robot=1;
				$retval = 1;
			}
			return $retval;
		}

		/**
		 * Determine if the browser is the W3C Validator or not
		 * @return boolean 1 if the browser is the W3C Validator otherwise 0
		 */
		protected function checkBrowserW3CValidator() {
			$retval = 0;
			if( preg_match('/W3C-checklink/i',$this->_agent) ) {
				$aresult = explode('/',stristr($this->_agent,'W3C-checklink'));
				$aversion = explode(' ',$aresult[1]);
				$this->setVersion($aversion[0]);
				$this->_name = "W3CVALIDATOR";
				$retval = 1;
			}
			return $retval;
		}

		/**
		 * Determine if the browser is the W3C Validator or not
		 * @return boolean 1 if the browser is the W3C Validator otherwise 0
		 */
		protected function checkBrowserSlurp() {
			$retval = 0;
			if( preg_match('/Slurp/i',$this->_agent) ) {
				$aresult = explode('/',stristr($this->_agent,'Slurp'));
				$aversion = explode(' ',$aresult[1]);
				$this->setVersion($aversion[0]);
				$this->_name = "SLURP";
        $this->_is_robot=1;
				$retval = 1;
			}
			return $retval;
		}

		/**
		 * Determine if the browser is Internet Explorer or not
		 * @return boolean 1 if the browser is Internet Explorer otherwise 0
		 */
		protected function checkBrowserInternetExplorer() {
			$retval = 0;

			// Test for v1 - v1.5 IE
			if( preg_match('/microsoft internet explorer/i', $this->_agent) ) {
				$this->_name="IE";
				$this->setVersion('1.0');
				$aresult = stristr($this->_agent, '/');
				if( preg_match('/308|425|426|474|0b1/i', $aresult) ) {
					$this->setVersion('1.5');
				}
				$retval = 1;
			}
			// Test for versions > 1.5
			else if( preg_match('/msie/i',$this->_agent) && !preg_match('/opera/i',$this->_agent) ) {
				$aresult = explode(' ',stristr(str_replace(';','; ',$this->_agent),'msie'));
				$this->_name="IE";
				$this->setVersion(str_replace(array('(',')',';'),'',$aresult[1]));
				$retval = 1;
			}
			// Test for Pocket IE
			else if( preg_match('/mspie/i',$this->_agent) || preg_match('/pocket/i', $this->_agent) ) {
				$aresult = explode(' ',stristr($this->_agent,'mspie'));
				$this->setPlatform("WINDOWS_CE");
				$this->_name="POCKET_IE";
				$this->_mobile=1;

				if( preg_match('/mspie/i', $this->_agent) ) {
					$this->setVersion($aresult[1]);
				}
				else {
					$aversion = explode('/',$this->_agent);
					$this->setVersion($aversion[1]);
				}
				$retval = 1;
			}
			return $retval;
		}

		/**
		 * Determine if the browser is Opera or not
		 * @return boolean 1 if the browser is Opera otherwise 0
		 */
		protected function checkBrowserOpera() {
			$retval = 0;
			if( preg_match('/opera mini/i',$this->_agent) ) {
				$resultant = stristr($this->_agent, 'opera mini');
				if( preg_match('/\//',$resultant) ) {
					$aresult = explode('/',$resultant);
					$aversion = explode(' ',$aresult[1]);
					$this->setVersion($aversion[0]);
					$this->_name = "OPERA_MINI";
					$this->_mobile=1;
          $retval = 1;
				}
				else {
					$aversion = explode(' ',stristr($resultant,'opera mini'));
					$this->setVersion($aversion[1]);
					$this->_name = "OPERA_MINI";
					$this->_mobile=1;
					$retval = 1;
				}
			}
			else if( preg_match('/opera/i',$this->_agent) ) {
				$resultant = stristr($this->_agent, 'opera');
				if( preg_match('/Version\/(10.*)$/',$resultant,$matches) ) {
					$this->setVersion($matches[1]);
					$this->_name = "OPERA";
					$retval = 1;
				}
				else if( preg_match('/\//',$resultant) ) {
					$aresult = explode('/',$resultant);
					$aversion = explode(' ',$aresult[1]);
					$this->setVersion($aversion[0]);
					$this->_name = "OPERA";
					$retval = 1;
				}
				else {
					$aversion = explode(' ',stristr($resultant,'opera'));
					$this->setVersion($aversion[1]);
					$this->_name = "OPERA";
					$retval = 1;
				}
			}
			return $retval;
		}

		/**
		 * Determine if the browser is WebTv or not
		 * @return boolean 1 if the browser is WebTv otherwise 0
		 */
		protected function checkBrowserWebTv() {
			$retval = 0;
			if( preg_match('/webtv/i',$this->_agent) ) {
				$aresult = explode('/',stristr($this->_agent,'webtv'));
				$aversion = explode(' ',$aresult[1]);
				$this->setVersion($aversion[0]);
				$this->_name = "WEBTV";
				$retval = 1;
			}
			return $retval;
		}

		/**
		 * Determine if the browser is NetPositive or not
		 * @return boolean 1 if the browser is NetPositive otherwise 0
		 */
		protected function checkBrowserNetPositive() {
			$retval = 0;
			if( preg_match('/NetPositive/i',$this->_agent) ) {
				$aresult = explode('/',stristr($this->_agent,'NetPositive'));
				$aversion = explode(' ',$aresult[1]);
				$this->setVersion(str_replace(array('(',')',';'),'',$aversion[0]));
				$this->_name = "NETPOSITIVE";
				$this->_platform = "BEOS";
				$retval = 1;
			}
			return $retval;
		}

		/**
		 * Determine if the browser is Galeon or not
		 * @return boolean 1 if the browser is Galeon otherwise 0
		 */
		protected function checkBrowserGaleon() {
			$retval = 0;
			if( preg_match('/galeon/i',$this->_agent) ) {
				$aresult = explode(' ',stristr($this->_agent,'galeon'));
				$aversion = explode('/',$aresult[0]);
				$this->setVersion($aversion[1]);
				$this->_name="GALEON";
				$retval = 1;
			}
			return $retval;
		}

		/**
		 * Determine if the browser is Konqueror or not
		 * @return boolean 1 if the browser is Konqueror otherwise 0
		 */
		protected function checkBrowserKonqueror() {
			$retval = 0;
			if( preg_match('/Konqueror/i',$this->_agent) ) {
				$aresult = explode(' ',stristr($this->_agent,'Konqueror'));
				$aversion = explode('/',$aresult[0]);
				$this->setVersion($aversion[1]);
				$this->_name="KONQUEROR";
				$retval = 1;
			}
			return $retval;
		}

		/**
		 * Determine if the browser is iCab or not
		 * @return boolean 1 if the browser is iCab otherwise 0
		 */
		protected function checkBrowserIcab() {
			$retval = 0;
			if( preg_match('/icab/i',$this->_agent) ) {
				$aversion = explode(' ',stristr(str_replace('/',' ',$this->_agent),'icab'));
				$this->setVersion($aversion[1]);
				$this->_name="ICAB";
				$retval = 1;
			}
			return $retval;
		}

		/**
		 * Determine if the browser is OmniWeb or not
		 * @return boolean 1 if the browser is OmniWeb otherwise 0
		 */
		protected function checkBrowserOmniWeb() {
			$retval = 0;
			if( preg_match('/omniweb/i',$this->_agent) ) {
				$aresult = explode('/',stristr($this->_agent,'omniweb'));
				$aversion = explode(' ',$aresult[1]);
				$this->setVersion($aversion[0]);
				$this->_name="OMNIWEB";
				$retval = 1;
			}
			return $retval;
		}

		/**
		 * Determine if the browser is Phoenix or not
		 * @return boolean 1 if the browser is Phoenix otherwise 0
		 */
		protected function checkBrowserPhoenix() {
			$retval = 0;
			if( preg_match('/Phoenix/i',$this->_agent) ) {
				$aversion = explode('/',stristr($this->_agent,'Phoenix'));
				$this->setVersion($aversion[1]);
				$this->_name="PHOENIX";
				$retval = 1;
			}
			return $retval;
		}

		/**
		 * Determine if the browser is Firebird or not
		 * @return boolean 1 if the browser is Firebird otherwise 0
		 */
		protected function checkBrowserFirebird() {
			$retval = 0;
			if( preg_match('/Firebird/i',$this->_agent) ) {
				$aversion = explode('/',stristr($this->_agent,'Firebird'));
				$this->setVersion($aversion[1]);
				$this->_name="FIREBIRD";
				$retval = 1;
			}
			return $retval;
		}

		/**
		 * Determine if the browser is Netscape Navigator 9+ or not (http://browser.netscape.com/ - Official support ended on March 1st, 2008)
		 * @return boolean 1 if the browser is Netscape Navigator 9+ otherwise 0
		 */
		protected function checkBrowserNetscapeNavigator9Plus() {
			$retval = 0;
			if( preg_match('/Firefox/i',$this->_agent) && preg_match('/Navigator\/([^ ]*)/i',$this->_agent,$matches) ) {
				$this->setVersion($matches[1]);
				$this->_name="NETSCAPE_NAVIGATOR";
				$retval = 1;
			}
			return $retval;
		}

		/**
		 * Determine if the browser is Shiretoko or not (https://wiki.mozilla.org/Projects/shiretoko)
		 * @return boolean 1 if the browser is Shiretoko otherwise 0
		 */
		protected function checkBrowserShiretoko() {
			$retval = 0;
			if( preg_match('/Mozilla/i',$this->_agent) && preg_match('/Shiretoko\/([^ ]*)/i',$this->_agent,$matches) ) {
				$this->setVersion($matches[1]);
				$this->_name="SHIRETOKO";
				$retval = 1;
			}
			return $retval;
		}

		/**
		 * Determine if the browser is Ice Cat or not (http://en.wikipedia.org/wiki/GNU_IceCat)
		 * @return boolean 1 if the browser is Ice Cat otherwise 0
		 */
		protected function checkBrowserIceCat() {
			$retval = 0;
			if( preg_match('/Mozilla/i',$this->_agent) && preg_match('/IceCat\/([^ ]*)/i',$this->_agent,$matches) ) {
				$this->setVersion($matches[1]);
				$this->_name="ICECAT";
				$retval = 1;
			}
			return $retval;
		}

		/**
		 * Determine if the browser is Firefox or not
		 * @return boolean 1 if the browser is Firefox otherwise 0
		 */
		protected function checkBrowserFirefox() {
			$retval = 0;
			if( preg_match('/Firefox/i',$this->_agent) ) {
				$aresult = explode('/',stristr($this->_agent,'Firefox'));
				$aversion = explode(' ',$aresult[1]);
				$this->setVersion($aversion[0]);
				$this->_name="FIREFOX";
				$retval = 1;
			}
			return $retval;
		}

		/**
		 * Determine if the browser is Mozilla or not
		 * @return boolean 1 if the browser is Mozilla otherwise 0
		 */
		protected function checkBrowserMozilla() {
			$retval = 0;
			if( preg_match('/mozilla/i',$this->_agent) && preg_match('/rv:[0-9].[0-9][a-b]?/i',$this->_agent) && !preg_match('/netscape/i',$this->_agent)) {
				$aversion = explode(' ',stristr($this->_agent,'rv:'));
				preg_match('/rv:[0-9].[0-9][a-b]?/i',$this->_agent,$aversion);
				$this->setVersion(str_replace('rv:','',$aversion[0]));
				$this->_name="MOZILLA";
				$retval = 1;
			}
			else if( preg_match('/mozilla/i',$this->_agent) && preg_match('/rv:[0-9]\.[0-9]/i',$this->_agent) && !preg_match('/netscape/i',$this->_agent) ) {
				$aversion = explode('',stristr($this->_agent,'rv:'));
        preg_match('/rv:[0-9]\.[0-9]\.[0-9]/i',$this->_agent,$aversion);
				$this->setVersion(str_replace('rv:','',$aversion[0]));
				$this->_name="MOZILLA";
				$retval = 1;
			}
			return $retval;
		}

		/**
		 * Determine if the browser is Lynx or not
		 * @return boolean 1 if the browser is Lynx otherwise 0
		 */
		protected function checkBrowserLynx() {
			$retval = 0;
			if( preg_match('/libwww/i',$this->_agent) && preg_match('/lynx/i', $this->_agent) ) {
				$aresult = explode('/',stristr($this->_agent,'Lynx'));
				$aversion = explode(' ',$aresult[1]);
				$this->setVersion($aversion[0]);
				$this->_name="LYNX";
				$retval = 1;
			}
			return $retval;
		}

		/**
		 * Determine if the browser is Amaya or not
		 * @return boolean 1 if the browser is Amaya otherwise 0
		 */
		protected function checkBrowserAmaya() {
			$retval = 0;
			if( preg_match('/libwww/i',$this->_agent) && preg_match('/amaya/i', $this->_agent) ) {
				$aresult = explode('/',stristr($this->_agent,'Amaya'));
				$aversion = explode(' ',$aresult[1]);
				$this->setVersion($aversion[0]);
				$this->_name="AMAYA";
				$retval = 1;
			}
			return $retval;
		}

		/**
		 * Determine if the browser is Chrome or not
		 * @return boolean 1 if the browser is Chrome otherwise 0
		 */
		protected function checkBrowserChrome() {
			$retval = 0;
			if( preg_match('/Chrome/i',$this->_agent) ) {
				$aresult = explode('/',stristr($this->_agent,'Chrome'));
				$aversion = explode(' ',$aresult[1]);
				$this->setVersion($aversion[0]);
				$this->_name="CHROME";
				$retval = 1;
			}
			return $retval;
		}

		/**
		 * Determine if the browser is Safari or not
		 * @return boolean 1 if the browser is Safari otherwise 0
		 */
		protected function checkBrowserSafari() {
			$retval = 0;
			if( preg_match('/Safari/i',$this->_agent) && ! preg_match('/iPhone/i',$this->_agent) && ! preg_match('/iPod/i',$this->_agent) ) {
				$aresult = explode('/',stristr($this->_agent,'Version'));
				if( isset($aresult[1]) ) {
					$aversion = explode(' ',$aresult[1]);
					$this->setVersion($aversion[0]);
				}
				else {
					$this->setVersion(version: 'o');
				}
				$this->_name="SAFARI";
				$retval = 1;
			}
			return $retval;
		}

		/**
		 * Determine if the browser is iPhone or not
		 * @return boolean 1 if the browser is iPhone otherwise 0
		 */
		protected function checkBrowseriPhone() {
			$retval = 0;
			if( preg_match('/iPhone/i',$this->_agent) ) {
				$aresult = explode('/',stristr($this->_agent,'Version'));
				if( isset($aresult[1]) ) {
					$aversion = explode(' ',$aresult[1]);
					$this->setVersion($aversion[0]);
				}
				$this->_mobile=1;
				$this->_name="IPHONE";
				$retval = 1;
			}
			return $retval;
		}

		/**
		 * Determine if the browser is iPod or not
		 * @return boolean 1 if the browser is iPod otherwise 0
		 */
		protected function checkBrowseriPod() {
			$retval = 0;
			if( preg_match('/iPod/i',$this->_agent) ) {
				$aresult = explode('/',stristr($this->_agent,'Version'));
				if( isset($aresult[1]) ) {
					$aversion = explode(' ',$aresult[1]);
					$this->setVersion($aversion[0]);
				}
				else {
					$this->setVersion('o');
				}
				$this->_mobile=1;
				$this->_name="IPOD";
				$retval = 1;
			}
			return $retval;
		}

		/**
		 * Determine if the browser is Android or not
		 * @return boolean 1 if the browser is Android otherwise 0
		 */
		protected function checkBrowserAndroid() {
			$retval = 0;
			if( preg_match('/Android/i',$this->_agent) ) {
				$aresult = explode('/',stristr($this->_agent,'Version'));
				if( isset($aresult[1]) ) {
					$aversion = explode(' ',$aresult[1]);
					$this->setVersion($aversion[0]);
				}
				else {
					$this->setVersion(0);
				}
				$this->_mobile=1;
				$this->_name="ANDROID";
				$retval = 1;
			}
			return $retval;
		}

		/**
		 * Determine the user's platform
		 */
		protected function checkPlatform() {
			if( preg_match('/iPhone/i', $this->_agent) ) {
				$this->_platform = "IPHONE";
			}
			else if( preg_match('/iPod/i', $this->_agent) ) {
				$this->_platform = "IPOD";
			}
			else if( preg_match('/BlackBerry/i', $this->_agent) ) {
				$this->_platform = "BLACKBERRY";
			}
			else if( preg_match('/win/i', $this->_agent) ) {
				$this->_platform = "WINDOWS";
			}
			elseif( preg_match('/mac/i', $this->_agent) ) {
				$this->_platform = "APPLE";
			}
			elseif( preg_match('/linux/i', $this->_agent) ) {
				$this->_platform = "LINUX";
			}
			elseif( preg_match('/OS\/2/i', $this->_agent) ) {
				$this->_platform = "OS2";
			}
			elseif( preg_match('/BeOS/i', $this->_agent) ) {
				$this->_platform = "BEOS";
			}
			return $this->_platform;
		}
	}

?>
