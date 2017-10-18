<?php

/**
 * Ckan_client class
 *
 * A PHP client for the CKAN (Comprehensive Knowledge Archive Network) API.
 *
 * For details and documentation, please see http://github.com/jeffreybarke/Ckan_client-PHP
 *
 * @author        Jeffrey Barke
 * @copyright    Copyright 2010 Jeffrey Barke
 * @license        http://github.com/jeffreybarke/Ckan_client-PHP/blob/master/LICENSE
 * @link        http://github.com/jeffreybarke/Ckan_client-PHP
 *
 */
class CkanClientVersion2 implements OcOpenDataClientInterface
{

    // Properties ---------------------------------------------------------

    /**
     * Client's API key. Required for any PUT or POST methods.
     *
     * @link    http://knowledgeforge.net/ckan/doc/ckan/api.html#ckan-api-keys
     * @since    Version 0.1.0
     */
    protected $api_key = false;

    /**
     * Version of the CKAN API we're using.
     *
     * @var        string
     * @link    http://knowledgeforge.net/ckan/doc/ckan/api.html#api-versions
     * @since    Version 0.1.0
     */
    protected $api_version = '2';

    /**
     * URI to the CKAN web service.
     *
     * @var        string
     * @since    Version 0.1.0
     */
    protected $base_url = 'http://ckan.net/api/%d/';

    /**
     * Internal cURL object.
     *
     * @since    Version 0.1.0
     */
    protected $ch = false;

    /**
     * cURL headers.
     *
     * @since    Version 0.1.0
     */
    protected $ch_headers;

    /**
     * Standard HTTP status codes.
     *
     * @var        array
     * @since    Version 0.1.0
     */
    protected $http_status_codes = array(
        '200' => 'OK',
        '201' => 'CREATED',
        '301' => 'Moved Permanently',
        '400' => 'Bad Request',
        '403' => 'Not Authorized',
        '404' => 'Not Found',
        '409' => 'Conflict (e.g. name already exists)',
        '500' => 'Service Error'
    );

    /**
     * Risorse Api
     *
     * @see parent::resources
     */
    protected $resources = array(
        'package_register' => 'rest/dataset',
        'package_entity' => 'rest/dataset',
        'group_register' => 'rest/group',
        'group_entity' => 'rest/group',
        'tag_register' => 'rest/tag',
        'tag_entity' => 'rest/tag',
        'rating_register' => 'rest/rating',
        'rating_entity' => 'rest/rating',
        'revision_register' => 'rest/revision',
        'revision_entity' => 'rest/revision',
        'license_list' => 'rest/licenses',
        'package_search' => 'search/dataset'
    );

    /**
     * Ckan_client user agent string.
     *
     * @var        string
     * @since    Version 0.1.0
     */
    protected $user_agent = 'Ckan_client-PHP/%s';

    protected $version = '0.1.0';


    public function __construct( $api_key = null, $base_url = null, $apiVersion = null )
    {
        if ( $apiVersion && $apiVersion != $this->api_version ){
            throw new Exception( "Api version $apiVersion not supported by " . __CLASS__ );
        }

        // If provided, set the API key.
        if ( $api_key )
        {
            $this->set_api_key( $api_key );
        }

        if ( $base_url )
        {
            $this->base_url = $base_url;
        }
        // Set base URI and Ckan_client user agent string.
        $this->set_base_url();
        $this->set_user_agent();
        // Create cURL object.
        $this->ch = curl_init();
        // Follow any Location: headers that the server sends.
        curl_setopt( $this->ch, CURLOPT_FOLLOWLOCATION, true );
        // However, don't follow more than five Location: headers.
        curl_setopt( $this->ch, CURLOPT_MAXREDIRS, 5 );
        // Automatically set the Referer: field in requests
        // following a Location: redirect.
        curl_setopt( $this->ch, CURLOPT_AUTOREFERER, true );
        // Return the transfer as a string instead of dumping to screen.
        curl_setopt( $this->ch, CURLOPT_RETURNTRANSFER, true );
        // If it takes more than 45 seconds, fail
        curl_setopt( $this->ch, CURLOPT_TIMEOUT, 45 );
        // We don't want the header (use curl_getinfo())
        curl_setopt( $this->ch, CURLOPT_HEADER, false );
        // Set user agent to Ckan_client
        curl_setopt( $this->ch, CURLOPT_USERAGENT, $this->user_agent );
        // Track the handle's request string
        curl_setopt( $this->ch, CURLINFO_HEADER_OUT, true );
        // Attempt to retrieve the modification date of the remote document.
        curl_setopt( $this->ch, CURLOPT_FILETIME, true );
        // Initialize cURL headers
        $this->set_headers();
    }

    public function pushDataset( $data )
    {
        $postData = json_encode( $data );
        $postData = str_replace( ';', ',', $postData );
        if ( isset( $data['id'] ) )
        {
            $response = $this->post_package_update( $postData, $data['id'] );
        }
        else
        {
            $response = $this->post_package_register( $postData );
        }

        return $response;
    }

    public function getDataset( $datasetId )
    {
        throw new Exception( 'Not implemented' );
    }

    public function deleteDataset( $dataset, $purge = false ){
        throw new Exception( 'Not implemented' );
    }

    public function pushOrganization( $data )
    {
        throw new Exception( 'Not implemented' );
    }

    public function deleteOrganization( $data, $purge = false )
    {
        throw new Exception( 'Not implemented' );
    }

    public function getOrganization( $organizationId )
    {
        throw new Exception( 'Not implemented' );
    }

    public function getLicenseList()
    {
        return $this->get_license_list();
    }

    public function __destruct()
    {
        // Cleanup
        if ( $this->ch )
        {
            curl_close( $this->ch );
            unset( $this->ch );
        }
    }

    public function set_api_key( $api_key )
    {
        $this->api_key = $api_key;
    }

    protected function set_base_url()
    {
        // Append the CKAN API version to the base URI.
        $this->base_url = sprintf( $this->base_url, $this->api_version );
    }

    protected function set_headers()
    {
        $date = new DateTime( null, new DateTimeZone( 'UTC' ) );
        $this->ch_headers = array(
            'Date: ' . $date->format( 'D, d M Y H:i:s' ) . ' GMT', // RFC 1123
            'Accept: application/json;q=1.0, application/xml;q=0.5, */*;q=0.0',
            'Accept-Charset: utf-8',
            'Accept-Encoding: gzip'
        );
    }

    protected function set_user_agent()
    {
        if ( '80' === @$_SERVER['SERVER_PORT'] )
        {
            $server_name = 'http://' . $_SERVER['SERVER_NAME'];
        }
        else
        {
            $server_name = '';
        }
        $this->user_agent = sprintf( $this->user_agent, $this->version ) .
                            ' (' . $server_name . $_SERVER['PHP_SELF'] . ')';
    }

    public function get_package_register()
    {
        return $this->make_request( 'GET', $this->resources['package_register'] );
    }

    public function post_package_update( $data, $packageId )
    {
        return $this->make_request(
            'POST',
            $this->resources['package_register'] . '/' . $packageId,
            $data
        );
    }

    public function post_package_register( $data )
    {
        return $this->make_request(
            'POST',
            $this->resources['package_register'],
            $data
        );
    }

    public function get_package_entity( $package )
    {
        return $this->make_request(
            'GET',
            $this->resources['package_entity'] . '/' . urlencode( $package )
        );
    }

    public function put_package_entity( $package, $data )
    {
        return $this->make_request(
            'PUT',
            $this->resources['package_entity'] . '/' . urlencode( $package ),
            $data
        );
    }

    public function get_package( $package = false )
    {
        if ( $package )
        {
            return $this->get_package_entity( $package );
        }
        else
        {
            return $this->get_package_register();
        }
    }

    public function get_group_register()
    {
        return $this->make_request( 'GET', $this->resources['group_register'] );
    }

    public function get_group_entity( $group )
    {
        return $this->make_request(
            'GET',
            $this->resources['group_entity'] . '/' . urlencode( $group )
        );
    }

    public function get_group( $group = null )
    {
        if ( $group )
        {
            return $this->get_group_entity( $group );
        }
        else
        {
            return $this->get_group_register();
        }
    }

    public function get_tag_register()
    {
        return $this->make_request( 'GET', $this->resources['tag_register'] );
    }

    public function get_tag_entity( $tag )
    {
        return $this->make_request(
            'GET',
            $this->resources['tag_entity'] .
            '/' . urlencode( $tag )
        );
    }

    public function get_tag( $tag = false )
    {
        if ( $tag )
        {
            return $this->get_tag_entity( $tag );
        }
        else
        {
            return $this->get_tag_register();
        }
    }

    public function get_revision_register()
    {
        return $this->make_request(
            'GET',
            $this->resources['revision_register']
        );
    }

    public function get_revision_entity( $revision )
    {
        return $this->make_request(
            'GET',
            $this->resources['revision_entity'] . '/' . urlencode( $revision )
        );
    }

    public function get_revision( $revision = false )
    {
        if ( $revision )
        {
            return $this->get_revision_entity( $revision );
        }
        else
        {
            return $this->get_revision_register();
        }
    }

    public function get_license_list()
    {
        return $this->make_request( 'GET', $this->resources['license_list'] );
    }

    public function get_license()
    {
        return $this->get_license_list();
    }

    public function search_package( $keywords, $opts = array() )
    {
        // Gots to have keywords or there's nothing to search for.
        // Also, $opts better be an array
        if ( 0 === strlen( trim( $keywords ) ) || false === is_array( $opts ) )
        {
            throw new Exception( 'We need keywords, yo!' );
        }
        $q = '';
        // Set querystring based on $opts param.
        $q .= '&order_by=' . ( ( isset( $opts['order_by'] ) )
                ? urlencode( $opts['order_by'] ) : 'rank' );
        $q .= '&offset=' . ( ( isset( $opts['offset'] ) )
                ? urlencode( $opts['offset'] ) : '0' );
        $q .= '&limit=' . ( ( isset( $opts['limit'] ) )
                ? urlencode( $opts['limit'] ) : '20' );
        $q .= '&filter_by_openness=' . ( ( isset( $opts['openness'] ) )
                ? urlencode( $opts['openness'] ) : '0' );
        $q .= '&filter_by_downloadable=' . ( ( isset( $opts['downloadable'] ) )
                ? urlencode( $opts['downloadable'] ) : '0' );

        return $data = $this->make_request(
            'GET',
            $this->resources['package_search'] . '?q=' .
            urlencode( $keywords ) . $q
        );
    }

    public function search( $keywords, $opts = array() )
    {
        return $this->search_package( $keywords, $opts );
    }

    public function search_display( $data, $opts = array() )
    {
        if ( $data )
        {
            // Set vars based on $opts param.
            $search_term = ( isset( $opts['search_term'] ) ) ?
                $opts['search_term'] : '';
            $title_tag = '<' .
                         ( ( isset( $opts['title_tag'] ) ) ? $opts['title_tag'] : 'h2' ) . '>';
            $title_close_tag = str_replace( '<', '</', $title_tag );
            $result_list_tag = ( isset( $opts['result_list_tag'] ) )
                ? $opts['result_list_tag'] : 'ul';
            if ( strlen( trim( $result_list_tag ) ) )
            {
                $result_list_close_tag = '</' . $result_list_tag . '>';
                $result_list_tag = '<' . $result_list_tag . '>';
            }
            else
            {
                $result_list_close_tag = '';
            }
            $show_notes = ( isset( $opts['show_notes'] ) )
                ? $opts['show_notes'] : false;
            $format_notes = ( isset( $opts['format_notes'] ) )
                ? $opts['format_notes'] : false;
            // Set search title string
            // is|are, count, ''|s, ''|search_term, .|:
            printf(
                $title_tag . 'There %s %d result%s%s%s' . $title_close_tag,
                ( ( $data->count === 1 ) ? 'is' : 'are' ),
                $data->count,
                ( ( $data->count === 1 ) ? '' : 's' ),
                ( strlen( trim( $search_term ) )
                    ? ' for &#8220;' . $search_term . '&#8221;' : '' ),
                ( ( $data->count === 0 ) ? '.' : ':' )
            );
            if ( $data->count > 0 )
            {
                print $result_list_tag;
                foreach ( $data->results as $val )
                {
                    $package = $this->get_package_entity( $val );
                    printf(
                        '<li><a href="%s">%s</a>',
                        $package->ckan_url,
                        $package->title
                    );
                    if ( isset( $package->notes ) && $package->notes && $show_notes )
                    {
                        print ': ';
                        if ( true === $format_notes )
                        {
                            print Markdown( $package->notes );
                        }
                        elseif ( false === $format_notes )
                        {
                            print $package->notes;
                        }
                        else
                        {
                            print strip_tags(
                                Markdown( $package->notes ),
                                $format_notes
                            );
                        }
                    }
                    print '</li>';
                }
                print $result_list_close_tag;
            }
        }
    }

    protected function make_request( $method, $url, $data = false )
    {
        // Set cURL method.
        curl_setopt( $this->ch, CURLOPT_CUSTOMREQUEST, strtoupper( $method ) );
        // Set cURL URI.
        curl_setopt( $this->ch, CURLOPT_URL, $this->base_url . $url );
        // If POST or PUT, add Authorization: header and request body
        if ( $method === 'POST' || $method === 'PUT' )
        {
            // We needs a key and some data, yo!
            if ( !( $this->api_key && $data ) )
            {
                // throw exception
                throw new Exception( 'Missing either an API key or POST data.' );
            }
            else
            {
                // Add Authorization: header.
                $this->ch_headers[] = 'Authorization: ' . $this->api_key;
                // Add data to request body.
                curl_setopt( $this->ch, CURLOPT_POSTFIELDS, $data );
            }
        }
        else
        {
            // Since we can't use HTTPS,
            // if it's in there, remove Authorization: header
            $key = array_search(
                'Authorization: ' . $this->api_key,
                $this->ch_headers
            );
            if ( $key !== false )
            {
                unset( $this->ch_headers[$key] );
            }
            curl_setopt( $this->ch, CURLOPT_POSTFIELDS, null );
        }
        // Set headers.
        curl_setopt( $this->ch, CURLOPT_HTTPHEADER, $this->ch_headers );
        // Execute request and get response headers.
        $response = curl_exec( $this->ch );
        $info = curl_getinfo( $this->ch );
        // Check HTTP response code
        if ( $info['http_code'] !== 200 && $info['http_code'] !== 201 )
        {
            throw new Exception(
                $method . ' ' .
                $this->base_url . $url . ' ' .
                $info['http_code'] . ': ' .
                $this->http_status_codes[$info['http_code']]
            );
        }
        // Determine how to parse
        if ( isset( $info['content_type'] ) && $info['content_type'] )
        {
            $content_type = str_replace(
                'application/',
                '',
                substr(
                    $info['content_type'],
                    0,
                    strpos( $info['content_type'], ';' )
                )
            );

            return $this->parse_response( $response, $content_type );
        }
        else
        {
            throw new Exception( 'Unknown content type.' );
        }
    }

    protected function parse_response( $data = false, $format = false )
    {
        if ( $data )
        {
            if ( 'json' === $format )
            {
                return json_decode( $data );
            }
            else
            {
                throw new Exception( 'Unable to parse this data format.' );
            }
        }

        return false;
    }

}
