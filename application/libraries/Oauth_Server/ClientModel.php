<?php

use \League\OAuth2\Server\Storage\ClientInterface;

class ClientModel implements ClientInterface {

    public function getClient($clientId, $clientSecret = null, $redirectUri = null, $grantType = null)
    {

		$redirectUri = rtrim($redirectUri, "/");
		$db = \OauthDbModel::table('oauth_clients')->db();

        if ( ! is_null($redirectUri) && is_null($clientSecret)) {

			$db->select('*'); 
			$db->from('oauth_clients'); 
			$db->join('oauth_client_endpoints', 'oauth_clients.id = oauth_client_endpoints.client_id');
			$db->where('oauth_clients.id', $clientId);
			$db->where('oauth_client_endpoints.redirect_uri', $redirectUri);
			$result = $db->get()->row_array();

        }

        elseif ( ! is_null($clientSecret) && is_null($redirectUri)) {

			$result = \OauthDbModel::table('oauth_clients')
				->first(array(
					'id' => $clientId, 'secret' => $clientSecret)
				);

        }

        elseif ( ! is_null($clientSecret) && ! is_null($redirectUri)) {

			$db->select('*');
			$db->from('oauth_clients');
			$db->join('oauth_client_endpoints', 'oauth_clients.id = oauth_client_endpoints.client_id');
			$db->where('oauth_clients.id', $clientId);
			$db->where('oauth_clients.secret', $clientSecret);
			$db->where('oauth_client_endpoints.redirect_uri', $redirectUri);
			$result = $db->get()->row_array();

        }

        if (is_null($result) || empty($result)) {
            return false;
        }

        return array(
            'client_id'     =>  $result['id'],
            'client_secret' =>  $result['secret'],
            'redirect_uri'  =>  (isset($result['redirect_uri'])) ? $result['redirect_uri'] : null,
            'name'          =>  $result['name'],
            'auto_approve'  =>  $result['auto_approve']
        );

    }

}
