<?php


/**
 * Base class for web services.
 */
class WebService {

  const NO_DATA = 204;
  const BAD_REQUEST = 400;
  const NOT_FOUND = 404;
  const CONFLICT = 409;
  const SERVER_ERROR = 500;
  const NOT_IMPLEMENTED = 501;
  const SERVICE_UNAVAILABLE = 503;

  // status message text
  public static $statusMessage = array(
    self::NO_DATA => 'No Data',
    self::BAD_REQUEST => 'Bad Request',
    self::NOT_FOUND => 'Not Found',
    self::CONFLICT => 'Conflict',
    self::SERVER_ERROR => 'Internal Server Error',
    self::NOT_IMPLEMENTED => 'Not Implemented',
    self::SERVICE_UNAVAILABLE => 'Service Unavailable'
  );


  public $version;


  public function __construct($version = '0.0.0') {
    $this->version = $version;
  }


  /**
   * Output a web service error.
   *
   * Calls #httpError() or #jsonError() depending on whether
   *     $_GET['format'] === 'json'.
   *
   * @param $code {Number}
   *     http error code, see class contants.
   * @param $message {String}
   *     message describing cause for error.
   */
  public function error($code, $message) {
    global $APP_DIR;
    // only cache errors for 60 seconds
    $CACHE_MAXAGE = 60;
    include $APP_DIR . '/lib/cache.inc.php';
    if (isset($_GET['format']) && $_GET['format'] === 'json') {
      // For json requests, user wants json output
      $this->jsonError($code, $message);
    } else {
      $this->httpError($code, $message);
    }
  }

  /**
   * Output json formatted error message.
   *
   * @param $code {Number}
   *     http error code, see class contants.
   * @param $message {String}
   *     message describing cause for error.
   */
  public function jsonError ($code, $message) {
    global $HOST_URL_PREFIX;
    header('Content-type: application/json');
    // Does this need to look fully like GeoJSON format?
    $response = array(
      'type' => 'Error',
      'metadata' => array(
        'status' => $code,
        'generated' => self::formatISO8601(),
        'url' => $HOST_URL_PREFIX . $_SERVER['REQUEST_URI'],
        'title' => self::$statusMessage[$code],
        'api' => $this->version,
        'error' => $message
      )
    );
    echo str_replace('\/', '/', self::safe_json_encode($response));
    exit();
  }

  /**
   * Output plain text error message.
   *
   * @param $code {Number}
   *     http error code, see class contants.
   * @param $message {String}
   *     message describing cause for error.
   */
  public function httpError ($code, $message) {
    if (isset(self::$statusMessage[$code])) {
      $codeMessage = ' ' . self::$statusMessage[$code];
    } else {
      $codeMessage = '';
    }
    header('HTTP/1.0 ' . $code . $codeMessage);
    if ($code < 400) {
      exit();
    }
    global $HOST_URL_PREFIX;
    global $MOUNT_PATH;

    // error message for 400 or 500
    header('Content-type: text/plain');
    echo implode("\n", array(
      'Error ' . $code . ': ' . self::$statusMessage[$code],
      '',
      $message,
      '',
      'Usage details are available from ' .
          $HOST_URL_PREFIX . $MOUNT_PATH . '/',
      '',
      'Request:',
      $_SERVER['REQUEST_URI'],
      '',
      'Request Submitted:',
      self::formatISO8601(),
      '',
      'Service version:',
      $this->version
    ));
    exit();
  }


  /**
   * Validate a time parameter.
   *
   * @param $param parameter name, for error message.
   * @param $value parameter value
   * @return value as epoch millisecond timestamp, exit with error if invalid.
   */
  protected function validateTime($param, $value) {
    $parsed = strtotime($value);
    if ($parsed === false) {
      $this->error(self::BAD_REQUEST,
        'Bad ' . $param . ' value "' . $value . '".' .
        ' Valid values are ISO-8601 timestamps.');
    }
    return $parsed;
  }

  /**
   * Validate a boolean parameter.
   *
   * @param $param parameter name, for error message
   * @param $value parameter value
   * @return value as boolean if valid ("true" or "false", case insensitively), exit with error if invalid.
   */
  protected function validateBoolean($param, $value) {
    $val = strtolower($value);
    if ($val !== 'true' && $val !== 'false') {
      $this->error(self::BAD_REQUEST,
          'Bad ' . $param . ' value "' . $value . '".' .
          ' Valid values are (case insensitive): "TRUE", "FALSE".');
    }
    return ($val === 'true');
  }

  /**
   * Validate an integer parameter.
   *
   * @param $param parameter name, for error message
   * @param $value parameter value
   * @param $min minimum valid value for parameter, or null if no minimum.
   * @param $max maximum valid value for parameter, or null if no maximum.
   * @return value as integer if valid (integer and in range), exit with error if invalid.
   */
  protected function validateInteger($param, $value, $min, $max) {
    if (
        !ctype_digit($value)
        || ($min !== null && intval($value) < $min)
        || ($max !== null && intval($value) > $max)
    ) {
      $message = '';
      if ($min === null && $max === null) {
        $message = 'integers';
      } else {
        $message = '';
        if ($min !== null) {
          $message .= $min . ' <= ';
        }
        $message .= $param;
        if ($max !== null) {
          $message .= ' <= ' . $max;
        }
      }
      $this->error(self::BAD_REQUEST, 'Bad ' . $param . ' value "' . $value . '".' .
          ' Valid values are ' . $message);
    }
    return intval($value);
  }

  /**
   * Validate a float parameter.
   *
   * @param $param parameter name, for error message
   * @param $value parameter value
   * @param $min minimum valid value for parameter, or null if no minimum.
   * @param $max maximum valid value for parameter, or null if no maximum.
   * @return value as float if valid (float and in range), exit with error if invalid.
   */
  protected function validateFloat($param, $value, $min, $max) {
    if (
        !is_numeric($value)
        || ($min !== null && floatval($value) < $min)
        || ($max !== null && floatval($value) > $max)
    ) {
      if ($min === null && $max === null) {
        $message = 'numeric';
      } else {
        $message = '';
        if ($min !== null) {
          $message .= $min . ' <= ';
        }
        $message .= $param;
        if ($max !== null) {
          $message .= ' <= ' . $max;
        }
      }

      $this->error(self::BAD_REQUEST, 'Bad ' . $param . ' value "' . $value . '".' .
          ' Valid values are ' . $mesasge);
    }
    return floatval($value);
  }

  /**
   * Validate a parameter that has an enumerated list of valid values.
   *
   * @param $param parameter name, for error message
   * @param $value parameter value
   * @param $enum array of valid parameter values.
   * @return value if valid (in array), exit with error if invalid.
   */
  protected function validateEnumerated($param, $value, $enum) {
    if (!in_array($value, $enum)) {
      $this->error(self::BAD_REQUEST, 'Bad ' . $param . ' value "' . $value . '".' .
        ' Valid values are: "' . implode('", "', $enum) . '".');
    }
    return $value;
  }


  /**
   * Safely json_encode values.
   *
   * Handles malformed UTF8 characters better than normal json_encode.
   * from http://stackoverflow.com/questions/10199017/how-to-solve-json-error-utf8-error-in-php-json-decode
   *
   * @param $value {Mixed}
   *        value to encode as json.
   * @return {String}
   *         json encoded value.
   * @throws Exception when unable to json encode.
   */
  public static function safe_json_encode($value){
    $encoded = json_encode($value);
    $lastError = json_last_error();
    switch ($lastError) {
      case JSON_ERROR_NONE:
        return $encoded;
      case JSON_ERROR_UTF8:
        return self::safe_json_encode(self::utf8_encode_array($value));
      default:
        throw new Exception('json_encode error (' . $lastError . ')');
    }
  }

  /**
   * Format a epoch timestamp as ISO8601 with milliseconds.
   *
   * @param $time {Number}
   *     default microtime(true).
   *     decimal epoch timestamp.
   * @param $timeSep {String}
   *     default 'T'.
   *     separator between date and time.
   * @param $utc {String}
   *     default 'Z'.
   *     timezone string for UTC.
   * @return {String}
   *     time formatted as ISO8601 with milliseconds.
   */
  public static function formatISO8601($time=null, $timeSep='\T', $utc='Z') {
    if ($time === null) {
      $time = microtime(true);
    }

    $timestr =
        // time without timezone
        gmdate('Y-m-d' . $timeSep . 'H:i:s', $time)
        // milliseconds
        . '.' . str_pad(($time * 1000) % 1000, 3, '0', STR_PAD_LEFT)
        // timezone
        . $utc;

    return $timestr;
  }

}
