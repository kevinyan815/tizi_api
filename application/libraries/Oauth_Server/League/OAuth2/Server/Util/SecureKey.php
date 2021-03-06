<?php
/**
 * OAuth 2.0 Secure key generator
 *
 * @package     php-loep/oauth2-server
 * @author      Alex Bilbie <hello@alexbilbie.com>
 * @copyright   Copyright (c) 2013 PHP League of Extraordinary Packages
 * @license     http://mit-license.org/
 * @link        http://github.com/php-loep/oauth2-server
 */

namespace League\OAuth2\Server\Util;

use League\OAuth2\Server\Util\KeyAlgorithm\DefaultAlgorithm;
use League\OAuth2\Server\Util\KeyAlgorithm\NewAlgorithm;
use League\OAuth2\Server\Util\KeyAlgorithm\KeyAlgorithmInterface;

/**
 * SecureKey class
 */
class SecureKey
{
    protected static $algorithm;

    /**
     * Generate a new unique code
     * @param  integer $len Length of the generated code
     * @return string
     */
    public static function make($len = 40)
    {
        return self::getAlgorithm()->make($len);
    }

    /**
     * @param KeyAlgorithmInterface $algorithm
     */
    public static function setAlgorithm(KeyAlgorithmInterface $algorithm)
    {
        self::$algorithm = $algorithm;
    }

    /**
     * @return KeyAlgorithmInterface
     */
    public static function getAlgorithm()
    {
        if (!self::$algorithm) {

            self::$algorithm = new DefaultAlgorithm();
        }

        return self::$algorithm;
    }

	public static function encrypt($str){
	
        $algorithm = new NewAlgorithm();
		return $algorithm -> encrypt($str);

	}

	public static function decrypt($str){
		
        $algorithm = new NewAlgorithm();
		return $algorithm -> decrypt($str);
	
	}




}
