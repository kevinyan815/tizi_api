<?php

use \League\OAuth2\Server\Storage\ScopeInterface;

class ScopeModel implements ScopeInterface {

    public function getScope($scope, $clientId = null, $grantType = null)
    {
        $result = \OauthDbModel::table('oauth_scopes')
			->first(array('scope'=>$scope));

        if (is_null($result) || empty($result)) {
            return false;
        }

        return array(
            'id'            =>  $result['id'],
            'scope'         =>  $result['scope'],
            'name'          =>  $result['name'],
            'description'   =>  $result['description'],
        );

    }

	/**
	 * @param array $scope
	 * @return array
	 */
	public function getScopes($scope) {
	
		$db = \OauthDbModel::table('oauth_scopes')->db();
		$db->select('*');
		$db->from('oauth_scopes');
		$db->where_in('scope', $scope);
		$query = $db->get();

		return $query->result_array();
	
	}

}
