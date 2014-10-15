<?php
/**
 * OAuth 2.0 Secure key New algorithm
 *
 * @author saeedwang
 *
 */

namespace League\OAuth2\Server\Util\KeyAlgorithm;


class NewAlgorithm implements KeyAlgorithmInterface
{

	private $iv = 'CUq4516ZH4LKwMd2';
	private $key = '0123456789abcdef';


    public function make($len = 40)
    {

    }

	/**
	 * @param string $str
	 * @return string Encrypted data
	 */
	function encrypt($str, $isBinary = false)
	{
		$iv = $this->iv;
		$str = $isBinary ? $str : utf8_decode($str);

		$td = mcrypt_module_open('rijndael-128', ' ', 'cbc', $iv);

		mcrypt_generic_init($td, $this->key, $iv);
		$encrypted = mcrypt_generic($td, $str);

		mcrypt_generic_deinit($td);
		mcrypt_module_close($td);

		return $isBinary ? $encrypted : bin2hex($encrypted);
	}

	/**
	 * @param string $code
	 * @return string Decrypted data
	 */
	function decrypt($code, $isBinary = false)
	{
		$code = $isBinary ? $code : $this->hex2bin($code);
		$iv = $this->iv;

		$td = mcrypt_module_open('rijndael-128', ' ', 'cbc', $iv);

		mcrypt_generic_init($td, $this->key, $iv);
		$decrypted = mdecrypt_generic($td, $code);

		mcrypt_generic_deinit($td);
		mcrypt_module_close($td);

		return $isBinary ? trim($decrypted) : utf8_encode(trim($decrypted));
	}

	private function hex2bin($hexdata)
	{
		$bindata = '';

		for ($i = 0; $i < strlen($hexdata); $i += 2) {
			$bindata .= chr(hexdec(substr($hexdata, $i, 2)));
		}

		return $bindata;
	}



	

}
