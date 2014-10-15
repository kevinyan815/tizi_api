<?php

use League\OAuth2\Server\Storage\SessionInterface;

class SessionModel implements SessionInterface
{
    /**
     * Create a new session
     * @param  string $clientId  The client ID
     * @param  string $ownerType The type of the session owner (e.g. "user")
     * @param  string $ownerId   The ID of the session owner (e.g. "123")
     * @return int               The session ID
     */
    public function createSession($clientId, $ownerType, $ownerId)
    {

        return \OauthDbModel::table('oauth_sessions')->insertGetId([
            'client_id'         => $clientId,
            'owner_type'        => $ownerType,
            'owner_id'          => $ownerId
        ]);

    }

    /**
     * Delete a session
     * @param  string $clientId  The client ID
     * @param  string $ownerType The type of the session owner (e.g. "user")
     * @param  string $ownerId   The ID of the session owner (e.g. "123")
     * @return void
     */
    public function deleteSession($clientId, $ownerType, $ownerId)
    {

		$data = array(

			'client_id' => $clientId,
			'owner_type' => $ownerType,
			'owner_id' => $ownerId

		);

        \OauthDbModel::table('oauth_sessions')
            ->del($data);

    }

	public function getSession($clientId, $ownerType, $ownerId){

		$data = array(

			'client_id' => $clientId,
			'owner_type' => $ownerType,
			'owner_id' => $ownerId

		);

		return \OauthDbModel::table('oauth_sessions')
			->first($data);
	
	}

    /**
     * Delete a revresh token
     * @param  string $refreshToken  The refresh token
     * @return void
     */
    public function removeRefreshToken($refreshToken)
    {
        exit('remove session');
        /*

        \OauthDbModel::table('oauth_session_refresh_tokens')
            ->where('refresh_token', $refreshToken)
            ->delete();
         */
    }

    
    public function associateAuthCodeScope($authCodeId, $scopeId)
    {
		
		\OauthDbModel::table('oauth_session_authcode_scopes')
			->insert([
				'oauth_session_authcode_id' => $authCodeId,
				'scope_id' => $scopeId
			]);
		
    }

	public function getAuthCodeScopes($oauthSessionAuthCodeId) {
	
		return \OauthDbModel::table('oauth_session_authcode_scopes')
			->get(['oauth_session_authcode_id' => $oauthSessionAuthCodeId]);

	}

    /**
     * Associate a redirect URI with a session
     * @param  int    $sessionId   The session ID
     * @param  string $redirectUri The redirect URI
     * @return void
     */
    public function associateRedirectUri($sessionId, $redirectUri)
    {

        \OauthDbModel::table('oauth_session_redirects')->insert([
            'session_id'    => $sessionId,
            'redirect_uri'  => $redirectUri,
        ]);

    }

    /**
     * Associate an access token with a session
     * @param  int    $sessionId   The session ID
     * @param  string $accessToken The access token
     * @param  int    $expireTime  Unix timestamp of the access token expiry time
     * @return int
     */
    public function associateAccessToken($sessionId, $accessToken, $expireTime)
    { 
		
		$params = [       
	  		'session_id'    => $sessionId,
			'access_token'  => $accessToken
		];

		$accessTokenDetails = \OauthDbModel::table('oauth_session_access_tokens')->first(
			$params
		);
		if (!empty($accessTokenDetails)){

			\OauthDbModel::table('oauth_session_access_tokens')->update(['id' => $accessTokenDetails['id']], ['access_token_expires' => $expireTime]);
			return $accessTokenDetails['id'];

		}else{

			return \OauthDbModel::table('oauth_session_access_tokens')->insertGetId([
				'session_id'            => $sessionId,
				'access_token'          => $accessToken,
				'access_token_expires'  => $expireTime,
			]);	

		}

    }

    /**
     * Associate a refresh token with a session
     * @param  int    $accessTokenId The access token ID
     * @param  string $refreshToken  The refresh token
     * @return void
     */
    public function associateRefreshToken($accessTokenId, $refreshToken, $expireTime, $clientId)
    {
        exit('create session2');
        /*

        \OauthDbModel::table('oauth_session_refresh_tokens')->insert([
            'session_access_token_id'  => $accessTokenId,
            'refresh_token'            => $refreshToken,
            'refresh_token_expires'    => $expireTime,
            'client_id'                => $clientId,
        ]);
         */
    }

    /**
     * Assocate an authorization code with a session
     * @param  int    $sessionId  The session ID
     * @param  string $authCode   The authorization code
     * @param  int    $expireTime Unix timestamp of the access token expiry time
     * @param  string $scopeIds   Comma seperated list of scope IDs to be later associated (default = null)
     * @return void
     */
    public function associateAuthCode($sessionId, $authCode, $expireTime, $scopeIds = null)
    {

		return  \OauthDbModel::table('oauth_session_authcodes')->insertGetId([
            'session_id'        => $sessionId,
            'auth_code'         => $authCode,
            'auth_code_expires' => $expireTime,
            'scope_ids'         => $scopeIds,
        ]);

    }

    /**
     * Remove an associated authorization token from a session
     * @param  int    $sessionId   The session ID
     * @return void
     */
    public function removeAuthCode($sessionId)
    {
        \OauthDbModel::table('oauth_session_authcodes')
            ->del(array('session_id' => $sessionId));
    }

    /**
     * Remove access_token
     * @param  int    $accessTokenId
     * @return bool
     */
    public function removeAccessToken($accessTokenId)
    {

        return \OauthDbModel::table('oauth_session_access_tokens')
            ->del(['accessTokenId' => $accessTokenId]);

    }

    /**
     * Validate an authorization code
     * @param  string $clientId    The client ID
     * @param  string $redirectUri The redirect URI
     * @param  string $authCode    The authorization code
     * @return void
     */
    public function validateAuthCode($clientId, $redirectUri, $authCode)
    {

		$db = \OauthDbModel::table('oauth_sessions')->db();

		$db->select('oauth_sessions.owner_id as user_id,oauth_sessions.id as session_id, oauth_session_authcodes.id as authcode_id');
		$db->select('oauth_sessions.id, oauth_session_authcodes.scope_ids');
		$db->from('oauth_sessions');
		$db->join('oauth_session_authcodes', 'oauth_sessions.id = oauth_session_authcodes.session_id');
		$db->join('oauth_session_redirects', 'oauth_sessions.id = oauth_session_redirects.session_id');
		$db->where('oauth_sessions.client_id', $clientId);
		$db->where('oauth_session_authcodes.auth_code', $authCode);
		$db->where('oauth_session_authcodes.auth_code_expires >=', time());
		$db->where('oauth_session_redirects.redirect_uri', $redirectUri);
		$query = $db->get();

		return $query->num_rows ? $query->row_array() : false;

    }

    /**
     * @param  string $clientId    The client ID
     * @param  string $userId
     * @return void
     */
    public function getUserAccessToken($clientId, $userId)
    {

		$db = \OauthDbModel::table('oauth_sessions')->db();

		$db->select('oauth_session_access_tokens.access_token,oauth_session_access_tokens.access_token_expires');
		$db->from('oauth_sessions');
		$db->join('oauth_session_access_tokens', 'oauth_session_access_tokens.session_id = oauth_sessions.id');
		$db->where('oauth_sessions.client_id', $clientId);
		$db->where('oauth_sessions.owner_id', $userId);
		$query = $db->get();

		return $query->num_rows ? $query->row_array() : false;

    }


    /**
     * Validate an access token
     * @param  string $accessToken The access token to be validated
     * @return void
     */
    public function validateAccessToken($accessToken)
    {
        $db = \OauthDbModel::table('oauth_session_access_tokens')->db();
		$db->select('*');
		$db->from('oauth_sessions');
		$db->join('oauth_session_access_tokens', 'oauth_session_access_tokens.session_id = oauth_sessions.id');
		$db->where('oauth_session_access_tokens.access_token', $accessToken);
		$db->where('oauth_session_access_tokens.access_token_expires >=', time());
		$query = $db->get();
		$result = $query->row_array();

        return (is_null($result)) ? false : (array) $result;
    }

    /**
     * Validate a refresh token
     * @param  string $refreshToken The access token
     * @return void
     */
    public function validateRefreshToken($refreshToken, $clientId)
    {
        $result = \OauthDbModel::table('oauth_session_refresh_tokens')
            ->where('refresh_token', $refreshToken)
            ->where('client_id', $clientId)
            ->where('refresh_token_expires', '>=', time())
            ->first();

        return (is_null($result)) ? false : $result->session_access_token_id;
    }

    /**
     * Get an access token by ID
     * @param  int    $accessTokenId The access token ID
     * @return array
     */
    public function getAccessToken($accessTokenId)
    {
        $result = \OauthDbModel::table('oauth_session_access_tokens')
            ->get(['id', $accessTokenId])
            ->first();

        return (is_null($result)) ? false : (array) $result;
    }

    /**
     * Associate a scope with an access token
     * @param  int    $accessTokenId The ID of the access token
     * @param  int    $scopeId       The ID of the scope
     * @return void
     */
    public function associateScope($accessTokenId, $scopeId) {

        \OauthDbModel::table('oauth_session_token_scopes')->replace([
            'session_access_token_id'   => $accessTokenId,
            'scope_id'                  => $scopeId,
        ]);

    }

    /**
     * Get all associated access tokens for an access token
     * @param  string $accessToken The access token
     * @return array
     */
    public function getScopes($accessToken)
    {

		$db = \OauthDbModel::table('oauth_session_token_scopes')->db();
		$db->select('oauth_scopes.*');
		$db->from('oauth_session_token_scopes');
		$db->join('oauth_session_access_tokens', 'oauth_session_token_scopes.session_access_token_id = oauth_session_access_tokens.id');
		$db->join('oauth_scopes', 'oauth_session_token_scopes.scope_id = oauth_scopes.id');
		$db->where('access_token', $accessToken);
		$query = $db->get();

		return $query->result_array();

    }

}
