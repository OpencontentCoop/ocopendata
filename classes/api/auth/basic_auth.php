<?php

class OCOpenDataApiBasicAuthStyle extends ezpRestAuthenticationStyle implements ezpRestAuthenticationStyleInterface
{
    public function setup(ezcMvcRequest $request)
    {
        if ($request->authentication === null) {
            $authRequest = clone $request;
            $authRequest->uri = "{$this->prefix}/auth/http-basic-auth";
            $authRequest->protocol = "http-get";

            return new ezcMvcInternalRedirect($authRequest);
        }

        $cred = new ezcAuthenticationPasswordCredentials($request->authentication->identifier, $request->authentication->password);

        $auth = new ezcAuthentication($cred);
        $auth->addFilter(new OCOpenDataApiAuthenticationEzFilter());
        return $auth;
    }

    public function authenticate(ezcAuthentication $auth, ezcMvcRequest $request)
    {
        if (!$auth->run()) {
            $request->uri = "{$this->prefix}/auth/http-basic-auth";
            $request->protocol = "http-get";

            return new ezcMvcInternalRedirect($request);
        } else {
            // We're in. Get the ezp user and return it
            return eZUser::fetchByName($auth->credentials->id);
        }
    }
}