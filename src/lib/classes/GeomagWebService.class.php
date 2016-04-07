<?php

include_once $LIB_DIR . '/classes/GeomagQuery.class.php';
include_once $LIB_DIR . '/classes/Iaga2002OutputFormat.class.php';
include_once $LIB_DIR . '/classes/JsonOutputFormat.class.php';


class GeomagWebService {

  const VERSION = '0.1.0';
  const ISO8601 = 'Y-m-d\TH:i:s\Z';

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


  public $waveserver;
  public $metadata;

  /**
   * Construct a new GeomagWebService.
   *
   * @param $waveserver {WaveServer}
   *    Waveserver object used to fetch data.
   * @param $metadata {Array<String, Array>}
   *        Associative array of metadata, keyed by upper case observatory id.
   */
  public function __construct($waveserver, $metadata) {
    $this->waveserver = $waveserver;
    $this->metadata = $metadata;
  }

  /**
   * Run web service.
   */
  public function run() {
    try {
      $query = $this->parseQuery($_GET);
    } catch (Exception $e) {
      $this->error(self::BAD_REQUEST, $e->getMessage());
    }

    try {
      $data = $this->getData($query);
      if ($query->format === 'iaga2002') {
        $output = new Iaga2002OutputFormat();
      } else {
        $output = new JsonOutputFormat();
      }
      $output->output($data, $query, $this->metadata);
    } catch (Exception $e) {
      $this->error(self::SERVER_ERROR, $e->getMessage());
    }
  }

  /**
   * Get requested data.
   *
   * @param $query {GeomagQuery}
   *        web service query.
   * @return {Array<String, WaveServerResponse}
   *         associative array of data.
   *         keys are requested elements.
   */
  protected function getData($query) {
    $data = [];

    $endtime = $query->endtime;
    $sampling_period = $query->sampling_period;
    $starttime = $query->starttime;
    $station = $query->id;
    $type = $query->type;

    // build times array
    $times = array();
    $len = ($endtime - $starttime) / $sampling_period;
    for ($i = 0; $i <= $len; $i++) {
      $times[] = $starttime + $i * $sampling_period;
    }
    $data['times'] = $times;

    foreach ($query->elements as $element) {
      $sncl = $this->getSNCL(
          $station,
          $element,
          $query->sampling_period,
          $query->type);
      $response = $this->waveserver->get(
          $starttime,
          $endtime,
          $sncl['station'],
          $sncl['network'],
          $sncl['channel'],
          $sncl['location']);

      // build values array
      $values = $response->getDataArray($starttime, $endtime);
      if (!is_array($values)) {
        // empty channel
        $values = array_fill(0, count($times), null);
      } else {
        $values = array_map(function ($val) {
          if ($val === null) {
            return null;
          }
          return $val / 1000;
        }, $values);
      }

      $data[$element] = array(
        'sncl' => $sncl,
        'element' => $element,
        'response' => $response,
        'values' => $values
      );
    }

    return $data;
  }

  /**
   * Translate requested elements to EDGE SNCL codes.
   *
   * @param $station {String}
   *        observatory.
   * @param $element {Array<String>}
   *        requested elements.
   * @param $sampling_period {Number}
   *        1 for seconds, 60 for minutes.
   * @param $type {String}
   *        'variation', 'adjusted', 'quasi-definitive', or 'definitive'.
   */
  protected function getSNCL($station, $element, $sampling_period, $type) {
    $network = 'NT';
    $station = $station;

    $channel = '';
    if ($sampling_period === 60) {
      $prefix = 'M';
    } else if ($sampling_period === 1) {
      $prefix = 'S';
    }

    $element = strtoupper($element);
    switch ($element) {
      case 'D':
      case 'E':
      case 'H':
      case 'X':
      case 'Y':
      case 'Z':
        $channel = $prefix . 'V' . $element;
        break;
      case 'F':
      case 'G':
        $channel = $prefix . 'S' . $element;
        break;
      case 'SQ':
      case 'SV':
        $channel = $prefix . $element;
        break;
      case 'DIST':
        $channel = $prefix . 'DT';
        break;
      case 'DST':
        $channel = $prefix . 'GD';
        break;
      default:
        if (preg_match('/^[A-Z]{3}$/', $element)) {
          // seems like an edge channel code
          $channel = $element;
        } else {
          $this->error(self::BAD_REQUEST, 'Unknown element "' . $element . '"');
        }
        break;
    }

    if ($type === 'variation') {
      $location = 'R0';
    } else if ($type === 'adjusted') {
      $location = 'A0';
    } else if ($type === 'quasi-definitive') {
      $location = 'Q0';
    } else if ($type === 'definitive') {
      $location = 'D0';
    } else {
      $location = $type;
    }

    return array(
      'station' => $station,
      'network' => $network,
      'channel' => $channel,
      'location' => $location
    );
  }


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

  public function jsonError ($code, $message) {
    global $HOST_URL_PREFIX;
    header('Content-type: application/json');
    // Does this need to look fully like GeoJSON format?
    $response = array(
      'type' => 'Error',
      'metadata' => array(
        'status' => $code,
        'generated' => gmdate(self::ISO8601),
        'url' => $HOST_URL_PREFIX . $_SERVER['REQUEST_URI'],
        'title' => self::$statusMessage[$code],
        'api' => self::VERSION,
        'error' => $message
      )
    );
    echo str_replace('\/', '/', JsonOutputFormat::safe_json_encode($response));
    exit();
  }

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
      gmdate(self::ISO8601),
      '',
      'Service version:',
      self::VERSION
    ));
    exit();
  }


  protected function parseQuery($params) {
    $query = new GeomagQuery();

    foreach ($params as $name => $value) {
      if ($value === '') {
        // treat empty values as missing parameters
        continue;
      }
      if ($name === 'id') {
        $query->id = $this->validateEnumerated($name, strtoupper($value),
            array_keys($this->metadata));
      } else if ($name === 'starttime') {
        $query->starttime = $this->validateTime($name, $value);
      } else if ($name === 'endtime') {
        $query->endtime = $this->validateTime($name, $value);
      } else if ($name === 'elements') {
        if (!is_array($value)) {
          $value = explode(',', $value);
        }
        $value = array_map(function ($value) {
          return strtoupper($value);
        }, $value);
        $query->elements = $value;
      } else if ($name === 'sampling_period') {
        $query->sampling_period = intval(
            $this->validateEnumerated($name, $value,
                // valid sampling periods
                // 1 = second
                // 60 = minute
                // 3600 = hour
                array(1, 60, 3600)));
      } else if ($name === 'type') {
        if (preg_match('/^[A-Z0-9]{2}$/', $value)) {
          // edge location code
          $query->type = $value;
        } else {
          $query->type = $this->validateEnumerated($name, $value,
              array('variation', 'adjusted', 'quasi-definitive', 'definitive'));
        }
      } else if ($name === 'format') {
        $query->format = $this->validateEnumerated($name, strtolower($value),
              array('iaga2002', 'json'));
      } else {
        $this->error(self::BAD_REQUEST, 'Unknown parameter "' . $name . '"');
      }
    }

    // set defaults
    if ($query->id === null) {
      throw new Exception('"id" is a required parameter');
    }
    if ($query->starttime === null) {
      $query->starttime = strtotime(gmdate('Y-m-d'));
    }
    if ($query->endtime === null) {
      // default to starttime + 24 hours
      $query->endtime = $query->starttime + (24 * 60 * 60 - 1);
    }
    if ($query->elements === null) {
      // default when not specified
      $query->elements = array('X', 'Y', 'Z', 'F');
    }

    return $query;
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

}
