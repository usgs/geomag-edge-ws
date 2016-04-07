<?php


/**
 * Web service main class.
 */
class EdgeWebService {

  public $waveserver;

  // service version number
  public $version = '0.1';

  const BAD_REQUEST = 400;
  const NOT_FOUND = 404;
  const SERVER_ERROR = 500;
  const NOT_IMPLEMENTED = 501;

  // status message text
  public static $statusMessage = array(
    self::BAD_REQUEST => 'Bad Request',
    self::NOT_FOUND => 'Not Found',
    self::SERVER_ERROR => 'Server Error',
    self::NOT_IMPLEMENTED => 'Not Implemented'
  );

  /**
   */
  public function __construct($waveserver) {
    $this->waveserver = $waveserver;
  }

  /**
   * Handle a get request.
   *
   * @param $params {Array}
   *        parameters for request, usually $_GET.
   */
  public function get($params) {
    // parse request
    try {
      $startTime = $this->validateTime($params, 'startTime');
      $endTime = $this->validateTime($params, 'endTime');
      $station = $this->validateRequired($params, 'station');
      $network = $this->validateRequired($params, 'network');
      $channel = $this->validateRequired($params, 'channel');
      $location = $this->validateRequired($params, 'location');

      // process request
      try {
        $response = $this->waveserver->get($startTime, $endTime,
            $station, $network, $channel, $location);
        $this->output($response, $startTime, $endTime);
      } catch (Exception $e) {
        trigger_error($e->getMessage());
        $this->error(self::SERVER_ERROR, 'Server Error');
      }
    } catch (Exception $e) {
      $this->error(self::BAD_REQUEST, $e->getMessage());
    }
  }

  /**
   * Output service response.
   *
   * @param $response {WaveServerResponse}
   *        response from waveserver.
   */
  public function output ($response, $startTime=null, $endTime=null) {
    $samplingRate = null;
    $delta = null;
    if ($response->flag === 'F') {
      $traceBuf = $response->traceBufs[0];
      $samplingRate = $traceBuf->samplingRate;
      $delta = 1 / $samplingRate;
    }

    $output = array(
      // service/request metadata
      'metadata' => array(
          'request' => $_SERVER['REQUEST_URI'],
          'submitted' => $this->_formatDate(time()),
          'version' => $this->version
      ),
      'response' => array(
        'station' => $response->station,
        'channel' => $response->channel,
        'network' => $response->network,
        'location' => $response->location,
        'startTime' => $this->_formatDate($startTime),
        'endTime' => $this->_formatDate($endTime),
        'samplingRate' => $samplingRate,
        'delta' => $delta,
        'flag' => $response->flag,
        'data' => $response->getDataArray($startTime, $endTime)
      )
    );

    global $APP_DIR;
    $CACHE_MAXAGE = 60;
    include $APP_DIR . '/lib/cache.inc.php';
    header('Content-Type: application/json');
    echo json_encode($output);
    exit();
  }

  /**
   * Format a date as an ISO8601 string.
   *
   * @param $date {Number}
   *        number of seconds since the epoch.
   * @return {String} formatted date.
   */
  protected function _formatDate ($date) {
    if ($date === null) {
      return null;
    }

    $iso = gmdate('c', $date);
    $iso = str_replace('+00:00', 'Z', $iso);
    return $iso;
  }

  /**
   * Handle a service error.
   *
   * @param $code {Number}
   *        status code.
   * @param $message {String}
   *        error message.
   */
  public function error ($code, $message) {
    global $APP_DIR;
    // only cache errors for 60 seconds
    $CACHE_MAXAGE = 60;
    include $APP_DIR . '/lib/cache.inc.php';
    if (isset(self::$statusMessage[$code])) {
      $codeMessage = self::$statusMessage[$code];
    } else {
      $codeMessage = '';
    }
    header('HTTP/1.0 ' . $code .
        ($codeMessage !== '' ? ' ' : '') . $codeMessage);
    if ($code < 400) {
      exit();
    }
    global $HOST_URL_PREFIX;
    global $MOUNT_PATH;
    header('Content-type: application/json');
    echo json_encode(array(
      'metadata' => array(
        'request' => $_SERVER['REQUEST_URI'],
        'submitted' => gmdate('c'),
        'version' => $this->version
      ),
      'error' => array(
        'code' => $code,
        'codeMessage' => $codeMessage,
        'message' => $message,
        'usage' => $HOST_URL_PREFIX . $MOUNT_PATH,
      )
    ));
    exit();
  }

  /**
   * Validate a require parameter.
   *
   * @param $params {Array}
   *        associative array of parameters.
   * @param $param {String}
   *        name of parameter.
   * @return {String}
   *         parameter value
   * @throws Exception if parameter is invalid.
   */
  protected function validateRequired($params, $param) {
    $value = isset($params[$param]) ? $params[$param] : null;
    if ($value === null || $value === '') {
      throw new Exception('"' . $param . '" is required');
    }
    return $value;
  }

  /**
   * Validate a time parameter.
   *
   * @param $params {Array}
   *        associative array of parameters.
   * @param $param {String}
   *        name of parameter.
   * @return {Number}
   *         unix epoch time.
   * @throws Exception if parameter is invalid.
   */
  protected function validateTime($params, $param) {
    $value = strtotime($this->validateRequired($params, $param));
    if ($value === FALSE) {
      throw new Exception('Invalid time "' . $value . '",' .
          ' valid values are ISO8601');
    }
    return $value;
  }

}
