<?php
/**
 * Google OAuth Handler Class
 * Handles Google OAuth 2.0 authentication
 */

class GoogleOAuth {
    private $clientId;
    private $clientSecret;
    private $redirectUri;
    private $scopes;
    
    public function __construct() {
        $this->clientId = GOOGLE_CLIENT_ID;
        $this->clientSecret = GOOGLE_CLIENT_SECRET;
        $this->redirectUri = GOOGLE_REDIRECT_URI;
        $this->scopes = GOOGLE_SCOPES ?? [
            'https://www.googleapis.com/auth/userinfo.email',
            'https://www.googleapis.com/auth/userinfo.profile'
        ];
    }
    
    /**
     * Get Google login URL
     * 
     * @return string
     */
    public function getAuthUrl() {
        if (empty($this->clientId) || empty($this->clientSecret)) {
            return '#';
        }
        
        $params = [
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUri,
            'response_type' => 'code',
            'scope' => implode(' ', $this->scopes),
            'access_type' => 'offline',
            'prompt' => 'consent'
        ];
        
        return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
    }
    
    /**
     * Exchange authorization code for access token
     * 
     * @param string $code Authorization code
     * @return array|false Token data or false on failure
     */
    public function getAccessToken($code) {
        $url = 'https://oauth2.googleapis.com/token';
        
        $params = [
            'code' => $code,
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'redirect_uri' => $this->redirectUri,
            'grant_type' => 'authorization_code'
        ];
        
        $response = $this->makeRequest($url, $params);
        
        return $response ? json_decode($response, true) : false;
    }
    
    /**
     * Get user info from Google
     * 
     * @param string $accessToken Access token
     * @return array|false User data or false on failure
     */
    public function getUserInfo($accessToken) {
        $url = 'https://www.googleapis.com/oauth2/v2/userinfo';
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $accessToken
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            return json_decode($response, true);
        }
        
        return false;
    }
    
    /**
     * Make HTTP POST request
     * 
     * @param string $url URL
     * @param array $params Parameters
     * @return string|false Response or false on failure
     */
    private function makeRequest($url, $params) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            return $response;
        }
        
        return false;
    }
    
    /**
     * Check if Google OAuth is configured
     * 
     * @return bool
     */
    public function isConfigured() {
        return !empty($this->clientId) && !empty($this->clientSecret);
    }
}
