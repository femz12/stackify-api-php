<?php

namespace Stackify\Log\Transport;

use Http\Client\Exception as HttpClientException;
use Http\Client\Socket\Client;
use Http\Message\MessageFactory\GuzzleMessageFactory;
use Stackify\Log\Transport\Config\Agent as Config;
use Http\Client\Socket\Exception\ConnectionException as HttpClientSocketConnectionException;

/**
 * This transport requires Stackify Agent to be installed and running on the same machine
 * Transport writes data over domain socket.
 * Agent aggregates log entries and sends data to API.
 */
class AgentSocketTransport extends AbstractApiTransport
{
    const ERROR_WRITE = 'Cannot write to domain socket at %s. Is Stackify Agent installed and running?';
    const ERROR_CONNECT = 'Cannot connect to domain socket at %s. Is Stackify Agent installed and running? [Error code: %d] [Error message: %s]';

    /**
     * Constructor
     *
     * @param array $options Socket transport options
     */
    public function __construct(array $options = array())
    {
        parent::__construct('NONE', $options);
    }

    /**
     * Transport name
     *
     * @return string
     */
    protected function getTransportName()
    {
        return 'AgentSocketTransport';
    }

    /**
     * Flush data to socket
     *
     * @param mixed $data Log message
     * 
     * @return void
     */
    protected function send($data)
    {
        try {
            if (!empty($data)) {
                $messageFactory = new GuzzleMessageFactory();
                $client = null;
                $config = array('remote_socket' => 'unix://' . $this->getDomainSocketPath());

                if (version_compare(phpversion(), '7.1', '>=')) {
                    $client = new Client($config);
                } else {
                    $client = new Client($messageFactory, $config);
                }

                $request = $messageFactory->createRequest(
                    'POST',
                    'http://log',
                    array(
                        'Content-Type' => 'application/json',
                        'Content-Length' => strlen($data)
                    ),
                    $data
                );

                $response = $client->sendRequest($request);

                if ($response->getStatusCode() != 200) {
                    $this->logError(
                        self::ERROR_WRITE,
                        $this->getDomainSocketPath()
                    );
                }
            }

        } catch (HttpClientException $e) {
            $this->logError(self::ERROR_CONNECT, $this->getDomainSocketPath(), $e->getCode(), $e->getMessage());
        }  catch (HttpClientSocketConnectionException $e) {
            $this->logError(self::ERROR_CONNECT, $this->getDomainSocketPath(), $e->getCode(), $e->getMessage());
        }
    }

    /**
     * Get domain socket path
     *
     * @return string
     */
    public function getDomainSocketPath()
    {
        if ($this->agentConfig) {
            return $this->agentConfig->getDomainSocketPath();
        }

        return Config::DOMAIN_SOCKET_PATH;
    }
}
