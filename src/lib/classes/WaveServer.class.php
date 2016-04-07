<?php

include_once $LIB_DIR . '/classes/Buffer.class.php';
include_once $LIB_DIR . '/classes/TraceBuf.class.php';
include_once $LIB_DIR . '/classes/WaveServerResponse.class.php';


/**
 * Wrapper for waveserver GETSCNLRAW requests.
 */
class WaveServer {

  public $host;
  public $port;
  public $timeout;

  protected $socket;

  /**
   * Create a new WaveServer.
   *
   * @param $host {String}
   *        waveserver hostname or ip address.
   * @param $port {Number}
   *        default 2060.
   *        waveserver port.
   * @param $timeout {Number}
   *        default 5.
   *        timeout in seconds.
   */
  public function __construct ($host, $port=2060, $timeout=5) {
    $this->host = $host;
    $this->port = $port;
    $this->timeout = $timeout;
    $this->socket = null;
  }

  /**
   * Get timeseries information.
   *
   * @param $start {Number}
   *        unix timestamp in seconds.
   * @param $end {Number}
   *        unix timestamp in seconds.
   * @param $station {String}
   *        station code.
   * @param $network {String}
   *        network code.
   * @param $channel {String}
   *        channel code.
   * @param $location {String}
   *        location code.
   * @return {WaveServerResponse}
   *         response from wave server.
   */
  public function get ($start, $end, $station, $network, $channel, $location) {
    $socket = $this->connect();

    // request data
    $request = implode(' ', array(
      'GETSCNLRAW:',
      'rwserv',
      $station,
      $channel,
      $network,
      $location,
      $start,
      $end
    )) . "\n";
    fputs($socket, $request);

    // read response
    $line = fgets($socket);
    $response = new WaveServerResponse($line);
    if ($response->numBytes > 0) {
      $bytes = '';
      $len = 0;
      do {
        $bytes .= fread($socket, $response->numBytes - $len);
        $len = strlen($bytes);
      } while ($len < $response->numBytes);
      $response->traceBufs = $this->parseTraceBufs($bytes);
    }
    return $response;
  }

  /**
   * Parse tracebuf objects from a stream.
   *
   * @param $bytes {String}
   *        tracebuf data.
   * @return {Array<TraceBuf>}
   *         parsed tracebuf objects.
   */
  public function parseTraceBufs ($bytes) {
    $parsed = array();

    // sniff endian-ness from first tracebuf header
    $endian = substr($bytes, 57, 1);
    if ($endian === 's' || $endian === 't') {
      $endian = '>';
    } else if ($endian === 'f' || $endian === 'i') {
      $endian = '<';
    } else {
      throw new Exception('Unexpected endian flag "' . $endian . '",' .
          ' expected one of "f", "i", "s", or "t"');
    }

    // parse all tracebufs.
    $buffer = new Buffer($bytes, $endian);
    while ($buffer->available() > 0) {
      $parsed[] = new TraceBuf($buffer);
    }

    return $parsed;
  }

  /**
   * Close socket if currently connected.
   */
  public function close() {
    if ($this->socket) {
      fclose($this->socket);
      $this->socket = null;
    }
  }

  /**
   * Connect to configured host and port.
   *
   * @return {Resource}
   *         connected socket.
   * @throws Exception if unable to connect.
   */
  protected function connect() {
    if (!$this->socket) {
      $this->socket = fsockopen($this->host, $this->port, $errno, $errstr, $this->timeout);
      if (!$this->socket) {
        throw new Exception('Unable to connect (' . $errno . ': ' . $errstr . ')');
      }
    }
    return $this->socket;
  }

}
