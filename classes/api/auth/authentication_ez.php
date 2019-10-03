<?php

class OCOpenDataApiAuthenticationEzFilter extends ezcAuthenticationFilter
{
    const STATUS_KO = 100;

    /**
     * @param ezcAuthenticationPasswordCredentials $credentials
     * @return int
     */
    public function run($credentials)
    {
        if (OCOpenDataApiAuthUser::authUser($credentials->id, $credentials->password)){
            return self::STATUS_OK;
        }

        return self::STATUS_KO;
    }

}

class OCOpenDataApiAuthUser extends eZUser
{
    public static function authUser($login, $password, $authenticationMatch = false)
    {
        return self::_loginUser($login, $password, $authenticationMatch) instanceof eZUser;
    }
}