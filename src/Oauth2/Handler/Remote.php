<?php

namespace Bolt\Extension\Bolt\Members\Oauth2\Handler;

use Bolt\Extension\Bolt\Members\Exception;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Provider\ResourceOwnerInterface;
use League\OAuth2\Client\Token\AccessToken;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Remote OAuth2 client login provider.
 *
 * @author Gawain Lynch <gawain.lynch@gmail.com>
 */
class Remote extends HandlerBase
{
    /** @var AccessToken */
    protected $accessToken;
    /** @var ResourceOwnerInterface */
    protected $resourceOwner;

    /**
     * {@inheritdoc}
     */
    public function login(Request $request)
    {
        $response = parent::login($request);
        if ($response instanceof Response) {
            $providerName = $this->providerManager->getProviderName(true);
            $profileEntities = $this->records->getProvisionsByProvider($providerName);
            foreach ($profileEntities as $profileEntity) {
                if ($profileEntity->getProvider() === $providerName) {
                    // User is logged in already, from whence they came return them now.
                    return $response;
                }
            }
        }

        $response = $this->getAuthorisationRedirectResponse();
        if ($response instanceof Response) {
            return $response;
        }

        throw new \RuntimeException('An error occured with the provider redirect handling.');
    }

    /**
     * {@inheritdoc}
     */
    public function process(Request $request, $grantType = 'authorization_code')
    {
        return parent::process($request, $grantType);
    }

    /**
     * {@inheritdoc}
     */
    public function logout(Request $request)
    {
        return parent::logout($request);
    }

    protected function getOauthResourceOwner(Request $request)
    {
        //if ($cookie = $request->cookies->get(Types::TOKEN_COOKIE_NAME)) {
        //    $profile = $this->records->getTokensByCookie($cookie);
        //
        //    if (!$profile) {
        //        throw new Exception\AccessDeniedException('No matching profile found.');
        //    } elseif (!$profile['enabled']) {
        //        throw new Exception\AccessDeniedException('Profile disabled.');
        //    }
        //
        //    // Compile the options from the database record.
        //    $options = [
        //        'resource_owner_id' => $profile->getResourceOwnerId(),
        //        'refresh_token'     => $profile->getRefreshToken(),
        //        'access_token'      => $profile->getAccessToken(),
        //        'expires'           => $profile->getExpires(),
        //    ];
        //
        //    // Create and refresh the token
        //    $accessToken = $this->getRefreshToken(new AccessToken($options));
        //    $resourceOwner = $this->provider->getResourceOwner($accessToken);
        //
        //    // Save the new token data
        //    $providerName = $this->providerManager->getProviderName();
        //    $this->records->saveToken($profile);
        //}
    }


    /**
     * Create a redirect response to fetch an authorisation code.
     *
     * @param string $approvalPrompt
     *
     * @return RedirectResponse
     */
    protected function getAuthorisationRedirectResponse($approvalPrompt = 'auto')
    {
        $providerName = $this->providerManager->getProviderName();
        $provider = $this->providerManager->getProvider($providerName);

        if ($providerName === 'Google' && $approvalPrompt == 'force') {
            /** @var \Bolt\Extension\Bolt\Members\Oauth2\Client\Provider\Google $provider */
            $provider->setAccessType('offline');
        }

        $providerOptions = $this->providerManager->getProviderOptions($providerName);
        $options = array_merge($providerOptions, ['approval_prompt' => $approvalPrompt]);
        $authorizationUrl = $provider->getAuthorizationUrl($options);

        // Get the state generated and store it to the session.
        $this->session->setStateToken($provider);
        $this->setDebugMessage('Storing state token: ' . $provider->getState());

        return new RedirectResponse($authorizationUrl);
    }

    /**
     * Get a refresh token from the OAuth provider.
     *
     * @param AccessToken $accessToken
     *
     * @throws IdentityProviderException
     *
     * @return AccessToken
     */
    protected function getRefreshToken(AccessToken $accessToken)
    {
        if ($accessToken->hasExpired()) {
            // Try to get an access token using the authorization code grant.
            $accessToken = $this->provider->getAccessToken('refresh_token', ['refresh_token' => $accessToken->getRefreshToken()]);
        }

        return $accessToken;
    }
}